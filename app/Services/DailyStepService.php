<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailyStep;
use App\Models\HealthProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DailyStepService
{
    public const STARTING_GOAL = 5000;
    public const GOAL_INCREMENT = 100;
    public const MAX_GOAL = 10000;

    /** Average adult stride length in metres, used when height is unknown. */
    public const DEFAULT_STRIDE_M = 0.762;

    public const MIN_STRIDE_M = 0.5;
    public const MAX_STRIDE_M = 1.0;

    /** Common rule of thumb: stride in metres is roughly 41.5% of height in metres. */
    private const STRIDE_FACTOR = 0.415;

    public function forToday(User $user): DailyStep
    {
        $timezone = $user->timezone ?: config('app.timezone', 'Africa/Kampala');
        $date = Carbon::now($timezone)->toDateString();

        $existing = DailyStep::query()
            ->where('user_id', $user->id)
            ->whereDate('tracking_date', $date)
            ->first();

        if ($existing) {
            return $existing;
        }

        $goal = $this->currentGoal($user);

        /*
         * Auto-start the day's tracking session. A person should not have to
         * remember to press "Start" every morning — the moment today's step
         * record is first touched (opening the app, syncing, viewing the
         * dashboard) tracking is already on. Tapping "Stop" still pauses it
         * for the rest of the day.
         */
        return DailyStep::firstOrCreate(
            [
                'user_id' => $user->id,
                'tracking_date' => $date,
            ],
            [
                'steps' => 0,
                'daily_goal' => $goal,
                'is_tracking' => true,
                'tracking_started_at' => now(),
            ]
        );
    }

    public function currentGoal(User $user): int
    {
        if (Schema::hasColumn('users', 'current_step_goal')) {
            $saved = (int) ($user->current_step_goal ?? 0);

            if ($saved >= self::STARTING_GOAL) {
                return min(self::MAX_GOAL, $saved);
            }
        }

        /*
         * Existing accounts may already have step history from before the
         * progressive-goal feature. Preserve their most recent target.
         */
        $latestGoal = (int) (
            DailyStep::query()
                ->where('user_id', $user->id)
                ->orderByDesc('tracking_date')
                ->value('daily_goal') ?? 0
        );

        $goal = $latestGoal >= self::STARTING_GOAL
            ? min(self::MAX_GOAL, $latestGoal)
            : self::STARTING_GOAL;

        $this->saveCurrentGoal($user, $goal);

        return $goal;
    }

    public function recordSteps(User $user, int $steps, ?int $distanceM = null): DailyStep
    {
        return DB::transaction(function () use ($user, $steps, $distanceM): DailyStep {
            $row = $this->forToday($user);

            // Never move the same day's count backwards.
            $row->steps = max((int) $row->steps, max(0, $steps));

            if ($distanceM !== null && (int) $distanceM > 0) {
                // Prefer the device-measured distance over our stride estimate.
                $row->distance_m = max((int) $row->distance_m, (int) $distanceM);
            }

            $row->last_synced_at = now();
            $row->save();

            /*
             * The user's NEXT daily target rises once today's target is met.
             * Today's row keeps its original goal, making the progress display
             * stable and ensuring the target only advances one level per day.
             */
            if ((int) $row->steps >= (int) $row->daily_goal) {
                $nextGoal = min(
                    self::MAX_GOAL,
                    max(self::STARTING_GOAL, (int) $row->daily_goal)
                        + self::GOAL_INCREMENT
                );

                $this->saveCurrentGoal($user, $nextGoal);
            }

            $fresh = $row->fresh();

            try {
                app(DailyWellbeingSyncService::class)->sync(
                    $user,
                    $fresh->tracking_date ?? now()
                );
            } catch (\Throwable $exception) {
                report($exception);
            }

            return $fresh;
        });
    }

    public function strideLengthM(User $user): float
    {
        $heightCm = optional(
            HealthProfile::query()->where('user_id', $user->id)->first()
        )->height_cm;

        $stride = is_numeric($heightCm) && (float) $heightCm > 0
            ? ((float) $heightCm * self::STRIDE_FACTOR) / 100
            : self::DEFAULT_STRIDE_M;

        return max(self::MIN_STRIDE_M, min(self::MAX_STRIDE_M, $stride));
    }

    public function distanceFor(User $user, int $steps, ?int $storedDistanceM = null): array
    {
        $strideM = $this->strideLengthM($user);
        $storedMeters = (int) ($storedDistanceM ?? 0);

        if ($storedMeters > 0) {
            return [
                'stride_m' => round($strideM, 3),
                'distance_m' => $storedMeters,
                'distance_km' => round($storedMeters / 1000, 1),
                'distance_source' => 'device',
            ];
        }

        $distanceM = max(0, $steps) * $strideM;

        return [
            'stride_m' => round($strideM, 3),
            'distance_m' => (int) round($distanceM),
            'distance_km' => round($distanceM / 1000, 1),
            'distance_source' => 'estimated',
        ];
    }

    public function payload(User $user): array
    {
        $row = $this->forToday($user);
        $nextGoal = $this->currentGoal($user);
        $storedMeters = (int) ($row->distance_m ?? 0);
        $distance = $this->distanceFor(
            $user,
            (int) $row->steps,
            $storedMeters > 0 ? $storedMeters : null
        );

        return [
            'date' => optional($row->tracking_date)->toDateString(),
            'steps' => (int) $row->steps,
            'daily_goal' => (int) $row->daily_goal,
            'next_daily_goal' => $nextGoal,
            'goal_achieved' => (int) $row->steps >= (int) $row->daily_goal,
            'progress_percent' => $row->progressPercent(),
            'remaining_steps' => max(
                0,
                (int) $row->daily_goal - (int) $row->steps
            ),
            'distance_m' => $distance['distance_m'],
            'distance_km' => $distance['distance_km'],
            'stride_m' => $distance['stride_m'],
            'distance_source' => $distance['distance_source'],
            'is_tracking' => (bool) $row->is_tracking,
            'tracking_started_at' => optional($row->tracking_started_at)?->toIso8601String(),
            'tracking_stopped_at' => optional($row->tracking_stopped_at)?->toIso8601String(),
            'last_synced_at' => optional($row->last_synced_at)?->toIso8601String(),
            'goal_rules' => [
                'starting_goal' => self::STARTING_GOAL,
                'increment' => self::GOAL_INCREMENT,
                'maximum_goal' => self::MAX_GOAL,
            ],
            'tracking_window' => [
                'starts_at' => '06:00',
                'resets_at' => '00:00',
            ],
        ];
    }

    public function history(User $user, int $days = 30): array
    {
        $days = max(7, min(90, $days));
        $strideM = $this->strideLengthM($user);

        return DailyStep::query()
            ->where('user_id', $user->id)
            ->orderByDesc('tracking_date')
            ->limit($days)
            ->get()
            ->map(function (DailyStep $row) use ($strideM): array {
                $storedMeters = (int) ($row->distance_m ?? 0);
                $distanceM = $storedMeters > 0
                    ? $storedMeters
                    : max(0, (int) $row->steps) * $strideM;

                return [
                    'date' => optional($row->tracking_date)->toDateString(),
                    'tracking_date' => optional($row->tracking_date)->toDateString(),
                    'steps' => (int) $row->steps,
                    'daily_goal' => max(
                        self::STARTING_GOAL,
                        min(self::MAX_GOAL, (int) $row->daily_goal)
                    ),
                    'progress_percent' => $row->progressPercent(),
                    'goal_achieved' => (int) $row->steps >= (int) $row->daily_goal,
                    'stride_m' => round($strideM, 3),
                    'distance_m' => (int) round($distanceM),
                    'distance_km' => round($distanceM / 1000, 1),
                    'distance_source' => $storedMeters > 0 ? 'device' : 'estimated',
                    'last_synced_at' => optional($row->last_synced_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    private function saveCurrentGoal(User $user, int $goal): void
    {
        $goal = max(
            self::STARTING_GOAL,
            min(self::MAX_GOAL, $goal)
        );

        if (! Schema::hasColumn('users', 'current_step_goal')) {
            return;
        }

        if ((int) ($user->current_step_goal ?? 0) === $goal) {
            return;
        }

        $user->forceFill([
            'current_step_goal' => $goal,
        ])->saveQuietly();

        $user->current_step_goal = $goal;
    }
}
