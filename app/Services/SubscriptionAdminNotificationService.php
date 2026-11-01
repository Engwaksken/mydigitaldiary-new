<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SubscriptionActivatedNotification;
use App\Notifications\SubscriptionExtensionSummaryNotification;

class SubscriptionAdminNotificationService
{
    public function notify(User $subscriber): void
    {
        User::query()
            ->whereIn('role', ['admin', 'super_admin'])
            ->eachById(fn (User $admin) => $admin->notify(
                new SubscriptionActivatedNotification($subscriber)
            ));
    }

    public function notifyExtensionSummary(int $trialCount, int $trialDays, int $expiredCount, int $expiredDays): void
    {
        User::query()
            ->whereIn('role', ['admin', 'super_admin'])
            ->eachById(fn (User $admin) => $admin->notify(
                new SubscriptionExtensionSummaryNotification($trialCount, $trialDays, $expiredCount, $expiredDays)
            ));
    }
}
