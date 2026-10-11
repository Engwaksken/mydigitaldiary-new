<?php

namespace App\Services\Ai;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class FormAssistService
{
    public function __construct(private ActiveAiClient $client)
    {
    }

    public function generate(string $module, string $topic, array $context = []): array
    {
        $definition = $this->definition($module);

        $topic = trim($topic);
        if ($topic === '') {
            throw ValidationException::withMessages([
                'topic' => ['Enter a topic or title first.'],
            ]);
        }

        $cleanContext = Arr::only($context, $definition['context_fields']);
        $cleanContext = collect($cleanContext)
            ->map(fn ($value) => is_scalar($value) ? trim((string) $value) : $value)
            ->filter(fn ($value) => $value !== '' && $value !== null && $value !== [])
            ->all();

        $fieldList = collect($definition['fields'])
            ->map(fn ($rule, $field) => $field . ': ' . $rule)
            ->implode("\n");

        $system = <<<'SYS'
You are a practical drafting assistant inside My Digital Diary.
Return ONE valid JSON object only. Do not use Markdown or HTML.
Never invent personal facts, names, contact details, private history, religious identity, dates, or commitments that the user did not provide.
Generated text is only a draft for the user to review and edit before saving.
Keep wording human, concise, useful and non-judgmental.
Use a standard hyphen (-), never an en dash or em dash.
SYS;

        $prompt = "MODULE: {$module}\n"
            . "USER TOPIC/TITLE: {$topic}\n\n"
            . "KNOWN FORM CONTEXT (may be empty):\n"
            . json_encode($cleanContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . "\n\nRETURN ONLY THESE APPROVED FIELDS:\n{$fieldList}\n\n"
            . $definition['instructions'];

        $raw = $this->client->json($system, $prompt);
        $allowed = array_keys($definition['fields']);

        $result = [];
        if ($module === 'spiritual-practices') {
            $faithPath = isset($cleanContext['faith_path']) && is_scalar($cleanContext['faith_path'])
                ? (string) $cleanContext['faith_path']
                : null;
            $references = (new ScriptureReferenceService())->forTopic($topic, $faithPath);
            if ($references !== []) {
                $result['bible_references'] = $references;
                $result['bible_references_text'] = mb_substr(
                    collect($references)
                        ->map(fn ($ref) => $ref['reference'].' — '.$ref['text'])
                        ->implode("\n"),
                    0,
                    1500
                );
            }
        }

        foreach ($allowed as $field) {
            if (! array_key_exists($field, $raw)) {
                continue;
            }

            $value = $raw[$field];
            if (is_array($value)) {
                $value = implode(', ', array_values(array_filter(array_map(
                    fn ($item) => is_scalar($item) ? trim((string) $item) : '',
                    $value
                ))));
            }

            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $value = trim((string) ($value ?? ''));
            if ($value === '') {
                continue;
            }

            $result[$field] = mb_substr($value, 0, $definition['limits'][$field] ?? 6000);
        }

        if ($module === 'spiritual-practices' && isset($result['bible_references_text'])) {
            $refsText = (string) $result['bible_references_text'];
            $existing = isset($result['inspirational_text']) ? (string) $result['inspirational_text'] : '';
            if (trim($existing) === '') {
                $result['inspirational_text'] = mb_substr($refsText, 0, 1500);
            } elseif ($refsText !== '' && ! str_contains($existing, (string) ($result['bible_references'][0]['reference'] ?? ''))) {
                $result['inspirational_text'] = mb_substr(trim($existing)."\n\n".$refsText, 0, 1500);
            }
        }

        return $result;
    }

    private function definition(string $module): array
    {
        return match ($module) {
            'spiritual-practices' => [
                'context_fields' => ['faith_path', 'practice_type'],
                'fields' => [
                    'practice_title' => 'short title, max 120 characters',
                    'practice_type' => 'one of prayer, meditation, worship, sacred_text_reading, reflection, gratitude, fasting, mindfulness, community_gathering, service, chanting, pilgrimage, study, personal_ritual, other',
                    'inspirational_text' => 'short non-fabricated inspirational passage or paraphrased prompt; do not falsely attribute a quotation',
                    'source_tradition' => 'only when safely supported by the provided context; otherwise omit',
                    'reflection' => 'first-person reflection prompt or draft',
                    'gratitude' => 'short gratitude prompt/draft',
                    'intention' => 'one practical intention',
                    'duration_minutes' => 'integer-like text between 5 and 60',
                    'notes' => 'brief practice notes or next step',
                ],
                'limits' => ['practice_title'=>120,'practice_type'=>40,'inspirational_text'=>1500,'source_tradition'=>180,'reflection'=>2500,'gratitude'=>1500,'intention'=>1500,'duration_minutes'=>3,'notes'=>2500],
                'instructions' => 'Respect the user faith_path if supplied. If no faith path is supplied, keep the wording inclusive and do not assign a religion. Do not invent scripture citations or sacred-text quotations.',
            ],
            'social-media-planner' => [
                'context_fields' => ['platforms', 'media_type'],
                'fields' => [
                    'title' => 'keep the user supplied post title unchanged where possible; max 160 characters',
                    'caption' => 'write 3 to 5 short, natural paragraphs in warm human English. Keep each paragraph to 1 or 2 short sentences and usually 20 to 45 words. Avoid corporate wording, repetition, filler, exaggerated claims, and long explanations. Do not include hashtags, the call to action, or a raw URL in the caption.',
                    'hashtags' => 'ONLY hashtags for the post, space-separated, each beginning with #. Use 4 to 8 useful hashtags. Do not include sentences, commentary, URLs, or caption text.',
                    'media_type' => 'one of text, image, video, carousel',
                    'content_objective' => 'short human-readable objective such as awareness, engagement, education, promotion, reflection',
                    'media_idea' => 'one concise practical image/video/carousel idea',
                    'call_to_action' => 'one short, friendly and natural CTA, usually one sentence',
                ],
                'limits' => ['title'=>160,'caption'=>2600,'hashtags'=>700,'media_type'=>20,'content_objective'=>160,'media_idea'=>700,'call_to_action'=>280],
                'instructions' => 'Write for a real person, not like a brochure. The caption must have 3 to 5 short paragraphs separated by blank lines. Aim for roughly 100 to 220 words total unless the user topic clearly needs less. Use simple, conversational English. Keep hashtags completely out of the caption and return them only in the hashtags field. Keep the call to action separate. Do not schedule, publish, select accounts, or invent facts about an organisation/product that the user did not provide.',
            ],
            'relationships' => [
                'context_fields' => ['name', 'category', 'relation_label', 'priority', 'interests'],
                'fields' => [
                    'interaction_notes' => 'neutral conversation idea or notes draft based only on supplied context',
                    'interests' => 'only expand interests already supplied; otherwise omit',
                    'commitments' => 'suggested commitment phrased as a future intention, not an invented existing promise',
                    'follow_up_items' => 'practical next action/check-in idea',
                    'strengthening_goal' => 'short relationship-strengthening goal',
                    'notes' => 'brief private note draft without invented personal facts',
                    'reminder_suggestion' => 'human-readable suggestion such as Follow up next week; do not invent a specific date',
                ],
                'limits' => ['interaction_notes'=>2200,'interests'=>1200,'commitments'=>1600,'follow_up_items'=>1600,'strengthening_goal'=>1600,'notes'=>2200,'reminder_suggestion'=>300],
                'instructions' => 'Never invent sensitive details about the other person. Write suggestions as possibilities, not facts. Never create email/phone/birthday/anniversary values.',
            ],
            'network-contacts' => [
                'context_fields' => ['name', 'relationship_type', 'network_groups', 'company', 'met_through'],
                'fields' => [
                    'opportunities' => 'possible opportunity framing based only on supplied context; do not claim an opportunity exists',
                    'action_points' => 'clear next actions',
                    'goal' => 'networking goal',
                    'notes' => 'brief context/meeting note draft',
                    'follow_up_message' => 'short optional follow-up message draft',
                    'conversation_points' => '2 to 4 concise conversation points',
                ],
                'limits' => ['opportunities'=>1800,'action_points'=>1800,'goal'=>1200,'notes'=>2200,'follow_up_message'=>1800,'conversation_points'=>1800],
                'instructions' => 'Never invent the contact name, company, role, phone, email or a prior interaction. When details are missing, keep suggestions generic and editable.',
            ],
            default => throw ValidationException::withMessages([
                'module' => ['AI form assistance is not enabled for this module.'],
            ]),
        };
    }
}
