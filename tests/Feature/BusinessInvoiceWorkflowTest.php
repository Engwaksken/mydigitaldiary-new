<?php

namespace Tests\Feature;

use App\Mail\BusinessInvoiceMail;
use App\Models\BusinessInvoice;
use App\Models\BusinessCard;
use App\Models\BusinessInvoiceTemplate;
use App\Models\Income;
use App\Models\User;
use App\Services\BusinessInvoiceDocumentService;
use App\Services\BusinessInvoiceNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
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
            ->assertSee('invoice-modal-form', false)
            ->assertSee('pm-dialog-lg', false)
            ->assertSee('class="pm-modal-body" data-modal-scroll', false)
            ->assertSee('data-line-amount', false)
            ->assertSee('Choose a template (optional)')
            ->assertSee('w-full max-w-full min-w-0 space-y-6', false)
            ->assertSee('aria-label="Download invoice PDF', false)
            ->assertSee('aria-label="Email invoice', false)
            ->assertSee('data-edit-invoice', false)
            ->assertSee('Total invoices')
            ->assertSee('Delete selected')
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

    public function test_user_can_bulk_delete_unpaid_invoices_but_cannot_delete_paid_or_foreign_invoices(): void
    {
        $user = User::factory()->create(['subscription_status' => 'active']);
        $other = User::factory()->create(['subscription_status' => 'active']);
        $unpaid = $this->invoiceFor($user);
        $paid = $this->invoiceFor($user, ['status' => 'paid']);
        $foreign = $this->invoiceFor($other);

        $this->actingAs($user)->post(route('business-invoices.bulk-delete'), [
            'invoice_ids' => [$unpaid->id],
        ])->assertRedirect(route('business-invoices.index'));
        $this->assertDatabaseMissing('business_invoices', ['id' => $unpaid->id]);

        $this->post(route('business-invoices.bulk-delete'), [
            'invoice_ids' => [$paid->id],
        ])->assertStatus(422);
        $this->post(route('business-invoices.bulk-delete'), [
            'invoice_ids' => [$foreign->id],
        ])->assertNotFound();
        $this->assertDatabaseHas('business_invoices', ['id' => $paid->id]);
        $this->assertDatabaseHas('business_invoices', ['id' => $foreign->id]);
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

    public function test_html_template_can_render_the_business_logo_and_watermark_placeholders(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $user = User::factory()->create(['subscription_status' => 'active']);
        $logoPath = 'business-card-logos/'.$user->id.'/logo.png';
        Storage::disk('public')->put($logoPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j4XcAAAAASUVORK5CYII='));
        BusinessCard::query()->create([
            'user_id' => $user->id,
            'slug' => 'logo-test-'.$user->id,
            'logo_path' => $logoPath,
            'name' => 'Logo Test',
        ]);
        $html = '<html><body>{{business_logo_watermark}}<header>{{business_logo}}</header><p>{{invoice_number}}</p>{{line_items_table}}</body></html>';

        $this->actingAs($user)->from(route('business-invoices.index'))->post(route('business-invoices.templates.store'), [
            'name' => 'Branded template',
            'template_file' => UploadedFile::fake()->createWithContent('invoice.html', $html),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $invoice = $this->invoiceFor($user, [
            'template_id' => BusinessInvoiceTemplate::query()->where('user_id', $user->id)->value('id'),
        ]);
        $pdf = app(BusinessInvoiceDocumentService::class)->output($invoice);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_invoice_details_are_overlaid_on_uploaded_pdf_template_pages(): void
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
        $pdfBytes = $response->streamedContent();
        $this->assertSame(1, $reader->setSourceFile(StreamReader::createByString($pdfBytes)));
        $text = (new \Smalot\PdfParser\Parser)->parseContent($pdfBytes)->getText();
        $this->assertStringContainsString('Company stationery', $text);
        $this->assertStringContainsString('Brand design', $text);
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
        $this->assertStringContainsString('{{business_logo}}', app(BusinessInvoiceDocumentService::class)->sampleTemplate());
        $this->assertStringContainsString('{{business_logo_watermark}}', app(BusinessInvoiceDocumentService::class)->sampleTemplate());
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

    public function test_invoice_line_items_render_with_aligned_numeric_columns_and_totals(): void
    {
        $user = User::factory()->create(['subscription_status' => 'active']);
        $invoice = $this->invoiceFor($user, [
            'line_items' => [
                ['description' => 'Brand design', 'quantity' => 2, 'unit_price' => 150, 'amount' => 300],
                ['description' => 'Printing', 'quantity' => 1.5, 'unit_price' => 1000, 'amount' => 1500],
            ],
            'total' => 1800,
        ]);

        $html = view('business-invoices.partials.line-items-table', ['invoice' => $invoice, 'showTotals' => true])->render();

        $this->assertStringContainsString('table-layout:fixed', $html);
        $this->assertStringContainsString('Amount (USD)', $html);
        $this->assertStringContainsString('text-align:right;white-space:nowrap;">1.50</td>', $html);
        $this->assertStringContainsString('text-align:right;white-space:nowrap;">2.00</td>', $html);
        $this->assertStringContainsString('1,000.00', $html);
        $this->assertStringContainsString('Total (USD)', $html);
        $this->assertStringContainsString('1,800.00', $html);
    }

    public function test_emailed_invoice_is_branded_with_the_business_name(): void
    {
        Mail::fake();
        config(['mail.from.address' => 'noreply@diary.test']);
        $user = User::factory()->create(['subscription_status' => 'active', 'name' => 'Owner Person']);

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($user)->post(route('business-invoices.store'), $this->invoicePayload(['business_name' => 'Acme Traders']))
                ->assertRedirect(route('business-invoices.index'));
        }
        $invoice = BusinessInvoice::query()->where('user_id', $user->id)->where('invoice_number', '003')->firstOrFail();

        $this->post(route('business-invoices.send', $invoice->id))->assertRedirect();

        Mail::assertSent(BusinessInvoiceMail::class, function (BusinessInvoiceMail $mail) use ($invoice): bool {
            $mail->assertHasSubject('Invoice 003 from Acme Traders');
            $mail->assertFrom('noreply@diary.test', 'Acme Traders');
            $mail->assertTo('customer@example.test', 'Customer One');
            $mail->assertSeeInText('Hello Customer One');
            $mail->assertSeeInText('invoice 003 from Acme Traders');
            $mail->assertSeeInText('Kind regards,');
            $mail->assertDontSeeInText('My Digital Diary');
            $mail->assertDontSeeInText((string) config('app.name'));
            $mail->assertHasAttachedData($mail->pdf, 'invoice-003.pdf', ['mime' => 'application/pdf']);

            return $mail->invoice->is($invoice);
        });
    }

    public function test_receipt_email_is_branded_with_the_business_name(): void
    {
        Mail::fake();
        $user = User::factory()->create(['subscription_status' => 'active']);
        $invoice = $this->invoiceFor($user, ['invoice_number' => '007', 'business_name' => 'Acme Traders', 'status' => 'paid']);

        $this->actingAs($user)->post(route('business-invoices.send-receipt', $invoice->id))->assertRedirect();

        Mail::assertSent(BusinessInvoiceMail::class, fn (BusinessInvoiceMail $mail): bool => $mail->hasSubject('Receipt for invoice 007 from Acme Traders')
            && $mail->hasFrom(config('mail.from.address'), 'Acme Traders'));
    }

    public function test_email_subject_falls_back_to_owner_name_then_app_name(): void
    {
        config(['app.name' => 'Diary App']);
        $owner = User::factory()->create(['name' => 'Jane Owner']);
        $invoice = $this->invoiceFor($owner, ['invoice_number' => '004', 'business_name' => '  ']);

        $mail = new BusinessInvoiceMail($invoice->fresh(), '%PDF-');
        $mail->assertHasSubject('Invoice 004 from Jane Owner');
        $mail->assertFrom(config('mail.from.address'), 'Jane Owner');
        $mail->assertSeeInText('Jane Owner');

        $owner->forceFill(['name' => ''])->save();
        $mail = new BusinessInvoiceMail($invoice->fresh(), '%PDF-');
        $mail->assertHasSubject('Invoice 004 from Diary App');
    }

    public function test_invoice_numbers_start_at_001_and_increment_per_business(): void
    {
        $first = User::factory()->create(['subscription_status' => 'active']);
        $second = User::factory()->create(['subscription_status' => 'active']);

        $this->actingAs($first)->post(route('business-invoices.store'), $this->invoicePayload())
            ->assertSessionHas('success', 'Invoice 001 created.');
        $this->actingAs($first)->post(route('business-invoices.store'), $this->invoicePayload());
        $this->actingAs($second)->post(route('business-invoices.store'), $this->invoicePayload());
        $this->actingAs($first)->post(route('business-invoices.store'), $this->invoicePayload());
        $this->actingAs($second)->post(route('business-invoices.store'), $this->invoicePayload());

        $this->assertSame(['001', '002', '003'], BusinessInvoice::query()->where('user_id', $first->id)->orderBy('id')->pluck('invoice_number')->all());
        $this->assertSame(['001', '002'], BusinessInvoice::query()->where('user_id', $second->id)->orderBy('id')->pluck('invoice_number')->all());

        $this->actingAs($first)->get(route('business-invoices.index'))->assertOk()->assertSee('<strong>003</strong>', false);
        $invoice = BusinessInvoice::query()->where('user_id', $first->id)->where('invoice_number', '003')->firstOrFail();
        $text = (new \Smalot\PdfParser\Parser)->parseContent(app(BusinessInvoiceDocumentService::class)->output($invoice))->getText();
        $this->assertStringContainsString('Invoice 003', $text);
    }

    public function test_numbering_continues_after_existing_invoices_without_rewriting_them(): void
    {
        $user = User::factory()->create(['subscription_status' => 'active']);
        $legacyRandom = $this->invoiceFor($user, ['invoice_number' => 'BI-2026-K3JD8X92']);
        $legacySequential = $this->invoiceFor($user, ['invoice_number' => 'INV-2026-0007']);
        $this->invoiceFor($user, ['invoice_number' => 'Custom ref']);
        $this->invoiceFor(User::factory()->create(), ['invoice_number' => '050']);

        $this->actingAs($user)->post(route('business-invoices.store'), $this->invoicePayload())
            ->assertSessionHas('success', 'Invoice 008 created.');

        $this->assertSame('BI-2026-K3JD8X92', $legacyRandom->fresh()->invoice_number);
        $this->assertSame('INV-2026-0007', $legacySequential->fresh()->invoice_number);
    }

    public function test_deleted_invoice_numbers_are_never_reused(): void
    {
        $user = User::factory()->create();
        $numbers = app(BusinessInvoiceNumberService::class);

        $numbers->createInvoice($user->id, $this->invoiceAttributes());
        $numbers->createInvoice($user->id, $this->invoiceAttributes())->delete();

        $this->assertSame('003', $numbers->peekNext($user->id));
        $this->assertSame('003', $numbers->createInvoice($user->id, $this->invoiceAttributes())->invoice_number);
    }

    public function test_invoice_numbers_grow_past_999_without_truncation(): void
    {
        $user = User::factory()->create();
        $this->invoiceFor($user, ['invoice_number' => '999']);
        $numbers = app(BusinessInvoiceNumberService::class);

        $this->assertSame('1000', $numbers->peekNext($user->id));
        $this->assertSame('1000', $numbers->createInvoice($user->id, $this->invoiceAttributes())->invoice_number);
        $this->assertSame('1001', $numbers->createInvoice($user->id, $this->invoiceAttributes())->invoice_number);
        $this->assertSame('001', $numbers->peekNext(User::factory()->create()->id));
        $this->assertNull($numbers->parse('BI-2026-0A1B2C3D'));
        $this->assertNull($numbers->parse('No digits'));
        $this->assertSame(12, $numbers->parse('INV-2026-0012'));
    }

    private function invoicePayload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Studio North',
            'customer_name' => 'Customer One',
            'customer_email' => 'customer@example.test',
            'line_items' => [['description' => 'Brand design', 'quantity' => 2, 'unit_price' => 150]],
            'currency' => 'usd',
            'issue_date' => today()->toDateString(),
        ], $overrides);
    }

    private function invoiceAttributes(): array
    {
        return [
            'business_name' => 'Studio North',
            'customer_name' => 'Customer One',
            'customer_email' => 'customer@example.test',
            'line_items' => [['description' => 'Brand design', 'quantity' => 1, 'unit_price' => 10, 'amount' => 10]],
            'currency' => 'USD',
            'total' => 10,
            'issue_date' => today(),
            'status' => 'unpaid',
        ];
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
