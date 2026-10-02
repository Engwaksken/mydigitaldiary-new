<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserSubscriptionUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_user_subscription_through_admin_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $plan = SubscriptionPlan::query()->firstOrFail();

        $response = $this->actingAs($admin)->patch(route('admin.users.subscription.update', $user), [
            'subscription_status' => 'active',
            'subscription_plan_id' => $plan->id,
            'subscription_started_at' => '2026-10-01',
            'subscription_expires_at' => '2027-10-01',
            'trial_ends_at' => '2026-10-15',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'subscription_status' => 'active',
            'subscription_plan_id' => $plan->id,
            'subscription_started_at' => '2026-10-01 00:00:00',
            'subscription_expires_at' => '2027-10-01 23:59:59',
            'trial_ends_at' => null,
        ]);
    }

    public function test_invalid_status_is_rejected_without_changing_subscription(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->patch(route('admin.users.subscription.update', $user), [
            'subscription_status' => 'pending',
        ]);

        $response->assertSessionHasErrors('subscription_status');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'subscription_status' => 'trialing',
        ]);
    }

    public function test_expiry_before_start_is_rejected_without_changing_subscription(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->patch(route('admin.users.subscription.update', $user), [
            'subscription_status' => 'active',
            'subscription_started_at' => '2026-10-02',
            'subscription_expires_at' => '2026-10-01',
        ]);

        $response->assertSessionHasErrors('subscription_expires_at');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'subscription_status' => 'trialing',
        ]);
    }
}
