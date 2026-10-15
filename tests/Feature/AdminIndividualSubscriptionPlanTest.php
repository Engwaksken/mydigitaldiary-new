<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminIndividualSubscriptionPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_plan_form_exposes_tiers_manual_price_and_monthly_allowance(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin', 'system_role' => 'super_admin']);

        $this->actingAs($superAdmin)->get(route('admin.subscription-plans.create'))
            ->assertOk()
            ->assertSee('Starter')
            ->assertSee('Gold')
            ->assertSee('Premium')
            ->assertSee('Custom')
            ->assertSee('monthly_transcription_minutes')
            ->assertSee('Flat price');
    }

    public function test_super_admin_can_create_priced_individual_tier_with_monthly_minutes(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin', 'system_role' => 'super_admin']);

        $this->actingAs($superAdmin)->post(route('admin.subscription-plans.store'), [
            'key' => 'gold-individual',
            'name' => 'Gold',
            'category' => 'individual',
            'individual_tier' => 'gold',
            'pricing_type' => 'flat',
            'duration_months_flat' => 1,
            'flat_price' => 25000,
            'monthly_transcription_minutes' => 300,
            'transcription_capacity_mb' => 30,
            'color' => '#D97706',
            'sort_order' => 2,
            'is_enabled' => 1,
        ])->assertRedirect(route('admin.subscription-plans.index'));

        $this->assertDatabaseHas('subscription_plans', [
            'key' => 'gold-individual',
            'individual_tier' => 'gold',
            'monthly_transcription_minutes' => 300,
            'flat_price' => 25000,
            'duration_months' => 1,
        ]);
    }

    public function test_regular_admin_cannot_change_subscription_pricing(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'system_role' => 'admin']);
        $plan = SubscriptionPlan::query()->create([
            'key' => 'starter-plan',
            'name' => 'Starter',
            'category' => 'individual',
            'individual_tier' => 'starter',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 1000,
            'monthly_transcription_minutes' => 60,
            'transcription_capacity_mb' => 30,
            'color' => '#475569',
            'is_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.subscription-plans.update', $plan), [
                'key' => 'starter-plan',
                'name' => 'Starter altered',
                'category' => 'individual',
                'individual_tier' => 'starter',
                'pricing_type' => 'flat',
                'flat_price' => 1,
                'monthly_transcription_minutes' => 1,
                'color' => '#475569',
            ])->assertForbidden();

        $this->assertSame('Starter', $plan->fresh()->name);
        $this->assertSame(60, $plan->fresh()->monthly_transcription_minutes);
    }
}
