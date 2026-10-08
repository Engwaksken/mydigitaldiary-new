<?php
namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SimpleDatabaseNotification;
use App\Services\DailyReminderService;
use App\Services\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * The EVENING slot of the daily rhythm ("Close your day"), at each person's
 * own evening time (communication_preferences.evening_time, default 20:00,
 * in their timezone — see DailyReminderService). Runs every 15 minutes and is
 * idempotent per local day.
 *
 *  - The in-app end-of-day summary notification is always recorded.
 *  - The push is the short, personal "You did 2 of 3 today 🔥 5-day streak —
 *    close your day?", only when the evening reminder is on and the person
 *    has not already closed the day.
 */
class SendEndOfDayDigest extends Command
{
    protected $signature = 'digest:end-of-day';
    protected $description = 'Evening "Close your day": summary notification and push at each user local evening time.';

    public function handle(FcmService $fcm, DailyReminderService $reminders): int
    {
        User::query()->whereNull('suspended_at')->chunkById(100, function ($users) use ($fcm, $reminders) {
            foreach ($users as $user) {
                try {
                    $this->handleUser($user, $fcm, $reminders);
                } catch (\Throwable $e) {
                    Log::warning('Evening reminder failed for user.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                }
            }
        });
        return self::SUCCESS;
    }

    private function handleUser(User $user, FcmService $fcm, DailyReminderService $reminders): void
    {
        if (! $user->hasActiveAccess()) return;
        $date = $reminders->dueDate($user, 'evening');
        if (! $date) return;

        // Marked first: a failure halfway must never cause a second send.
        $reminders->markSent($user, 'evening', $date);

        $plan = \App\Models\DailyPlan::where('user_id', $user->id)->whereDate('plan_date', $date)->with('items')->first();
        $completed = $plan?->items->where('is_completed', true)->count() ?? 0;
        $pending = $plan?->items->where('is_completed', false)->count() ?? 0;
        $expenses = (float) \App\Models\Expense::where('user_id', $user->id)->whereDate('spent_at', $date)->sum('amount');
        $savings = (float) \App\Models\SavingsContribution::where('user_id', $user->id)->whereDate('contributed_at', $date)->sum('amount');
        $exercise = \App\Models\ExerciseLog::where('user_id', $user->id)->whereDate('performed_at', $date)->count();
        $spiritual = \App\Models\SpiritualPractice::where('user_id', $user->id)->whereDate('practiced_at', $date)->count();

        $body = "Completed {$completed}; pending {$pending}; expenses ".format_money($expenses)."; savings ".format_money($savings)."; exercise {$exercise}; spiritual entries {$spiritual}.";
        $user->notify(new SimpleDatabaseNotification('Your end-of-day summary', $body, 'end_of_day_summary'));

        if (! $reminders->preferences($user)['evening_enabled'] || $reminders->hasCheckin($user, $date, 'close_day')) {
            return;
        }

        $message = $reminders->eveningMessage($user, $date);
        $fcm->sendToUser($user, $message['title'], $message['body'], [
            'type' => 'end_of_day_summary',
            'date' => $date,
            'link' => $message['link'],
        ]);
    }
}
