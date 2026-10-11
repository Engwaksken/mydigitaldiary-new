<?php

namespace App\Services;

use App\Models\AiPlan;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Ai\AiTextFormatter;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Takes the cross-module snapshot from UserSnapshotService and asks an AI
 * provider to turn it into a concrete, prioritized plan.
 *
 * Two ways a user can generate a plan:
 *   1. Their OWN API key (added at /api-credentials) — unlimited, their
 *      own cost, always takes priority if they have one active.
 *   2. The site's shared, admin-supplied default key (configured at
 *      /admin/settings) — free, but capped at
 *      `default_ai_free_limit_per_month` generations per user per month,
 *      since real API cost is incurred on the ADMIN's account for every
 *      shared-key generation, not the user's own.
 */
class AiPlannerService
{
    public function __construct(protected UserSnapshotService $snapshotService)
    {
    }

    /**
     * $now defaults to server time — unchanged behavior for the web
     * app. Mobile passes the phone's own local time instead, same
     * reasoning as UserSnapshotService::build()'s $now parameter,
     * which this passes straight through to.
     *
     * @return array{content: string, used_shared_key: bool}
     */
    public function generate(User $user, ?\Illuminate\Support\Carbon $now = null, ?string $customPrompt = null): array
    {
        $credential = $user->activeApiCredential();

        if ($credential) {
            $prompt = $this->buildPrompt($user, $now, $customPrompt);

            return [
                'content' => $this->call($credential->provider, $credential->api_key, $prompt),
                'used_shared_key' => false,
            ];
        }

        $settings = SiteSetting::current();

        if (! $settings->hasDefaultAiKey()) {
            throw new RuntimeException('No active AI API key. Add one under "API Keys" first.');
        }

        $usedThisMonth = AiPlan::where('user_id', $user->id)
            ->where('used_shared_key', true)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        if ($usedThisMonth >= $settings->default_ai_free_limit_per_month) {
            throw new RuntimeException(
                "You've used all {$settings->default_ai_free_limit_per_month} of your free AI plans for this month. " .
                'Add your own API key under "API Keys" to keep generating plans, or wait until next month.'
            );
        }

        $prompt = $this->buildPrompt($user, $now, $customPrompt);

        return [
            'content' => $this->call($settings->default_ai_provider, $settings->default_ai_api_key, $prompt),
            'used_shared_key' => true,
        ];
    }

    private function call(string $provider, string $apiKey, string $prompt): string
    {
        return AiTextFormatter::normalize(match ($provider) {
            'anthropic' => $this->callAnthropic($apiKey, $prompt),
            'openai' => $this->callOpenAi($apiKey, $prompt),
            default => $this->callCustomProvider($provider, $apiKey, $prompt),
        });
    }

    /**
     * Any provider that isn't one of the two built-ins is assumed
     * OpenAI-compatible (see the ai_providers migration's reasoning) —
     * looked up by its `key` for the base URL/model an admin configured,
     * then called with the exact same request shape as callOpenAi(),
     * just against a different endpoint/model.
     */
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
            'max_tokens' => 1500,
        ]);

        if ($response->failed()) {
            throw new RuntimeException($provider->name . ' API error: ' . $this->extractError($response));
        }

        return (string) $response->json('choices.0.message.content', '');
    }

    protected function buildPrompt(User $user, ?\Illuminate\Support\Carbon $now = null, ?string $customPrompt = null): string
    {
        $now ??= \Illuminate\Support\Carbon::now();
        $snapshot = $this->snapshotService->build($user, $now);
        $today = $now->format('l, F j, Y');
        $customInstruction = trim((string) $customPrompt);
        $customInstruction = $customInstruction !== ''
            ? "\nUSER REQUEST (follow this as the primary planning instruction while staying grounded in the snapshot):\n{$customInstruction}\n"
            : '';

        return <<<PROMPT
You are a supportive, practical personal-life coach. Today's date is
{$today} — use this as the actual current date for any "next 7 days",
"next 30 days", "overdue", or "coming up" reasoning below; do not assume
or guess a different date.

Below is a JSON snapshot of one person's own self-tracked data across
their finances, health, projects, education, professional network, and
personal relationships.

Using ONLY this data, write a concrete, encouraging action plan with three
sections:
1. "Next 7 days" — a short prioritized checklist.
2. "Next 30 days" — a few bigger goals worth focusing on.
3. "Watch out for" — anything urgent or at risk (e.g. over budget, an
   overdue relationship check-in, a health checkup coming up, a stalled
   project).

Be specific, reference actual items from the data by name, and keep the
whole response under 400 words. Do not invent data that isn't present.
When the snapshot contains goal_learnings, use those reflections as practical
memory: repeat strategies the user said worked, avoid or adjust patterns they
said were difficult, and carry forward lessons they explicitly recorded. Never
present a reflection as a fact beyond what the user wrote.

Formatting: PLAIN TEXT ONLY. Do not use Markdown syntax of any kind — no
"##" headings, no "**bold**", no "-" or "*" bullet markers. Write each
section's heading as a plain capitalized line (e.g. "Next 7 Days" on its
own line, followed by a blank line), and write list items as plain numbered
lines (e.g. "1. Finish the report"). This will be displayed as-is, with no
Markdown renderer, so any Markdown symbols would show up literally as
clutter rather than formatting.
Use a standard hyphen (-), never an en dash or em dash, in your response.

{$customInstruction}
SNAPSHOT:
{$this->snapshotService->toJson($snapshot)}
PROMPT;
    }

    protected function callAnthropic(string $apiKey, string $prompt): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 1500,
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

    protected function callOpenAi(string $apiKey, string $prompt): string
    {
        $response = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 1500,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI API error: ' . $this->extractError($response));
        }

        return (string) $response->json('choices.0.message.content', '');
    }

    protected function extractError($response): string
    {
        return $response->json('error.message') ?? $response->body();
    }
}
