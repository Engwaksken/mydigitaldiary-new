<?php

namespace App\Services;

use App\Models\DailyPlanItem;
use App\Models\DailyWellbeingLog;
use App\Models\GoalMilestone;
use App\Models\Note;
use App\Models\PersonalGoal;
use App\Models\SavingsContribution;
use App\Models\SpiritualPractice;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * "On this day" memories for the dashboard and the morning reminder: what
 * the person wrote, finished, saved or practised exactly a week, a month,
 * three months and a year ago — from their own data only.
 *
 * Every source is one bounded query over the four anchor days (date columns
 * use whereIn, datetime columns four OR'ed ranges), all scoped by user_id, so
 * the cost is a handful of small indexed lookups regardless of history size.
 * Each source is isolated: one missing table or column skips that source
 * instead of hiding the whole card.
 */
class OnThisDayService
{
    /** Oldest first: a memory from a year ago is the most delightful one. */
    private const ANCHORS = [
        'year' => 'A year ago',
        'quarter' => '3 months ago',
        'month' => 'A month ago',
        'week' => 'A week ago',
    ];

    private const PER_SOURCE = 4;

    /**
     * @return array{items: list<array{anchor:string,when:string,date:string,icon:string,tone:string,text:string,url:?string}>, comparison: ?string, more_url: ?string}
     */
    public function forUser(User $user, ?CarbonInterface $now = null, int $limit = 3): array
    {
        $timezone = app(DailyReminderService::class)->timezone($user);
        $today = Carbon::instance($now ?? Carbon::now())->setTimezone($timezone)->startOfDay();

        $anchors = [
            'year' => $today->copy()->subYearNoOverflow(),
            'quarter' => $today->copy()->subMonthsNoOverflow(3),
            'month' => $today->copy()->subMonthNoOverflow(),
            'week' => $today->copy()->subWeek(),
        ];

        $candidates = collect();
        foreach ($this->sources() as $source) {
            try {
                $candidates = $candidates->merge($source($user, $anchors));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $items = $this->pick($candidates, $limit);
        $oldest = $items->first();

        return [
            'items' => $items->all(),
            'comparison' => $this->comparison($user, $today),
            'more_url' => $oldest && Route::has('activity')
                ? route('activity', ['period' => 'range', 'from' => $oldest['date'], 'to' => $oldest['date']])
                : null,
        ];
    }

    /** One short sentence for the morning push, or null. */
    public function pushLine(User $user, ?CarbonInterface $now = null): ?string
    {
        $item = $this->forUser($user, $now, 1)['items'][0] ?? null;

        if (! $item) {
            return null;
        }

        return Str::limit($item['when'].': '.Str::lcfirst($item['text']).'.', 90);
    }

    /**
     * @return list<callable(User, array<string, Carbon>): Collection>
     */
    private function sources(): array
    {
        return [
            // Personal notes / diary entries.
            function (User $user, array $anchors) {
                return Note::query()
                    ->where('user_id', $user->id)
                    ->where(fn ($q) => $this->withinDays($q, 'created_at', $anchors))
                    ->orderBy('created_at')
                    ->limit(self::PER_SOURCE)
                    ->get(['id', 'title', 'created_at'])
                    ->map(fn ($note) => $this->item($anchors, $note->created_at, 'fa-note-sticky', 'amber',
                        'You wrote “'.Str::limit((string) ($note->title ?: 'a note'), 60).'”',
                        Route::has('notes.show') ? route('notes.show', $note->id) : null));
            },

            // Completed planner tasks, by the day they were planned for.
            function (User $user, array $anchors) {
                return DailyPlanItem::query()
                    ->join('daily_plans', 'daily_plans.id', '=', 'daily_plan_items.daily_plan_id')
                    ->where('daily_plans.user_id', $user->id)
                    ->where(fn ($q) => $this->onDates($q, 'daily_plans.plan_date', $anchors))
                    ->where('daily_plan_items.is_completed', true)
                    ->orderBy('daily_plan_items.id')
                    ->limit(self::PER_SOURCE)
                    ->get(['daily_plan_items.title', 'daily_plans.plan_date'])
                    ->map(fn ($row) => $this->item($anchors, $row->plan_date, 'fa-circle-check', 'emerald',
                        'You completed “'.Str::limit((string) $row->title, 60).'”',
                        route('daily-planner.index', ['date' => substr((string) $row->plan_date, 0, 10)])));
            },

            // Completed goals and goal milestones.
            function (User $user, array $anchors) {
                $goals = PersonalGoal::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->where(fn ($q) => $this->withinDays($q, 'updated_at', $anchors))
                    ->limit(self::PER_SOURCE)
                    ->get(['id', 'title', 'updated_at'])
                    ->map(fn ($goal) => $this->item($anchors, $goal->updated_at, 'fa-bullseye', 'rose',
                        'You completed your goal “'.Str::limit((string) $goal->title, 50).'”',
                        Route::has('personal-goals.progress') ? route('personal-goals.progress', $goal->id) : null));

                $milestones = GoalMilestone::query()
                    ->where('user_id', $user->id)
                    ->whereNotNull('completed_at')
                    ->where(fn ($q) => $this->withinDays($q, 'completed_at', $anchors))
                    ->limit(self::PER_SOURCE)
                    ->get(['personal_goal_id', 'title', 'completed_at'])
                    ->map(fn ($milestone) => $this->item($anchors, $milestone->completed_at, 'fa-flag-checkered', 'rose',
                        'You reached “'.Str::limit((string) $milestone->title, 55).'”',
                        Route::has('personal-goals.progress') ? route('personal-goals.progress', $milestone->personal_goal_id) : null));

                return $goals->merge($milestones);
            },

            // Savings: one line per anchor day, summed.
            function (User $user, array $anchors) {
                return SavingsContribution::query()
                    ->leftJoin('savings_goals', 'savings_goals.id', '=', 'savings_contributions.savings_goal_id')
                    ->where('savings_contributions.user_id', $user->id)
                    ->where(fn ($q) => $this->onDates($q, 'savings_contributions.contributed_at', $anchors))
                    ->groupBy('savings_contributions.contributed_at')
                    ->selectRaw('savings_contributions.contributed_at as day, SUM(savings_contributions.amount) as total, MIN(savings_goals.name) as goal_name, MIN(savings_contributions.savings_goal_id) as goal_id')
                    ->limit(self::PER_SOURCE)
                    ->get()
                    ->filter(fn ($row) => (float) $row->total > 0)
                    ->map(fn ($row) => $this->item($anchors, $row->day, 'fa-piggy-bank', 'teal',
                        'You saved '.format_money((float) $row->total).($row->goal_name ? ' toward “'.Str::limit((string) $row->goal_name, 40).'”' : ''),
                        $row->goal_id && Route::has('savings-goals.show') ? route('savings-goals.show', $row->goal_id) : (Route::has('savings.index') ? route('savings.index') : null)));
            },

            // Spiritual practice.
            function (User $user, array $anchors) {
                return SpiritualPractice::query()
                    ->where('user_id', $user->id)
                    ->where(fn ($q) => $this->withinDays($q, 'practiced_at', $anchors))
                    ->limit(self::PER_SOURCE)
                    ->get(['id', 'title', 'practice_title', 'practice_type', 'practiced_at'])
                    ->map(fn ($practice) => $this->item($anchors, $practice->practiced_at, 'fa-seedling', 'violet',
                        'You made time for “'.Str::limit((string) ($practice->title ?: $practice->practice_title ?: Str::headline((string) $practice->practice_type) ?: 'reflection'), 50).'”',
                        Route::has('spiritual-practices.show') ? route('spiritual-practices.show', $practice->id) : null));
            },

            // Good days in the wellbeing log.
            function (User $user, array $anchors) {
                return DailyWellbeingLog::query()
                    ->where('user_id', $user->id)
                    ->where(fn ($q) => $this->onDates($q, 'log_date', $anchors))
                    ->whereIn('mood', ['good', 'great'])
                    ->limit(self::PER_SOURCE)
                    ->get(['log_date', 'mood'])
                    ->map(fn ($log) => $this->item($anchors, $log->log_date?->toDateString(), 'fa-face-smile', 'sky',
                        'You were feeling '.$log->mood,
                        Route::has('wellbeing.index') ? route('wellbeing.index') : null));
            },

            // What they were grateful for when they closed the day.
            function (User $user, array $anchors) {
                if (! Schema::hasTable('daily_checkins')) {
                    return collect();
                }

                return DB::table('daily_checkins')
                    ->where('user_id', $user->id)
                    ->where('type', 'close_day')
                    ->where(fn ($q) => $this->onDates($q, 'checkin_date', $anchors))
                    ->where(fn ($q) => $q->whereNotNull('gratitude')->where('gratitude', '!=', ''))
                    ->limit(self::PER_SOURCE)
                    ->get(['checkin_date', 'gratitude'])
                    ->map(fn ($row) => $this->item($anchors, $row->checkin_date, 'fa-heart', 'rose',
                        'You were grateful for “'.Str::limit((string) $row->gratitude, 60).'”', null));
            },
        ];
    }

    /**
     * One per anchor first (oldest first), then fill from what is left.
     */
    private function pick(Collection $candidates, int $limit): Collection
    {
        $order = array_flip(array_keys(self::ANCHORS));
        $candidates = $candidates->filter()->sortBy(fn ($item) => $order[$item['anchor']] ?? 99)->values();

        $picked = $candidates->unique('anchor')->take($limit);

        if ($picked->count() < $limit) {
            $picked = $picked->merge(
                $candidates->reject(fn ($item) => $picked->contains($item))->take($limit - $picked->count())
            );
        }

        return $picked->sortBy(fn ($item) => $order[$item['anchor']] ?? 99)->values();
    }

    /**
     * "Same week last month you saved X and completed Y tasks." — two cheap
     * aggregates; null when both are zero.
     */
    private function comparison(User $user, Carbon $today): ?string
    {
        try {
            $end = $today->copy()->subMonthNoOverflow();
            $start = $end->copy()->subDays(6);

            $saved = (float) SavingsContribution::query()
                ->where('user_id', $user->id)
                ->whereBetween('contributed_at', [$start->toDateString(), $end->toDateString().' 23:59:59'])
                ->sum('amount');

            $completed = DailyPlanItem::query()
                ->join('daily_plans', 'daily_plans.id', '=', 'daily_plan_items.daily_plan_id')
                ->where('daily_plans.user_id', $user->id)
                ->whereBetween('daily_plans.plan_date', [$start->toDateString(), $end->toDateString().' 23:59:59'])
                ->where('daily_plan_items.is_completed', true)
                ->count();
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }

        $parts = [];
        if ($saved > 0) {
            $parts[] = 'saved '.format_money($saved);
        }
        if ($completed > 0) {
            $parts[] = 'completed '.$completed.' '.Str::plural('task', $completed);
        }

        return $parts ? 'Same week last month you '.implode(' and ', $parts).'.' : null;
    }

    /**
     * Matches the anchor a timestamp/date falls on, or null (the row then
     * gets filtered out by pick()).
     */
    private function item(array $anchors, mixed $when, string $icon, string $tone, string $text, ?string $url): ?array
    {
        if (! $when) {
            return null;
        }

        $date = substr((string) ($when instanceof CarbonInterface ? $when->copy()->setTimezone($anchors['week']->getTimezone())->toDateString() : $when), 0, 10);

        foreach ($anchors as $key => $day) {
            if ($day->toDateString() === $date) {
                return [
                    'anchor' => $key,
                    'when' => self::ANCHORS[$key],
                    'date' => $date,
                    'icon' => $icon,
                    'tone' => $tone,
                    'text' => $text,
                    'url' => $url,
                ];
            }
        }

        return null;
    }

    /**
     * Date columns: whereDate rather than whereIn, because SQLite stores
     * Eloquent "date" casts as "Y-m-d 00:00:00" strings.
     */
    private function onDates($query, string $column, array $anchors): void
    {
        foreach ($anchors as $day) {
            $query->orWhereDate($column, $day->toDateString());
        }
    }

    /**
     * Four OR'ed [start, end] ranges of a datetime column, each one local
     * day converted to the app timezone timestamps are stored in.
     */
    private function withinDays($query, string $column, array $anchors): void
    {
        $storage = config('app.timezone', 'UTC');

        foreach ($anchors as $day) {
            $query->orWhereBetween($column, [
                $day->copy()->startOfDay()->setTimezone($storage)->format('Y-m-d H:i:s'),
                $day->copy()->endOfDay()->setTimezone($storage)->format('Y-m-d H:i:s'),
            ]);
        }
    }
}
