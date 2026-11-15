<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Site-wide view across every quotation, invoice, and receipt - not
 * scoped to one user or one Enterprise inquiry the way the Subscription
 * page and the Enterprise Inquiries "Documents" modal are. Built
 * specifically so this stays manageable as the numbers grow: search,
 * status filter, period filter, and real pagination on both tabs,
 * rather than one long unpaginated list.
 */
class AdminInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        // ---- Quotations & Invoices tab ----
        $invoiceSearch = $request->query('invoice_q');
        $invoiceStatus = $request->query('invoice_status');
        $invoicePeriod = $request->query('invoice_period');
        $invoiceFrom = $request->query('invoice_from');
        $invoiceTo = $request->query('invoice_to');
        $invoicePerPage = (int) $request->query('invoice_per_page', 25);
        $invoicePerPage = in_array($invoicePerPage, [10, 25, 50, 100], true) ? $invoicePerPage : 25;

        $invoiceQuery = Invoice::with(['user', 'enterpriseInquiry', 'plan', 'payment.latestTransaction'])
            ->when($invoiceSearch, fn ($q) => $q->where(function ($sub) use ($invoiceSearch) {
                $sub->where('invoice_number', 'like', "%{$invoiceSearch}%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$invoiceSearch}%"))
                    ->orWhereHas('enterpriseInquiry', fn ($e) => $e->where('email', 'like', "%{$invoiceSearch}%")->orWhere('phone', 'like', "%{$invoiceSearch}%"))
                    ->orWhereHas('payment.latestTransaction', fn ($tx) => $tx->where('phone_number', 'like', "%{$invoiceSearch}%"));
            }))
            ->when($invoiceStatus, fn ($q) => $q->where('status', $invoiceStatus));

        $this->applyPeriod($invoiceQuery, $invoicePeriod, $invoiceFrom, $invoiceTo);

        $invoices = $invoiceQuery->orderByDesc('id')->paginate($invoicePerPage, ['*'], 'invoice_page')->withQueryString();

        $invoiceStats = [
            'quotations' => Invoice::where('status', 'quote')->count(),
            'unpaid' => Invoice::where('status', 'unpaid')->count(),
            'paid' => Invoice::where('status', 'paid')->count(),
            'cancelled' => Invoice::where('status', 'cancelled')->count(),
        ];

        // ---- Receipts tab (a receipt = a completed Payment) ----
        $receiptSearch = $request->query('receipt_q');
        $receiptPeriod = $request->query('receipt_period');
        $receiptFrom = $request->query('receipt_from');
        $receiptTo = $request->query('receipt_to');
        $receiptPerPage = (int) $request->query('receipt_per_page', 25);
        $receiptPerPage = in_array($receiptPerPage, [10, 25, 50, 100], true) ? $receiptPerPage : 25;

        $receiptQuery = Payment::where('status', 'completed')
            ->with(['user', 'plan', 'latestTransaction'])
            ->when($receiptSearch, fn ($q) => $q->where(function ($sub) use ($receiptSearch) {
                $sub->where('receipt_number', 'like', "%{$receiptSearch}%")
                    ->orWhere('reference', 'like', "%{$receiptSearch}%")
                    ->when(
                        \Illuminate\Support\Facades\Schema::hasColumn('payments', 'gateway_transaction_id'),
                        fn ($query) => $query->orWhere('gateway_transaction_id', 'like', "%{$receiptSearch}%")
                    )
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$receiptSearch}%"))
                    ->orWhereHas('latestTransaction', fn ($tx) => $tx->where('phone_number', 'like', "%{$receiptSearch}%"));
            }));

        $this->applyPeriod($receiptQuery, $receiptPeriod, $receiptFrom, $receiptTo);

        $receipts = $receiptQuery->orderByDesc('id')->paginate($receiptPerPage, ['*'], 'receipt_page')->withQueryString();

        return view('admin.invoices.index', [
            'invoices' => $invoices,
            'invoiceStats' => $invoiceStats,
            'invoiceSearch' => $invoiceSearch,
            'invoiceStatus' => $invoiceStatus,
            'invoicePeriod' => $invoicePeriod,
            'invoiceFrom' => $invoiceFrom,
            'invoiceTo' => $invoiceTo,
            'invoicePerPage' => $invoicePerPage,
            'receipts' => $receipts,
            'receiptSearch' => $receiptSearch,
            'receiptPeriod' => $receiptPeriod,
            'receiptFrom' => $receiptFrom,
            'receiptTo' => $receiptTo,
            'receiptPerPage' => $receiptPerPage,
        ]);
    }

    /**
     * Only a quotation or unpaid invoice can be deleted - a 'paid'
     * record has to stay for audit/accounting purposes regardless of
     * how large the list gets. See Invoice::isDeletable().
     */
    public function destroy(Invoice $invoice): RedirectResponse
    {
        if (! $invoice->isDeletable()) {
            return back()->withErrors(['invoice' => 'Only quotations and unpaid invoices can be deleted - this one is ' . $invoice->status . '.']);
        }

        $invoice->delete();

        return back()->with('success', 'Deleted.');
    }

    /**
     * Multi-select bulk delete for the Quotations & Invoices tab -
     * same isDeletable() safeguard as the single-item destroy() above,
     * applied per-row rather than all-or-nothing: a paid invoice
     * accidentally included in the selection is silently skipped
     * (and counted) rather than blocking deletion of the rest, or
     * being deleted itself.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_ids' => ['required', 'array'],
            'invoice_ids.*' => ['integer'],
        ]);

        $invoices = Invoice::whereIn('id', $data['invoice_ids'])->get();
        $deletedCount = 0;
        $skippedCount = 0;

        foreach ($invoices as $invoice) {
            if ($invoice->isDeletable()) {
                $invoice->delete();
                $deletedCount++;
            } else {
                $skippedCount++;
            }
        }

        $message = $deletedCount === 1 ? '1 deleted.' : "{$deletedCount} deleted.";
        if ($skippedCount > 0) {
            $message .= " {$skippedCount} skipped (paid or cancelled invoices can't be deleted).";
        }

        return back()->with($deletedCount > 0 ? 'success' : 'warning', $message);
    }

    private function applyPeriod($query, ?string $period, ?string $from, ?string $to): void
    {
        match ($period) {
            'daily' => $query->whereDate('created_at', now()->toDateString()),
            'weekly' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'monthly' => $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
            'range' => ($from && $to)
                ? $query->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                : null,
            default => null,
        };
    }
}
