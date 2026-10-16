<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminIndividualSubscriptionPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_plan_form_exposes_tiers_manual_price_and_per_meeting_duration_limit(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin', 'system_role' => 'super_admin']);

        $this->actingAs($superAdmin)->get(route('admin.subscription-plans.create'))
            ->assertOk()
            ->assertSee('Starter')
            ->assertSee('Gold')
            ->assertSee('Premium')
            ->assertSee('Custom')
            ->assertSee('max_transcription_minutes_per_meeting')
            ->assertSee('Flat price');
    }

    public function test_super_admin_can_open_the_legacy_plan_edit_url(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin', 'system_role' => 'super_admin']);
        $plan = SubscriptionPlan::query()->create([
            'key' => 'legacy-edit-plan',
            'name' => 'Legacy Edit Plan',
            'category' => 'individual',
            'individual_tier' => 'starter',
            'duration_months' => 1,
            'discount_percent' => 0,
            'max_transcription_minutes_per_meeting' => 60,
            'transcription_capacity_mb' => 30,
            'color' => '#475569',
            'is_enabled' => true,
        ]);

        $this->actingAs($superAdmin)
            ->get('/admin/subscription-plans/'.$plan->id)
            ->assertOk()
            ->assertSee('Edit Subscription Plan')
            ->assertSee('Legacy Edit Plan');
    }

    public function test_super_admin_can_create_priced_individual_tier_with_per_meeting_duration_limit(): void
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
            'max_transcription_minutes_per_meeting' => 300,
            'transcription_capacity_mb' => 30,
            'color' => '#D97706',
            'sort_order' => 2,
            'is_enabled' => 1,
        ])->assertRedirect(route('admin.subscription-plans.index'));

        $this->assertDatabaseHas('subscription_plans', [
            'key' => 'gold-individual',
            'individual_tier' => 'gold',
            'max_transcription_minutes_per_meeting' => 300,
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
            'max_transcription_minutes_per_meeting' => 60,
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
                'max_transcription_minutes_per_meeting' => 1,
                'color' => '#475569',
            ])->assertForbidden();

        $this->assertSame('Starter', $plan->fresh()->name);
        $this->assertSame(60, $plan->fresh()->max_transcription_minutes_per_meeting);
    }

    public function test_super_admin_can_edit_and_save_an_existing_individual_plan(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin', 'system_role' => 'super_admin']);
        $plan = SubscriptionPlan::query()->create([
            'key' => 'starter-plan',
            'name' => 'Starter',
            'category' => 'individual',
            'individual_tier' => 'starter',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 1000,
            'max_transcription_minutes_per_meeting' => 60,
            'transcription_capacity_mb' => 30,
            'color' => '#475569',
            'is_enabled' => true,
        ]);

        $this->actingAs($superAdmin)->put(route('admin.subscription-plans.update', $plan), [
            'key' => 'starter-plan',
            'name' => 'Starter Plus',
            'category' => 'individual',
            'individual_tier' => 'starter',
            'pricing_type' => 'flat',
            'duration_months_flat' => 1,
            'flat_price' => 1500,
            'max_transcription_minutes_per_meeting' => 90,
            'transcription_capacity_mb' => 40,
            'color' => '#475569',
            'sort_order' => 1,
            'is_enabled' => 1,
        ])->assertRedirect(route('admin.subscription-plans.index'));

        $this->assertDatabaseHas('subscription_plans', [
            'id' => $plan->id,
            'name' => 'Starter Plus',
            'flat_price' => 1500,
            'max_transcription_minutes_per_meeting' => 90,
            'transcription_capacity_mb' => 40,
        ]);
    }

    public function test_editing_plan_with_another_plans_key_returns_validation_error(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin', 'system_role' => 'super_admin']);
        $plan = SubscriptionPlan::query()->create([
            'key' => 'starter-plan',
            'name' => 'Starter',
            'category' => 'individual',
            'individual_tier' => 'starter',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 1000,
            'max_transcription_minutes_per_meeting' => 60,
            'transcription_capacity_mb' => 30,
            'color' => '#475569',
            'is_enabled' => true,
        ]);
        $duplicateKey = 'edit-conflict-'.uniqid();
        SubscriptionPlan::query()->create([
            'key' => $duplicateKey,
            'name' => 'Monthly',
            'category' => 'individual',
            'individual_tier' => 'gold',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 1500,
            'max_transcription_minutes_per_meeting' => 60,
            'transcription_capacity_mb' => 30,
            'color' => '#475569',
            'is_enabled' => true,
        ]);

        $this->actingAs($superAdmin)
            ->from(route('admin.subscription-plans.edit', $plan))
            ->put(route('admin.subscription-plans.update', $plan), [
                'key' => $duplicateKey,
                'name' => 'Starter Updated',
                'category' => 'individual',
                'individual_tier' => 'starter',
                'pricing_type' => 'flat',
                'duration_months_flat' => 1,
                'flat_price' => 1200,
                'max_transcription_minutes_per_meeting' => 60,
                'transcription_capacity_mb' => 30,
                'color' => '#475569',
            ])
            ->assertRedirect(route('admin.subscription-plans.edit', $plan))
            ->assertSessionHasErrors('key');

        $this->assertDatabaseHas('subscription_plans', [
            'id' => $plan->id,
            'key' => 'starter-plan',
            'name' => 'Starter',
        ]);
    }

    public function test_super_admin_can_change_an_existing_plan_between_individual_and_team_categories(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin', 'system_role' => 'super_admin']);
        $plan = SubscriptionPlan::query()->create([
            'key' => 'convertible-plan',
            'name' => 'Convertible',
            'category' => 'individual',
            'individual_tier' => 'gold',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 1500,
            'max_transcription_minutes_per_meeting' => 300,
            'transcription_capacity_mb' => 30,
            'color' => '#475569',
            'is_enabled' => true,
        ]);

        $this->actingAs($superAdmin)->put(route('admin.subscription-plans.update', $plan), [
            'key' => 'convertible-plan',
            'name' => 'Team Convertible',
            'category' => 'family_team',
            'individual_tier' => 'gold',
            'pricing_type' => 'flat',
            'duration_months_flat' => 1,
            'flat_price' => 5000,
            'max_transcription_minutes_per_meeting' => 300,
            'transcription_capacity_mb' => 30,
            'included_seats' => 5,
            'color' => '#475569',
            'is_enabled' => 1,
        ])->assertRedirect(route('admin.subscription-plans.index'));

        $this->assertDatabaseHas('subscription_plans', [
            'id' => $plan->id,
            'category' => 'family_team',
            'individual_tier' => null,
            'included_seats' => 5,
        ]);
    }
}
