<?php

namespace App\Services\Ai;

use App\Models\AiProvider;
use App\Models\SiteSetting;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes one line to storage/logs/ai-*.log for every request the app makes to
 * an AI provider: which feature, which user, whose key, the outcome and the
 * size. Never the prompt, the response or the key itself.
 *
 * It hangs off Laravel's HTTP client events rather than each service, so the
 * older services that build their own Http:: calls are covered too. It is
 * registered in AppServiceProvider (not app/Listeners) because this app
 * caches discovered events, and a discovered copy would log twice. This is
 * the audit trail for spotting abuse (one account hammering the shared key),
 * provider failures, and a user's own key silently falling back to the
 * shared one.
 */
class AiRequestLogger
{
    private const KNOWN_HOSTS = ['api.openai.com', 'api.anthropic.com', 'generativelanguage.googleapis.com'];

    public function handle(ResponseReceived|ConnectionFailed $event): void
    {
        try {
            $request = $event->request;
            $host = strtolower((string) parse_url($request->url(), PHP_URL_HOST));

            if (! $this->isAiHost($host)) {
                return;
            }

            $response = $event instanceof ResponseReceived ? $event->response : null;

            Log::channel('ai')->info('AI provider request', [
                'feature' => $this->feature(),
                'user_id' => auth()->id(),
                'host' => $host,
                'path' => (string) parse_url($request->url(), PHP_URL_PATH),
                'key' => $this->keySource($request),
                'status' => $response?->status() ?? 'connection_failed',
                'ms' => $response ? (int) round(((float) ($response->handlerStats()['total_time'] ?? 0)) * 1000) : null,
                'request_bytes' => strlen($request->body()),
            ]);
        } catch (Throwable) {
            // Logging must never break the AI feature it is observing.
        }
    }

    private function isAiHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        if (in_array($host, self::KNOWN_HOSTS, true)) {
            return true;
        }

        // Admin-configured OpenAI-compatible providers.
        $customHosts = Cache::remember('ai-provider-hosts', 600, fn () => AiProvider::query()
            ->whereNotNull('api_base_url')
            ->pluck('api_base_url')
            ->map(fn ($url) => strtolower((string) parse_url((string) $url, PHP_URL_HOST)))
            ->filter()
            ->values()
            ->all());

        return in_array($host, $customHosts, true);
    }

    /**
     * "shared" when the request carries the administrator's key, otherwise
     * "user". Compared in constant time; the key is never logged.
     */
    private function keySource(Request $request): string
    {
        $sent = $request->header('x-api-key')[0]
            ?? preg_replace('/^Bearer\s+/i', '', (string) ($request->header('Authorization')[0] ?? ''));
        $shared = trim((string) SiteSetting::current()->default_ai_api_key);

        if ($sent === '' || $sent === null) {
            return 'none';
        }

        return $shared !== '' && hash_equals($shared, (string) $sent) ? 'shared' : 'user';
    }

    /**
     * The app class that made the call, e.g. "MeetingSummaryService".
     */
    private function feature(): string
    {
        $skip = [self::class, ActiveAiClient::class, AiCredentialResolver::class];

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 60) as $frame) {
            $class = $frame['class'] ?? '';

            if (str_starts_with($class, 'App\\') && ! in_array($class, $skip, true)) {
                return class_basename($class);
            }
        }

        return 'unknown';
    }
}
