<?php

namespace App\Services;

use App\Models\DietLog;
use App\Models\DailyFoodJournal;
use App\Models\HealthProfile;
use App\Models\SleepLog;
use App\Models\User;
use App\Services\Ai\ActiveAiClient;
use App\Services\Ai\AiTextFormatter;
use Illuminate\Support\Facades\Log;

class HealthAiCoachService
{
    public function __construct(private readonly ActiveAiClient $ai)
    {
    }

    public function profile(User $user): HealthProfile
    {
        return HealthProfile::firstOrCreate(['user_id' => $user->id]);
    }

    public function sleepAdvice(User $user, bool $force = false): ?array
    {
        $profile = $this->profile($user);

        if (! $profile->weight_kg) {
            return null;
        }

        if (
            ! $force
            && is_array($profile->sleep_advice)
            && $profile->sleep_advice_generated_at?->isToday()
        ) {
            return AiTextFormatter::normalize($profile->sleep_advice);
        }

        $recent = SleepLog::where('user_id', $user->id)
            ->orderByDesc('sleep_date')
            ->limit(7)
            ->get(['sleep_date', 'bed_time', 'wake_time', 'duration_minutes', 'quality'])
            ->map(fn (SleepLog $log) => [
                'date' => optional($log->sleep_date)->toDateString(),
                'bed_time' => $log->bed_time,
                'wake_time' => $log->wake_time,
                'hours' => $log->duration_minutes ? round($log->duration_minutes / 60, 1) : null,
                'quality' => $log->quality,
            ])
            ->values()
            ->all();

        try {
            $advice = $this->ai->json(
                $this->healthSafetySystem('sleep'),
                json_encode([
                    'profile' => $this->profileContext($profile),
                    'recent_sleep' => $recent,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            $clean = [
                'recommended_bedtime' => trim((string) ($advice['recommended_bedtime'] ?? '')),
                'recommended_wake_time' => trim((string) ($advice['recommended_wake_time'] ?? '')),
                'recommended_hours' => trim((string) ($advice['recommended_hours'] ?? '')),
                'summary' => trim((string) ($advice['summary'] ?? '')),
                'tips' => array_values(array_slice(array_filter(
                    (array) ($advice['tips'] ?? []),
                    fn ($tip) => is_string($tip) && trim($tip) !== ''
                ), 0, 5)),
                'medical_note' => trim((string) ($advice['medical_note'] ?? '')),
            ];

            $profile->forceFill([
                'sleep_advice' => $clean,
                'sleep_advice_generated_at' => now(),
            ])->save();

            return $clean;
        } catch (\Throwable $e) {
            Log::warning('Sleep AI advice unavailable', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return is_array($profile->sleep_advice) ? AiTextFormatter::normalize($profile->sleep_advice) : null;
        }
    }

    public function dietAdvice(User $user, bool $force = false): ?array
    {
        $profile = $this->profile($user);

        if (! $profile->weight_kg) {
            return null;
        }

        if (
            ! $force
            && is_array($profile->diet_advice)
            && $profile->diet_advice_generated_at?->isToday()
        ) {
            return AiTextFormatter::normalize($profile->diet_advice);
        }

        $todayMeals = DietLog::where('user_id', $user->id)
            ->whereDate('logged_at', now()->toDateString())
            ->orderBy('id')
            ->get(['meal_type', 'food_items', 'calories'])
            ->map(fn (DietLog $log) => [
                'meal' => $log->meal_type,
                'food_items' => $log->food_items,
                'estimated_calories' => $log->calories,
            ])
            ->values()
            ->all();

        $dailyJournal = DailyFoodJournal::where('user_id', $user->id)
            ->whereDate('journal_date', now()->toDateString())
            ->orderBy('created_at')
            ->pluck('daily_food_notes')
            ->filter()
            ->values()
            ->all();

        $recentCalories = DietLog::where('user_id', $user->id)
            ->where('logged_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(logged_at) as day, SUM(calories) as calories')
            ->groupByRaw('DATE(logged_at)')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'day' => $row->day,
                'calories' => $row->calories !== null ? (int) $row->calories : null,
            ])
            ->all();

        try {
            $advice = $this->ai->json(
                $this->healthSafetySystem('diet'),
                json_encode([
                    'profile' => $this->profileContext($profile),
                    'today_food_log' => $todayMeals,
                    'today_daily_eating_notes' => $dailyJournal,
                    'recent_daily_calorie_estimates' => $recentCalories,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            $clean = [
                'summary' => trim((string) ($advice['summary'] ?? '')),
                'today_focus' => trim((string) ($advice['today_focus'] ?? '')),
                'meal_ideas' => array_values(array_slice(array_filter(
                    (array) ($advice['meal_ideas'] ?? []),
                    fn ($tip) => is_string($tip) && trim($tip) !== ''
                ), 0, 5)),
                'habits' => array_values(array_slice(array_filter(
                    (array) ($advice['habits'] ?? []),
                    fn ($tip) => is_string($tip) && trim($tip) !== ''
                ), 0, 5)),
                'allergy_note' => trim((string) ($advice['allergy_note'] ?? '')),
                'medical_note' => trim((string) ($advice['medical_note'] ?? '')),
            ];

            $profile->forceFill([
                'diet_advice' => $clean,
                'diet_advice_generated_at' => now(),
            ])->save();

            return $clean;
        } catch (\Throwable $e) {
            Log::warning('Diet AI advice unavailable', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return is_array($profile->diet_advice) ? AiTextFormatter::normalize($profile->diet_advice) : null;
        }
    }

    public function invalidateSleep(User $user): void
    {
        HealthProfile::where('user_id', $user->id)->update([
            'sleep_advice' => null,
            'sleep_advice_generated_at' => null,
        ]);
    }

    public function invalidateDiet(User $user): void
    {
        HealthProfile::where('user_id', $user->id)->update([
            'diet_advice' => null,
            'diet_advice_generated_at' => null,
        ]);
    }

    private function profileContext(HealthProfile $profile): array
    {
        return [
            'weight_kg' => $profile->weight_kg,
            'height_cm' => $profile->height_cm,
            'age_range' => $profile->age_range,
            'activity_level' => $profile->activity_level,
            'health_goal' => $profile->health_goal,
            'food_allergies' => $profile->food_allergies,
            'dietary_preferences' => $profile->dietary_preferences,
            'health_conditions_or_current_illness' => $profile->health_conditions,
            'sleep_challenges' => $profile->sleep_challenges,
            'usual_bed_time' => $profile->usual_bed_time,
            'usual_wake_time' => $profile->usual_wake_time,
        ];
    }

    private function healthSafetySystem(string $module): string
    {
        $output = $module === 'sleep'
            ? <<<'JSON'
Return JSON only:
{
  "recommended_bedtime": "e.g. 10:30 PM or empty if not enough information",
  "recommended_wake_time": "e.g. 6:30 AM or empty if not enough information",
  "recommended_hours": "short range such as 7-9 hours, only when appropriate",
  "summary": "short personalised general-wellness explanation",
  "tips": ["up to five practical low-risk sleep habits"],
  "medical_note": "short note when clinician input would be appropriate"
}
JSON
            : <<<'JSON'
Return JSON only:
{
  "summary": "short overview of the user's logged eating pattern",
  "today_focus": "one practical food focus for today",
  "meal_ideas": ["up to five practical meal or snack ideas compatible with stated allergies/preferences"],
  "habits": ["up to five sustainable eating habits"],
  "allergy_note": "state how the suggestions avoid the user's stated allergies; empty if none stated",
  "medical_note": "short note when clinician/dietitian input would be appropriate"
}
JSON;

        return <<<SYSTEM
You are the My Digital Diary AI wellbeing coach for {$module}.

You provide general wellness education and habit suggestions only. You do NOT diagnose,
treat disease, prescribe medication, recommend stopping medication, or replace a doctor,
dietitian, psychologist, or sleep specialist.

Safety rules:
- Treat health conditions/current illness as context, not as a diagnosis.
- Do not claim that weight alone determines health.
- Do not provide extreme calorie restriction, fasting, purging, rapid weight-loss plans,
  or rigid numerical targets that could encourage disordered eating.
- Never recommend a food that conflicts with a stated allergy.
- If there is not enough information for a precise recommendation, say so rather than guessing.
- If the user reports a condition that can materially affect sleep/nutrition, keep advice
  conservative and recommend discussing personalised targets with a qualified clinician.
- For urgent warning signs, advise seeking urgent medical care; do not attempt diagnosis.
- Prefer practical, culturally flexible foods and habits; do not assume a Western diet.
- Clearly describe calorie values as estimates when they come from logged food descriptions.

{$output}
SYSTEM;
    }
}
