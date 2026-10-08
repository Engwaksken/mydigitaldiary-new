<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends push notifications via Firebase Cloud Messaging's current HTTP v1
 * API (NOT the legacy server-key API, which Google has been shutting
 * down) — implemented with plain PHP/openssl rather than pulling in
 * kreait/firebase-php or google/auth, so it works with zero new Composer
 * dependencies. If you'd rather use kreait/firebase-php for its nicer
 * API, this class is a drop-in replacement target — nothing outside this
 * file needs to change, everything calls FcmService::sendToUser().
 *
 * Setup (one-time):
 *   1. Firebase Console -> Project Settings -> Service Accounts ->
 *      "Generate new private key" -> downloads a JSON file.
 *   2. Put that file somewhere OUTSIDE your web root (e.g.
 *      storage/app/firebase-service-account.json — already .gitignore'd
 *      via storage/app/.gitignore's default rules, but double check).
 *   3. .env:
 *        FIREBASE_PROJECT_ID=your-firebase-project-id
 *        FIREBASE_CREDENTIALS_PATH=storage/app/firebase-service-account.json
 */
class FcmService
{
    private const TOKEN_CACHE_KEY = 'fcm_access_token';
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /**
     * Sends the same notification to every device this user has
     * registered (see DeviceTokenController) — a user with the app open
     * on both a phone and a tablet gets it on both. Removes any device
     * token FCM reports as invalid/unregistered along the way, so a
     * uninstalled app or a stale token doesn't keep getting (harmlessly
     * failing) push attempts forever.
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $accessToken = $this->getAccessToken();

        if (! $accessToken) {
            return; // Not configured, or Google's token endpoint is unreachable — fail silently, same "push is a bonus, not critical" philosophy as the in-app alarm sound.
        }

        $projectId = config('services.firebase.project_id');
        $webpush = $this->webpushOptions($title, $body, $data);

        foreach ($user->deviceTokens as $device) {
            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $device->fcm_token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => array_map('strval', $data), // FCM data payloads must be string => string
                        // Browser / installed web app tokens (platform 'web').
                        // Ignored by FCM for Android/iOS tokens and vice versa.
                        'webpush' => $webpush,
                        'android' => [
                            'priority' => 'high',
                            'notification' => [
                                // Must match Flutter's channel. Using a new
                                // channel id also avoids Android retaining an
                                // older low-importance channel configuration.
                                'channel_id' => 'reminders_v2',
                                'sound' => 'default',
                                'default_vibrate_timings' => true,
                                'notification_priority' => 'PRIORITY_MAX',
                            ],
                        ],
                        'apns' => [
                            'headers' => ['apns-priority' => '10'],
                            'payload' => [
                                'aps' => [
                                    'sound' => 'default',
                                    'content-available' => 1,
                                ],
                            ],
                        ],
                    ],
                ]);

            if ($this->isDeadToken($response)) {
                // UNREGISTERED, or a token FCM says is not a valid
                // registration token, means the app was uninstalled, the
                // browser revoked permission, or the token rotated and the
                // device hasn't re-registered yet — clean it up rather than
                // retrying it forever on every future reminder.
                $device->delete();
            } elseif ($response->failed()) {
                Log::warning('FCM push failed.', [
                    'user_id' => $user->id,
                    'device_id' => $device->device_id,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
            }
        }
    }

    /**
     * Public Firebase web-app identifiers for browser push, or null when any
     * is missing — the UI then hides the "Turn on daily reminders" control
     * instead of offering a button that can never work.
     *
     * @return array{apiKey:string,appId:string,messagingSenderId:string,projectId:string,vapidKey:string}|null
     */
    public static function webConfig(): ?array
    {
        $web = (array) config('services.firebase.web', []);
        $config = [
            'apiKey' => (string) ($web['api_key'] ?? ''),
            'appId' => (string) ($web['app_id'] ?? ''),
            'messagingSenderId' => (string) ($web['messaging_sender_id'] ?? ''),
            'projectId' => (string) config('services.firebase.project_id', ''),
            'vapidKey' => (string) ($web['vapid_key'] ?? ''),
        ];

        foreach ($config as $value) {
            if (trim($value) === '') {
                return null;
            }
        }

        return $config;
    }

    /**
     * 404 is always UNREGISTERED. A 400 only means a dead token when FCM says
     * so (INVALID_ARGUMENT about the registration token, or an UNREGISTERED
     * detail) — any other 400 is a payload problem on our side, and deleting
     * a healthy device token for it would silently stop all future pushes.
     */
    private function isDeadToken(\Illuminate\Http\Client\Response $response): bool
    {
        if ($response->status() === 404) {
            return true;
        }

        if ($response->status() !== 400) {
            return false;
        }

        $error = (array) $response->json('error', []);
        $codes = collect($error['details'] ?? [])->pluck('errorCode')->filter()->all();

        if (in_array('UNREGISTERED', $codes, true)) {
            return true;
        }

        return str_contains(strtolower((string) ($error['message'] ?? '')), 'registration token');
    }

    /**
     * Web push options: urgent delivery, the app icon, and a click-through to
     * the deep link. FCM rejects a non-HTTPS fcm_options.link, so it is only
     * set on HTTPS deployments; the service worker also reads data.link.
     */
    private function webpushOptions(string $title, string $body, array $data): array
    {
        $notification = ['title' => $title, 'body' => $body];

        try {
            $notification['icon'] = url(app(PwaIconService::class)->url('icon-192'));
        } catch (\Throwable) {
            // No icon is fine; the browser shows its default.
        }

        if (! empty($data['type'])) {
            // Same tag replaces an older unread reminder instead of stacking.
            $notification['tag'] = (string) $data['type'];
        }

        $options = [
            'headers' => ['Urgency' => 'high'],
            'notification' => $notification,
        ];

        $link = (string) ($data['link'] ?? '');
        if (str_starts_with($link, 'https://')) {
            $options['fcm_options'] = ['link' => $link];
        }

        return $options;
    }

    /**
     * Google's OAuth2 access tokens are valid for ~1 hour — cached for 55
     * minutes so a burst of reminders firing close together (e.g. the
     * per-minute scheduler catching several at once) doesn't re-authenticate
     * with Google on every single one.
     */
    private function getAccessToken(): ?string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(55), function () {
            $credentialsPath = config('services.firebase.credentials_path');

            if (! $credentialsPath || ! file_exists(base_path($credentialsPath)) && ! file_exists($credentialsPath)) {
                Log::warning('FCM not configured — FIREBASE_CREDENTIALS_PATH is missing or the file does not exist.');
                return null;
            }

            $path = file_exists($credentialsPath) ? $credentialsPath : base_path($credentialsPath);
            $credentials = json_decode(file_get_contents($path), true);

            if (! $credentials || empty($credentials['private_key']) || empty($credentials['client_email'])) {
                Log::warning('FCM service account JSON is malformed or unreadable.');
                return null;
            }

            $jwt = $this->buildSignedJwt($credentials);

            $response = Http::asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->failed()) {
                Log::warning('Could not exchange FCM service account JWT for an access token.', [
                    'response' => $response->json(),
                ]);
                return null;
            }

            return $response->json('access_token');
        });
    }

    /**
     * Builds and RS256-signs a Google service-account JWT by hand (base64url
     * header + claims + a raw openssl_sign over that), rather than pulling
     * in firebase/php-jwt or google/auth as a dependency — this is the
     * standard "JWT bearer" flow Google documents for server-to-server auth.
     */
    private function buildSignedJwt(array $credentials): string
    {
        $now = time();

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));

        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signingInput = "{$header}.{$claims}";

        openssl_sign($signingInput, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);

        return $signingInput . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
