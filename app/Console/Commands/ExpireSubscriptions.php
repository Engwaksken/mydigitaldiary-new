<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Mark paid subscriptions and trials expired when their expiry date has passed.';

    public function handle(): int
    {
        if (! Schema::hasColumn('users', 'subscription_status')) {
            $this->info('Subscription status is not available on users.');

            return self::SUCCESS;
        }

        $eligible = User::query()
            ->where('role', '!=', 'admin')
            ->where(function ($query): void {
                $query->where(function ($paid): void {
                    $paid->where('subscription_status', 'active')
                        ->whereNotNull('subscription_expires_at')
                        ->where('subscription_expires_at', '<=', now());
                })->orWhere(function ($trial): void {
                    $trial->whereIn('subscription_status', ['trial', 'trialing'])
                        ->whereNotNull('trial_ends_at')
                        ->where('trial_ends_at', '<=', now());
                });
            });

        if (Schema::hasColumn('users', 'suspended_at')) {
            $eligible->whereNull('suspended_at');
        }

        // A non-owner organization member gets access from the owner's plan;
        // don't mutate their independent status based on their own dates.
        if (Schema::hasColumn('users', 'organization_id') && Schema::hasTable('organizations')) {
            $eligible->where(function ($query): void {
                $query->whereNull('organization_id')
                    ->orWhereHas('organization', fn ($organization) => $organization
                        ->whereColumn('organizations.owner_user_id', 'users.id'));
            });
        }

        $count = $eligible->update(['subscription_status' => 'expired']);
        $this->info("Marked {$count} subscription(s) expired.");

        return self::SUCCESS;
    }
}
