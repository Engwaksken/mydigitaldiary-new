<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessInvoice;
use App\Models\BusinessInvoiceTemplate;
use App\Services\BusinessInvoiceDocumentService;
use App\Services\BusinessInvoicePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BusinessInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $invoices = BusinessInvoice::query()->where('user_id', $request->user()->id);

        return response()->json([
            'data' => (clone $invoices)->latest()->limit(100)->get(),
            'stats' => [
                'total' => (clone $invoices)->count(),
                'unpaid' => (clone $invoices)->where('status', 'unpaid')->count(),
                'paid' => (clone $invoices)->where('status', 'paid')->count(),
                'overdue' => (clone $invoices)->where('status', 'unpaid')->whereNotNull('due_date')->whereDate('due_date', '<', today())->count(),
            ],
        ]);
    }

    public function templates(Request $request): JsonResponse
    {
        return response()->json(['data' => BusinessInvoiceTemplate::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')->orderBy('name')
            ->get(['id', 'name', 'storage_path', 'is_default'])
            ->map(fn (BusinessInvoiceTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'format' => strtolower(pathinfo($template->storage_path, PATHINFO_EXTENSION)),
                'is_default' => $template->is_default,
            ])]);
    }

    public function uploadTemplate(Request $request, BusinessInvoiceDocumentService $documents): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'template_file' => ['required', 'file', 'mimes:html,htm,pdf', 'max:10240'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        $file = $request->file('template_file');
        $extension = strtolower($file->getClientOriginalExtension());
        abort_unless(in_array($extension, ['html', 'htm', 'pdf'], true), 422, 'Upload an HTML or PDF invoice template.');
        $content = $file->get();
        $error = $extension === 'pdf'
            ? $documents->validatePdfTemplate($content)
            : $documents->validateTemplate($content);
        if ($error) {
            return response()->json(['message' => $error, 'errors' => ['template_file' => [$error]]], 422);
        }

        $path = $file->storeAs('business-invoice-templates/'.$request->user()->id, Str::uuid().'.'.$extension, 'local');
        $template = DB::transaction(function () use ($request, $data, $path): BusinessInvoiceTemplate {
            if ($request->boolean('is_default')) {
                BusinessInvoiceTemplate::query()->where('user_id', $request->user()->id)->update(['is_default' => false]);
            }

            return BusinessInvoiceTemplate::query()->create([
                'user_id' => $request->user()->id,
                'name' => $data['name'],
                'storage_path' => $path,
                'is_default' => $request->boolean('is_default'),
            ]);
        });

        return response()->json(['message' => 'Invoice template uploaded.', 'data' => [
            'id' => $template->id,
            'name' => $template->name,
            'format' => $extension,
            'is_default' => $template->is_default,
        ]], 201);
    }

    public function preview(Request $request, BusinessInvoiceDocumentService $documents)
    {
        $data = $this->validatedInvoiceData($request);
        $data['template_id'] ??= BusinessInvoiceTemplate::query()
            ->where('user_id', $request->user()->id)->where('is_default', true)->value('id');
        $invoice = new BusinessInvoice([
            ...$data,
            'user_id' => $request->user()->id,
            'invoice_number' => 'PREVIEW-'.now()->format('Y'),
            'status' => 'unpaid',
        ]);
        $invoice->load('template');

        return response($documents->output($invoice))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="invoice-preview.pdf"');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedInvoiceData($request);

        $data['template_id'] = $data['template_id'] ?? BusinessInvoiceTemplate::query()
            ->where('user_id', $request->user()->id)
            ->where('is_default', true)
            ->value('id');
        $data['user_id'] = $request->user()->id;
        $data['invoice_number'] = 'BI-'.now()->format('Y').'-'.strtoupper(bin2hex(random_bytes(4)));
        $data['status'] = 'unpaid';

        $invoice = BusinessInvoice::create($data);
        return response()->json(['data' => $invoice], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $invoice = $this->owned($request, $id);
        abort_unless($invoice->status !== 'paid', 422, 'Paid invoices cannot be edited.');
        $invoice->update($this->validatedInvoiceData($request));

        return response()->json(['message' => 'Invoice updated.', 'data' => $invoice->fresh()]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_ids' => ['required', 'array', 'min:1', 'max:100'],
            'invoice_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $invoices = BusinessInvoice::query()->where('user_id', $request->user()->id)
            ->whereIn('id', $data['invoice_ids'])->with('income')->get();
        abort_if($invoices->count() !== count($data['invoice_ids']), 404);
        abort_if($invoices->contains(fn (BusinessInvoice $invoice): bool => $invoice->status === 'paid' || $invoice->income !== null), 422,
            'Paid invoices linked to Finance income cannot be deleted.');

        DB::transaction(static fn () => $invoices->each->delete());

        return response()->json(['message' => $invoices->count().' invoices deleted.', 'deleted' => $invoices->count()]);
    }

    private function validatedInvoiceData(Request $request): array
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
            'currency' => ['nullable', 'string', 'size:3'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'template_id' => ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                if ($value && ! BusinessInvoiceTemplate::query()->where('user_id', $request->user()->id)->whereKey($value)->exists()) {
                    $fail('Select one of your own invoice templates.');
                }
            }],
        ]);

        $data['line_items'] = array_map(function (array $line): array {
            $quantity = (float) $line['quantity'];
            $price = (float) $line['unit_price'];
            return ['description' => trim($line['description']), 'quantity' => $quantity,
                'unit_price' => $price, 'amount' => round($quantity * $price, 2)];
        }, $data['line_items']);
        $data['total'] = round(array_sum(array_column($data['line_items'], 'amount')), 2);
        $data['currency'] = strtoupper($data['currency'] ?? 'UGX');

        return $data;
    }

    public function send(Request $request, int $id, BusinessInvoiceDocumentService $documents): JsonResponse
    {
        $invoice = $this->owned($request, $id);
        $pdf = $documents->output($invoice);
        Mail::send([], [], function ($message) use ($invoice, $pdf): void {
            $message->to($invoice->customer_email, $invoice->customer_name)
                ->subject('Invoice '.$invoice->invoice_number)
                ->text('Please find attached invoice '.$invoice->invoice_number.' from '.$invoice->business_name.'.')
                ->attachData($pdf, 'invoice-'.$invoice->invoice_number.'.pdf', ['mime' => 'application/pdf']);
        });
        $invoice->forceFill(['sent_at' => now()])->save();
        return response()->json(['message' => 'Invoice sent.', 'data' => $invoice->fresh()]);
    }

    public function download(Request $request, int $id, BusinessInvoiceDocumentService $documents)
    {
        $invoice = $this->owned($request, $id);
        return response()->streamDownload(
            static fn () => print $documents->output($invoice),
            'invoice-'.$invoice->invoice_number.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function markPaid(Request $request, int $id, BusinessInvoicePaymentService $payments): JsonResponse
    {
        $invoice = $this->owned($request, $id);
        $data = $request->validate(['received_at' => ['required', 'date', 'before_or_equal:today']]);
        $payments->markPaid($request->user(), $invoice, $data['received_at']);

        return response()->json(['message' => 'Invoice marked paid and added to Finance income.', 'data' => $invoice->fresh()->load('income')]);
    }

    public function sendReceipt(Request $request, int $id, BusinessInvoiceDocumentService $documents): JsonResponse
    {
        $invoice = $this->owned($request, $id);
        abort_unless($invoice->status === 'paid', 422, 'Mark the invoice paid before sending a receipt.');
        $pdf = $documents->output($invoice, 'PAYMENT RECEIPT');

        Mail::send([], [], function ($message) use ($invoice, $pdf): void {
            $message->to($invoice->customer_email, $invoice->customer_name)
                ->subject('Receipt for invoice '.$invoice->invoice_number)
                ->text('Thank you for your payment. Please find the receipt for invoice '.$invoice->invoice_number.' attached.')
                ->attachData($pdf, 'receipt-'.$invoice->invoice_number.'.pdf', ['mime' => 'application/pdf']);
        });

        return response()->json(['message' => 'Payment receipt sent.', 'data' => $invoice]);
    }

    private function owned(Request $request, int $id): BusinessInvoice
    {
        return BusinessInvoice::where('user_id', $request->user()->id)->findOrFail($id);
    }
}
