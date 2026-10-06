<?php

namespace Tests\Feature;

use App\Models\IoTecSubscriptionTransaction;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRecordingExtraGrant;
use App\Models\User;
use App\Models\UserExtraRequest;
use App\Services\ApplyExtraRequestService;
use App\Services\SubscriptionPaymentActivationService;
use App\Services\SubscriptionRecordingQuotaGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Feature\Support\SubscriptionPlanFactory;

class SubscriptionRecordingMinutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_iotec_activation_grants_plan_included_recording_minutes(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::create([
            'key' => 'grant-test-monthly',
            'name' => 'Grant Test Monthly',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 100,
            'is_enabled' => true,
            'sort_order' => 99,
            'included_extra_recording_minutes' => 120,
        ]);

        $transaction = IoTecSubscriptionTransaction::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'external_id' => (string) Str::uuid(),
            'payment_channel' => 'mobile_money',
            'payer' => '256700000000',
            'amount' => 100,
            'currency' => 'UGX',
            'status' => 'success',
            'paid_at' => now(),
        ]);

        app(SubscriptionPaymentActivationService::class)->activateFromIoTec($transaction);

        $this->assertDatabaseHas('subscription_recording_extra_grants', [
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'included_minutes' => 120,
        ]);

        $user->refresh();
        $this->assertSame(120, $user->extra_recording_quota_minutes);
    }

    public function test_iotec_activation_is_idempotent_for_same_transaction(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::create([
            'key' => 'idempotent-monthly',
            'name' => 'Idempotent Monthly',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 100,
            'is_enabled' => true,
            'sort_order' => 99,
            'included_extra_recording_minutes' => 60,
        ]);

        $transaction = IoTecSubscriptionTransaction::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'external_id' => (string) Str::uuid(),
            'payment_channel' => 'mobile_money',
            'payer' => '256700000000',
            'amount' => 100,
            'currency' => 'UGX',
            'status' => 'success',
            'paid_at' => now(),
        ]);

        $service = app(SubscriptionPaymentActivationService::class);
        $service->activateFromIoTec($transaction);

        // Reload the user to get the updated state
        $user->refresh();
        $this->assertSame(60, $user->extra_recording_quota_minutes);

        // Only one grant row should exist
        $this->assertSame(1, SubscriptionRecordingExtraGrant::where('user_id', $user->id)->count());

        // Replay the same transaction — the idempotency path must kick in:
        // no double-add and no second grant row.
        $service->activateFromIoTec($transaction);

        $user->refresh();
        $this->assertSame(60, $user->extra_recording_quota_minutes);
        $this->assertSame(1, SubscriptionRecordingExtraGrant::where('user_id', $user->id)->count());
    }

    public function test_admin_activation_grants_plan_included_recording_minutes(): void
    {
        $user = User::factory()->create(['subscription_status' => 'trial']);
        $plan = SubscriptionPlanFactory::new()->create([
            'key' => 'admin-grant-monthly',
            'name' => 'Admin Grant Monthly',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 100,
            'is_enabled' => true,
            'sort_order' => 99,
            'included_extra_recording_minutes' => 200,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('admin.users.subscription.update', $user), [
                'subscription_status' => 'active',
                'subscription_plan_id' => $plan->id,
                'subscription_started_at' => now()->toDateString(),
                'subscription_expires_at' => now()->addMonth()->toDateString(),
        ])->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('subscription_recording_extra_grants', [
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'included_minutes' => 200,
        ]);

        $user->refresh();
        $this->assertSame(200, $user->extra_recording_quota_minutes);

        // Repeat save should not double-grant
        $this->actingAs($admin)->patch(route('admin.users.subscription.update', $user), [
                'subscription_status' => 'active',
                'subscription_plan_id' => $plan->id,
                'subscription_started_at' => now()->toDateString(),
                'subscription_expires_at' => now()->addMonth()->toDateString(),
        ])->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();

        $this->assertSame(1, SubscriptionRecordingExtraGrant::where('user_id', $user->id)->count());
        $user->refresh();
        $this->assertSame(200, $user->extra_recording_quota_minutes);
    }

    public function test_plan_with_zero_included_minutes_grants_nothing(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::create([
            'key' => 'zero-grant-monthly',
            'name' => 'Zero Grant Monthly',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 100,
            'is_enabled' => true,
            'sort_order' => 99,
            'included_extra_recording_minutes' => 0,
        ]);

        $transaction = IoTecSubscriptionTransaction::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'external_id' => (string) Str::uuid(),
            'payment_channel' => 'mobile_money',
            'payer' => '256700000000',
            'amount' => 100,
            'currency' => 'UGX',
            'status' => 'success',
            'paid_at' => now(),
        ]);

        app(SubscriptionPaymentActivationService::class)->activateFromIoTec($transaction);

        $this->assertDatabaseCount('subscription_recording_extra_grants', 0);

        $user->refresh();
        $this->assertSame(0, $user->extra_recording_quota_minutes);
    }

    public function test_admin_activation_of_lifetime_plan_grants_minutes_without_expiry(): void
    {
        $user = User::factory()->create(['subscription_status' => 'trial']);
        $plan = SubscriptionPlanFactory::new()->create([
            'key' => 'admin-grant-lifetime',
            'name' => 'Admin Grant Lifetime',
            'duration_months' => null,
            'discount_percent' => 0,
            'flat_price' => 500,
            'is_enabled' => true,
            'sort_order' => 99,
            'included_extra_recording_minutes' => 300,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.users.subscription.update', $user), [
                'subscription_status' => 'active',
                'subscription_plan_id' => $plan->id,
                'subscription_started_at' => now()->toDateString(),
        ])->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();

        $grant = SubscriptionRecordingExtraGrant::where('user_id', $user->id)->firstOrFail();

        // Lifetime plan: the grant never expires.
        $this->assertNull($grant->expires_at);
        $this->assertSame(300, $grant->included_minutes);

        $user->refresh();
        $this->assertSame(300, $user->extra_recording_quota_minutes);
        $this->assertNull($user->extra_quota_expires_at);
    }

    public function test_plan_with_null_included_minutes_grants_nothing(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::create([
            'key' => 'null-grant-monthly',
            'name' => 'Null Grant Monthly',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 100,
            'is_enabled' => true,
            'sort_order' => 99,
            'included_extra_recording_minutes' => null,
        ]);

        $transaction = IoTecSubscriptionTransaction::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'external_id' => (string) Str::uuid(),
            'payment_channel' => 'mobile_money',
            'payer' => '256700000000',
            'amount' => 100,
            'currency' => 'UGX',
            'status' => 'success',
            'paid_at' => now(),
        ]);

        app(SubscriptionPaymentActivationService::class)->activateFromIoTec($transaction);

        $this->assertDatabaseCount('subscription_recording_extra_grants', 0);

        $user->refresh();
        $this->assertSame(0, $user->extra_recording_quota_minutes);
    }

    public function test_long_activation_event_key_is_stored_as_sha1(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::create([
            'key' => 'long-key-monthly',
            'name' => 'Long Key Monthly',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 100,
            'is_enabled' => true,
            'sort_order' => 99,
            'included_extra_recording_minutes' => 60,
        ]);

        $longKey = str_repeat('x', 300);

        $grant = app(SubscriptionRecordingQuotaGrantService::class)->grantIncludedMinutes(
            $user,
            $plan,
            $longKey,
            now()->addMonth(),
            null,
            'test_long_key'
        );

        $this->assertNotNull($grant);
        $this->assertStringStartsWith('sha1:', $grant->activation_event_key);
        $this->assertSame('sha1:'.sha1($longKey), $grant->activation_event_key);
        $this->assertSame(40, strlen(substr($grant->activation_event_key, 5)));

        $user->refresh();
        $this->assertSame(60, $user->extra_recording_quota_minutes);
    }

    public function test_grant_replaces_expired_extra_quota_expiry_with_plan_expiry(): void
    {
        $user = User::factory()->create([
            'extra_recording_quota_minutes' => 50,
            'extra_quota_expires_at' => now()->subDay(),
        ]);
        $plan = SubscriptionPlan::create([
            'key' => 'expired-quota-monthly',
            'name' => 'Expired Quota Monthly',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 100,
            'is_enabled' => true,
            'sort_order' => 99,
            'included_extra_recording_minutes' => 90,
        ]);

        $transaction = IoTecSubscriptionTransaction::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'external_id' => (string) Str::uuid(),
            'payment_channel' => 'mobile_money',
            'payer' => '256700000000',
            'amount' => 100,
            'currency' => 'UGX',
            'status' => 'success',
            'paid_at' => now(),
        ]);

        app(SubscriptionPaymentActivationService::class)->activateFromIoTec($transaction);

        $user->refresh();

        // The new expiry is the plan's expiry, not the stale past date.
        $this->assertTrue($user->extra_quota_expires_at->isFuture());
        $this->assertSame(140, $user->extra_recording_quota_minutes);
        $this->assertSame(140, $user->availableExtraRecordingQuotaMinutes());
    }

    public function test_lifetime_grant_preserves_existing_future_extra_quota_expiry(): void
    {
        $user = User::factory()->create([
            'subscription_status' => 'trial',
            'extra_recording_quota_minutes' => 60,
            'extra_quota_expires_at' => now()->addDays(30),
        ]);
        $plan = SubscriptionPlanFactory::new()->create([
            'key' => 'lifetime-preserve-expiry',
            'name' => 'Lifetime Preserve Expiry',
            'duration_months' => null,
            'discount_percent' => 0,
            'flat_price' => 500,
            'is_enabled' => true,
            'sort_order' => 99,
            'included_extra_recording_minutes' => 300,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.users.subscription.update', $user), [
                'subscription_status' => 'active',
                'subscription_plan_id' => $plan->id,
                'subscription_started_at' => now()->toDateString(),
        ])->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();

        $user->refresh();

        // Lifetime grant must NOT convert the expiring purchased quota
        // into permanent quota: minutes add up, but the original future
        // expiry is preserved (not null, not shortened).
        $this->assertSame(360, $user->extra_recording_quota_minutes);
        $this->assertNotNull($user->extra_quota_expires_at);
        $this->assertTrue($user->extra_quota_expires_at->isSameDay(now()->addDays(30)));
    }

    public function test_lifetime_grant_clears_stale_past_extra_quota_expiry(): void
    {
        $user = User::factory()->create([
            'subscription_status' => 'trial',
            'extra_recording_quota_minutes' => 50,
            'extra_quota_expires_at' => now()->subDay(),
        ]);
        $plan = SubscriptionPlanFactory::new()->create([
            'key' => 'lifetime-clear-stale',
            'name' => 'Lifetime Clear Stale',
            'duration_months' => null,
            'discount_percent' => 0,
            'flat_price' => 500,
            'is_enabled' => true,
            'sort_order' => 99,
            'included_extra_recording_minutes' => 300,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.users.subscription.update', $user), [
                'subscription_status' => 'active',
                'subscription_plan_id' => $plan->id,
                'subscription_started_at' => now()->toDateString(),
        ])->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();

        $user->refresh();

        // Stale past expiry is cleared so the lifetime minutes are usable.
        $this->assertNull($user->extra_quota_expires_at);
        $this->assertSame(350, $user->extra_recording_quota_minutes);
        $this->assertSame(350, $user->availableExtraRecordingQuotaMinutes());
    }

    public function test_apply_quota_preserves_null_expiry_for_lifetime_user(): void
    {
        $user = User::factory()->create([
            'extra_recording_quota_minutes' => 300,
            'extra_quota_expires_at' => null,
        ]);
        $extraRequest = UserExtraRequest::factory()->for($user)->create([
            'quota_amount' => 120,
            'quota_used' => 0,
            'expires_at' => now()->addDays(30),
        ]);

        app(ApplyExtraRequestService::class)->applyQuota($extraRequest);

        $user->refresh();

        // Lifetime (null) expiry must stay null — the request's shorter
        // 30-day expiry must not downgrade the never-expires entitlement.
        $this->assertSame(420, $user->extra_recording_quota_minutes);
        $this->assertNull($user->extra_quota_expires_at);
        $this->assertSame('applied', $extraRequest->fresh()->status);
    }

    public function test_iotec_activation_of_lifetime_plan_grants_minutes_without_expiry(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::create([
            'key' => 'iotec-lifetime',
            'name' => 'IoTec Lifetime',
            'duration_months' => null,
            'discount_percent' => 0,
            'flat_price' => 500,
            'is_enabled' => true,
            'sort_order' => 99,
            'included_extra_recording_minutes' => 300,
        ]);

        $transaction = IoTecSubscriptionTransaction::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'external_id' => (string) Str::uuid(),
            'payment_channel' => 'mobile_money',
            'payer' => '256700000000',
            'amount' => 500,
            'currency' => 'UGX',
            'status' => 'success',
            'paid_at' => now(),
        ]);

        app(SubscriptionPaymentActivationService::class)->activateFromIoTec($transaction);

        $grant = SubscriptionRecordingExtraGrant::where('user_id', $user->id)->firstOrFail();

        // Lifetime plan via ioTec: the grant never expires.
        $this->assertNull($grant->expires_at);
        $this->assertSame(300, $grant->included_minutes);

        $user->refresh();
        $this->assertSame(300, $user->extra_recording_quota_minutes);
        $this->assertNull($user->extra_quota_expires_at);
        $this->assertNull($user->subscription_expires_at);
    }
}
