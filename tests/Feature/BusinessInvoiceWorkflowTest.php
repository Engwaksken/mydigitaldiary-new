<?php

namespace Tests\Feature;

use App\Models\BusinessInvoice;
use App\Models\BusinessInvoiceTemplate;
use App\Models\Income;
use App\Models\User;
use App\Services\BusinessInvoiceDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\TestCase;

class BusinessInvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_invoice_and_view_business_invoice_page(): void
    {
        $user = User::factory()->create(['subscription_status' => 'active']);

        $this->actingAs($user)->post(route('business-invoices.store'), [
            'business_name' => 'Studio North',
            'business_email' => 'studio@example.test',
            'customer_name' => 'Customer One',
            'customer_email' => 'customer@example.test',
            'line_items' => [
                ['description' => 'Brand design', 'quantity' => 2, 'unit_price' => 150],
            ],
            'currency' => 'usd',
            'issue_date' => today()->toDateString(),
            'due_date' => today()->addDays(14)->toDateString(),
        ])->assertRedirect(route('business-invoices.index'));

        $invoice = BusinessInvoice::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('USD', $invoice->currency);
        $this->assertSame('300.00', $invoice->total);
        $this->assertSame(300, $invoice->line_items[0]['amount']);

        $this->actingAs($user)->get(route('business-invoices.index'))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('Business invoices')
            ->assertSee('Finance')
            ->assertSee('invoice-modal')
            ->assertSee('data-edit-invoice', false)
            ->assertSee('fa-arrow-right', false);
    }

    public function test_user_can_edit_and_delete_an_unpaid_invoice(): void
    {
        $user = User::factory()->create(['subscription_status' => 'active']);
        $invoice = $this->invoiceFor($user);

        $this->actingAs($user)->put(route('business-invoices.update', $invoice->id), [
            'business_name' => 'Studio North Ltd',
            'customer_name' => 'Customer Two',
            'customer_email' => 'two@example.test',
            'line_items' => [
                ['description' => 'Design', 'quantity' => 3, 'unit_price' => 200],
            ],
            'currency' => 'usd',
            'issue_date' => today()->toDateString(),
            'due_date' => today()->addDays(10)->toDateString(),
        ])->assertRedirect(route('business-invoices.index'));

        $this->assertDatabaseHas('business_invoices', [
            'id' => $invoice->id,
            'business_name' => 'Studio North Ltd',
            'customer_name' => 'Customer Two',
            'total' => 600,
        ]);

        $this->delete(route('business-invoices.destroy', $invoice->id))
            ->assertRedirect(route('business-invoices.index'));
        $this->assertDatabaseMissing('business_invoices', ['id' => $invoice->id]);
    }

    public function test_marking_invoice_paid_creates_one_linked_finance_income_and_receipt_can_be_emailed(): void
    {
        \Illuminate\Support\Facades\Mail::shouldReceive('send')->once();
        $user = User::factory()->create(['subscription_status' => 'active']);
        $invoice = $this->invoiceFor($user);

        $this->actingAs($user)->post(route('business-invoices.paid', $invoice->id), [
            'received_at' => today()->toDateString(),
        ])->assertRedirect();
        $this->post(route('business-invoices.paid', $invoice->id), [
            'received_at' => today()->toDateString(),
        ])->assertRedirect();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(1, Income::query()->where('business_invoice_id', $invoice->id)->count());
        $this->assertDatabaseHas('incomes', [
            'user_id' => $user->id,
            'business_invoice_id' => $invoice->id,
            'amount' => 300,
            'category' => 'Business income',
        ]);

        $this->post(route('business-invoices.send-receipt', $invoice->id))->assertRedirect();
    }

    public function test_user_can_upload_default_html_template_and_use_it_on_an_invoice(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['subscription_status' => 'active']);
        $html = '<html><body><h1>{{business_name}}</h1><p>{{invoice_number}}</p>{{line_items_table}}<p>{{total}}</p></body></html>';

        $this->actingAs($user)->from(route('business-invoices.index'))->post(route('business-invoices.templates.store'), [
            'name' => 'My clean template',
            'template_file' => UploadedFile::fake()->createWithContent('invoice.html', $html),
            'is_default' => 1,
        ])->assertRedirect();

        $template = BusinessInvoiceTemplate::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertTrue($template->is_default);
        Storage::disk('local')->assertExists($template->storage_path);

        $invoice = $this->invoiceFor($user, ['template_id' => $template->id]);
        $this->actingAs($user)->get(route('business-invoices.download', $invoice->id))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_user_can_upload_a_pdf_stationery_template_used_before_invoice_pages(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['subscription_status' => 'active']);
        $templateBytes = \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<h1>Company stationery</h1>')->output();

        $this->actingAs($user)->from(route('business-invoices.index'))->post(route('business-invoices.templates.store'), [
            'name' => 'PDF stationery',
            'template_file' => UploadedFile::fake()->createWithContent('stationery.pdf', $templateBytes),
            'is_default' => 1,
        ])->assertRedirect(route('business-invoices.index'))->assertSessionHasNoErrors();

        $template = BusinessInvoiceTemplate::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertStringEndsWith('.pdf', $template->storage_path);
        Storage::disk('local')->assertExists($template->storage_path);

        $invoice = $this->invoiceFor($user, ['template_id' => $template->id]);
        $response = $this->actingAs($user)->get(route('business-invoices.download', $invoice->id));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $reader = new Fpdi;
        $this->assertSame(2, $reader->setSourceFile(StreamReader::createByString($response->streamedContent())));
    }

    public function test_paid_invoice_cannot_be_edited_or_deleted(): void
    {
        $user = User::factory()->create(['subscription_status' => 'active']);
        $invoice = $this->invoiceFor($user, ['status' => 'paid']);

        $this->actingAs($user)->put(route('business-invoices.update', $invoice->id), [])->assertStatus(422);
        $this->delete(route('business-invoices.destroy', $invoice->id))->assertStatus(422);
        $this->assertDatabaseHas('business_invoices', ['id' => $invoice->id, 'status' => 'paid']);
    }

    public function test_user_can_download_the_starter_invoice_template(): void
    {
        $user = User::factory()->create(['subscription_status' => 'active']);

        $this->actingAs($user)->get(route('business-invoices.templates.sample'))
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=business-invoice-template.html');

        $this->assertStringContainsString('{{invoice_number}}', app(BusinessInvoiceDocumentService::class)->sampleTemplate());
        $this->assertStringContainsString('{{line_items_table}}', app(BusinessInvoiceDocumentService::class)->sampleTemplate());
    }

    public function test_template_rejects_scripts_and_another_users_cannot_mark_an_invoice_paid(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['subscription_status' => 'active']);
        $other = User::factory()->create(['subscription_status' => 'active']);
        $invoice = $this->invoiceFor($owner);

        $this->actingAs($owner)->from(route('business-invoices.index'))->post(route('business-invoices.templates.store'), [
            'name' => 'Unsafe template',
            'template_file' => UploadedFile::fake()->createWithContent('unsafe.html', '<script>alert(1)</script>{{invoice_number}}{{line_items_table}}'),
        ])->assertRedirect(route('business-invoices.index'))->assertSessionHasErrors('template_file');

        $this->actingAs($other)->post(route('business-invoices.paid', $invoice->id), [
            'received_at' => today()->toDateString(),
        ])->assertNotFound();

        $this->assertSame('unpaid', $invoice->fresh()->status);
        $this->assertDatabaseMissing('incomes', ['business_invoice_id' => $invoice->id]);
    }

    private function invoiceFor(User $user, array $overrides = []): BusinessInvoice
    {
        return BusinessInvoice::query()->create(array_merge([
            'user_id' => $user->id,
            'invoice_number' => 'BI-2026-'.strtoupper(bin2hex(random_bytes(4))),
            'business_name' => 'Studio North',
            'business_email' => 'studio@example.test',
            'customer_name' => 'Customer One',
            'customer_email' => 'customer@example.test',
            'line_items' => [['description' => 'Brand design', 'quantity' => 2, 'unit_price' => 150, 'amount' => 300]],
            'currency' => 'USD',
            'total' => 300,
            'issue_date' => today(),
            'status' => 'unpaid',
        ], $overrides));
    }
}
