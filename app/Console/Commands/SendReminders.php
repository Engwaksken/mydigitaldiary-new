<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Notifications\ReminderFallbackMailNotification;
use App\Notifications\ReminderNotification;
use App\Services\FcmService;
use App\Services\ReminderCopyService;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

class SendReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Send all due reminders and reschedule recurring ones';

    public function handle(FcmService $fcm, ReminderCopyService $reminderCopy): int
    {
        $now = Carbon::now();

        $due = Reminder::with('user')
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->orderBy('next_run_at')
            ->limit(500)
            ->get();

        if ($due->isEmpty()) {
            $this->info('No reminders due at '.$now->toDateTimeString().'.');
            return self::SUCCESS;
        }

        foreach ($due as $reminder) {
            $user = $reminder->user;

            if ($this->sourceIsCompleted($reminder)) {
                Reminder::query()
                    ->where('user_id', $reminder->user_id)
                    ->where('source_type', $reminder->source_type)
                    ->where('source_id', $reminder->source_id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
                continue;
            }

            if (! $user) {
                $this->warn("Reminder #{$reminder->id} has no user; skipping.");
                continue;
            }

            // The product intentionally disables reminder delivery after a
            // subscription/trial expires. Move recurring reminders forward so
            // they do not build up hundreds of missed occurrences while access
            // is inactive.
            if (! $user->hasActiveAccess()) {
                $reminder->scheduleNext();
                continue;
            }

            $copy = $reminderCopy->forDelivery($reminder, $user);
            $deliveryReminder = clone $reminder;
            $deliveryReminder->title = $copy['title'];
            $deliveryReminder->message = $copy['message'];

            $occurrence = $reminder->next_run_at->copy();
            $occurrenceSuffix = $occurrence->format('YmdHi');
            $pushKey = "reminder-push-sent:{$reminder->id}:{$occurrenceSuffix}";
            $databaseKey = "reminder-database-sent:{$reminder->id}:{$occurrenceSuffix}";
            $databaseNotificationId = null;

            /*
             * 1) DATABASE / IN-APP FIRST.
             *
             * Storing the notification before FCM means the push can carry the
             * exact Laravel notification id. Mobile can then mark the same row
             * as read immediately when the user taps the notification.
             */
            if (! Cache::has($databaseKey)) {
                try {
                    $before = now()->subSeconds(2);
                    NotificationFacade::sendNow($user, new ReminderNotification($deliveryReminder, ['database']));

                    $databaseNotificationId = $this->latestReminderNotificationId(
                        $user->notifications()
                            ->where('type', ReminderNotification::class)
                            ->where('created_at', '>=', $before)
                            ->latest('created_at')
                            ->limit(10)
                            ->get(),
                        $reminder->id
                    );

                    Cache::put($databaseKey, true, now()->addDays(2));
                } catch (Throwable $e) {
                    Log::warning('Could not store reminder database notification.', [
                        'reminder_id' => $reminder->id,
                        'user_id' => $user->id,
                        'exception' => get_class($e),
                        'error' => $e->getMessage(),
                    ]);
                    $this->warn("In-app notification failed for reminder #{$reminder->id}: {$e->getMessage()}");
                }
            } else {
                $databaseNotificationId = $this->latestReminderNotificationId(
                    $user->notifications()
                        ->where('type', ReminderNotification::class)
                        ->latest('created_at')
                        ->limit(15)
                        ->get(),
                    $reminder->id
                );
            }

            /*
             * 2) PUSH — independent from email/database.
             * A missing Firebase configuration or stale token must never stop
             * database/email delivery or the rest of the reminder batch.
             */
            if ($reminder->alarm_enabled && ! $user->alarms_muted && ! Cache::has($pushKey)) {
                try {
                    $data = [
                        'reminder_id' => (string) $reminder->id,
                        'type' => 'reminder',
                        'scheduled_at' => $occurrence->toIso8601String(),
                        'target' => 'reminders',
                        'reminder_stage' => $copy['stage'],
                        'link' => ReminderCopyService::linkFor($reminder),
                    ];

                    if ($reminder->source_type) $data['source_type'] = (string) $reminder->source_type;
                    if ($reminder->source_id) $data['source_id'] = (string) $reminder->source_id;

                    if ($databaseNotificationId) {
                        $data['notification_id'] = $databaseNotificationId;
                    }

                    $fcm->sendToUser(
                        $user,
                        $deliveryReminder->title,
                        $deliveryReminder->message,
                        $data
                    );
                    Cache::put($pushKey, true, now()->addDays(2));
                    $this->line("Push sent for reminder #{$reminder->id}.");
                } catch (Throwable $e) {
                    Log::warning('FCM push attempt threw unexpectedly.', [
                        'reminder_id' => $reminder->id,
                        'user_id' => $user->id,
                        'exception' => get_class($e),
                        'error' => $e->getMessage(),
                    ]);
                    $this->warn("Push failed for reminder #{$reminder->id}: {$e->getMessage()}");
                }
            }

            // In-App Only reminders are complete once database/push were attempted.
            if ($reminder->channel !== 'mail') {
                $reminder->scheduleNext();
                $this->info("Processed in-app reminder #{$reminder->id} \"{$reminder->title}\" for {$user->email}");
                continue;
            }

            /*
             * 3) EMAIL — first try the branded template. If the SMTP provider
             * rejects the richer HTML, immediately retry once with the simpler
             * fallback notification.
             */
            $brandedError = null;

            try {
                NotificationFacade::sendNow($user, new ReminderNotification($deliveryReminder, ['mail']));
            } catch (Throwable $e) {
                $brandedError = $e;
                Log::warning('Branded reminder email failed; trying simple fallback.', [
                    'reminder_id' => $reminder->id,
                    'user_id' => $user->id,
                    'exception' => get_class($e),
                    'error' => $e->getMessage(),
                ]);
            }

            if ($brandedError) {
                try {
                    NotificationFacade::sendNow($user, new ReminderFallbackMailNotification($deliveryReminder));
                    $this->warn("Branded email was rejected for reminder #{$reminder->id}, but the simple fallback email was sent successfully.");
                } catch (Throwable $fallbackError) {
                    Log::error('Both reminder email attempts failed.', [
                        'reminder_id' => $reminder->id,
                        'user_id' => $user->id,
                        'branded_exception' => get_class($brandedError),
                        'branded_error' => $brandedError->getMessage(),
                        'fallback_exception' => get_class($fallbackError),
                        'fallback_error' => $fallbackError->getMessage(),
                    ]);

                    $this->error("Failed reminder #{$reminder->id} \"{$reminder->title}\" to {$user->email}.");
                    $this->line('The reminder remains due and will retry on a later scheduler run.');
                    continue;
                }
            }

            $reminder->scheduleNext();
            $this->info("Sent reminder #{$reminder->id} \"{$reminder->title}\" to {$user->email}");
        }

        return self::SUCCESS;
    }

    private function sourceIsCompleted(Reminder $reminder): bool
    {
        if (! $reminder->source_type || ! $reminder->source_id) return false;

        $source = match ($reminder->source_type) {
            'daily_plan_item', \App\Models\DailyPlanItem::class => \App\Models\DailyPlanItem::query()->find($reminder->source_id),
            'project_task', \App\Models\ProjectTask::class => \App\Models\ProjectTask::query()->find($reminder->source_id),
            \App\Models\Meeting::class => \App\Models\Meeting::query()->find($reminder->source_id),
            default => $this->findLinkedModel($reminder),
        };

        if (! $source) return false;

        if ($source instanceof \App\Models\DailyPlanItem) return (bool) $source->is_completed;

        $finishedStatuses = ['completed', 'complete', 'done', 'paid', 'cancelled', 'canceled', 'closed', 'settled', 'resolved'];
        foreach ([$source->status, $source->meeting_status] as $rawStatus) {
            if (in_array(strtolower(trim((string) $rawStatus)), $finishedStatuses, true)) {
                return true;
            }
        }

        foreach (['is_completed', 'is_paid', 'is_expensed', 'is_archived'] as $flag) {
            if ($source->getAttribute($flag) === true || $source->getAttribute($flag) === 1 || $source->getAttribute($flag) === '1') {
                return true;
            }
        }

        return filled($source->getAttribute('paid_at'));
    }

    private function findLinkedModel(Reminder $reminder): ?\Illuminate\Database\Eloquent\Model
    {
        $class = $reminder->source_type;
        if (! is_string($class) || ! class_exists($class)
            || ! is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)) {
            return null;
        }

        return $class::query()->find($reminder->source_id);
    }

    private function latestReminderNotificationId(iterable $notifications, int $reminderId): ?string
    {
        foreach ($notifications as $notification) {
            if (! $notification instanceof DatabaseNotification) {
                continue;
            }

            $data = is_array($notification->data) ? $notification->data : [];
            if ((int) ($data['reminder_id'] ?? 0) === $reminderId) {
                return (string) $notification->id;
            }
        }

        return null;
    }
}
