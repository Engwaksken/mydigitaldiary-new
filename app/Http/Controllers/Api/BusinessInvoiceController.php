<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class BusinessInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => BusinessInvoice::where('user_id', $request->user()->id)
            ->latest()->limit(100)->get()]);
    }

    public function store(Request $request): JsonResponse
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
        ]);

        $data['line_items'] = array_map(function (array $line): array {
            $quantity = (float) $line['quantity'];
            $price = (float) $line['unit_price'];
            return ['description' => trim($line['description']), 'quantity' => $quantity,
                'unit_price' => $price, 'amount' => round($quantity * $price, 2)];
        }, $data['line_items']);
        $data['total'] = round(array_sum(array_column($data['line_items'], 'amount')), 2);
        $data['currency'] = strtoupper($data['currency'] ?? 'UGX');
        $data['user_id'] = $request->user()->id;
        $data['invoice_number'] = 'BI-'.now()->format('Y').'-'.strtoupper(bin2hex(random_bytes(4)));
        $data['status'] = 'unpaid';

        $invoice = BusinessInvoice::create($data);
        return response()->json(['data' => $invoice], 201);
    }

    public function send(Request $request, int $id): JsonResponse
    {
        $invoice = $this->owned($request, $id);
        $pdf = Pdf::loadView('business-invoices.pdf', ['invoice' => $invoice])->setPaper('a4');
        Mail::send([], [], function ($message) use ($invoice, $pdf): void {
            $message->to($invoice->customer_email, $invoice->customer_name)
                ->subject('Invoice '.$invoice->invoice_number)
                ->text('Please find attached invoice '.$invoice->invoice_number.' from '.$invoice->business_name.'.')
                ->attachData($pdf->output(), 'invoice-'.$invoice->invoice_number.'.pdf', ['mime' => 'application/pdf']);
        });
        $invoice->forceFill(['sent_at' => now()])->save();
        return response()->json(['message' => 'Invoice sent.', 'data' => $invoice->fresh()]);
    }

    public function download(Request $request, int $id)
    {
        $invoice = $this->owned($request, $id);
        return Pdf::loadView('business-invoices.pdf', ['invoice' => $invoice])
            ->setPaper('a4')->download('invoice-'.$invoice->invoice_number.'.pdf');
    }

    private function owned(Request $request, int $id): BusinessInvoice
    {
        return BusinessInvoice::where('user_id', $request->user()->id)->findOrFail($id);
    }
}
