<?php

namespace App\Console\Commands;

use App\Models\DailyPlan;
use App\Models\ProjectTask;
use App\Models\User;
use App\Notifications\DailyTopTasksNotification;
use App\Services\DailyReminderService;
use App\Services\FcmService;
use App\Services\OnThisDayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * The MORNING slot of the daily rhythm ("Plan your day"), sent at each
 * person's own morning time (communication_preferences.morning_time, default
 * 07:00, in their timezone — see DailyReminderService). The scheduler runs
 * this every 15 minutes; a per-day "sent on" marker makes it idempotent.
 *
 * Two independent deliveries share the slot:
 *  - the Top 3 email / in-app notification, for users with
 *    daily_digest_enabled (unchanged content);
 *  - the "Plan your day" push to every registered phone/browser, when the
 *    morning reminder is on and the person has not already started the day
 *    (pressed Start my day, or logged something today).
 *
 * The push replaces the separate 08:00 Top 3 push and, for people with the
 * morning reminder on, the 08:00 push:daily-due-items push (its due count is
 * folded into this message), so the morning is one notification, not three.
 */
class SendDailyTopTasksDigest extends Command
{
    protected $signature = 'digest:daily-top-tasks';

    protected $description = 'Morning "Plan your day": Top 3 email and push at each user local morning time';

    public function handle(FcmService $fcm, DailyReminderService $reminders, OnThisDayService $memories): int
    {
        User::query()
            ->whereNull('suspended_at')
            ->where(fn ($query) => $query->where('daily_digest_enabled', true)->orWhereHas('deviceTokens'))
            ->chunkById(100, function ($users) use ($fcm, $reminders, $memories) {
                foreach ($users as $user) {
                    try {
                        $this->handleUser($user, $fcm, $reminders, $memories);
                    } catch (Throwable $e) {
                        Log::warning('Morning reminder failed for user.', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return self::SUCCESS;
    }

    private function handleUser(User $user, FcmService $fcm, DailyReminderService $reminders, OnThisDayService $memories): void
    {
        if (! $user->hasActiveAccess()) {
            return;
        }

        $date = $reminders->dueDate($user, 'morning');
        if (! $date) {
            return;
        }

        // Marked first: a failure halfway must never cause a second send.
        $reminders->markSent($user, 'morning', $date);

        if ($user->daily_digest_enabled) {
            $tasks = $this->topTasksFor($user, $date);

            if ($tasks !== []) {
                try {
                    // sendNow bypasses ShouldQueue on the notification so
                    // this job does not depend on a queue worker.
                    Notification::sendNow($user, new DailyTopTasksNotification($tasks));
                } catch (Throwable $e) {
                    Log::warning('Could not send daily Top 3 email/database notification.', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (! $reminders->preferences($user)['morning_enabled']
            || ! $user->deviceTokens()->exists()
            || $reminders->hasCheckin($user, $date, 'start_day')
            || $reminders->activeOn($user, $date)) {
            return;
        }

        $memory = null;
        try {
            $memory = $memories->pushLine($user);
        } catch (Throwable $e) {
            report($e);
        }

        $message = $reminders->morningMessage($user, $date, $memory);

        $fcm->sendToUser($user, $message['title'], $message['body'], [
            'type' => 'start_of_day',
            'date' => $date,
            'link' => $message['link'],
        ]);

        $this->info("Sent morning reminder to {$user->email}");
    }

    /** @return array<int, array{title:string,source:string}> */
    private function topTasksFor(User $user, string $date): array
    {
        $items = collect();

        // 1) Today's Daily Planner items first.
        $plan = DailyPlan::query()
            ->where('user_id', $user->id)
            ->whereDate('plan_date', $date)
            ->with(['items' => function ($query) {
                $query->where('is_completed', false)
                    ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                    ->orderByRaw('CASE WHEN start_time IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('start_time')
                    ->orderBy('sort_order')
                    ->orderBy('id');
            }])
            ->first();

        if ($plan) {
            foreach ($plan->items->take(3) as $item) {
                $items->push(['title' => $item->title, 'source' => 'Daily Planner']);
            }
        }

        // 2) Fill any remaining slots with Project Tasks explicitly due today.
        if ($items->count() < 3) {
            ProjectTask::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['todo', 'in_progress'])
                ->whereDate('due_date', $date)
                ->orderBy('due_date')
                ->orderBy('id')
                ->limit(3 - $items->count())
                ->get(['title'])
                ->each(fn ($task) => $items->push([
                    'title' => $task->title,
                    'source' => 'Task',
                ]));
        }

        return $items->take(3)->values()->all();
    }
}
