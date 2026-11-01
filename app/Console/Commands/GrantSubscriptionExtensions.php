<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SubscriptionAdminNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * One-off operator action: give current trial users extra trial days and
 * grant expired paid subscribers a free window, then tell admins what was
 * offered. Run manually (or via --dry-run first) — not scheduled, since the
 * day counts are a deliberate promotional decision, not a recurring rule.
 */
final class GrantSubscriptionExtensions extends Command
{
    protected $signature = 'subscriptions:grant-extension
                            {--trial-days=30 : Extra days to grant to users currently on trial}
                            {--expired-days=14 : Free days to grant to expired paid subscribers}
                            {--dry-run : Report who would be updated without writing}';

    protected $description = 'Extend current trial users and grant expired subscribers free days, then notify admins.';

    public function handle(SubscriptionAdminNotificationService $adminNotifications): int
    {
        if (! Schema::hasColumn('users', 'subscription_status')) {
            $this->error('users.subscription_status is missing.');

            return self::FAILURE;
        }

        $trialDays = (int) $this->option('trial-days');
        $expiredDays = (int) $this->option('expired-days');

        // Users currently on trial (canonical 'trial', plus the legacy
        // 'trialing' spelling some rows still carry).
        $trialQuery = User::query()
            ->where('role', '!=', 'admin')
            ->whereRaw("LOWER(TRIM(COALESCE(subscription_status, ''))) IN ('trial', 'trialing')");

        // Users who actually subscribed (so they have a paid expiry date) but
        // have since lapsed. Former trial users that never paid are NOT
        // included here — they're covered by the trial branch above while
        // still trialing, and a lapsed trial isn't a "subscribed" user.
        $expiredQuery = User::query()
            ->where('role', '!=', 'admin')
            ->whereRaw("LOWER(TRIM(COALESCE(subscription_status, ''))) = 'expired'")
            ->whereNotNull('subscription_expires_at');

        $this->excludeManagedOrganizationMembers($trialQuery);
        $this->excludeManagedOrganizationMembers($expiredQuery);

        $trialCount = (clone $trialQuery)->count();
        $expiredCount = (clone $expiredQuery)->count();

        if ($this->option('dry-run')) {
            $this->info("Would extend {$trialCount} trial user(s) by {$trialDays} day(s).");
            $this->info("Would grant {$expiredCount} expired subscriber(s) {$expiredDays} free day(s).");

            return self::SUCCESS;
        }

        $now = now();

        if ($trialCount > 0) {
            $trialQuery->update(['trial_ends_at' => $now->copy()->addDays($trialDays)]);
        }

        if ($expiredCount > 0) {
            $expiredQuery->update([
                'subscription_status' => 'active',
                'subscription_expires_at' => $now->copy()->addDays($expiredDays),
            ]);
        }

        if ($trialCount > 0 || $expiredCount > 0) {
            $adminNotifications->notifyExtensionSummary($trialCount, $trialDays, $expiredCount, $expiredDays);
        }

        $this->info("Extended {$trialCount} trial user(s) by {$trialDays} day(s).");
        $this->info("Granted {$expiredCount} expired subscriber(s) {$expiredDays} free day(s).");

        return self::SUCCESS;
    }

    /**
     * A non-owner organization member gets access from the owner's plan, so
     * their own dates/status shouldn't be granted independent free days.
     */
    private function excludeManagedOrganizationMembers($query): void
    {
        if (Schema::hasColumn('users', 'organization_id') && Schema::hasTable('organizations')) {
            $query->where(function ($q): void {
                $q->whereNull('organization_id')
                    ->orWhereHas('organization', fn ($org) => $org
                        ->whereColumn('organizations.owner_user_id', 'users.id'));
            });
        }
    }
}
