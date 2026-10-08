<?php

namespace App\Services;

use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Models\Expense;
use App\Models\ExerciseLog;
use App\Models\Income;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PeriodReviewMetricsService
{
    public function review(User $user, string $period): array
    {
        [$from, $to] = $this->range($period, $user);

        // Recurrence-aware, exactly like GET /daily-planner?date=… summed
        // over the period, but computed in two queries. The mobile app used
        // to rebuild this itself with one request per day (31 sequential
        // round trips for a month); `tasks_source` tells it these totals can
        // be used as-is.
        try {
            $stats = app(DailyPlannerRecurrenceService::class)
                ->statisticsForRange($user->id, $from, $to);
            $tasksTotal = $stats['total'];
            $tasksCompleted = $stats['completed'];
            $plannerDays = $stats['days'];
            $tasksSource = 'daily_planner';
        } catch (\Throwable $exception) {
            report($exception);

            $taskQuery = DailyPlanItem::query()
                ->whereHas('plan', function ($query) use ($user, $from, $to) {
                    $query
                        ->where('user_id', $user->id)
                        ->whereBetween('plan_date', [
                            $from->toDateString(),
                            $to->toDateString(),
                        ]);
                });

            $tasksTotal = (clone $taskQuery)->count();
            $tasksCompleted = (clone $taskQuery)
                ->where('is_completed', true)
                ->count();
            $plannerDays = null;
            $tasksSource = 'plan_items';
        }

        // Archived rows are excluded, matching the Incomes/Expenses lists.
        $income = (float) Income::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->whereBetween('received_at', [
                $from->toDateString(),
                $to->toDateString(),
            ])
            ->sum('amount');

        $expenses = (float) Expense::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->whereBetween('spent_at', [
                $from->toDateString(),
                $to->toDateString(),
            ])
            ->sum('amount');

        $exerciseSessions = ExerciseLog::query()
            ->where('user_id', $user->id)
            ->whereBetween('performed_at', [
                $from->copy()->startOfDay(),
                $to->copy()->endOfDay(),
            ])
            ->count();

        return [
            'period' => $period,
            'period_start' => $from->toDateString(),
            'period_end' => $to->toDateString(),

            'tasks_total' => $tasksTotal,
            'tasks_completed' => $tasksCompleted,
            'tasks_pending' => max(0, $tasksTotal - $tasksCompleted),
            'completion_percent' => $tasksTotal > 0
                ? (int) round(($tasksCompleted / $tasksTotal) * 100)
                : 0,
            'planner_days' => $plannerDays,
            'tasks_source' => $tasksSource,

            'income' => $income,
            'expenses' => $expenses,
            'exercise_sessions' => $exerciseSessions,
        ];
    }

    /**
     * Pending Daily Planner tasks for the user's current LOCAL day.
     *
     * Recurrence-aware: recurring series live on their original DailyPlan row
     * and are resolved per-date at read time, so they appear here even when
     * no concrete DailyPlan exists for today. This mirrors the web
     * DashboardController, keeping Mobile Today's Focus identical to Web.
     */
    public function todayFocus(User $user, int $limit = 6): Collection
    {
        $today = Carbon::now($this->timezone($user));

        try {
            if (class_exists(\App\Services\DailyPlannerRecurrenceService::class)) {
                $items = app(\App\Services\DailyPlannerRecurrenceService::class)
                    ->itemsForDate($user->id, $today);

                return $items
                    ->filter(fn (DailyPlanItem $item) =>
                        ! (bool) ($item->is_completed ?? false))
                    ->sortBy(function (DailyPlanItem $item) {
                        $priority = strtolower((string) ($item->priority ?? ''));
                        $weight = in_array($priority, ['urgent', 'high'], true)
                            ? 0
                            : ($priority === 'medium' ? 1 : 2);

                        $time = (string) ($item->start_time ?? '');
                        return sprintf(
                            '%d-%s-%010d',
                            $weight,
                            $time !== '' ? $time : '99:99:99',
                            (int) $item->id
                        );
                    })
                    ->take(max(1, $limit))
                    ->values()
                    ->map(function (DailyPlanItem $item) {
                        $item->setAttribute('source', 'Daily Planner');
                        return $item;
                    });
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        // Fallback: concrete items on today's DailyPlan row.
        $todayDate = $today->toDateString();

        $plan = DailyPlan::query()
            ->where('user_id', $user->id)
            ->whereDate('plan_date', $todayDate)
            ->first();

        if (! $plan) {
            return collect();
        }

        return $plan->items()
            ->where('is_completed', false)
            ->orderByRaw("
                CASE priority
                    WHEN 'high' THEN 1
                    WHEN 'medium' THEN 2
                    ELSE 3
                END
            ")
            ->orderByRaw('CASE WHEN start_time IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_time')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(function (DailyPlanItem $item) {
                $item->setAttribute('source', 'Daily Planner');
                return $item;
            });
    }

    public function todayTaskProgress(User $user): array
    {
        $today = Carbon::now($this->timezone($user))->toDateString();

        $plan = DailyPlan::query()
            ->where('user_id', $user->id)
            ->whereDate('plan_date', $today)
            ->first();

        if (! $plan) {
            return [
                'tasks_total' => 0,
                'tasks_completed' => 0,
                'tasks_pending' => 0,
                'completion_percent' => 0,
            ];
        }

        $total = $plan->items()->count();
        $completed = $plan->items()
            ->where('is_completed', true)
            ->count();

        return [
            'tasks_total' => $total,
            'tasks_completed' => $completed,
            'tasks_pending' => max(0, $total - $completed),
            'completion_percent' => $total > 0
                ? (int) round(($completed / $total) * 100)
                : 0,
        ];
    }

    private function range(string $period, User $user): array
    {
        $now = Carbon::now($this->timezone($user));

        if ($period === 'month') {
            return [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ];
        }

        return [
            $now->copy()->startOfWeek(Carbon::MONDAY),
            $now->copy()->endOfWeek(Carbon::SUNDAY),
        ];
    }

    private function timezone(User $user): string
    {
        $timezone = trim((string) ($user->timezone ?? ''));

        return $timezone !== ''
            ? $timezone
            : 'Africa/Kampala';
    }
}
