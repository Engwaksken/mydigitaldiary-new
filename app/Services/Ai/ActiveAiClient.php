<?php

namespace App\Services\Ai;

use App\Models\AiProvider;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ActiveAiClient
{
    /**
     * Resolve the same administrator-managed AI configuration used by the
     * rest of My Digital Diary.
     *
     * The selected provider + encrypted shared API key live in site_settings.
     * ai_providers stores only provider metadata (enabled state, endpoint/model).
     */
    public function __construct(private readonly AiCredentialResolver $credentials)
    {
    }

    public function configuration(?User $user = null): array
    {
        $settings = SiteSetting::current();
        $providerKey = strtolower(trim((string) $settings->default_ai_provider));
        $resolved = $user
            ? $this->credentials->resolveForUser($user, 'openai')
            : null;
        $resolved ??= $this->credentials->resolveForUser($user, $providerKey ?: 'openai');

        if (! $resolved) {
            throw new RuntimeException(
                'No default AI provider/API key is configured in Admin Settings.'
            );
        }

        $providerKey = $resolved['provider'];
        $apiKey = $resolved['api_key'];

        if ($providerKey === '' || $apiKey === '') {
            throw new RuntimeException(
                'The administrator AI provider configuration is incomplete.'
            );
        }

        $provider = AiProvider::query()
            ->where('key', $providerKey)
            ->first();

        // If ai_providers exists for this installation, the selected provider
        // must still be enabled. Built-ins are also normally seeded here.
        if ($provider && ! $provider->is_enabled) {
            throw new RuntimeException(
                ($provider->name ?: $providerKey) . ' is disabled in Admin AI Providers.'
            );
        }

        // OpenAI and Anthropic remain usable even if an older installation
        // does not yet contain the metadata seed rows.
        if (! $provider && ! in_array($providerKey, ['openai', 'anthropic'], true)) {
            throw new RuntimeException(
                'The selected Admin AI provider is missing from AI Providers: ' . $providerKey
            );
        }

        return [
            'key' => $providerKey,
            'name' => $provider?->name ?: match ($providerKey) {
                'openai' => 'ChatGPT',
                'anthropic' => 'Claude',
                default => ucfirst($providerKey),
            },
            'api_key' => $apiKey,
            'credential_source' => $resolved['source'],
            'api_base_url' => trim((string) ($provider?->api_base_url ?? '')),
            'model' => trim((string) ($provider?->default_model ?? '')),
        ];
    }

    public function json(string $system, string $prompt, ?User $user = null, int $maxTokens = 700): array
    {
        $cfg = $this->configuration($user);
        $system .= "\nUse a standard hyphen (-), never an en dash or em dash, in generated text.";

        try {
            return AiTextFormatter::normalize($this->sendJson($cfg, $system, $prompt, $maxTokens));
        } catch (RuntimeException $exception) {
            // An invalid personal key must not prevent use of a valid shared
            // OpenAI key. Other provider failures remain visible in logs.
            if ($user && $cfg['credential_source'] === 'user' && $this->isAuthenticationFailure($exception)) {
                $shared = $this->configuration();

                if ($shared['key'] === 'openai' && $shared['credential_source'] === 'shared') {
                    return AiTextFormatter::normalize($this->sendJson($shared, $system, $prompt, $maxTokens));
                }
            }

            throw $exception;
        }
    }

    public function testConnection(?User $user = null): void
    {
        $this->json(
            'Reply with one JSON object only.',
            'Return {"status":"ok"}.',
            $user,
            40
        );
    }

    private function sendJson(array $cfg, string $system, string $prompt, int $maxTokens): array
    {
        return match ($cfg['key']) {
            'anthropic' => $this->anthropic($cfg, $system, $prompt, $maxTokens),
            'openai' => $this->openAi($cfg, $system, $prompt, $maxTokens),
            default => $this->customProvider($cfg, $system, $prompt, $maxTokens),
        };
    }

    private function openAi(array $cfg, string $system, string $prompt, int $maxTokens): array
    {
        $url = $cfg['api_base_url'] !== ''
            ? $this->normaliseChatCompletionsUrl($cfg['api_base_url'])
            : 'https://api.openai.com/v1/chat/completions';

        $response = Http::withToken($cfg['api_key'])
            ->acceptJson()
            ->timeout(60)
            ->post($url, [
                'model' => $cfg['model'] !== '' ? $cfg['model'] : 'gpt-4o-mini',
                'temperature' => 0.45,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => $maxTokens,
            ]);

        $this->throwProviderError($response, $cfg['name']);

        return $this->decodeJson(
            (string) data_get($response->json(), 'choices.0.message.content', '')
        );
    }

    private function anthropic(array $cfg, string $system, string $prompt, int $maxTokens): array
    {
        $url = $cfg['api_base_url'] !== ''
            ? rtrim($cfg['api_base_url'], '/')
            : 'https://api.anthropic.com/v1/messages';

        if (! str_ends_with($url, '/messages')) {
            $url .= '/messages';
        }

        $response = Http::withHeaders([
                'x-api-key' => $cfg['api_key'],
                'anthropic-version' => '2023-06-01',
            ])
            ->acceptJson()
            ->timeout(60)
            ->post($url, [
                'model' => $cfg['model'] !== '' ? $cfg['model'] : 'claude-sonnet-4-6',
                'max_tokens' => $maxTokens,
                'temperature' => 0.45,
                'system' => $system,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        $this->throwProviderError($response, $cfg['name']);

        return $this->decodeJson(
            collect($response->json('content', []))
                ->where('type', 'text')
                ->pluck('text')
                ->implode("\n")
        );
    }

    /**
     * Admin-created providers are OpenAI-compatible by design in this app.
     * The configured api_base_url is respected exactly, matching
     * AiPlannerService::callCustomProvider(). If an admin entered a base URL
     * rather than the full chat-completions endpoint, normalise it safely.
     */
    private function customProvider(array $cfg, string $system, string $prompt, int $maxTokens): array
    {
        if ($cfg['api_base_url'] === '') {
            throw new RuntimeException(
                $cfg['name'] . ' has no API endpoint configured in Admin AI Providers.'
            );
        }

        if ($cfg['model'] === '') {
            throw new RuntimeException(
                $cfg['name'] . ' has no default model configured in Admin AI Providers.'
            );
        }

        $url = $this->normaliseCustomUrl($cfg['api_base_url']);

        $response = Http::withToken($cfg['api_key'])
            ->acceptJson()
            ->timeout(60)
            ->post($url, [
                'model' => $cfg['model'],
                'temperature' => 0.45,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => $maxTokens,
            ]);

        $this->throwProviderError($response, $cfg['name']);

        return $this->decodeJson(
            (string) data_get($response->json(), 'choices.0.message.content', '')
        );
    }

    private function normaliseCustomUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');

        // Preserve a complete endpoint exactly as configured.
        if (
            str_ends_with($url, '/chat/completions')
            || str_contains($url, ':generateContent')
            || str_ends_with($url, '/responses')
        ) {
            return $url;
        }

        // This application's custom-provider contract is OpenAI compatible.
        if (str_ends_with($url, '/v1')) {
            return $url . '/chat/completions';
        }

        // AiPlannerService historically sends directly to api_base_url.
        // Keep that behaviour for arbitrary configured endpoints.
        return $url;
    }

    private function normaliseChatCompletionsUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');

        if (str_ends_with($url, '/chat/completions')) {
            return $url;
        }

        return $url . '/chat/completions';
    }

    private function throwProviderError(Response $response, string $providerName): void
    {
        if ($response->successful()) {
            return;
        }

        $message = (string) (
            $response->json('error.message')
            ?? $response->json('message')
            ?? $response->body()
        );

        $message = trim(strip_tags($message));
        if ($message === '') {
            $message = 'HTTP ' . $response->status();
        }

        throw new RuntimeException(
            $providerName . ' API error (' . $response->status() . '): ' . mb_substr($message, 0, 500)
        );
    }

    private function isAuthenticationFailure(RuntimeException $exception): bool
    {
        return str_contains($exception->getMessage(), ' API error (401)')
            || str_contains($exception->getMessage(), ' API error (403)')
            || str_contains($exception->getMessage(), ' API error (429)');
    }

    private function decodeJson(string $content): array
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $content) ?? $content;

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            // Some providers wrap valid JSON in explanatory text. Extract the
            // outermost JSON object without exposing provider output to users.
            $first = strpos($content, '{');
            $last = strrpos($content, '}');

            if ($first !== false && $last !== false && $last > $first) {
                $decoded = json_decode(substr($content, $first, $last - $first + 1), true);
            }
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('The active AI provider did not return valid insight JSON.');
        }

        return $decoded;
    }
}
