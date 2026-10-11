<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Ai\AiTextFormatter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Turns a meeting transcript into a structured summary — main points,
 * decisions, action items (with assignee/deadline where the transcript
 * actually states one), and open questions. Uses the same two-tier key
 * resolution as AiPlannerService (user's own key, else the site's shared
 * default) — duplicated here rather than shared, specifically so a
 * change to one doesn't risk breaking the other; they're two small,
 * independent call sites into the same provider APIs.
 */
class MeetingSummaryService
{
    /**
     * @return array{main_points: array, decisions: array, action_items: array, questions_for_followup: array}
     * @throws RuntimeException
     */
    public function generate(User $user, string $transcript): array
    {
        [$provider, $apiKey] = $this->resolveCredential($user);

        $prompt = $this->buildPrompt($transcript);
        $raw = $this->call($provider, $apiKey, $prompt);

        return $this->parseResponse($raw);
    }

    private function resolveCredential(User $user): array
    {
        $credential = $user->activeApiCredential();
        if ($credential) {
            return [$credential->provider, $credential->api_key];
        }

        $settings = SiteSetting::current();
        if ($settings->hasDefaultAiKey()) {
            return [$settings->default_ai_provider, $settings->default_ai_api_key];
        }

        throw new RuntimeException('No active AI API key. Add one under "API Keys" first, or ask your admin to set a default provider.');
    }

    private function buildPrompt(string $transcript): string
    {
        return <<<PROMPT
You are summarizing a meeting transcript. Read it carefully and respond with
ONLY a single JSON object (no markdown code fences, no commentary before or
after) with exactly these keys:

{
  "main_points": ["short string per key discussion point"],
  "decisions": ["short string per decision that was made"],
  "action_items": [{"task": "string", "assigned_to": "string or null if unclear", "deadline": "string or null if unclear"}],
  "questions_for_followup": ["short string per open question that needs a follow-up"]
}

Only include an action item's assigned_to or deadline if the transcript
actually states one — use null rather than guessing. If a section has
nothing to report, use an empty array for it. Do not invent content that
isn't supported by the transcript.
Use a standard hyphen (-), never an en dash or em dash, in generated text.

TRANSCRIPT:
{$transcript}
PROMPT;
    }

    private function call(string $provider, string $apiKey, string $prompt): string
    {
        return match ($provider) {
            'anthropic' => $this->callAnthropic($apiKey, $prompt),
            'openai' => $this->callOpenAi($apiKey, $prompt),
            default => $this->callCustomProvider($provider, $apiKey, $prompt),
        };
    }

    private function callCustomProvider(string $providerKey, string $apiKey, string $prompt): string
    {
        $provider = \App\Models\AiProvider::where('key', $providerKey)->where('is_enabled', true)->first();

        if (! $provider) {
            throw new RuntimeException('Unknown or disabled AI provider: ' . $providerKey);
        }

        $response = Http::withToken($apiKey)->post($provider->api_base_url, [
            'model' => $provider->default_model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 2000,
        ]);

        if ($response->failed()) {
            throw new RuntimeException($provider->name . ' API error: ' . $this->extractError($response));
        }

        return (string) $response->json('choices.0.message.content', '');
    }

    private function callAnthropic(string $apiKey, string $prompt): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 2000,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic API error: ' . $this->extractError($response));
        }

        return collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");
    }

    private function callOpenAi(string $apiKey, string $prompt): string
    {
        $response = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 2000,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI API error: ' . $this->extractError($response));
        }

        return (string) $response->json('choices.0.message.content', '');
    }

    private function extractError($response): string
    {
        return $response->json('error.message') ?? $response->body();
    }

    /**
     * The prompt asks for pure JSON, but models sometimes wrap it in a
     * ```json fence anyway despite instructions not to — strip that
     * defensively before decoding rather than trusting it was followed.
     */
    private function parseResponse(string $raw): array
    {
        $cleaned = trim($raw);

        $cleaned = preg_replace('/^```(?:json)?\s*/', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        if (str_starts_with($cleaned, '{') && str_ends_with($cleaned, '}')) {
        } elseif (preg_match('/\{.*\}/s', $cleaned, $matches)) {
            $cleaned = $matches[0];
        }

        $decoded = json_decode($cleaned, true);

        if (! is_array($decoded)) {
            Log::warning('Failed to parse AI summary response', ['raw' => $raw, 'cleaned' => $cleaned]);
            throw new RuntimeException('The AI provider returned a response that could not be parsed as a summary. Try again.');
        }

        return AiTextFormatter::normalize([
            'main_points' => $decoded['main_points'] ?? [],
            'decisions' => $decoded['decisions'] ?? [],
            'action_items' => $decoded['action_items'] ?? [],
            'questions_for_followup' => $decoded['questions_for_followup'] ?? [],
        ]);
    }
}
