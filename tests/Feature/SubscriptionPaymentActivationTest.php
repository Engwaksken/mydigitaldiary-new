<?php

namespace Tests\Feature;

use App\Models\IoTecSubscriptionTransaction;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionPaymentActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Feature\Support\SubscriptionPlanFactory;

class SubscriptionPaymentActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_iotec_activation_creates_completed_payment_and_paid_invoice(): void
    {
        $user = User::factory()->create();

        $plan = SubscriptionPlan::create([
            'key' => 'test-monthly',
            'name' => 'Test Monthly',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 100,
            'is_enabled' => true,
            'sort_order' => 99,
        ]);

        $externalId = (string) Str::uuid();

        $transaction = IoTecSubscriptionTransaction::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'external_id' => $externalId,
            'payment_channel' => 'mobile_money',
            'payer' => '256700000000',
            'amount' => 100,
            'currency' => 'UGX',
            'status' => 'success',
            'paid_at' => now(),
        ]);

        app(SubscriptionPaymentActivationService::class)->activateFromIoTec($transaction);

        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'method' => 'mobile_money',
            'status' => 'completed',
            'reference' => $externalId,
            'gateway_transaction_id' => $externalId,
        ]);

        $paymentId = $transaction->fresh()->payment_id;

        $this->assertNotNull($paymentId);

        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'payment_id' => $paymentId,
            'status' => 'paid',
        ]);
    }

    public function test_successful_iotec_activation_notifies_every_admin_in_app(): void
    {
        Notification::fake();

        $firstAdmin = User::factory()->create(['role' => 'admin']);
        $secondAdmin = User::factory()->create(['role' => 'admin']);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $subscriber = User::factory()->create();
        $plan = SubscriptionPlan::create([
            'key' => 'admin-notification-monthly',
            'name' => 'Admin Notification Monthly',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 100,
            'is_enabled' => true,
            'sort_order' => 99,
        ]);
        $transaction = IoTecSubscriptionTransaction::create([
            'user_id' => $subscriber->id,
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

        Notification::assertSentTo($firstAdmin, \App\Notifications\SubscriptionActivatedNotification::class, function ($notification, array $channels) use ($subscriber) {
            return $notification->subscriber->is($subscriber) && $channels === ['database'];
        });
        Notification::assertSentTo($secondAdmin, \App\Notifications\SubscriptionActivatedNotification::class, function ($notification, array $channels) use ($subscriber) {
            return $notification->subscriber->is($subscriber) && $channels === ['database'];
        });
        Notification::assertSentTo($superAdmin, \App\Notifications\SubscriptionActivatedNotification::class, function ($notification, array $channels) use ($subscriber) {
            return $notification->subscriber->is($subscriber) && $channels === ['database'];
        });
        Notification::assertNotSentTo($subscriber, \App\Notifications\SubscriptionActivatedNotification::class);
    }

    public function test_admin_granting_an_active_subscription_notifies_admins_in_app(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $subscriber = User::factory()->create(['subscription_status' => 'trial']);
        $plan = SubscriptionPlanFactory::new()->create([
            'key' => 'admin-granted-monthly',
            'name' => 'Admin Granted Monthly',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 100,
            'is_enabled' => true,
            'sort_order' => 99,
        ]);

        $this->actingAs($admin)->patch(route('admin.users.subscription.update', $subscriber), [
                'subscription_status' => 'active',
                'subscription_plan_id' => $plan->id,
                'subscription_started_at' => now()->toDateString(),
                'subscription_expires_at' => now()->addMonth()->toDateString(),
        ])->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();

        Notification::assertSentTo($admin, \App\Notifications\SubscriptionActivatedNotification::class, function ($notification, array $channels) use ($subscriber) {
            return $notification->subscriber->is($subscriber) && $channels === ['database'];
        });
    }
}
