<?php

namespace App\Http\Controllers;

use App\Models\BusinessCard;
use App\Models\BusinessInvoice;
use App\Models\BusinessInvoiceTemplate;
use App\Services\BusinessInvoiceDocumentService;
use App\Services\BusinessInvoicePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->id;

        return view('business-invoices.index', [
            'invoices' => BusinessInvoice::query()->where('user_id', $userId)->with(['template', 'income'])->latest()->paginate(12),
            'templates' => BusinessInvoiceTemplate::query()->where('user_id', $userId)->orderByDesc('is_default')->orderBy('name')->get(),
            'businessCard' => BusinessCard::query()->where('user_id', $userId)->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'business_address' => ['nullable', 'string', 'max:2000'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:40'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'line_items' => ['required', 'array', 'min:1', 'max:50'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'template_id' => ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                if ($value && ! BusinessInvoiceTemplate::query()->where('user_id', $request->user()->id)->whereKey($value)->exists()) {
                    $fail('Select one of your own invoice templates.');
                }
            }],
        ]);

        $data['template_id'] = $data['template_id'] ?? BusinessInvoiceTemplate::query()
            ->where('user_id', $request->user()->id)
            ->where('is_default', true)
            ->value('id');

        $lineItems = array_map(static function (array $line): array {
            $quantity = (float) $line['quantity'];
            $unitPrice = (float) $line['unit_price'];

            return [
                'description' => trim($line['description']),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => round($quantity * $unitPrice, 2),
            ];
        }, $data['line_items']);

        $invoice = BusinessInvoice::query()->create([
            ...$data,
            'user_id' => $request->user()->id,
            'invoice_number' => 'BI-'.now()->format('Y').'-'.strtoupper(Str::random(8)),
            'line_items' => $lineItems,
            'currency' => strtoupper($data['currency']),
            'total' => round(array_sum(array_column($lineItems, 'amount')), 2),
            'status' => 'unpaid',
        ]);

        return redirect()->route('business-invoices.index')->with('success', 'Invoice '.$invoice->invoice_number.' created.');
    }

    public function send(Request $request, int $id, BusinessInvoiceDocumentService $documents): RedirectResponse
    {
        $invoice = $this->ownedInvoice($request, $id);
        $pdf = $documents->pdf($invoice);

        Mail::send([], [], function ($message) use ($invoice, $pdf): void {
            $message->to($invoice->customer_email, $invoice->customer_name)
                ->subject('Invoice '.$invoice->invoice_number)
                ->text('Please find attached invoice '.$invoice->invoice_number.' from '.$invoice->business_name.'.')
                ->attachData($pdf->output(), 'invoice-'.$invoice->invoice_number.'.pdf', ['mime' => 'application/pdf']);
        });

        $invoice->forceFill(['sent_at' => now()])->save();

        return back()->with('success', 'Invoice emailed to '.$invoice->customer_email.'.');
    }

    public function sendReceipt(Request $request, int $id, BusinessInvoiceDocumentService $documents): RedirectResponse
    {
        $invoice = $this->ownedInvoice($request, $id);
        abort_unless($invoice->status === 'paid', 422, 'Mark the invoice paid before sending a receipt.');
        $pdf = $documents->pdf($invoice, 'PAYMENT RECEIPT');

        Mail::send([], [], function ($message) use ($invoice, $pdf): void {
            $message->to($invoice->customer_email, $invoice->customer_name)
                ->subject('Receipt for invoice '.$invoice->invoice_number)
                ->text('Thank you for your payment. Please find the receipt for invoice '.$invoice->invoice_number.' attached.')
                ->attachData($pdf->output(), 'receipt-'.$invoice->invoice_number.'.pdf', ['mime' => 'application/pdf']);
        });

        return back()->with('success', 'Payment receipt emailed to '.$invoice->customer_email.'.');
    }

    public function download(Request $request, int $id, BusinessInvoiceDocumentService $documents): Response
    {
        $invoice = $this->ownedInvoice($request, $id);

        return $documents->pdf($invoice)->download('invoice-'.$invoice->invoice_number.'.pdf');
    }

    public function markPaid(Request $request, int $id, BusinessInvoicePaymentService $payments): RedirectResponse
    {
        $invoice = $this->ownedInvoice($request, $id);
        $data = $request->validate(['received_at' => ['required', 'date', 'before_or_equal:today']]);

        $payments->markPaid($request->user(), $invoice, $data['received_at']);

        return back()->with('success', 'Invoice marked paid and added to Finance → Income. You can now send the customer a receipt.');
    }

    public function uploadTemplate(Request $request, BusinessInvoiceDocumentService $documents): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'template_file' => ['required', 'file', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        $file = $request->file('template_file');
        abort_unless(in_array(strtolower($file->getClientOriginalExtension()), ['html', 'htm'], true), 422, 'Upload an .html or .htm invoice template.');
        $content = $file->get();
        if ($error = $documents->validateTemplate($content)) {
            return back()->withErrors(['template_file' => $error])->withInput();
        }

        $path = $file->storeAs('business-invoice-templates/'.$request->user()->id, Str::uuid().'.html', 'local');

        DB::transaction(function () use ($request, $data, $path): void {
            if ($request->boolean('is_default')) {
                BusinessInvoiceTemplate::query()->where('user_id', $request->user()->id)->update(['is_default' => false]);
            }

            BusinessInvoiceTemplate::query()->create([
                'user_id' => $request->user()->id,
                'name' => $data['name'],
                'storage_path' => $path,
                'is_default' => $request->boolean('is_default'),
            ]);
        });

        return back()->with('success', 'Invoice template uploaded.');
    }

    public function setDefaultTemplate(Request $request, int $id): RedirectResponse
    {
        $template = $this->ownedTemplate($request, $id);

        DB::transaction(function () use ($template, $request): void {
            BusinessInvoiceTemplate::query()->where('user_id', $request->user()->id)->update(['is_default' => false]);
            $template->update(['is_default' => true]);
        });

        return back()->with('success', 'Default invoice template updated.');
    }

    public function deleteTemplate(Request $request, int $id): RedirectResponse
    {
        $template = $this->ownedTemplate($request, $id);
        Storage::disk('local')->delete($template->storage_path);
        $template->delete();

        return back()->with('success', 'Invoice template deleted. Invoices using it will use the standard layout.');
    }

    public function sampleTemplate(BusinessInvoiceDocumentService $documents): StreamedResponse
    {
        return response()->streamDownload(
            static fn () => print $documents->sampleTemplate(),
            'business-invoice-template.html',
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    private function ownedInvoice(Request $request, int $id): BusinessInvoice
    {
        return BusinessInvoice::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function ownedTemplate(Request $request, int $id): BusinessInvoiceTemplate
    {
        return BusinessInvoiceTemplate::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }
}
