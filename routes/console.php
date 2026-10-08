<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Reminder Scheduling
|--------------------------------------------------------------------------
|
| Checks every minute for reminders that are due.
|
*/
Schedule::command('reminders:send')
    ->everyMinute()
    ->withoutOverlapping(2);

/*
|--------------------------------------------------------------------------
| Health Checkup Reminders
|--------------------------------------------------------------------------
|
| Creates reminder records for upcoming health checkups.
|
*/
Schedule::command('checkups:create-reminders')
    ->dailyAt('06:00')
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Recurring Meetings
|--------------------------------------------------------------------------
|
| Generates future occurrences of recurring meetings.
|
*/
Schedule::command('meetings:generate-recurring')
    ->dailyAt('06:30')
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Recurring Spiritual Growth
|--------------------------------------------------------------------------
|
| Generates upcoming Daily / Weekly / Monthly recurring Spiritual Growth
| sessions.
|
*/
Schedule::command('spiritual-growth:generate-recurring')
    ->dailyAt('06:40')
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Daily Planner / Due Item Notifications
|--------------------------------------------------------------------------
|
| These commands may run frequently. Each command decides whether an
| individual user actually needs a notification.
|
| digest:daily-top-tasks is the morning "Plan your day" reminder and
| digest:end-of-day the evening "Close your day" reminder. Both run every
| 15 minutes and fire inside each user's own local morning/evening time
| (communication_preferences, defaults 07:00 / 20:00), once per day.
|
*/
Schedule::command('digest:daily-top-tasks')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('push:daily-due-items')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Subscription Expiry / Trial Reminders
|--------------------------------------------------------------------------
|
| The command runs once every morning.
|
| SubscriptionReminderService controls delivery:
|
| - starts when 14 days or fewer remain;
| - sends only twice per week;
| - reminder days are Monday and Thursday;
| - prevents duplicate reminders;
| - stops immediately once a subscription becomes active/paid;
| - excludes organisation-managed members from independent billing
|   reminders.
|
*/
Schedule::command('subscriptions:send-expiry-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping(10);

/*
|--------------------------------------------------------------------------
| Automatic Subscription Renewal
|--------------------------------------------------------------------------
|
| Checks users who:
|
| - explicitly enabled Auto Renewal;
| - have a paid subscription plan;
| - reached their subscription expiry date;
| - have a saved Mobile Money renewal number/network.
|
| The user still approves the Mobile Money prompt on their phone.
|
*/
Schedule::command('subscriptions:auto-renew --limit=100')
    ->everyThirtyMinutes()
    ->withoutOverlapping(25);

/*
|--------------------------------------------------------------------------
| Currency Exchange Rates
|--------------------------------------------------------------------------
|
| Refreshes current exchange rates used by Laravel and the Flutter mobile
| application.
|
| Amounts remain stored in the configured base currency, for example UGX.
| When a user selects USD, EUR, GBP, KES or another supported currency,
| the application converts the displayed amount using the latest cached
| rate.
|
| CurrencyService can also refresh a stale rate on demand, so this hourly
| task provides a reliable background refresh rather than being the only
| source of updated rates.
|
*/
Schedule::command('currency:refresh-rates')
    ->hourly()
    ->withoutOverlapping(10);

/*
|--------------------------------------------------------------------------
| End-of-Day Digest
|--------------------------------------------------------------------------
|
| The command checks the user's preferred delivery time internally.
|
*/
Schedule::command('digest:end-of-day')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Engagement
|--------------------------------------------------------------------------
|
| Daily tips and smart nudges use their own internal duplicate protection.
|
*/
Schedule::command('engagement:daily-tip')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

Schedule::command('engagement:send-smart-nudges')
    ->hourly()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Today's Insight
|--------------------------------------------------------------------------
|
| Today's Insight notifications are event-driven.
|
| Every time the current insight is generated or changes, the generator
| should call:
|
| app(\App\Services\TodaysInsightNotificationService::class)
|     ->notifyIfChanged($user, $insight);
|
| When the insight title/message changes:
|
| - an in-app/database notification is created;
| - the Today's Insight popup can be displayed;
| - an email is sent;
| - unchanged content is not sent repeatedly.
|
| Therefore there is no separate scheduled Today's Insight email command
| here.
|
*/

/*
|--------------------------------------------------------------------------
| Account Cleanup
|--------------------------------------------------------------------------
|
| Permanently removes accounts whose scheduled deletion waiting period
| has elapsed.
|
*/
Schedule::command('accounts:purge-scheduled-deletions')
    ->hourly()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Scheduled Backups
|--------------------------------------------------------------------------
|
| Checks whether a configured backup is due and runs it when required.
|
*/
Schedule::command('backups:run-scheduled')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Chat / Support Cleanup
|--------------------------------------------------------------------------
|
| Releases conversations whose assigned support agent has remained idle
| beyond the allowed period.
|
*/
Schedule::command('support:release-idle-assignments')
    ->everyMinute()
    ->withoutOverlapping(5);

/*
|--------------------------------------------------------------------------
| Mobile Synchronisation Cleanup
|--------------------------------------------------------------------------
|
| Removes old mobile idempotency / synchronisation records after 30 days.
|
*/
Schedule::call(function (): void {
    if (! Schema::hasTable('mobile_sync_requests')) {
        return;
    }

    DB::table('mobile_sync_requests')
        ->where(
            'created_at',
            '<',
            now()->subDays(30)
        )
        ->delete();
})
    ->dailyAt('02:20')
    ->name('cleanup-mobile-sync-requests')
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Social Media Planner
|--------------------------------------------------------------------------
|
| Processes due social-media posts and reminders.
|
| The currently installed command does not require a --limit option.
|
*/
Schedule::command('social-media:process-scheduled')
    ->everyMinute()
    ->withoutOverlapping(5);

/*
|--------------------------------------------------------------------------
| Social Media Analytics
|--------------------------------------------------------------------------
|
| Refreshes available social-media post performance metrics.
|
| Keep --limit=250 only while:
|
| php artisan help social-media:sync-metrics
|
| confirms that the command supports this option.
|
*/
Schedule::command(
    'social-media:sync-metrics --limit=250'
)
    ->everyThirtyMinutes()
    ->withoutOverlapping(10);

/*
|--------------------------------------------------------------------------
| Debt and Savings Reminders
|--------------------------------------------------------------------------
*/
Schedule::command('debts:send-reminders --limit=200')
    ->hourly()
    ->withoutOverlapping(15);

Schedule::command('savings:send-reminders --limit=200')
    ->hourly()
    ->withoutOverlapping(15);
