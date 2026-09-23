<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\MeetingPlatformConfig;
use App\Models\UserMeetingConnection;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExternalCalendarSyncService
{
    /**
     * Sync all connected external calendars/meeting providers for one user.
     *
     * @return array{imported:int, updated:int, connections:int, errors:array<int,string>}
     */
    public function syncUser(int $userId, ?Carbon $from = null, ?Carbon $to = null, ?string $provider = null, bool $includeRecurring = true): array
    {
        // Calendar history is intentionally never imported. Apply this at
        // the service boundary so web, API, OAuth callback, and scheduled
        // sync callers all share the same lower bound.
        $from = $this->currentMonthStart($from);

        $connections = UserMeetingConnection::where('user_id', $userId)
            ->when($provider, fn ($q) => $q->where('platform', $provider))
            ->get();
        $result = ['imported' => 0, 'updated' => 0, 'connections' => 0, 'errors' => []];

        foreach ($connections as $connection) {
            try {
                $stats = $this->syncConnection($connection, $from, $to, $includeRecurring);
                $result['imported'] += $stats['imported'];
                $result['updated'] += $stats['updated'];
                $result['connections']++;
            } catch (Throwable $e) {
                Log::warning('External calendar sync failed', [
                    'user_id' => $userId,
                    'platform' => $connection->platform,
                    'message' => $e->getMessage(),
                ]);
                $result['errors'][] = ucfirst($connection->platform) . ': ' . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * @return array{imported:int,updated:int}
     */
    public function syncConnection(UserMeetingConnection $connection, ?Carbon $from = null, ?Carbon $to = null, bool $includeRecurring = true): array
    {
        $from = $this->currentMonthStart($from);
        if ($to && $to->lessThan($from)) {
            return ['imported' => 0, 'updated' => 0];
        }

        $platform = $connection->platform;
        // Existing user connections can continue syncing with their current
        // access token even if the administrator later disables NEW OAuth
        // connections for this provider. Administrator credentials are only
        // required when the access token has expired and must be refreshed.
        $config = MeetingPlatformConfig::where('platform', $platform)->first();

        if (empty($connection->access_token)) {
            throw new \RuntimeException('This connected account has no access token. Please disconnect and reconnect it.');
        }

        if ($connection->isExpired()) {
            if (! $config || ! $config->isConfigured()) {
                throw new \RuntimeException('This account is connected, but the administrator OAuth credentials are missing, so the expired sign-in cannot be refreshed. Please ask an administrator to configure this provider, then reconnect if needed.');
            }

            $this->refreshTokenIfNeeded($connection, $config);
        }

        $events = match ($platform) {
            'google' => $this->fetchGoogleEvents($connection, $from, $to, $includeRecurring),
            'microsoft' => $this->fetchMicrosoftEvents($connection, $from, $to),
            'zoom' => $this->fetchZoomMeetings($connection),
            'webex' => $this->fetchWebexMeetings($connection),
            default => [],
        };

        // Google and Microsoft already accept a server-side date window.
        // Zoom/Webex APIs may return a broader list, so apply the selected
        // period again here for every provider. This guarantees that the
        // user's From/To dates are respected consistently.
        if ($from || $to) {
            $rangeStart = $from->copy()->startOfDay();
            $rangeEnd = ($to ?: $rangeStart->copy()->addMonth())->copy()->endOfDay();

            $events = array_values(array_filter($events, function (array $event) use ($rangeStart, $rangeEnd): bool {
                if (empty($event['start_at'])) {
                    return false;
                }

                try {
                    $eventStart = Carbon::parse($event['start_at']);
                } catch (\Throwable) {
                    return false;
                }

                return $eventStart->betweenIncluded($rangeStart, $rangeEnd);
            }));
        }

        $imported = 0;
        $updated = 0;

        foreach ($events as $event) {
            $meeting = Meeting::where('user_id', $connection->user_id)
                ->where('external_platform', $platform)
                ->where('external_id', $event['external_id'])
                ->first();

            $payload = [
                'user_id' => $connection->user_id,
                'title' => $event['title'],
                'start_at' => $this->normaliseDate($event['start_at']),
                'end_at' => $this->normaliseDate($event['end_at'] ?? null),
                'location' => $event['location'] ?? null,
                'attendees' => $event['attendees'] ?? null,
                'status' => ($event['cancelled'] ?? false) ? 'cancelled' : 'scheduled',
                'meeting_status' => ($event['cancelled'] ?? false) ? 'cancelled' : 'scheduled',
                'external_platform' => $platform,
                'external_id' => $event['external_id'],
                'notes' => $event['notes'] ?? null,
            ];

            if ($meeting) {
                $meeting->update($payload);
                $updated++;
            } else {
                Meeting::create($payload);
                $imported++;
            }
        }

        $connection->update(['last_synced_at' => now()]);

        return compact('imported', 'updated');
    }

    private function refreshTokenIfNeeded(UserMeetingConnection $connection, MeetingPlatformConfig $config): void
    {
        if (! $connection->isExpired()) {
            return;
        }

        if (! $connection->refresh_token) {
            throw new \RuntimeException('The sign-in has expired. Please disconnect and reconnect this account.');
        }

        $tokenUrl = match ($connection->platform) {
            'google' => 'https://oauth2.googleapis.com/token',
            'microsoft' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'zoom' => 'https://zoom.us/oauth/token',
            'webex' => 'https://webexapis.com/v1/access_token',
            default => null,
        };

        if (! $tokenUrl) {
            return;
        }

        $payload = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $connection->refresh_token,
            'client_id' => $config->client_id,
            'client_secret' => $config->client_secret,
        ];

        $response = Http::asForm()->post($tokenUrl, $payload);
        if (! $response->successful()) {
            throw new \RuntimeException('Could not refresh the calendar sign-in. Please reconnect the account.');
        }

        $data = $response->json();
        $connection->update([
            'access_token' => $data['access_token'] ?? $connection->access_token,
            'refresh_token' => $data['refresh_token'] ?? $connection->refresh_token,
            'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds((int) $data['expires_in']) : now()->addHour(),
        ]);
        $connection->refresh();
    }

    /**
     * Google: sync events from ALL calendars visible in the connected account,
     * not just the primary calendar. Events are paginated to avoid the old
     * maxResults=50 truncation.
     */
    private function fetchGoogleEvents(UserMeetingConnection $connection, ?Carbon $from = null, ?Carbon $to = null, bool $includeRecurring = true): array
    {
        $calendarList = $this->getJsonWithToken($connection, 'https://www.googleapis.com/calendar/v3/users/me/calendarList?maxResults=250');
        $events = [];
        $timeMin = ($from ?: $this->currentMonthStart())->copy()->startOfDay()->toIso8601String();
        $timeMax = ($to ?: now()->addMonths(18))->copy()->endOfDay()->toIso8601String();

        foreach ($calendarList['items'] ?? [] as $calendar) {
            if (($calendar['deleted'] ?? false) || ! isset($calendar['id'])) {
                continue;
            }

            $calendarId = rawurlencode((string) $calendar['id']);
            $pageToken = null;

            do {
                $url = 'https://www.googleapis.com/calendar/v3/calendars/' . $calendarId . '/events';
                $query = [
                    'maxResults' => 250,
                    'orderBy' => $includeRecurring ? 'startTime' : 'updated',
                    'singleEvents' => $includeRecurring ? 'true' : 'false',
                    'timeMin' => $timeMin,
                    'timeMax' => $timeMax,
                    'showDeleted' => 'true',
                ];
                if ($pageToken) {
                    $query['pageToken'] = $pageToken;
                }

                $data = $this->getJsonWithToken($connection, $url, $query);
                foreach ($data['items'] ?? [] as $e) {
                    $start = $e['start']['dateTime'] ?? $e['start']['date'] ?? null;
                    if (! $start || ! isset($e['id'])) {
                        continue;
                    }

                    $calendarName = $calendar['summaryOverride'] ?? $calendar['summary'] ?? 'Google Calendar';
                    $events[] = [
                        'external_id' => (string) $calendar['id'] . ':' . (string) $e['id'],
                        'title' => $e['summary'] ?? 'Google Calendar Event',
                        'start_at' => $start,
                        'end_at' => $e['end']['dateTime'] ?? $e['end']['date'] ?? null,
                        'location' => $e['hangoutLink'] ?? $e['location'] ?? $e['htmlLink'] ?? null,
                        'attendees' => collect($e['attendees'] ?? [])->pluck('email')->filter()->implode(', '),
                        'notes' => trim('Synced from Google Calendar: ' . $calendarName . (isset($e['description']) ? "\n\n" . $e['description'] : '')),
                        'cancelled' => ($e['status'] ?? '') === 'cancelled',
                    ];
                }

                $pageToken = $data['nextPageToken'] ?? null;
            } while ($pageToken);
        }

        return $events;
    }

    /** Microsoft Outlook/Teams calendar view for a broad useful window. */
    private function fetchMicrosoftEvents(UserMeetingConnection $connection, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $events = [];
        $url = 'https://graph.microsoft.com/v1.0/me/calendarView';
        $query = [
            'startDateTime' => ($from ?: $this->currentMonthStart())->copy()->startOfDay()->utc()->toIso8601String(),
            'endDateTime' => ($to ?: now()->addMonths(18))->copy()->endOfDay()->utc()->toIso8601String(),
            '$top' => 100,
            '$orderby' => 'start/dateTime',
            '$select' => 'id,subject,start,end,location,onlineMeeting,attendees,bodyPreview,isCancelled,webLink',
        ];

        do {
            $data = $this->getJsonWithToken($connection, $url, $query);
            $query = [];

            foreach ($data['value'] ?? [] as $e) {
                $start = $e['start']['dateTime'] ?? null;
                if (! $start || ! isset($e['id'])) {
                    continue;
                }

                $startZone = $e['start']['timeZone'] ?? 'UTC';
                $endZone = $e['end']['timeZone'] ?? $startZone;
                $events[] = [
                    'external_id' => (string) $e['id'],
                    'title' => $e['subject'] ?? 'Outlook Calendar Event',
                    'start_at' => $this->dateWithZone($start, $startZone),
                    'end_at' => isset($e['end']['dateTime']) ? $this->dateWithZone($e['end']['dateTime'], $endZone) : null,
                    'location' => $e['onlineMeeting']['joinUrl'] ?? $e['location']['displayName'] ?? $e['webLink'] ?? null,
                    'attendees' => collect($e['attendees'] ?? [])->pluck('emailAddress.address')->filter()->implode(', '),
                    'notes' => isset($e['bodyPreview']) ? 'Synced from Outlook Calendar' . "\n\n" . $e['bodyPreview'] : 'Synced from Outlook Calendar',
                    'cancelled' => (bool) ($e['isCancelled'] ?? false),
                ];
            }

            $url = $data['@odata.nextLink'] ?? null;
        } while ($url);

        return $events;
    }

    private function fetchZoomMeetings(UserMeetingConnection $connection): array
    {
        $events = [];
        $nextPageToken = null;

        do {
            $query = ['type' => 'upcoming', 'page_size' => 100];
            if ($nextPageToken) {
                $query['next_page_token'] = $nextPageToken;
            }
            $data = $this->getJsonWithToken($connection, 'https://api.zoom.us/v2/users/me/meetings', $query);

            foreach ($data['meetings'] ?? [] as $m) {
                if (! isset($m['id'], $m['start_time'])) {
                    continue;
                }
                $duration = (int) ($m['duration'] ?? 0);
                $events[] = [
                    'external_id' => (string) $m['id'],
                    'title' => $m['topic'] ?? 'Zoom Meeting',
                    'start_at' => $m['start_time'],
                    'end_at' => $duration > 0 ? Carbon::parse($m['start_time'])->addMinutes($duration)->toIso8601String() : null,
                    'location' => $m['join_url'] ?? null,
                    'notes' => 'Synced from Zoom',
                    'cancelled' => false,
                ];
            }

            $nextPageToken = $data['next_page_token'] ?? null;
        } while ($nextPageToken);

        return $events;
    }

    private function fetchWebexMeetings(UserMeetingConnection $connection): array
    {
        $data = $this->getJsonWithToken($connection, 'https://webexapis.com/v1/meetings', ['max' => 100]);

        return collect($data['items'] ?? [])->filter(fn ($m) => isset($m['id'], $m['start']))->map(fn ($m) => [
            'external_id' => (string) $m['id'],
            'title' => $m['title'] ?? 'Webex Meeting',
            'start_at' => $m['start'],
            'end_at' => $m['end'] ?? null,
            'location' => $m['webLink'] ?? null,
            'notes' => 'Synced from Webex',
            'cancelled' => ($m['state'] ?? '') === 'ended' ? false : (($m['state'] ?? '') === 'cancelled'),
        ])->values()->all();
    }

    private function getJsonWithToken(UserMeetingConnection $connection, string $url, array $query = []): array
    {
        /** @var Response $response */
        $response = Http::withToken($connection->access_token)
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500)
            ->get($url, $query);

        if ($response->status() === 401) {
            throw new \RuntimeException('The calendar authorization is no longer valid. Please reconnect the account.');
        }

        if (! $response->successful()) {
            $payload = $response->json() ?: [];

            if ($connection->platform === 'zoom' && (int) ($payload['code'] ?? 0) === 4711) {
                throw new \RuntimeException(
                    'Zoom authorization is missing the meeting-list permission. Ask the administrator to add the Zoom scope meeting:read:list_meetings, then disconnect and reconnect Zoom to grant the updated permission.'
                );
            }

            $providerMessage = trim((string) ($payload['message'] ?? ''));
            throw new \RuntimeException(
                $providerMessage !== ''
                    ? 'The external calendar could not be read: ' . \Illuminate\Support\Str::limit($providerMessage, 180)
                    : 'The external calendar could not be read right now.'
            );
        }

        return $response->json() ?: [];
    }

    private function dateWithZone(string $value, string $zone): string
    {
        try {
            return Carbon::parse($value, $zone)->toIso8601String();
        } catch (Throwable) {
            return $value;
        }
    }

    private function normaliseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }

    private function currentMonthStart(?Carbon $from = null): Carbon
    {
        $minimum = now()->startOfMonth();

        return $from && $from->greaterThan($minimum)
            ? $from->copy()
            : $minimum;
    }
}
