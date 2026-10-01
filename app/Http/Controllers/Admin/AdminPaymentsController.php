<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingEventLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentTransactionLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Review queue for payments — auto-completed card payments show up here
 * for record-keeping, while bank/mobile_money submissions need an admin to
 * manually approve or reject them (there's no live API integration for
 * arbitrary banks/mobile money providers, so verification is manual by
 * design: the user submits a reference/transaction code after paying
 * outside the app, and an admin confirms it actually came through).
 */
class AdminPaymentsController extends Controller
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

        $query = Payment::with(['user', 'gateway', 'plan', 'latestTransaction'])
            ->when($search, fn ($query) => $query->where(function ($sub) use ($search) {
                $sub->where('reference', 'like', "%{$search}%")
                    ->when(
                        \Illuminate\Support\Facades\Schema::hasColumn('payments', 'gateway_transaction_id'),
                        fn ($query) => $query->orWhere('gateway_transaction_id', 'like', "%{$search}%")
                    )
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('latestTransaction', fn ($tx) => $tx->where('phone_number', 'like', "%{$search}%"));
            }))
            ->when($status, fn ($query) => $query->where('status', $status));

        $this->applyPeriod($query, $period, $from, $to);

        $payments = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        // "Amount collected" is completed payments only — pending/failed/
        // rejected haven't actually landed, so counting them toward a
        // "collected" figure would overstate real revenue. Scoped to the
        // SAME period filter as the table below it, so switching "This
        // week" -> "This month" updates both together, matching what the
        // admin is currently looking at rather than always showing an
        // all-time figure alongside a filtered table.
        $collectedQuery = Payment::where('status', 'completed');
        $this->applyPeriod($collectedQuery, $period, $from, $to);
        $amountCollected = (float) $collectedQuery->sum('amount');

        $periodQuery = Payment::query();
        $this->applyPeriod($periodQuery, $period, $from, $to);
        $paymentsInPeriod = (clone $periodQuery)->count();
        $pendingInPeriod = (clone $periodQuery)->where('status', 'pending')->count();

        $stats = [
            ['label' => 'Amount Collected', 'value' => format_money($amountCollected), 'icon' => 'fa-solid fa-sack-dollar', 'color' => 'emerald'],
            ['label' => 'Payments', 'value' => (string) $paymentsInPeriod, 'icon' => 'fa-solid fa-money-check-dollar', 'color' => 'blue'],
            ['label' => 'Pending Review', 'value' => (string) $pendingInPeriod, 'icon' => 'fa-solid fa-hourglass-half', 'color' => 'amber'],
        ];

        return view('admin.payments.index', compact('payments', 'search', 'status', 'period', 'from', 'to', 'perPage', 'stats'));
    }

    /**
     * Shared by the main table query and both stats sub-queries above so
     * "daily/weekly/monthly/annual/range" is defined in exactly one
     * place — filters on created_at (when the payment record was made),
     * not on whatever the gateway's own timestamp might be.
     */
    private function applyPeriod(\Illuminate\Database\Eloquent\Builder $query, ?string $period, ?string $from, ?string $to): void
    {
        match ($period) {
            'daily' => $query->whereDate('created_at', now()->toDateString()),
            'weekly' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'monthly' => $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
            'annual' => $query->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]),
            'range' => ($from && $to)
                ? $query->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                : null,
            default => null,
        };
    }

    public function approve(Request $request, Payment $payment): RedirectResponse
    {
        $payment->update(['status' => 'completed']);
        $payment->assignReceiptNumber();
        $payment->invoice?->update(['status' => 'paid']);

        $user = $payment->user;
        // The subscription goes to the beneficiary when the payer paid
        // for someone else; notifications and the receipt stay with
        // the payer ($user).
        $subscriber = $payment->subscriptionRecipient();
        $plan = $payment->plan;

        // Same "extend from current expiry if still future, else from now"
        // logic as the automated card path (SubscriptionController) —
        // duplicated rather than shared since this controller and
        // SubscriptionController live in different areas of the app and
        // this is a small, self-contained calculation.
        $expiresAt = null;
        if ($plan && ! $plan->isLifetime()) {
            $base = ($subscriber->subscription_expires_at && $subscriber->subscription_expires_at->isFuture())
                ? $subscriber->subscription_expires_at
                : now();
            $expiresAt = $base->copy()->addMonths($plan->duration_months);
        }

        $subscriber->forceFill([
            'subscription_status' => 'active',
            'subscribed_at' => $subscriber->subscribed_at ?? now(),
            'subscription_plan_id' => $plan?->id,
            'subscription_expires_at' => $expiresAt,
            'last_expiry_reminder_days' => null,
        ])->save();

        app(\App\Services\SubscriptionAdminNotificationService::class)->notify($subscriber);

        if ($plan && ! $plan->isIndividual()) {
            $organization = \App\Models\Organization::where('owner_user_id', $subscriber->id)->first();
            if ($organization) {
                $organization->update(['subscription_plan_id' => $plan->id]);
            } else {
                \App\Models\Organization::create([
                    'name' => $subscriber->name . "'s Organization",
                    'owner_user_id' => $subscriber->id,
                    'subscription_plan_id' => $plan->id,
                ]);
            }
        }

        \App\Models\BillingEventLog::record('payment_status_changed', $user->id, ['payment_id' => $payment->id, 'details' => 'approved manually by admin']);

        $user->notify(new \App\Notifications\PaymentSuccessfulNotification($payment));

        try {
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\PaymentReceiptMail($payment));
            \App\Models\BillingEventLog::record('receipt_emailed', $user->id, ['payment_id' => $payment->id, 'recipient_email' => $user->email]);
        } catch (\Throwable $e) {
            \App\Models\BillingEventLog::record('receipt_emailed', $user->id, [
                'payment_id' => $payment->id, 'recipient_email' => $user->email, 'status' => 'failed', 'details' => $e->getMessage(),
            ]);
        }

        return back()->with('success', "Payment approved — {$subscriber->name}'s subscription is now active.");
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payment->update(['status' => 'rejected', 'notes' => $data['notes'] ?? null]);
        $payment->invoice?->update(['status' => 'cancelled']);
        \App\Models\BillingEventLog::record('payment_status_changed', $payment->user_id, ['payment_id' => $payment->id, 'status' => 'failed', 'details' => 'rejected by admin: ' . ($data['notes'] ?? 'no reason given')]);

        $payment->user?->notify(new \App\Notifications\PaymentFailedNotification($payment, $data['notes'] ?? null));

        return back()->with('success', 'Payment marked as rejected.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payment_ids' => ['required', 'array', 'min:1'],
            'payment_ids.*' => ['integer'],
        ]);

        $selectedIds = collect($data['payment_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            return back()->with('warning', 'No payments were selected.');
        }

        $eligibleIds = Payment::query()
            ->whereIn('id', $selectedIds)
            ->whereIn('status', ['failed', 'rejected'])
            ->where('created_at', '<=', now()->subDays(5))
            ->pluck('id');

        if ($eligibleIds->isEmpty()) {
            return back()->with('warning', 'No selected payments are eligible for deletion. Only failed/rejected payments older than 5 days can be deleted.');
        }

        DB::transaction(function () use ($eligibleIds): void {
            if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'payment_id')) {
                Invoice::query()
                    ->whereIn('payment_id', $eligibleIds)
                    ->where('status', '!=', 'paid')
                    ->update(['payment_id' => null]);
            }

            if (Schema::hasTable('payment_transaction_logs') && Schema::hasColumn('payment_transaction_logs', 'payment_id')) {
                PaymentTransactionLog::query()
                    ->whereIn('payment_id', $eligibleIds)
                    ->update(['payment_id' => null]);
            }

            if (Schema::hasTable('billing_event_logs') && Schema::hasColumn('billing_event_logs', 'payment_id')) {
                BillingEventLog::query()
                    ->whereIn('payment_id', $eligibleIds)
                    ->update(['payment_id' => null]);
            }

            Payment::query()
                ->whereIn('id', $eligibleIds)
                ->delete();
        });

        $deleted = $eligibleIds->count();
        $skipped = $selectedIds->count() - $deleted;
        $message = $deleted === 1 ? '1 failed/rejected payment deleted.' : "{$deleted} failed/rejected payments deleted.";

        if ($skipped > 0) {
            $message .= " {$skipped} skipped because they are not failed/rejected or are newer than 5 days.";
        }

        return back()->with('success', $message);
    }
}
