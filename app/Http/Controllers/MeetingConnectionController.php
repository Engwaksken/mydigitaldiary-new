<?php

namespace App\Http\Controllers;

use App\Models\MeetingPlatformConfig;
use App\Models\UserMeetingConnection;
use App\Services\ExternalCalendarSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Services\DailyInsightService;

class MeetingConnectionController extends Controller
{
    /**
     * OAuth settings for the external calendars/meeting accounts supported
     * by My Digital Diary. Google and Microsoft are calendar-first; Zoom and
     * Webex remain supported for users who keep meetings there directly.
     *
     * @return array<string,string>|null
     */
    private function providerConfig(string $platform): ?array
    {
        return match ($platform) {
            'zoom' => [
                'authorize_url' => 'https://zoom.us/oauth/authorize',
                'token_url' => 'https://zoom.us/oauth/token',
                'scope' => 'meeting:read:list_meetings',
            ],
            'google' => [
                'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_url' => 'https://oauth2.googleapis.com/token',
                'scope' => 'https://www.googleapis.com/auth/calendar.readonly',
            ],
            'microsoft' => [
                'authorize_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
                'token_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                'scope' => 'offline_access Calendars.Read User.Read',
            ],
            'webex' => [
                'authorize_url' => 'https://webexapis.com/v1/authorize',
                'token_url' => 'https://webexapis.com/v1/access_token',
                'scope' => 'meeting:schedules_read',
            ],
            default => null,
        };
    }

    public function connect(Request $request, string $platform): RedirectResponse
    {
        $config = MeetingPlatformConfig::where('platform', $platform)
            ->where('is_enabled', true)
            ->first();
        $providerConfig = $this->providerConfig($platform);

        if (! $config || ! $config->isConfigured() || ! $providerConfig) {
            return back()->withErrors(['platform' => 'This external calendar is not enabled/configured by the administrator.']);
        }

        $state = Str::random(40);
        session(['meeting_oauth_state_' . $platform => $state]);

        $params = [
            'response_type' => 'code',
            'client_id' => $config->client_id,
            'redirect_uri' => $this->redirectUri($platform),
            'scope' => $providerConfig['scope'],
            'state' => $state,
        ];

        if ($platform === 'google') {
            $params['access_type'] = 'offline';
            $params['prompt'] = 'consent';
            $params['include_granted_scopes'] = 'true';
        }

        return redirect($providerConfig['authorize_url'] . '?' . http_build_query($params));
    }

    public function callback(Request $request, string $platform, ExternalCalendarSyncService $syncService): RedirectResponse
    {
        $config = MeetingPlatformConfig::where('platform', $platform)->first();
        $providerConfig = $this->providerConfig($platform);

        if (! $config || ! $providerConfig) {
            return redirect()->route('meetings.index')->withErrors(['platform' => 'Unknown external calendar.']);
        }

        $expectedState = (string) session('meeting_oauth_state_' . $platform);
        session()->forget('meeting_oauth_state_' . $platform);

        if (! $expectedState || ! hash_equals($expectedState, (string) $request->query('state'))) {
            return redirect()->route('meetings.index')->withErrors(['platform' => 'Could not verify this sign-in attempt. Please connect again.']);
        }

        if ($request->has('error')) {
            return redirect()->route('meetings.index')->withErrors(['platform' => 'Calendar connection was cancelled or denied.']);
        }

        $tokenRequest = [
            'grant_type' => 'authorization_code',
            'code' => $request->query('code'),
            'redirect_uri' => $this->redirectUri($platform),
            'client_id' => $config->client_id,
            'client_secret' => $config->client_secret,
        ];

        $response = Http::asForm()->timeout(30)->post($providerConfig['token_url'], $tokenRequest);
        if (! $response->successful()) {
            return redirect()->route('meetings.index')->withErrors(['platform' => 'The provider rejected the connection. Check the OAuth redirect URI and credentials.']);
        }

        $tokenData = $response->json();
        $existing = UserMeetingConnection::where('user_id', $request->user()->id)
            ->where('platform', $platform)
            ->first();

        $connection = UserMeetingConnection::updateOrCreate(
            ['user_id' => $request->user()->id, 'platform' => $platform],
            [
                'access_token' => $tokenData['access_token'] ?? null,
                // Google may omit refresh_token on a later consent; preserve the old one.
                'refresh_token' => $tokenData['refresh_token'] ?? $existing?->refresh_token,
                'token_expires_at' => isset($tokenData['expires_in']) ? now()->addSeconds((int) $tokenData['expires_in']) : null,
                'connected_email' => $request->user()->email,
            ]
        );

        try {
            $stats = $syncService->syncConnection($connection);
            return redirect()->route('meetings.index')->with(
                'success',
                ucfirst($platform) . " connected. {$stats['imported']} external meeting(s) imported and {$stats['updated']} updated."
            );
        } catch (\Throwable $e) {
            return redirect()->route('meetings.index')->with(
                'success',
                ucfirst($platform) . ' connected. Use “Sync calendars now” to import the meetings.'
            );
        }
    }

    public function disconnect(Request $request, string $platform): RedirectResponse
    {
        UserMeetingConnection::where('user_id', $request->user()->id)
            ->where('platform', $platform)
            ->delete();

        // Keep already-synced meeting history visible; disconnecting only stops future syncs.
        return redirect()->route('meetings.index')->with('success', ucfirst($platform) . ' disconnected. Previously synced meetings were kept.');
    }

    public function sync(Request $request, ExternalCalendarSyncService $syncService): RedirectResponse
    {
        $data=$request->validate([
            'provider'=>['nullable','in:google,microsoft,zoom,webex'],
            'sync_from_date'=>['required','date'],
            'sync_to_date'=>['nullable','date','after_or_equal:sync_from_date'],
            'include_recurring'=>['nullable','boolean'],
        ]);
        $from=Carbon::parse($data['sync_from_date']);
        $to=!empty($data['sync_to_date'])?Carbon::parse($data['sync_to_date']):$from->copy()->addMonth();
        $result=$syncService->syncUser((int)$request->user()->id,$from,$to,$data['provider'] ?? null,(bool)($data['include_recurring'] ?? true));
        app(DailyInsightService::class)->invalidateFor($request->user());
        $message="Calendar sync complete for {$from->format('d M Y')} - {$to->format('d M Y')}: {$result['imported']} new, {$result['updated']} updated.";
        if($result['errors']) return redirect()->route('meetings.index')->with('success',$message)->withErrors(['calendar_sync'=>implode(' ',$result['errors'])]);
        return redirect()->route('meetings.index')->with('success',$message);
    }

    private function redirectUri(string $platform): string
    {
        return url('/meetings/connect/' . $platform . '/callback');
    }
}
