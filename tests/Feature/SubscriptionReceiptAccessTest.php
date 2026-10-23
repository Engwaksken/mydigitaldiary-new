<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionReceiptAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_a_users_subscription_receipt(): void
    {
        $owner = User::factory()->create(['name' => 'Receipt Owner']);
        $admin = User::factory()->create(['role' => 'admin']);
        $payment = $this->paymentFor($owner);

        $response = $this->actingAs($admin)->get(route('subscription.receipt', $payment));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_non_owner_non_admin_cannot_download_a_users_subscription_receipt(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $payment = $this->paymentFor($owner);

        $this->actingAs($otherUser)
            ->get(route('subscription.receipt', $payment))
            ->assertForbidden();
    }

    private function paymentFor(User $user): Payment
    {
        return Payment::query()->create([
            'user_id' => $user->id,
            'method' => 'bank',
            'amount' => 25,
            'currency' => 'USD',
            'status' => 'completed',
            'reference' => 'TEST-RECEIPT',
            'receipt_number' => '2026-0001',
        ]);
    }
}
