<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DailyReminderService;
use App\Services\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Runs daily (see routes/console.php) — a real push notification (not
 * an in-app/email one) summarizing everything due TODAY across
 * reminders and every module with a meaningful due-date-like field:
 * Plans (target_date), Debts (due_date), Health Checkups
 * (next_due_date), Education Plans (target_completion_date), Projects
 * (deadline), Project Tasks (due_date), Savings Goals (target_date).
 * Same "SafeBoda/Jumia-style daily nudge" idea the person asked for —
 * one combined notification per user per day, not one per item.
 *
 * Reuses the existing FcmService (already proven via SendReminders —
 * see that class/DeviceTokenController for the full setup this
 * depends on) rather than adding a second, separate push mechanism.
 */
class SendDailyDueItemsPush extends Command
{
    protected $signature = 'push:daily-due-items';

    protected $description = 'Send each user with a registered device a push notification summarizing everything due today';

    public function handle(FcmService $fcm, DailyReminderService $reminders): int
    {
        $users = User::has('deviceTokens')->get();

        foreach ($users as $user) {
            if (! $user->hasActiveAccess()) { continue; }
            // People with the morning "Plan your day" reminder on already get
            // today's due count in that push (digest:daily-top-tasks); a
            // second morning notification would just be noise.
            if ($reminders->preferences($user)['morning_enabled']) { continue; }
            $tz = $user->timezone ?: 'Africa/Kampala';
            $localNow = now($tz);
            if ((int) $localNow->format('G') !== 8 || (int) $localNow->format('i') >= 30) { continue; }
            $date = $localNow->toDateString();
            $cacheKey = "daily-due-push:{$user->id}:{$date}";
            if (Cache::has($cacheKey)) { continue; }

            $items = $reminders->dueTodayFor($user, $date);

            if (empty($items)) {
                continue; // nothing due today — skip rather than sending an empty nudge
            }

            $title = count($items) === 1 ? '1 item due today' : count($items) . ' items due today';
            $body = collect($items)->take(3)->pluck('label')->implode(' • ');
            if (count($items) > 3) {
                $body .= ' • +' . (count($items) - 3) . ' more';
            }

            $fcm->sendToUser($user, $title, $body, ['type'=>'start_of_day','date'=>$date,'link'=>route('dashboard')]);
            Cache::put($cacheKey, true, now()->addDays(2));
            $this->info("Sent daily due-items push to {$user->email}");
        }

        return self::SUCCESS;
    }
}
