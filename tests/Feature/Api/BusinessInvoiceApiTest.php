<?php

namespace Tests\Feature\Api;

use App\Models\BusinessInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
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

    public function test_mobile_can_preview_invoice_without_saving_it(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/business-invoices/preview', [
            'business_name' => 'Acme Studio',
            'customer_name' => 'Jane Customer',
            'customer_email' => 'jane@example.com',
            'line_items' => [['description' => 'Design work', 'quantity' => 2, 'unit_price' => 125]],
            'issue_date' => today()->toDateString(),
        ])->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertDatabaseCount('business_invoices', 0);
    }

    public function test_mobile_can_upload_and_select_its_invoice_templates(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post('/api/business-invoices/templates', [
            'name' => 'My stationery',
            'is_default' => '1',
            'template_file' => UploadedFile::fake()->createWithContent(
                'stationery.html',
                '<html><body>{{invoice_number}}{{line_items_table}}</body></html>'
            ),
        ])->assertCreated()->assertJsonPath('data.name', 'My stationery');

        $this->getJson('/api/business-invoices/templates')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'My stationery')
            ->assertJsonPath('data.0.format', 'html')
            ->assertJsonPath('data.0.is_default', true);
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

        $this->getJson('/api/business-invoices')->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('stats.total', 0)->assertJsonPath('stats.unpaid', 0);
    }

    public function test_mobile_can_bulk_delete_only_its_unpaid_invoices(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);
        $invoice = BusinessInvoice::create([
            'user_id' => $user->id, 'invoice_number' => 'BI-2026-BULK1234',
            'business_name' => 'Acme Studio', 'customer_name' => 'Jane Customer',
            'customer_email' => 'jane@example.com', 'line_items' => [],
            'currency' => 'UGX', 'total' => 0, 'issue_date' => today(), 'status' => 'unpaid',
        ]);
        $paidInvoice = BusinessInvoice::create([
            'user_id' => $user->id, 'invoice_number' => 'BI-2026-PAID5678',
            'business_name' => 'Acme Studio', 'customer_name' => 'Jane Customer',
            'customer_email' => 'jane@example.com', 'line_items' => [],
            'currency' => 'UGX', 'total' => 0, 'issue_date' => today(), 'status' => 'paid',
        ]);
        $foreignInvoice = BusinessInvoice::create([
            'user_id' => $other->id, 'invoice_number' => 'BI-2026-FOREIGN',
            'business_name' => 'Other Studio', 'customer_name' => 'Other Customer',
            'customer_email' => 'other@example.com', 'line_items' => [],
            'currency' => 'UGX', 'total' => 0, 'issue_date' => today(), 'status' => 'unpaid',
        ]);

        $this->postJson('/api/business-invoices/bulk-delete', ['invoice_ids' => [$invoice->id]])
            ->assertOk()->assertJsonPath('deleted', 1);
        $this->postJson('/api/business-invoices/bulk-delete', ['invoice_ids' => [$paidInvoice->id]])
            ->assertStatus(422);
        $this->postJson('/api/business-invoices/bulk-delete', ['invoice_ids' => [$foreignInvoice->id]])
            ->assertNotFound();

        $this->assertDatabaseMissing('business_invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('business_invoices', ['id' => $paidInvoice->id]);
        $this->assertDatabaseHas('business_invoices', ['id' => $foreignInvoice->id]);
    }

    public function test_mobile_can_edit_unpaid_invoice_and_summary_counts_are_returned(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $invoice = BusinessInvoice::create([
            'user_id' => $user->id, 'invoice_number' => 'BI-2026-EDIT1234',
            'business_name' => 'Old Studio', 'customer_name' => 'Old Customer',
            'customer_email' => 'old@example.com', 'line_items' => [],
            'currency' => 'UGX', 'total' => 0, 'issue_date' => today(), 'status' => 'unpaid',
        ]);

        $this->putJson("/api/business-invoices/{$invoice->id}", [
            'business_name' => 'Updated Studio',
            'customer_name' => 'Updated Customer',
            'customer_email' => 'updated@example.com',
            'line_items' => [['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 75]],
            'currency' => 'USD',
            'issue_date' => today()->toDateString(),
        ])->assertOk()->assertJsonPath('data.total', '150.00')
            ->assertJsonPath('data.customer_name', 'Updated Customer');

        $this->getJson('/api/business-invoices')->assertOk()
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('stats.unpaid', 1)
            ->assertJsonPath('stats.paid', 0);

        $invoice->update(['status' => 'paid']);
        $this->putJson("/api/business-invoices/{$invoice->id}", [])->assertStatus(422);
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

    public function test_mobile_can_mark_invoice_paid_and_send_the_matching_receipt(): void
    {
        \Illuminate\Support\Facades\Mail::shouldReceive('send')->once();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $invoice = BusinessInvoice::create([
            'user_id' => $user->id, 'invoice_number' => 'BI-2026-PAID1234',
            'business_name' => 'Acme Studio', 'customer_name' => 'Jane Customer',
            'customer_email' => 'jane@example.com',
            'line_items' => [['description' => 'Design work', 'quantity' => 1, 'unit_price' => 125, 'amount' => 125]],
            'currency' => 'UGX', 'total' => 125, 'issue_date' => today(), 'status' => 'unpaid',
        ]);

        $this->postJson("/api/business-invoices/{$invoice->id}/paid", [
            'received_at' => today()->toDateString(),
        ])->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.income.amount', '125.00');
        $this->postJson("/api/business-invoices/{$invoice->id}/send-receipt")->assertOk();

        $this->assertDatabaseHas('incomes', [
            'user_id' => $user->id,
            'business_invoice_id' => $invoice->id,
            'amount' => 125,
        ]);
    }
}
