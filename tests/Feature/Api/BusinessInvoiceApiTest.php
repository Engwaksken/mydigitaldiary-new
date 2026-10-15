<?php

namespace Tests\Feature\Api;

use App\Models\BusinessInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessInvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_business_invoice_and_computes_amounts_from_line_items(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/business-invoices', [
            'business_name' => 'Acme Studio',
            'customer_name' => 'Jane Customer',
            'customer_email' => 'jane@example.com',
            'line_items' => [
                ['description' => 'Design work', 'quantity' => 2, 'unit_price' => 12500],
                ['description' => 'Hosting', 'quantity' => 1, 'unit_price' => 5000],
            ],
            'issue_date' => today()->toDateString(),
        ])->assertCreated()->assertJsonPath('data.total', '30000.00')
            ->assertJsonPath('data.currency', 'UGX')
            ->assertJsonPath('data.line_items.0.amount', 25000);

        $this->assertDatabaseHas('business_invoices', ['user_id' => $user->id, 'total' => 30000]);
    }

    public function test_business_invoice_lists_are_scoped_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        BusinessInvoice::create([
            'user_id' => $other->id, 'invoice_number' => 'BI-2026-ABCD1234',
            'business_name' => 'Other business', 'customer_name' => 'Customer',
            'customer_email' => 'customer@example.com', 'line_items' => [],
            'currency' => 'UGX', 'total' => 10, 'issue_date' => today(), 'status' => 'unpaid',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/business-invoices')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_owner_can_send_and_download_an_invoice(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $invoice = BusinessInvoice::create([
            'user_id' => $user->id, 'invoice_number' => 'BI-2026-ABCD5678',
            'business_name' => 'Acme Studio', 'customer_name' => 'Jane Customer',
            'customer_email' => 'jane@example.com',
            'line_items' => [['description' => 'Design work', 'quantity' => 1, 'unit_price' => 125, 'amount' => 125]],
            'currency' => 'UGX', 'total' => 125, 'issue_date' => today(), 'status' => 'unpaid',
        ]);

        $this->postJson("/api/business-invoices/{$invoice->id}/send")
            ->assertOk()->assertJsonPath('data.sent_at', fn ($value) => $value !== null);
        $this->assertNotNull($invoice->fresh()->sent_at);

        $this->get("/api/business-invoices/{$invoice->id}/download")
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_invoice_actions_are_scoped_to_the_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $invoice = BusinessInvoice::create([
            'user_id' => $owner->id, 'invoice_number' => 'BI-2026-ABCD9999',
            'business_name' => 'Acme Studio', 'customer_name' => 'Jane Customer',
            'customer_email' => 'jane@example.com', 'line_items' => [],
            'currency' => 'UGX', 'total' => 0, 'issue_date' => today(), 'status' => 'unpaid',
        ]);
        Sanctum::actingAs($other);

        $this->postJson("/api/business-invoices/{$invoice->id}/send")->assertNotFound();
        $this->getJson("/api/business-invoices/{$invoice->id}/download")->assertNotFound();
        $this->assertNull($invoice->fresh()->sent_at);
    }
}
