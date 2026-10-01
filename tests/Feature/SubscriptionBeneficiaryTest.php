<?php

namespace Tests\Feature;

use App\Models\IoTecSubscriptionTransaction;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionPaymentActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionBeneficiaryTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'key' => 'beneficiary-monthly',
            'name' => 'Beneficiary Monthly',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 100,
            'is_enabled' => true,
            'sort_order' => 99,
        ]);
    }

    public function test_iotec_activation_for_a_beneficiary_activates_them_and_keeps_billing_with_the_payer(): void
    {
        $payer = User::factory()->create(['subscription_status' => 'trial']);
        $beneficiary = User::factory()->create(['subscription_status' => 'trial']);
        $plan = $this->plan();
        $externalId = (string) Str::uuid();

        $transaction = IoTecSubscriptionTransaction::create([
            'user_id' => $payer->id,
            'beneficiary_user_id' => $beneficiary->id,
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

        $beneficiary->refresh();
        $this->assertSame('active', $beneficiary->subscription_status);
        $this->assertSame($plan->id, (int) $beneficiary->subscription_plan_id);
        $this->assertNotNull($beneficiary->subscription_expires_at);

        $this->assertSame('trial', $payer->fresh()->subscription_status);

        $this->assertDatabaseHas('payments', [
            'user_id' => $payer->id,
            'beneficiary_user_id' => $beneficiary->id,
            'reference' => $externalId,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('invoices', [
            'user_id' => $payer->id,
            'payment_id' => $transaction->fresh()->payment_id,
            'status' => 'paid',
        ]);
    }

    public function test_admin_approval_activates_the_beneficiary_and_notifies_the_payer(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $payer = User::factory()->create(['subscription_status' => 'trial']);
        $beneficiary = User::factory()->create(['subscription_status' => 'trial']);
        $plan = $this->plan();

        $payment = Payment::forceCreate([
            'user_id' => $payer->id,
            'beneficiary_user_id' => $beneficiary->id,
            'subscription_plan_id' => $plan->id,
            'method' => 'bank',
            'amount' => 100,
            'currency' => 'UGX',
            'status' => 'pending',
            'reference' => 'bank-ref-1',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payments.approve', $payment))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('active', $beneficiary->fresh()->subscription_status);
        $this->assertSame('trial', $payer->fresh()->subscription_status);

        Notification::assertSentTo($payer, \App\Notifications\PaymentSuccessfulNotification::class);
        Notification::assertNotSentTo($beneficiary, \App\Notifications\PaymentSuccessfulNotification::class);
    }

    public function test_beneficiary_lookup_returns_only_id_and_name(): void
    {
        $payer = User::factory()->create();
        $beneficiary = User::factory()->create(['email' => 'friend@example.com']);

        Sanctum::actingAs($payer);

        $this->getJson('/api/subscription/beneficiary?email=FRIEND@example.com')
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $beneficiary->id,
                'name' => $beneficiary->name,
            ]]);
    }

    public function test_beneficiary_lookup_rejects_own_and_unknown_emails(): void
    {
        $payer = User::factory()->create(['email' => 'me@example.com']);

        Sanctum::actingAs($payer);

        $this->getJson('/api/subscription/beneficiary?email=me@example.com')
            ->assertStatus(422);

        $this->getJson('/api/subscription/beneficiary?email=nobody@example.com')
            ->assertNotFound();
    }

    public function test_manual_payment_records_the_beneficiary(): void
    {
        $payer = User::factory()->create();
        $beneficiary = User::factory()->create(['email' => 'friend@example.com']);
        $plan = $this->plan();
        $gateway = PaymentGateway::forceCreate([
            'name' => 'Test Bank',
            'type' => 'bank',
            'is_enabled' => true,
        ]);

        Sanctum::actingAs($payer);

        $this->postJson('/api/subscription/pay/manual', [
            'payment_gateway_id' => $gateway->id,
            'plan_id' => $plan->id,
            'reference' => 'manual-ref-1',
            'beneficiary_email' => 'friend@example.com',
        ])->assertOk();

        $this->assertDatabaseHas('payments', [
            'user_id' => $payer->id,
            'beneficiary_user_id' => $beneficiary->id,
            'reference' => 'manual-ref-1',
            'status' => 'pending',
        ]);
    }

    public function test_payment_for_an_unknown_beneficiary_fails_validation(): void
    {
        $payer = User::factory()->create();
        $plan = $this->plan();
        $gateway = PaymentGateway::forceCreate([
            'name' => 'Test Bank',
            'type' => 'bank',
            'is_enabled' => true,
        ]);

        Sanctum::actingAs($payer);

        $this->postJson('/api/subscription/pay/manual', [
            'payment_gateway_id' => $gateway->id,
            'plan_id' => $plan->id,
            'reference' => 'manual-ref-2',
            'beneficiary_email' => 'nobody@example.com',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('beneficiary_email');

        $this->assertDatabaseMissing('payments', ['reference' => 'manual-ref-2']);
    }

    public function test_web_checkout_lookup_works_with_a_session_login(): void
    {
        $payer = User::factory()->create();
        $beneficiary = User::factory()->create(['email' => 'friend@example.com']);

        $this->actingAs($payer)
            ->getJson(route('subscription.beneficiary', ['email' => 'friend@example.com']))
            ->assertOk()
            ->assertJsonPath('data.id', $beneficiary->id);
    }
}
