<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DietLog;
use App\Models\ExerciseLog;
use App\Models\Plan;
use App\Models\SleepLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AnnualPlanWellbeingSyncService
{
    public function syncCompletion(User $user, Plan $plan, bool $completed): ?string
    {
        $module = 'unknown';

        try {
            // Keep the entire optional wellbeing integration best-effort. In
            // particular, a relationship/schema error here must not turn an
            // otherwise successful Annual Plan update into an HTTP 500.
            $plan->loadMissing('personalGoal');
            $goal = $plan->personalGoal;

            if (! $goal) {
                return null;
            }

            $module = strtolower(trim((string) $goal->module));
            if (! in_array($module, ['diet', 'sleep', 'exercise'], true)) {
                return null;
            }

            $timezone = $user->timezone ?: config('app.timezone', 'Africa/Kampala');
            $date = Carbon::now($timezone)->startOfDay();
            $sourceKey = "annual-plan:{$plan->id}:{$module}";

            if (! $completed) {
                $this->remove($module, (int) $user->id, $sourceKey);
                app(DailyWellbeingSyncService::class)->sync($user, $date->toDateString());
                return 'Plan reopened. Its linked wellbeing contribution was removed.';
            }

            $message = match ($module) {
                'diet' => $this->diet($user, $plan, $date, $sourceKey),
                'sleep' => $this->sleep($user, $plan, $date, $sourceKey),
                'exercise' => $this->exercise($user, $plan, $date, $sourceKey),
            };

            app(DailyWellbeingSyncService::class)->sync($user, $date->toDateString());
            return $message;
        } catch (Throwable $e) {
            Log::warning('Annual Plan wellbeing sync failed', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'module' => $module,
                'message' => $e->getMessage(),
            ]);
            return 'Plan completed, but its linked wellbeing statistics could not be updated.';
        }
    }

    private function diet(User $user, Plan $plan, Carbon $date, string $sourceKey): string
    {
        if (! Schema::hasColumn('diet_logs', 'annual_plan_source_key')) {
            return 'Plan completed. Run the latest migration to enable Annual Plan wellbeing linking.';
        }

        $log = DietLog::query()->where('user_id', $user->id)
            ->where('annual_plan_source_key', $sourceKey)->first() ?? new DietLog();

        $text = trim((string) ($plan->description ?: $plan->title));

        $log->forceFill([
            'user_id' => $user->id,
            'annual_plan_source_key' => $sourceKey,
            'meal_type' => $this->mealType($text),
            'food_items' => $text,
            'calories' => null,
            'logged_at' => $date->toDateString(),
            'notes' => 'Automatically added from a completed Annual Plan linked to a Diet goal.',
            'is_archived' => false,
        ])->save();

        return 'Annual Plan completed and linked to Diet and Daily Wellbeing.';
    }

    private function exercise(User $user, Plan $plan, Carbon $date, string $sourceKey): string
    {
        if (! Schema::hasColumn('exercise_logs', 'annual_plan_source_key')) {
            return 'Plan completed. Run the latest migration to enable Annual Plan wellbeing linking.';
        }

        $minutes = $this->exerciseMinutes($plan);
        if ($minutes < 1) {
            return 'Plan completed. Add an exercise duration or measurable linked goal target to feed exercise statistics.';
        }

        $log = ExerciseLog::query()->where('user_id', $user->id)
            ->where('annual_plan_source_key', $sourceKey)->first() ?? new ExerciseLog();

        $log->forceFill([
            'user_id' => $user->id,
            'annual_plan_source_key' => $sourceKey,
            'activity' => $plan->title,
            'duration_minutes' => $minutes,
            'intensity' => 'moderate',
            'calories_burned' => null,
            'performed_at' => $date,
            'notes' => 'Automatically added from a completed Annual Plan linked to an Exercise goal.',
            'is_archived' => false,
        ])->save();

        return 'Annual Plan completed and linked to Exercise and Daily Wellbeing.';
    }

    private function sleep(User $user, Plan $plan, Carbon $date, string $sourceKey): string
    {
        if (! Schema::hasColumn('sleep_logs', 'annual_plan_source_key')) {
            return 'Plan completed. Run the latest migration to enable Annual Plan wellbeing linking.';
        }

        $minutes = $this->sleepMinutes($plan);
        if ($minutes < 1) {
            return 'Plan completed. Add sleep hours or a measurable linked goal target to feed sleep statistics.';
        }

        $wake = Carbon::createFromTime(7, 0);
        $bed = $wake->copy()->subMinutes($minutes);

        $log = SleepLog::query()->where('user_id', $user->id)
            ->where('annual_plan_source_key', $sourceKey)->first() ?? new SleepLog();

        $log->forceFill([
            'user_id' => $user->id,
            'annual_plan_source_key' => $sourceKey,
            'sleep_date' => $date->toDateString(),
            'bed_time' => $bed->format('H:i'),
            'wake_time' => $wake->format('H:i'),
            'duration_minutes' => $minutes,
            'quality' => 'fair',
            'notes' => 'Automatically added from a completed Annual Plan linked to a Sleep goal.',
            'is_archived' => false,
        ])->save();

        return 'Annual Plan completed and linked to Sleep and Daily Wellbeing.';
    }

    private function exerciseMinutes(Plan $plan): int
    {
        $text = trim($plan->title.' '.($plan->description ?? ''));

        if (preg_match('/\b(\d{1,4})\s*(?:min|mins|minute|minutes)\b/i', $text, $m)) {
            return min(1440, max(1, (int) $m[1]));
        }

        $target = (float) ($plan->personalGoal?->target_value ?? 0);
        return $target >= 1 && $target <= 1440 ? (int) round($target) : 0;
    }

    private function sleepMinutes(Plan $plan): int
    {
        $text = trim($plan->title.' '.($plan->description ?? ''));

        if (preg_match('/\b(\d{1,2}(?:\.\d+)?)\s*(?:hour|hours|hr|hrs)\b/i', $text, $m)) {
            return min(1440, max(1, (int) round(((float) $m[1]) * 60)));
        }

        $target = (float) ($plan->personalGoal?->target_value ?? 0);
        return $target >= 1 && $target <= 24 ? (int) round($target * 60) : 0;
    }

    private function mealType(string $text): string
    {
        $text = strtolower($text);
        foreach (['breakfast', 'lunch', 'snack', 'dinner', 'supper'] as $meal) {
            if (str_contains($text, $meal)) return $meal;
        }
        return 'lunch';
    }

    private function remove(string $module, int $userId, string $sourceKey): void
    {
        $model = match ($module) {
            'diet' => DietLog::class,
            'sleep' => SleepLog::class,
            'exercise' => ExerciseLog::class,
        };

        $table = (new $model)->getTable();
        if (! Schema::hasColumn($table, 'annual_plan_source_key')) return;

        $model::query()->where('user_id', $userId)
            ->where('annual_plan_source_key', $sourceKey)->delete();
    }
}
