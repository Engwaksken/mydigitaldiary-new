<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\EducationPlan;
use App\Models\Budget;
use App\Models\DailyStep;
use App\Models\DietLog;
use App\Models\ExerciseLog;
use App\Models\Expense;
use App\Models\HealthCheckup;
use App\Models\Meeting;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Reminder;
use App\Models\SavingsGoal;
use App\Models\SleepLog;
use App\Models\SpiritualPractice;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The morning "Plan your day" and evening "Close your day" phone reminders.
 *
 * Preferences live in communication_preferences (the same row the mobile app
 * reads and writes through /api/growth/preferences): morning_brief /
 * evening_review on-off, morning_time / evening_time, plus a per-slot
 * "sent on" date that makes delivery idempotent per local day.
 *
 * The existing digest commands do the sending — SendDailyTopTasksDigest is
 * the morning slot, SendEndOfDayDigest the evening slot — and ask this
 * service whether a user is due, what to say, and to record that it was sent.
 */
class DailyReminderService
{
    public const SLOTS = ['morning', 'evening'];

    public const DEFAULT_TIMES = ['morning' => '07:00', 'evening' => '20:00'];

    /**
     * How long after the chosen time a reminder may still go out. The
     * scheduler runs every 15 minutes; an hour tolerates a few missed runs
     * without ever firing a "good morning" at lunchtime.
     */
    public const WINDOW_MINUTES = 60;

    private const ENABLED_COLUMNS = ['morning' => 'morning_brief', 'evening' => 'evening_review'];

    public function timezone(User $user): string
    {
        $timezone = (string) ($user->timezone ?: 'Africa/Kampala');

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'Africa/Kampala';
    }

    /**
     * @return array{morning_enabled:bool,morning_time:string,evening_enabled:bool,evening_time:string,morning_sent_on:?string,evening_sent_on:?string}
     */
    public function preferences(User $user): array
    {
        $row = $this->row($user);

        $prefs = [];
        foreach (self::SLOTS as $slot) {
            $enabledColumn = self::ENABLED_COLUMNS[$slot];
            $prefs[$slot.'_enabled'] = $row ? (bool) ($row->{$enabledColumn} ?? true) : true;
            $prefs[$slot.'_time'] = $this->normaliseTime($row->{$slot.'_time'} ?? null, self::DEFAULT_TIMES[$slot]);
            $sent = $row->{$slot.'_sent_on'} ?? null;
            $prefs[$slot.'_sent_on'] = $sent ? substr((string) $sent, 0, 10) : null;
        }

        return $prefs;
    }

    /**
     * @param  array{morning_enabled?:bool,morning_time?:?string,evening_enabled?:bool,evening_time?:?string}  $data
     */
    public function savePreferences(User $user, array $data): void
    {
        $values = ['timezone' => $this->timezone($user), 'updated_at' => now()];

        foreach (self::SLOTS as $slot) {
            if (array_key_exists($slot.'_enabled', $data)) {
                $values[self::ENABLED_COLUMNS[$slot]] = (bool) $data[$slot.'_enabled'];
            }
            if (array_key_exists($slot.'_time', $data)) {
                $values[$slot.'_time'] = $this->normaliseTime($data[$slot.'_time'], self::DEFAULT_TIMES[$slot]).':00';
            }
        }

        $this->upsert($user, $values);
    }

    /**
     * The user's local date when the slot is due right now (inside its
     * window and not yet sent today), otherwise null.
     */
    public function dueDate(User $user, string $slot, ?CarbonInterface $now = null): ?string
    {
        $prefs = $this->preferences($user);
        $local = Carbon::instance($now ?? Carbon::now())->setTimezone($this->timezone($user));

        [$hour, $minute] = array_map('intval', explode(':', $prefs[$slot.'_time']));
        $target = $local->copy()->setTime($hour, $minute);

        if ($local->lt($target) || $local->gte($target->copy()->addMinutes(self::WINDOW_MINUTES))) {
            return null;
        }

        $date = $local->toDateString();

        return $prefs[$slot.'_sent_on'] === $date ? null : $date;
    }

    public function markSent(User $user, string $slot, string $date): void
    {
        $this->upsert($user, [$slot.'_sent_on' => $date, 'updated_at' => now()]);
    }

    /** Whether the person already pressed Start my day / Close my day. */
    public function hasCheckin(User $user, string $date, string $type): bool
    {
        return Schema::hasTable('daily_checkins')
            && DB::table('daily_checkins')
                ->where('user_id', $user->id)
                ->whereDate('checkin_date', $date)
                ->where('type', $type)
                ->exists();
    }

    /** Any meaningful action logged today (task ticked, entry saved, ...). */
    public function activeOn(User $user, string $date): bool
    {
        return Schema::hasTable('engagement_events')
            && DB::table('engagement_events')
                ->where('user_id', $user->id)
                ->whereDate('event_date', $date)
                ->exists();
    }

    /** Current streak, or 0 when its last active day is older than yesterday. */
    public function streak(User $user, string $date): int
    {
        if (! Schema::hasTable('engagement_streaks')) {
            return 0;
        }

        $row = DB::table('engagement_streaks')->where('user_id', $user->id)->first();
        if (! $row || ! $row->last_meaningful_day) {
            return 0;
        }

        $last = substr((string) $row->last_meaningful_day, 0, 10);
        $yesterday = Carbon::parse($date)->subDay()->toDateString();

        return in_array($last, [$date, $yesterday], true) ? (int) $row->current_streak : 0;
    }

    /**
     * Today's planner items (recurrence-aware, same as the dashboard).
     *
     * @return Collection<int, mixed>
     */
    public function plannerItems(User $user, string $date): Collection
    {
        try {
            return app(DailyPlannerRecurrenceService::class)
                ->itemsForDate($user->id, Carbon::parse($date, $this->timezone($user)));
        } catch (\Throwable $exception) {
            report($exception);

            return collect();
        }
    }

    /**
     * @return array{title:string,body:string,link:string}
     */
    public function morningMessage(User $user, string $date, ?string $memory = null): array
    {
        $open = $this->plannerItems($user, $date)
            ->filter(fn ($item) => ! (bool) data_get($item, 'is_completed', false))
            ->sortBy(fn ($item) => sprintf(
                '%d-%s',
                match (strtolower((string) data_get($item, 'priority'))) {
                    'urgent', 'high' => 0,
                    'medium' => 1,
                    default => 2,
                },
                (string) data_get($item, 'start_time', '') ?: '99:99:99'
            ))
            ->values();

        $count = $open->count();
        $due = count($this->dueTodayFor($user, $date));

        if ($count > 0) {
            $top = Str::limit((string) data_get($open->first(), 'title', ''), 48);
            $body = 'Good morning — '.$count.' '.Str::plural('task', $count).' today. Top: '.$top.'.';
        } else {
            $body = 'Good morning — plan your day in 1 minute.';
        }

        if ($due > 0) {
            $body .= ' '.$due.' '.Str::plural('item', $due).' due.';
        }

        if ($memory) {
            $body .= ' '.$memory;
        }

        return [
            'title' => 'Plan your day',
            'body' => Str::limit($body, 230),
            'link' => route('dashboard', ['routine' => 'start']),
        ];
    }

    /**
     * @return array{title:string,body:string,link:string}
     */
    public function eveningMessage(User $user, string $date): array
    {
        $items = $this->plannerItems($user, $date);
        $total = $items->count();
        $done = $items->filter(fn ($item) => (bool) data_get($item, 'is_completed', false))->count();
        $streak = $this->streak($user, $date);
        $activeToday = $this->activeOn($user, $date);

        $body = $total > 0
            ? 'You did '.$done.' of '.$total.' today'
            : 'How did today go?';

        if ($streak >= 2) {
            $body .= $activeToday
                ? ' · '.$streak.'-day streak'
                : ' Keep your '.$streak.'-day streak alive';
        }

        $body .= ' — close your day?';

        return [
            'title' => 'Close your day',
            'body' => $body,
            'link' => route('dashboard', ['routine' => 'close']),
        ];
    }

    /**
     * Everything with a due date of today across modules — also used by
     * push:daily-due-items for people who switched the morning reminder off.
     *
     * @return array<int, array{label: string}>
     */
    public function dueTodayFor(User $user, string $date): array
    {
        $userId = $user->id;
        $items = collect();

        Reminder::where('user_id', $userId)
            ->where('is_active', true)
            ->whereDate('next_run_at', $date)
            ->get(['title'])
            ->each(fn ($r) => $items->push(['label' => "Reminder: {$r->title}"]));

        Meeting::where('user_id', $userId)
            ->where('status', 'scheduled')
            ->whereDate('start_at', $date)
            ->get(['title'])
            ->each(fn ($m) => $items->push(['label' => "Meeting: {$m->title}"]));

        Plan::where('user_id', $userId)->where('is_archived', false)
            ->where('status', '!=', 'completed')
            ->whereDate('target_date', $date)
            ->get(['title'])
            ->each(fn ($p) => $items->push(['label' => "Plan: {$p->title}"]));

        Debt::where('user_id', $userId)->where('is_archived', false)
            ->where('status', '!=', 'paid')
            ->whereDate('due_date', $date)
            ->get(['person_name'])
            ->each(fn ($d) => $items->push(['label' => "Debt due: {$d->person_name}"]));

        HealthCheckup::where('user_id', $userId)->where('is_archived', false)
            ->whereDate('next_due_date', $date)
            ->get(['checkup_type'])
            ->each(fn ($h) => $items->push(['label' => "Checkup: {$h->checkup_type}"]));

        EducationPlan::where('user_id', $userId)->where('is_archived', false)
            ->where('status', '!=', 'completed')
            ->whereDate('target_completion_date', $date)
            ->get(['title'])
            ->each(fn ($e) => $items->push(['label' => "Education: {$e->title}"]));

        Project::where('user_id', $userId)->where('is_archived', false)
            ->where('status', '!=', 'completed')
            ->whereDate('deadline', $date)
            ->get(['name'])
            ->each(fn ($p) => $items->push(['label' => "Project deadline: {$p->name}"]));

        ProjectTask::where('user_id', $userId)->where('is_archived', false)
            ->where('status', '!=', 'done')
            ->whereDate('due_date', $date)
            ->get(['title'])
            ->each(fn ($t) => $items->push(['label' => "Task: {$t->title}"]));

        SavingsGoal::where('user_id', $userId)->where('is_archived', false)
            ->where('status', '!=', 'completed')
            ->whereDate('target_date', $date)
            ->get(['name'])
            ->each(fn ($s) => $items->push(['label' => "Savings goal target: {$s->name}"]));

        // Subscription / trial renewal is as easy to forget as any task.
        $expiry = $user->relevantExpiryDate();
        if ($expiry && $expiry->isSameDay(Carbon::parse($date))) {
            $items->push(['label' => in_array((string) $user->subscription_status, ['trial', 'trialing'], true)
                ? 'Trial ends today'
                : 'Subscription expires today']);
        }

        // Spiritual growth sessions planned for today.
        SpiritualPractice::where('user_id', $userId)
            ->where('is_archived', false)
            ->whereDate('next_planned_date', $date)
            ->get(['practice_type', 'title'])
            ->each(fn ($s) => $items->push(['label' => 'Spiritual practice: '.($s->title ?: $s->practice_type)]));

        // Budgets whose maturity date is today but not yet expensed.
        Budget::where('user_id', $userId)
            ->where('is_archived', false)
            ->where('is_expensed', false)
            ->whereDate('expensed_at', $date)
            ->get(['category'])
            ->each(fn ($b) => $items->push(['label' => 'Budget due: '.($b->category ?: 'budget')]));

        $this->pushDailyRoutine($items, $userId, $date);

        return $items->all();
    }

    /**
     * Recurring daily habit nudges. These modules have no future "due date" —
     * they are logged every day — so they are listed whenever they have not
     * yet been done today, reminding the user each day until completed.
     *
     * @param  \Illuminate\Support\Collection<int, array{label: string}>  $items
     */
    private function pushDailyRoutine($items, int $userId, string $date): void
    {
        if (Schema::hasTable('daily_steps')
            && ! DailyStep::where('user_id', $userId)
                ->whereDate('tracking_date', $date)
                ->whereRaw('steps >= COALESCE(daily_goal, 0)')
                ->exists()) {
            $items->push(['label' => 'Hit your step goal']);
        }

        if (Schema::hasTable('exercise_logs')
            && ! ExerciseLog::where('user_id', $userId)->whereDate('performed_at', $date)->exists()) {
            $items->push(['label' => 'Exercise today']);
        }

        if (Schema::hasTable('sleep_logs')
            && ! SleepLog::where('user_id', $userId)->whereDate('sleep_date', $date)->exists()) {
            $items->push(['label' => 'Log your sleep']);
        }

        if (Schema::hasTable('diet_logs')
            && ! DietLog::where('user_id', $userId)->whereDate('logged_at', $date)->exists()) {
            $items->push(['label' => 'Log your meals']);
        }

        if (Schema::hasTable('expenses')
            && ! Expense::where('user_id', $userId)->whereDate('spent_at', $date)->exists()) {
            $items->push(['label' => 'Log today\'s expenses']);
        }
    }

    private function row(User $user): ?object
    {
        if (! Schema::hasTable('communication_preferences')) {
            return null;
        }

        return DB::table('communication_preferences')->where('user_id', $user->id)->first();
    }

    private function upsert(User $user, array $values): void
    {
        if (! Schema::hasTable('communication_preferences')) {
            return;
        }

        $exists = DB::table('communication_preferences')->where('user_id', $user->id)->exists();

        if ($exists) {
            DB::table('communication_preferences')->where('user_id', $user->id)->update($values);

            return;
        }

        DB::table('communication_preferences')->insert(array_merge([
            'user_id' => $user->id,
            'timezone' => $this->timezone($user),
            'morning_time' => self::DEFAULT_TIMES['morning'].':00',
            'evening_time' => self::DEFAULT_TIMES['evening'].':00',
            'created_at' => now(),
            'updated_at' => now(),
        ], $values));
    }

    private function normaliseTime(mixed $value, string $fallback): string
    {
        $value = trim((string) $value);

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d/', $value) ? substr($value, 0, 5) : $fallback;
    }
}
