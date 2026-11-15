<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\EnterpriseInquiryReplyMail;
use App\Mail\InvoiceMail;
use App\Mail\PaymentReceiptMail;
use App\Models\EnterpriseInquiry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * A "quotation" isn't a separate document type - see Invoice::isQuote():
 * it's an Invoice row with status='quote', sent before a deal closes and
 * not necessarily tied to any user yet. Converting it to a real invoice
 * later just flips that same row's status rather than creating a second
 * record. Receipts, by contrast, only ever exist for a COMPLETED
 * payment - this controller can only resend one that already exists
 * against the inquiry's linked user, never fabricate one.
 */
class AdminEnterpriseInquiryController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('q');
        $status = $request->query('status');
        $period = $request->query('period');
        $from = $request->query('from');
        $to = $request->query('to');
        $perPage = (int) $request->query('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $query = EnterpriseInquiry::with([
            'user',
            'user.payments' => fn ($q) => $q->where('status', 'completed')->orderByDesc('id'),
            'invoices' => fn ($q) => $q->orderByDesc('id')->limit(10),
        ])
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%")
                    ->orWhere('about', 'like', "%{$search}%");
            }))
            ->when($status, fn ($q) => $q->where('status', $status));

        match ($period) {
            'daily' => $query->whereDate('created_at', now()->toDateString()),
            'weekly' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'monthly' => $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
            'range' => ($from && $to)
                ? $query->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                : null,
            default => null,
        };

        $inquiries = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        $stats = [
            'today' => EnterpriseInquiry::whereDate('created_at', now()->toDateString())->count(),
            'this_week' => EnterpriseInquiry::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'this_month' => EnterpriseInquiry::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'new' => EnterpriseInquiry::where('status', 'new')->count(),
            'contacted' => EnterpriseInquiry::where('status', 'contacted')->count(),
            'closed' => EnterpriseInquiry::where('status', 'closed')->count(),
        ];

        $plans = SubscriptionPlan::where('category', '!=', 'individual')->where('is_enabled', true)->orderBy('sort_order')->get();

        return view('admin.enterprise-inquiries.index', compact('inquiries', 'stats', 'plans', 'search', 'status', 'period', 'from', 'to', 'perPage'));
    }

    public function updateStatus(Request $request, EnterpriseInquiry $enterpriseInquiry): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:new,contacted,closed']]);
        $enterpriseInquiry->update($data);

        return back()->with('success', 'Status updated.');
    }

    /**
     * Creates (or reuses, if one was already sent and not yet accepted)
     * a quotation for this inquiry and emails it as a PDF - either
     * priced off one of the existing organization/family-team plans, or
     * a custom description and amount for something bespoke.
     */
    public function sendQuotation(Request $request, EnterpriseInquiry $enterpriseInquiry): RedirectResponse
    {
        $data = $request->validate([
            'subscription_plan_id' => ['nullable', 'exists:subscription_plans,id'],
            'description' => ['nullable', 'string', 'max:500', 'required_without:subscription_plan_id'],
            'amount' => ['nullable', 'numeric', 'min:0', 'required_without:subscription_plan_id'],
            'valid_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $settings = SiteSetting::current();
        $plan = ! empty($data['subscription_plan_id']) ? SubscriptionPlan::find($data['subscription_plan_id']) : null;
        $amount = $plan ? $plan->computedPrice((float) $settings->monthly_price) : (float) $data['amount'];

        $quotation = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'user_id' => $enterpriseInquiry->user_id,
            'enterprise_inquiry_id' => $enterpriseInquiry->id,
            'subscription_plan_id' => $plan?->id,
            'description' => $plan ? null : $data['description'],
            'amount' => $amount,
            'currency' => $settings->default_currency_code,
            'status' => 'quote',
            'due_date' => now()->addDays((int) $data['valid_days'])->toDateString(),
        ]);

        Mail::to($enterpriseInquiry->email)->send(new InvoiceMail($quotation));

        return back()->with('success', 'Quotation ' . $quotation->invoice_number . ' sent to ' . $enterpriseInquiry->email . '.');
    }

    /**
     * Flips an existing quotation to a real, payable invoice and
     * re-sends it - used once the lead has agreed to the quoted price.
     * Does NOT create a new document; same row, new status.
     */
    public function convertToInvoice(Request $request, EnterpriseInquiry $enterpriseInquiry, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->enterprise_inquiry_id === $enterpriseInquiry->id, 404);
        abort_unless($invoice->isQuote(), 422);

        $invoice->update(['status' => 'unpaid', 'due_date' => now()->addDays(14)->toDateString()]);

        Mail::to($enterpriseInquiry->email)->send(new InvoiceMail($invoice));

        return back()->with('success', 'Converted to invoice ' . $invoice->invoice_number . ' and sent to ' . $enterpriseInquiry->email . '.');
    }

    /**
     * Re-sends an EXISTING invoice or quotation for this inquiry, as-is
     * - no status change, just another copy emailed out (e.g. the lead
     * says they lost the first one).
     */
    public function resendInvoice(Request $request, EnterpriseInquiry $enterpriseInquiry, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->enterprise_inquiry_id === $enterpriseInquiry->id, 404);

        Mail::to($enterpriseInquiry->email)->send(new InvoiceMail($invoice));

        return back()->with('success', ($invoice->isQuote() ? 'Quotation' : 'Invoice') . ' resent to ' . $enterpriseInquiry->email . '.');
    }

    public function replyToInquiry(Request $request, EnterpriseInquiry $enterpriseInquiry): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string'],
        ]);

        Mail::to($enterpriseInquiry->email)->send(
            new EnterpriseInquiryReplyMail($enterpriseInquiry, $data['subject'], $data['body'])
        );

        return back()->with('success', 'Reply sent to ' . $enterpriseInquiry->email . '.');
    }

    /**
     * A receipt only ever exists for a payment that actually completed
     * - this can only resend one already on file against the inquiry's
     * linked user, never generate one for a lead who hasn't paid.
     */
    public function sendReceipt(Request $request, EnterpriseInquiry $enterpriseInquiry): RedirectResponse
    {
        $data = $request->validate(['payment_id' => ['required', 'exists:payments,id']]);

        $payment = Payment::where('id', $data['payment_id'])
            ->where('user_id', $enterpriseInquiry->user_id)
            ->where('status', 'completed')
            ->first();

        if (! $payment) {
            return back()->withErrors(['payment_id' => 'No matching completed payment found for this inquiry\'s account.']);
        }

        Mail::to($enterpriseInquiry->email)->send(new PaymentReceiptMail($payment));

        return back()->with('success', 'Receipt for payment #' . $payment->id . ' resent to ' . $enterpriseInquiry->email . '.');
    }
}
