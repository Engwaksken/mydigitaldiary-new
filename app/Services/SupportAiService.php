<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\SupportConversation;
use Illuminate\Support\Facades\Http;

class SupportAiService
{
    public function reply(SupportConversation $conversation, string $question): ?string
    {
        if ($conversation->assigned_to_user_id || $conversation->status === 'human') {
            return null;
        }

        $settings = SiteSetting::current();

        if (! $settings->hasDefaultAiKey() || strtolower((string) $settings->default_ai_provider) !== 'openai') {
            return 'Thanks for your message. I have saved it for the support team, and a support person will attend to you as soon as possible.';
        }

        $history = $conversation->messages()
            ->latest()
            ->limit(12)
            ->get()
            ->reverse()
            ->map(fn ($message) => [
                'role' => $message->sender_type === 'user' ? 'user' : 'assistant',
                'content' => $message->message,
            ])
            ->values()
            ->all();

        $systemPrompt = <<<'PROMPT'
You are the friendly support assistant for My Digital Diary.

Your job is to help users understand and use the actual My Digital Diary application. Speak like a helpful human support person, not like a generic AI assistant.

Language:
- Understand and answer in English, Luganda, or Kiswahili.
- Reply in the same language the user is mainly using.
- If the user mixes languages, reply naturally in the dominant language and you may keep familiar product/menu names in English.
- Use clear, natural East African wording where appropriate.

Style:
- Be warm, practical, concise and conversational.
- Do not say things like "Open the app and create a diary entry" when a specific My Digital Diary feature exists.
- Refer to real product areas where relevant, such as Daily Planner, Annual Plans, Financial Planner, Income, Budgets, Expenses, Savings Goals, Projects, Tasks, Meetings, Reminders, Health, Education, Business Card, Personal Reports, Subscriptions and Support.
- Give direct step-by-step guidance only when steps are useful.
- Do not over-explain simple questions.
- Do not use Markdown formatting. Do not use asterisks for bold, hash headings, backticks, markdown tables or decorative symbols.
- Plain numbered steps such as "1. Open Daily Planner" are allowed.
- Use a standard hyphen (-), never an en dash or em dash.
- Do not end every answer with generic phrases such as "If you need more assistance, let me know" unless it genuinely helps.

Accuracy and safety:
- Do not invent account data, balances, payment status, subscription status, reminders, transactions or actions you cannot verify.
- If something requires an administrator or human support person, clearly say that a support person needs to take over.
- Never ask for passwords, OTP codes, card PINs, CVV numbers, mobile-money PINs or API secrets.
- Never claim to have completed an action unless the system actually performed it.
PROMPT;

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $history,
            [['role' => 'user', 'content' => trim($question)]]
        );

        $response = Http::withToken($settings->default_ai_api_key)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'temperature' => 0.45,
                'max_tokens' => 650,
            ]);

        if (! $response->successful()) {
            report(new \RuntimeException('Support AI failed: '.$response->status().' '.$response->body()));

            return 'I could not prepare an automated answer just now. Your message has been saved for support follow-up.';
        }

        $reply = trim((string) data_get($response->json(), 'choices.0.message.content'));

        return $reply !== '' ? self::plainText($reply) : null;
    }

    /**
     * Convert AI-style Markdown into clean chat text.
     * Also used when rendering older stored replies.
     */
    public static function plainText(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        // Remove fenced code markers but retain the useful text inside them.
        $text = preg_replace('/```(?:[a-z0-9_+-]+)?\s*/i', '', $text) ?? $text;
        $text = str_replace('```', '', $text);

        // Markdown headings -> normal text.
        $text = preg_replace('/^\s{0,3}#{1,6}\s+/m', '', $text) ?? $text;

        // Bold / italic / inline-code markers.
        $text = str_replace(['**', '__', '`'], '', $text);
        $text = preg_replace('/(?<!\*)\*([^\n*]+)\*(?!\*)/', '$1', $text) ?? $text;
        $text = preg_replace('/(?<!_)_([^\n_]+)_(?!_)/', '$1', $text) ?? $text;

        // Markdown links: [label](url) -> label (url)
        $text = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/i', '$1 ($2)', $text) ?? $text;

        // Blockquotes and unordered markdown bullets -> simple bullets.
        $text = preg_replace('/^\s*>\s?/m', '', $text) ?? $text;
        $text = preg_replace('/^\s*[-*+]\s+/m', '• ', $text) ?? $text;

        // Remove markdown horizontal rules.
        $text = preg_replace('/^\s*[-*_]{3,}\s*$/m', '', $text) ?? $text;

        // Avoid excessive blank lines while preserving readable paragraphs.
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim(str_replace(['—', '–'], '-', $text));
    }
}
