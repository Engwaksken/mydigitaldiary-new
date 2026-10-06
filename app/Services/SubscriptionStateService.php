<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class SubscriptionStateService
{
    public function normaliseStatus(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        return match ($status) {
            'active', 'trial', 'expired', 'inactive', 'suspended', 'cancelled' => $status,
            'trialing' => 'trial',
            'canceled' => 'cancelled',
            default => 'inactive',
        };
    }

    public function activate(
        User $user,
        ?Carbon $startsAt = null,
        ?Carbon $expiresAt = null,
        ?int $planId = null
    ): User {
        $startsAt ??= now();

        $user->forceFill([
            'subscription_status' => 'active',
            'subscription_started_at' => $startsAt,
            'subscription_expires_at' => $expiresAt,
            'trial_ends_at' => null,
            'subscription_plan_id' => $planId,
        ])->save();

        return $user->fresh();
    }

    public function setStatus(
        User $user,
        string $status,
        ?Carbon $startsAt = null,
        ?Carbon $expiresAt = null,
        ?int $planId = null
    ): User {
        $status = $this->normaliseStatus($status);

        if ($status === 'active') {
            return $this->activate(
                $user,
                $startsAt,
                $expiresAt,
                $planId
            );
        }

        $values = [
            'subscription_status' => $status,
            'subscription_expires_at' => $expiresAt,
            'subscription_plan_id' => $planId,
        ];

        if ($startsAt) {
            $values['subscription_started_at'] = $startsAt;
        }

        /*
         * Trial is only recreated when explicitly selected.
         * Switching away from trial never leaves the old trial active.
         */
        if ($status !== 'trial') {
            $values['trial_ends_at'] = null;
        }

        $user->forceFill($values)->save();

        return $user->fresh();
    }

    public function isActive(User $user): bool
    {
        return $this->normaliseStatus($user->subscription_status) === 'active';
    }

    public function onTrial(User $user): bool
    {
        /*
         * PAID ACTIVE ALWAYS WINS.
         */
        if ($this->isActive($user)) {
            return false;
        }

        if ($this->normaliseStatus($user->subscription_status) !== 'trial') {
            return false;
        }

        if (! $user->trial_ends_at) {
            return false;
        }

        return Carbon::parse($user->trial_ends_at)->isFuture();
    }

    public function trialDaysLeft(User $user): int
    {
        if (! $this->onTrial($user)) {
            return 0;
        }

        return max(
            0,
            (int) now()->startOfDay()->diffInDays(
                Carbon::parse($user->trial_ends_at)->startOfDay(),
                false
            )
        );
    }
}
