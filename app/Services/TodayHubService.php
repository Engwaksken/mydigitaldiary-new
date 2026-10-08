<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Income;
use App\Models\Meeting;
use App\Models\Plan;
use App\Models\ProjectTask;
use App\Models\Reminder;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The mobile "Today" hub — the same calm daily view as the web dashboard
 * (resources/views/dashboard.blade.php): greeting, today's task progress,
 * streak with the last 7 days, one nudge line, Start / Close my day state,
 * the get-started checklist, today's top tasks, what's coming up, progress
 * rings and "On this day" memories.
 *
 * Built from the same services the web DashboardController uses
 * (DailyPlannerRecurrenceService, DailyEngagementService, GrowthStrategyService,
 * PersonalProgressService, DailyReminderService, OnThisDayService). Every
 * optional block is isolated so one failing source never hides the hub.
 */
class TodayHubService
{
    private const TOP_TASKS = 5;

    private const COMING_UP = 4;

    public function build(User $user): array
    {
        $timezone = app(DailyReminderService::class)->timezone($user);
        $now = Carbon::now($timezone);
        $today = $now->toDateString();
        $yesterday = $now->copy()->subDay()->toDateString();
        $hour = (int) $now->format('G');

        $engagement = $this->safe(fn () => app(DailyEngagementService::class)->dashboard($user), []);
        $tasks = $this->tasks($user, $now);

        // Streak: the stored "current" only resets on the next action, so a
        // streak whose last active day is older than yesterday is shown as 0.
        $activeDays = $this->recentActiveDays($user, $now);
        $lastActive = substr((string) data_get($engagement, 'streak.last_day', ''), 0, 10);
        $activeToday = $lastActive === $today || in_array($today, $activeDays, true);
        $alive = $activeToday || $lastActive === $yesterday;
        $storedStreak = (int) data_get($engagement, 'streak.current', 0);

        $week = collect(range(6, 0))->map(function (int $daysAgo) use ($now, $activeDays, $today, $activeToday) {
            $day = $now->copy()->subDays($daysAgo);
            $date = $day->toDateString();

            return [
                'date' => $date,
                'label' => substr($day->format('D'), 0, 1),
                'active' => in_array($date, $activeDays, true) || ($date === $today && $activeToday),
                'today' => $date === $today,
            ];
        })->values()->all();

        $reminders = $this->dailyReminders($user);

        return [
            'date' => $today,
            'date_label' => $now->format('l, j F'),
            'timezone' => $timezone,
            'local_time' => $now->format('H:i'),
            'greeting' => $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening'),
            'first_name' => trim(explode(' ', (string) ($user->name ?: 'there'))[0] ?? 'there'),
            'evening' => $hour >= 17,
            'tasks' => $tasks,
            'streak' => [
                'current' => $alive ? $storedStreak : 0,
                'best' => (int) data_get($engagement, 'streak.best', 0),
                'active_today' => $activeToday,
                'alive' => $alive,
                'week' => $week,
            ],
            'nudge' => $this->nudge($activeToday, $alive, $storedStreak, $tasks['total'], $tasks['completed']),
            'routine' => [
                'start_done' => (bool) data_get($engagement, 'start_day.completed', false),
                'close_done' => (bool) data_get($engagement, 'close_day.completed', false),
            ],
            'celebration' => data_get($engagement, 'celebration.title'),
            'get_started' => $this->getStarted($user, $tasks['total'], $reminders),
            'coming_up' => $this->comingUp($user, $now),
            'rings' => $this->rings($user),
            'money' => $this->money($user, $now),
            'on_this_day' => $this->onThisDay($user),
            'daily_reminders' => $reminders,
        ];
    }

    /**
     * Recurrence-aware totals plus the top open tasks (same ordering as the
     * web dashboard: priority, then start time).
     */
    private function tasks(User $user, Carbon $now): array
    {
        $empty = ['total' => 0, 'completed' => 0, 'percent' => 0, 'items' => [], 'has_more' => false];

        return $this->safe(function () use ($user, $now, $empty) {
            $service = app(DailyPlannerRecurrenceService::class);
            $all = $service->itemsForDate($user->id, $now->copy());
            $stats = $service->statistics($all);

            $open = $all
                ->filter(fn ($item) => ! (bool) data_get($item, 'is_completed', false))
                ->sortBy(function ($item) {
                    $priority = strtolower((string) data_get($item, 'priority', ''));
                    $weight = in_array($priority, ['urgent', 'high'], true) ? 0 : ($priority === 'medium' ? 1 : 2);
                    $time = (string) data_get($item, 'start_time', '');

                    return sprintf('%d-%s-%010d', $weight, $time ?: '99:99:99', (int) data_get($item, 'id', 0));
                })
                ->values();

            $items = $open->take(self::TOP_TASKS)->map(function ($item) use ($now) {
                $start = data_get($item, 'start_time');

                return [
                    'id' => (int) data_get($item, 'id'),
                    'title' => (string) (data_get($item, 'title') ?: 'Daily task'),
                    'priority' => (string) (data_get($item, 'priority') ?: ''),
                    'start_time' => $start ? substr((string) $start, 0, 5) : null,
                    'time_label' => $start ? $this->timeLabel((string) $start) : 'Anytime',
                    'occurrence_date' => substr((string) (data_get($item, 'occurrence_date') ?: $now->toDateString()), 0, 10),
                    'is_completed' => false,
                    'is_recurring' => (string) (data_get($item, 'repeat_type') ?: 'once') !== 'once',
                ];
            })->values()->all();

            $total = (int) ($stats['total'] ?? 0);
            $completed = (int) ($stats['completed'] ?? 0);

            return [
                'total' => $total,
                'completed' => $completed,
                'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
                'items' => $items,
                'has_more' => $open->count() > count($items),
            ];
        }, $empty);
    }

    /** @return list<string> local dates (Y-m-d) of the last 7 days with a meaningful action */
    private function recentActiveDays(User $user, Carbon $now): array
    {
        return $this->safe(function () use ($user, $now) {
            if (! Schema::hasTable('engagement_events')) {
                return [];
            }

            return DB::table('engagement_events')
                ->where('user_id', $user->id)
                ->whereBetween('event_date', [$now->copy()->subDays(6)->toDateString(), $now->toDateString()])
                ->distinct()
                ->pluck('event_date')
                ->map(fn ($date) => substr((string) $date, 0, 10))
                ->unique()
                ->values()
                ->all();
        }, []);
    }

    private function nudge(bool $activeToday, bool $alive, int $streak, int $total, int $done): string
    {
        if ($activeToday) {
            return $total > 0 && $done >= $total
                ? 'Everything done. Enjoy your evening.'
                : 'You showed up today. Keep going.';
        }

        if ($alive && $streak > 0) {
            return 'Tick off one task to keep your streak.';
        }

        return 'One small action today starts a streak.';
    }

    private function dailyReminders(User $user): array
    {
        $prefs = $this->safe(fn () => app(DailyReminderService::class)->preferences($user), [
            'morning_enabled' => true,
            'morning_time' => DailyReminderService::DEFAULT_TIMES['morning'],
            'evening_enabled' => true,
            'evening_time' => DailyReminderService::DEFAULT_TIMES['evening'],
        ]);

        $hasDevice = $this->safe(fn () => $user->deviceTokens()->exists(), false);

        return [
            'morning_enabled' => (bool) $prefs['morning_enabled'],
            'morning_time' => (string) $prefs['morning_time'],
            'evening_enabled' => (bool) $prefs['evening_enabled'],
            'evening_time' => (string) $prefs['evening_time'],
            'device_registered' => $hasDevice,
            'on' => ($prefs['morning_enabled'] || $prefs['evening_enabled']) && $hasDevice,
        ];
    }

    /** Same steps as the web Get-started card; the app resolves "install" itself (always done). */
    private function getStarted(User $user, int $taskTotal, array $reminders): array
    {
        $activation = $this->safe(fn () => collect(data_get(app(GrowthStrategyService::class)->dashboard($user), 'activation.steps', [])), collect())
            ->keyBy(fn ($step) => is_array($step) ? ($step['key'] ?? '') : ($step->key ?? ''));
        $done = fn (string $key) => (bool) data_get($activation->get($key), 'complete', false);

        $steps = [
            ['key' => 'plan', 'label' => 'Add your first task', 'done' => $done('plan') || $taskTotal > 0],
            ['key' => 'goal', 'label' => 'Set a goal', 'done' => $done('goal')],
            ['key' => 'money', 'label' => 'Record money', 'done' => $done('money')],
            ['key' => 'daily-reminders', 'label' => 'Turn on daily reminders', 'done' => (bool) $reminders['on']],
        ];

        $completed = collect($steps)->where('done', true)->count();

        return [
            'steps' => $steps,
            'done' => $completed,
            'total' => count($steps),
            'complete' => $completed === count($steps),
        ];
    }

    /** Meetings, reminders and project tasks due later today, earliest first. */
    private function comingUp(User $user, Carbon $now): array
    {
        $start = $now->copy()->startOfDay()->utc();
        $end = $now->copy()->endOfDay()->utc();
        $timezone = $now->getTimezone();
        $rows = collect();

        $this->safe(function () use ($user, $start, $end, $timezone, $rows) {
            Meeting::where('user_id', $user->id)
                ->where('status', 'scheduled')
                ->whereBetween('start_at', [$start, $end])
                ->orderBy('start_at')
                ->limit(12)
                ->get()
                ->each(fn ($meeting) => $rows->push($this->agendaRow('meeting', $meeting->id, $meeting->title, $meeting->start_at, $timezone)));
        }, null);

        $this->safe(function () use ($user, $start, $end, $timezone, $rows) {
            Reminder::where('user_id', $user->id)
                ->where('is_active', true)
                ->whereBetween('next_run_at', [$start, $end])
                ->orderBy('next_run_at')
                ->limit(12)
                ->get()
                ->each(fn ($reminder) => $rows->push($this->agendaRow('reminder', $reminder->id, $reminder->title, $reminder->next_run_at, $timezone)));
        }, null);

        $this->safe(function () use ($user, $now, $rows) {
            ProjectTask::where('user_id', $user->id)
                ->whereIn('status', ['todo', 'in_progress'])
                ->whereDate('due_date', $now->toDateString())
                ->orderByDesc('id')
                ->limit(12)
                ->get()
                ->each(fn ($task) => $rows->push($this->agendaRow('project_task', $task->id, $task->title, null, null)));
        }, null);

        $sorted = $rows->sortBy(fn ($row) => $row['time_sort'])->values();

        return [
            'items' => $sorted->take(self::COMING_UP)->map(fn ($row) => collect($row)->except('time_sort')->all())->all(),
            'more' => max(0, $sorted->count() - self::COMING_UP),
        ];
    }

    private function agendaRow(string $type, int $id, ?string $title, $time, $timezone): array
    {
        $local = $time ? Carbon::parse($time)->setTimezone($timezone) : null;

        return [
            'type' => $type,
            'id' => $id,
            'title' => (string) ($title ?: ucfirst(str_replace('_', ' ', $type))),
            'time' => $local?->toIso8601String(),
            'time_label' => $local ? $local->format('g:i A') : 'Today',
            'time_sort' => $local ? $local->format('H:i') : '99:99',
        ];
    }

    /** Week / annual plans / savings / money health — only where data exists. */
    private function rings(User $user): array
    {
        $progress = $this->safe(fn () => app(PersonalProgressService::class)->summary($user), []);
        $weekly = (array) ($progress['weekly_review'] ?? []);
        $health = (array) ($progress['financial_health'] ?? []);

        $plans = $this->safe(function () use ($user) {
            $query = Plan::where('user_id', $user->id)->where('plan_year', now()->year);

            return [
                'total' => (clone $query)->count(),
                'completed' => (clone $query)->where('status', 'completed')->count(),
                'progress' => (int) round((float) ((clone $query)->avg('progress_percent') ?? 0)),
            ];
        }, ['total' => 0, 'completed' => 0, 'progress' => 0]);

        $savings = $this->safe(function () use ($user) {
            $goals = SavingsGoal::where('user_id', $user->id)->withSum('contributions', 'amount')->get();

            return ['saved' => (float) $goals->sum('contributions_sum_amount'), 'target' => (float) $goals->sum('target_amount')];
        }, ['saved' => 0.0, 'target' => 0.0]);

        $savingsPercent = $savings['target'] > 0
            ? (int) min(100, round(($savings['saved'] / $savings['target']) * 100))
            : null;

        return [
            [
                'key' => 'week',
                'label' => 'This week',
                'value' => (int) ($weekly['completion_percent'] ?? 0),
                'text' => (int) ($weekly['completed_tasks'] ?? 0).'/'.(int) ($weekly['total_tasks'] ?? 0).' tasks',
                'unit' => '%',
                'empty' => false,
            ],
            [
                'key' => 'annual_plans',
                'label' => 'Annual plans',
                'value' => $plans['progress'],
                'text' => $plans['total'] > 0 ? $plans['completed'].'/'.$plans['total'].' done' : 'Add a plan',
                'unit' => '%',
                'empty' => $plans['total'] === 0,
            ],
            [
                'key' => 'savings',
                'label' => 'Savings',
                'value' => (int) ($savingsPercent ?? 0),
                'text' => $savingsPercent !== null ? format_money($savings['saved']) : 'Set a target',
                'unit' => '%',
                'empty' => $savingsPercent === null,
            ],
            [
                'key' => 'money_health',
                'label' => 'Money health',
                'value' => (int) ($health['score'] ?? 0),
                'text' => (string) ($health['label'] ?? 'Getting started'),
                'unit' => '',
                'empty' => false,
            ],
        ];
    }

    private function money(User $user, Carbon $now): array
    {
        return $this->safe(function () use ($user, $now) {
            $from = $now->copy()->startOfMonth()->toDateString();
            $to = $now->copy()->endOfMonth()->toDateString();

            $spent = (float) Expense::where('user_id', $user->id)->whereDate('spent_at', '>=', $from)->whereDate('spent_at', '<=', $to)->sum('amount');
            $income = (float) Income::where('user_id', $user->id)->whereDate('received_at', '>=', $from)->whereDate('received_at', '<=', $to)->sum('amount');
            $budget = app(ExpenseBudgetLinkService::class)->monthBudgetTotal($user);

            return [
                'spent' => $spent,
                'income' => $income,
                'budget' => $budget,
                'spent_label' => format_money($spent),
                'income_label' => format_money($income),
                'budget_label' => $budget > 0 ? format_money($budget) : null,
            ];
        }, ['spent' => 0.0, 'income' => 0.0, 'budget' => 0.0, 'spent_label' => format_money(0), 'income_label' => format_money(0), 'budget_label' => null]);
    }

    private function onThisDay(User $user): array
    {
        $data = $this->safe(fn () => app(OnThisDayService::class)->forUser($user), ['items' => [], 'comparison' => null]);

        return [
            'items' => collect($data['items'] ?? [])->take(3)->map(fn ($item) => [
                'anchor' => (string) ($item['anchor'] ?? ''),
                'when' => (string) ($item['when'] ?? ''),
                'date' => (string) ($item['date'] ?? ''),
                'tone' => (string) ($item['tone'] ?? 'slate'),
                'icon' => (string) ($item['icon'] ?? ''),
                'text' => (string) ($item['text'] ?? ''),
                // Web URL; the app maps its path to a screen.
                'link' => $item['url'] ?? null,
            ])->values()->all(),
            'comparison' => $data['comparison'] ?? null,
        ];
    }

    private function timeLabel(string $time): string
    {
        try {
            return Carbon::parse($time)->format('g:i A');
        } catch (\Throwable) {
            return $time;
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @param  T  $fallback
     * @return T
     */
    private function safe(callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $exception) {
            report($exception);

            return $fallback;
        }
    }
}
