<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SubscriptionExpiredWeeklyReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly renewal nudge for users whose subscription has lapsed. The
 * scheduler runs it once a week; a per-user 7-day guard keeps a manual
 * re-run from double-sending within the same week.
 */
final class SendExpiredUserWeeklyReminder extends Command
{
    protected $signature = 'subscriptions:send-expired-weekly-reminder';

    protected $description = 'Send a weekly renewal reminder to every user whose subscription has expired.';

    public function handle(): int
    {
        if (! Schema::hasColumn('users', 'subscription_status')) {
            return self::SUCCESS;
        }

        $cutoff = now()->subDays(7);
        $sent = 0;

        $query = User::query()
            ->where('role', '!=', 'admin')
            ->whereRaw("LOWER(TRIM(COALESCE(subscription_status, ''))) = 'expired'");

        if (Schema::hasColumn('users', 'suspended_at')) {
            $query->whereNull('suspended_at');
        }

        // Non-owner organization members get access from the owner's plan;
        // don't remind them about a subscription they never held themselves.
        if (Schema::hasColumn('users', 'organization_id') && Schema::hasTable('organizations')) {
            $query->where(function ($q): void {
                $q->whereNull('organization_id')
                    ->orWhereHas('organization', fn ($org) => $org
                        ->whereColumn('organizations.owner_user_id', 'users.id'));
            });
        }

        $query->orderBy('id')->chunkById(200, function ($users) use (&$sent, $cutoff): void {
            foreach ($users as $user) {
                if ($this->alreadyRemindedRecently($user->id, $cutoff)) {
                    continue;
                }

                $user->notify(new SubscriptionExpiredWeeklyReminderNotification());
                $sent++;
            }
        });

        $this->info("{$sent} expired-user reminder(s) sent.");

        return self::SUCCESS;
    }

    private function alreadyRemindedRecently(int $userId, $cutoff): bool
    {
        if (! Schema::hasTable('notifications')) {
            return false;
        }

        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->where('type', SubscriptionExpiredWeeklyReminderNotification::class)
            ->where('created_at', '>=', $cutoff)
            ->exists();
    }
}
