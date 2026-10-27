<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

final class DefaultSubscriptionServiceProvider extends ServiceProvider
{
    private const DEFAULT_TRIAL_DAYS = 30;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        User::created(function (User $user): void {
            $this->assignMonthlyTrialWhenMissing($user);
        });
    }

    private function assignMonthlyTrialWhenMissing(User $user): void
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasTable('subscription_plans')
            || ! Schema::hasColumn('users', 'subscription_plan_id')
        ) {
            return;
        }

        /*
         * Organisation-managed members inherit the workspace owner's paid
         * plan and must NOT be turned into independent billable subscribers.
         */
        if ($this->isManagedOrganizationMember($user)) {
            return;
        }

        if (! empty($user->subscription_plan_id)) {
            return;
        }

        $monthlyPlan = $this->findMonthlyPlan();

        if (! $monthlyPlan) {
            Log::warning(
                'Default Monthly trial could not be assigned.',
                [
                    'user_id' => $user->id,
                    'reason' => 'No suitable one-month Monthly plan was found.',
                ]
            );

            return;
        }

        Log::info('Default trial assigned', [
            'user_id' => $user->id,
            'plan_id' => $monthlyPlan->getKey(),
        ]);

        $changes = [
            'subscription_plan_id' => $monthlyPlan->getKey(),
        ];

        if (Schema::hasColumn('users', 'subscription_status')) {
            $status = strtolower(trim((string) $user->subscription_status));

            if ($status === '' || in_array($status, ['trialing', 'trial'], true)) {
                $changes['subscription_status'] = $this->trialStatusValue();
            }
        }

        if (
            Schema::hasColumn('users', 'subscription_started_at')
            && empty($user->subscription_started_at)
        ) {
            $changes['subscription_started_at'] = now();
        }

        if (
            Schema::hasColumn('users', 'trial_ends_at')
            && empty($user->trial_ends_at)
        ) {
            $changes['trial_ends_at'] = now()->addDays(self::DEFAULT_TRIAL_DAYS);
        }

        /*
         * A trial should not also look like a paid subscription expiry.
         * Keep an existing value if another flow deliberately supplied one.
         */
        $user->updateQuietly($changes);
    }

    private function trialStatusValue(): string
    {
        if (! Schema::hasColumn('users', 'subscription_status')) {
            return 'trialing';
        }

        // SQLite stores Laravel enums as text, so the normal trialing value
        // needs no driver-specific column inspection.
        if (DB::getDriverName() !== 'mysql') {
            return 'trialing';
        }

        try {
            $column = DB::selectOne(
                "SHOW COLUMNS FROM `users` LIKE 'subscription_status'"
            );

            $type = strtolower((string) ($column->Type ?? ''));

            if (str_starts_with($type, 'enum(')) {
                preg_match_all("/'([^']+)'/", $type, $matches);
                $allowed = array_map('strtolower', $matches[1] ?? []);

                if (in_array('trial', $allowed, true)) {
                    return 'trial';
                }

                if (in_array('trialing', $allowed, true)) {
                    return 'trialing';
                }

                if (in_array('active', $allowed, true)) {
                    return 'active';
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return 'trialing';
    }

    private function isManagedOrganizationMember(User $user): bool
    {
        if (
            Schema::hasColumn('users', 'organization_id')
            && ! empty($user->organization_id)
        ) {
            return true;
        }

        if (
            Schema::hasTable('organization_members')
            && Schema::hasColumn('organization_members', 'user_id')
        ) {
            return \Illuminate\Support\Facades\DB::table('organization_members')
                ->where('user_id', $user->id)
                ->whereIn('status', ['active', 'invited'])
                ->exists();
        }

        return false;
    }

    private function findMonthlyPlan(): ?SubscriptionPlan
    {
        /*
         * 1) Prefer machine-readable code/slug exactly equal to monthly.
         */
        foreach (['code', 'slug'] as $column) {
            if (! Schema::hasColumn('subscription_plans', $column)) {
                continue;
            }

            $plan = SubscriptionPlan::query()
                ->whereRaw("LOWER(TRIM({$column})) = ?", ['monthly'])
                ->first();

            if ($plan && $this->isTrialEligible($plan)) {
                return $plan;
            }
        }

        /*
         * 2) Accept display names such as "Monthly", "Monthly Plan",
         *    "Individual Monthly", etc.
         */
        foreach (['name', 'title'] as $column) {
            if (! Schema::hasColumn('subscription_plans', $column)) {
                continue;
            }

            $plan = SubscriptionPlan::query()
                ->whereRaw("LOWER({$column}) LIKE ?", ['%monthly%'])
                ->orderBy('id')
                ->first();

            if ($plan && $this->isTrialEligible($plan)) {
                return $plan;
            }
        }

        /*
         * 3) Common interval-based schemas.
         */
        if (Schema::hasColumn('subscription_plans', 'billing_interval')) {
            $plan = SubscriptionPlan::query()
                ->whereRaw(
                    'LOWER(TRIM(billing_interval)) = ?',
                    ['monthly']
                )
                ->orderBy('id')
                ->first();

            if ($plan && $this->isTrialEligible($plan)) {
                return $plan;
            }
        }

        /*
         * 4) Final structured fallback: one-month self-serve plan.
         */
        if (Schema::hasColumn('subscription_plans', 'duration_months')) {
            $query = SubscriptionPlan::query()
                ->where('duration_months', 1);

            if (Schema::hasColumn('subscription_plans', 'category')) {
                $query->where(function ($q): void {
                    $q->whereNull('category')
                        ->orWhereRaw(
                            'LOWER(TRIM(category)) IN (?, ?, ?)',
                            ['individual', 'personal', 'monthly']
                        );
                });
            }

            $plan = $query->orderBy('id')->first();

            if ($plan && $this->isTrialEligible($plan)) {
                return $plan;
            }
        }

        return null;
    }

    /**
     * A plan may only be auto-assigned as a default trial when its trial
     * semantics are verified — a paid-only plan must never be handed out
     * as a free trial. Checks are schema-aware so older installations
     * without the newer columns keep working:
     *
     * - `is_trial` column present  -> must be true
     * - `price` column present     -> must be 0 or null
     * - `flat_price` column present -> must be null or 0
     */
    private function isTrialEligible(SubscriptionPlan $plan): bool
    {
        if (Schema::hasColumn('subscription_plans', 'is_trial')) {
            if (! (bool) $plan->getAttribute('is_trial')) {
                return false;
            }
        }

        if (Schema::hasColumn('subscription_plans', 'price')) {
            $price = $plan->getAttribute('price');

            if ($price !== null && (float) $price > 0) {
                return false;
            }
        }

        if (Schema::hasColumn('subscription_plans', 'flat_price')) {
            $flatPrice = $plan->getAttribute('flat_price');

            if ($flatPrice !== null && (float) $flatPrice > 0) {
                return false;
            }
        }

        return true;
    }
}
