<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionExpiryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_marks_expired_paid_and_trial_subscriptions(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(12, 0));
        $paid = User::factory()->create([
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->subMinute(),
        ]);
        $trial = User::factory()->create([
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->subMinute(),
        ]);
        $current = User::factory()->create([
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDay(),
        ]);
        $lifetime = User::factory()->create([
            'subscription_status' => 'active',
            'subscription_expires_at' => null,
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame('expired', $paid->fresh()->subscription_status);
        $this->assertSame('expired', $trial->fresh()->subscription_status);
        $this->assertSame('active', $current->fresh()->subscription_status);
        $this->assertSame('active', $lifetime->fresh()->subscription_status);
        $this->assertSame('active', $admin->fresh()->subscription_status);
    }

    public function test_command_does_not_expire_non_owner_organization_members(): void
    {
        $owner = User::factory()->create([
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->subDay(),
        ]);
        $organization = Organization::query()->create([
            'name' => 'Expiry test organization',
            'owner_user_id' => $owner->id,
        ]);
        $owner->update(['organization_id' => $organization->id]);
        $member = User::factory()->create([
            'organization_id' => $organization->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame('expired', $owner->fresh()->subscription_status);
        $this->assertSame('active', $member->fresh()->subscription_status);
    }
}
