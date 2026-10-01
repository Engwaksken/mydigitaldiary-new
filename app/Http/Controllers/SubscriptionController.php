<?php

namespace App\Http\Controllers;

use App\Models\BillingEventLog;
use App\Models\BusinessCard;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\SiteSetting;
use App\Models\SubscriptionPlan;
use App\Services\MonthlyReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;


class SubscriptionController extends Controller
{
    public function show(Request $request, MonthlyReviewService $monthlyReviewService): View
    {
        $user = $request->user();
        $settings = Schema::hasTable((new SiteSetting())->getTable())
            ? SiteSetting::current()
            : new SiteSetting(['site_name' => 'My Digital Diary']);

        /*
         * Production-safe schema handling.
         *
         * The subscription screen must never assume that an older related
         * table has a `user_id` column. The current production database has
         * at least one legacy table in this flow without that column.
         */
        $gatewayTable = (new PaymentGateway())->getTable();
        $gateways = collect();

        if (Schema::hasTable($gatewayTable)) {
            $gatewayQuery = PaymentGateway::query();

            if (Schema::hasColumn($gatewayTable, 'is_enabled')) {
                $gatewayQuery->where('is_enabled', true);
            }

            $gatewayQuery->orderBy(
                Schema::hasColumn($gatewayTable, 'type') ? 'type' : 'id'
            );

            $gateways = $gatewayQuery->get();
        }

        $planTable = (new SubscriptionPlan())->getTable();
        $plans = collect();

        if (Schema::hasTable($planTable)) {
            $planQuery = SubscriptionPlan::query();

            if (Schema::hasColumn($planTable, 'is_enabled')) {
                $planQuery->where('is_enabled', true);
            }

            if (Schema::hasColumn($planTable, 'sort_order')) {
                $planQuery->orderBy('sort_order');
            } elseif (Schema::hasColumn($planTable, 'display_order')) {
                $planQuery->orderBy('display_order');
            } elseif (Schema::hasColumn($planTable, 'name')) {
                $planQuery->orderBy('name');
            } else {
                $planQuery->orderBy('id');
            }

            $plans = $planQuery->get();
        }

        $search = $request->query('billing_q');
        $period = $request->query('billing_period');
        $from = $request->query('billing_from');
        $to = $request->query('billing_to');

        $billingPerPage = (int) $request->query('billing_per_page', 10);

        if (! in_array($billingPerPage, [10, 25, 50, 100], true)) {
            $billingPerPage = 10;
        }

        $paymentTable = (new Payment())->getTable();
        $paymentHasUserId = Schema::hasTable($paymentTable)
            && Schema::hasColumn($paymentTable, 'user_id');

        if ($paymentHasUserId) {
            $paymentsQuery = Payment::query()
                ->where('user_id', $user->id)
                ->with(['gateway', 'plan', 'invoice', 'transactionLogs'])
                ->when($search, function ($query) use ($search): void {
                    $query->where(function ($sub) use ($search): void {
                        if (Schema::hasColumn((new Payment())->getTable(), 'reference')) {
                            $sub->where('reference', 'like', "%{$search}%");
                        }

                        if (Schema::hasColumn((new Payment())->getTable(), 'gateway_transaction_id')) {
                            $sub->orWhere('gateway_transaction_id', 'like', "%{$search}%");
                        }

                        try {
                            $sub->orWhereHas(
                                'plan',
                                fn ($planQuery) =>
                                    $planQuery->where('name', 'like', "%{$search}%")
                            );
                        } catch (\Throwable $exception) {
                            Log::debug('Subscription search skipped plan relationship.', [
                                'error' => $exception->getMessage(),
                            ]);
                        }
                    });
                });

            if (Schema::hasColumn($paymentTable, 'created_at')) {
                match ($period) {
                    'daily' => $paymentsQuery->whereDate(
                        'created_at',
                        now()->toDateString()
                    ),
                    'weekly' => $paymentsQuery->whereBetween(
                        'created_at',
                        [now()->startOfWeek(), now()->endOfWeek()]
                    ),
                    'monthly' => $paymentsQuery->whereBetween(
                        'created_at',
                        [now()->startOfMonth(), now()->endOfMonth()]
                    ),
                    'range' => ($from && $to)
                        ? $paymentsQuery->whereBetween(
                            'created_at',
                            [$from.' 00:00:00', $to.' 23:59:59']
                        )
                        : null,
                    default => null,
                };
            }

            try {
                $payments = $paymentsQuery
                    ->orderByDesc('id')
                    ->paginate(
                        $billingPerPage,
                        ['*'],
                        'billing_page'
                    )
                    ->withQueryString();
            } catch (\Throwable $exception) {
                Log::warning('Subscription payment history could not be loaded.', [
                    'table' => $paymentTable,
                    'user_id' => $user->id,
                    'error' => $exception->getMessage(),
                ]);

                $payments = $this->emptyBillingPaginator(
                    $request,
                    $billingPerPage
                );
            }
        } else {
            Log::warning('Subscription page: payment table has no user_id column.', [
                'table' => $paymentTable,
                'user_id' => $user->id,
            ]);

            $payments = $this->emptyBillingPaginator(
                $request,
                $billingPerPage
            );
        }

        $accountPhone = $user->phone_number ?? null;

        if (! $accountPhone) {
            $businessCardTable = (new BusinessCard())->getTable();

            if (
                Schema::hasTable($businessCardTable)
                && Schema::hasColumn($businessCardTable, 'user_id')
                && Schema::hasColumn($businessCardTable, 'phone')
            ) {
                $accountPhone = BusinessCard::query()
                    ->where('user_id', $user->id)
                    ->value('phone');
            } else {
                Log::info('Subscription page skipped BusinessCard user lookup.', [
                    'table' => $businessCardTable,
                    'has_user_id' => Schema::hasTable($businessCardTable)
                        && Schema::hasColumn($businessCardTable, 'user_id'),
                ]);
            }
        }

        $automaticMobileGateway = $gateways->first(
            fn ($gateway) =>
                method_exists($gateway, 'collectsAutomatically')
                && $gateway->collectsAutomatically()
        );

        $bankGateways = $gateways
            ->filter(
                fn ($gateway) =>
                    strtolower((string) ($gateway->type ?? '')) === 'bank'
            )
            ->values();

        $invoiceTable = (new Invoice())->getTable();
        $totalInvoices = 0;

        if (
            Schema::hasTable($invoiceTable)
            && Schema::hasColumn($invoiceTable, 'user_id')
        ) {
            $totalInvoices = Invoice::query()
                ->where('user_id', $user->id)
                ->count();
        } elseif (
            Schema::hasTable($invoiceTable)
            && $paymentHasUserId
            && Schema::hasColumn($invoiceTable, 'payment_id')
        ) {
            $totalInvoices = Invoice::query()
                ->whereIn(
                    'payment_id',
                    Payment::query()
                        ->where('user_id', $user->id)
                        ->select('id')
                )
                ->count();

            Log::info('Subscription invoice count uses payment_id fallback.', [
                'table' => $invoiceTable,
            ]);
        } elseif (Schema::hasTable($invoiceTable)) {
            Log::info('Subscription page invoice table has no compatible user ownership column.', [
                'table' => $invoiceTable,
            ]);
        }

        if ($paymentHasUserId) {
            $userPayments = Payment::query()
                ->where('user_id', $user->id);

            $billingStats = [
                'total_invoices' => $totalInvoices,
                'completed' => (clone $userPayments)
                    ->where('status', 'completed')
                    ->count(),
                'pending' => (clone $userPayments)
                    ->where('status', 'pending')
                    ->count(),
                'failed' => (clone $userPayments)
                    ->whereIn('status', ['failed', 'rejected'])
                    ->count(),
            ];
        } else {
            $billingStats = [
                'total_invoices' => $totalInvoices,
                'completed' => 0,
                'pending' => 0,
                'failed' => 0,
            ];
        }

        $valueSummary = [
            'tasks_completed' => 0,
            'expenses_tracked' => 0.0,
            'saved' => 0.0,
            'ai_plans' => 0,
            'meetings' => 0,
        ];

        try {
            $monthReview = $monthlyReviewService->build(
                $user,
                Carbon::now($user->timezone ?: 'Africa/Kampala')
            );

            $monthValue = $monthReview['value'] ?? [];

            $valueSummary = [
                'tasks_completed' => (int) ($monthValue['tasks_completed'] ?? 0),
                'expenses_tracked' => (float) ($monthReview['money']['expenses'] ?? 0),
                'saved' => (float) ($monthReview['money']['saved'] ?? 0),
                'ai_plans' => (int) ($monthValue['ai_plans'] ?? 0),
                'meetings' => (int) ($monthValue['meetings'] ?? 0),
            ];
        } catch (\Throwable $exception) {
            Log::warning('Subscription monthly value summary could not be built.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $autoRenewGateway = $gateways->first(
            fn ($gateway) =>
                in_array(
                    strtolower((string) ($gateway->type ?? '')),
                    ['mobile_money', 'aggregator'],
                    true
                )
                && method_exists($gateway, 'collectsAutomatically')
                && $gateway->collectsAutomatically()
        );

        $autoRenewEnabled = Schema::hasColumn('users', 'auto_renew_subscription')
            ? (bool) $user->getAttribute('auto_renew_subscription')
            : false;

        $autoRenewPhone = Schema::hasColumn('users', 'auto_renew_phone')
            ? ($user->getAttribute('auto_renew_phone') ?: $accountPhone)
            : $accountPhone;

        $autoRenewNetwork = Schema::hasColumn('users', 'auto_renew_network')
            ? ($user->getAttribute('auto_renew_network') ?: 'mtn')
            : 'mtn';

        return view('subscription.show', [
            'user' => $user,
            'settings' => $settings,
            'gateways' => $gateways,
            'plans' => $plans,
            'payments' => $payments,
            'billingStats' => $billingStats,
            'billingSearch' => $search,
            'billingPeriod' => $period,
            'billingFrom' => $from,
            'billingTo' => $to,
            'billingPerPage' => $billingPerPage,
            'accountPhone' => $accountPhone,
            'automaticMobileGateway' => $automaticMobileGateway,
            'bankGateways' => $bankGateways,
            'valueSummary' => $valueSummary,
            'autoRenewGateway' => $autoRenewGateway,
            'autoRenewEnabled' => $autoRenewEnabled,
            'autoRenewPhone' => $autoRenewPhone,
            'autoRenewNetwork' => $autoRenewNetwork,
        ]);
    }

    private function emptyBillingPaginator(
        Request $request,
        int $perPage
    ): LengthAwarePaginator {
        return new LengthAwarePaginator(
            [],
            0,
            $perPage,
            (int) $request->query('billing_page', 1),
            [
                'path' => $request->url(),
                'query' => $request->query(),
                'pageName' => 'billing_page',
            ]
        );
    }

    /**
     * Given a chosen plan, works out the correct subscription_expires_at —
     * extending from the CURRENT expiry if it's still in the future
     * (so renewing before running out doesn't lose already-paid time),
     * otherwise from now. Null for the lifetime plan (never expires).
     */
    private function expiryFor(SubscriptionPlan $plan, $user): ?Carbon
    {
        if ($plan->isLifetime()) {
            return null;
        }

        $base = ($user->subscription_expires_at && $user->subscription_expires_at->isFuture())
            ? $user->subscription_expires_at
            : now();

        return $base->copy()->addMonths($plan->duration_months);
    }

    /**
     * Raised the moment a user commits to a plan by submitting any
     * payment method — represents what's owed for this billing period,
     * separate from the receipt that follows once payment actually
     * completes. Emailed immediately as a PDF attachment; every step is
     * recorded to billing_event_logs for admin visibility.
     */
    private function createAndSendInvoice(Payment $payment, SubscriptionPlan $plan, $user): Invoice
    {
        $periodStart = ($user->subscription_expires_at && $user->subscription_expires_at->isFuture())
            ? $user->subscription_expires_at->copy()
            : now();
        $periodEnd = $plan->isLifetime() ? null : $periodStart->copy()->addMonths($plan->duration_months);

        $invoice = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => 'unpaid',
            'billing_period_start' => $periodStart->toDateString(),
            'billing_period_end' => $periodEnd?->toDateString(),
            'due_date' => now()->toDateString(),
        ]);

        BillingEventLog::record('invoice_generated', $user->id, ['invoice_id' => $invoice->id, 'payment_id' => $payment->id]);

        try {
            Mail::to($user->email)->send(new \App\Mail\InvoiceMail($invoice));
            BillingEventLog::record('invoice_emailed', $user->id, ['invoice_id' => $invoice->id, 'recipient_email' => $user->email]);
        } catch (\Throwable $e) {
            BillingEventLog::record('invoice_emailed', $user->id, [
                'invoice_id' => $invoice->id,
                'recipient_email' => $user->email,
                'status' => 'failed',
                'details' => $e->getMessage(),
            ]);
        }

        return $invoice;
    }

    private function findEnabledPlan(int $planId): SubscriptionPlan
    {
        return SubscriptionPlan::where('is_enabled', true)->findOrFail($planId);
    }

    /**
     * Demo/no-gateway-configured fallback — flips the account to active
     * immediately with no charge. Only reachable from the UI when no
     * payment gateways are configured at all (see subscription/show.blade.php).
     */
    public function subscribe(Request $request): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['nullable', 'exists:subscription_plans,id']]);
        $user = $request->user();
        $plan = ! empty($data['plan_id']) ? $this->findEnabledPlan($data['plan_id']) : null;

        $user->forceFill([
            'subscription_status' => 'active',
            'subscribed_at' => now(),
            'subscription_plan_id' => $plan?->id,
            'subscription_expires_at' => $plan ? $this->expiryFor($plan, $user) : null,
        ])->save();

        app(\App\Services\SubscriptionAdminNotificationService::class)->notify($user);

        if ($plan) {
            $this->ensureOrganizationForPlan($user, $plan);
        }

        return redirect()->route('dashboard')->with('success', 'Subscription activated — welcome back!');
    }

    /**
     * A Family/Team or Organization plan needs an actual Organization
     * record to manage seats against — nothing else in the app creates
     * one, so every path that activates a subscription (this one, the
     * card callback below, the webhook controller, and admin manual
     * approval) needs to call this. An individual plan does nothing
     * here. If the buyer already owns an organization (upgrading or
     * renewing their tier), that same organization's plan is updated
     * rather than creating a second one.
     */
    private function ensureOrganizationForPlan(\App\Models\User $user, SubscriptionPlan $plan): void
    {
        if ($plan->isIndividual()) {
            return;
        }

        $organization = \App\Models\Organization::where('owner_user_id', $user->id)->first();

        if ($organization) {
            $organization->update(['subscription_plan_id' => $plan->id]);
        } else {
            \App\Models\Organization::create([
                'name' => $user->name . "'s Organization",
                'owner_user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
            ]);
        }
    }

    /**
     * Let the account owner enable/disable automatic renewal.
     *
     * Auto renewal does NOT store a Mobile Money PIN or card details.
     * On the expiry date the scheduler creates the next invoice/payment and
     * sends a Mobile Money collection prompt to the saved phone number.
     * Subscription access is extended only after the gateway confirms success.
     */
    public function updateAutoRenew(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            Schema::hasColumn('users', 'auto_renew_subscription'),
            503,
            'Auto renewal is not installed yet. Run the latest migration.'
        );

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'network' => ['nullable', 'in:mtn,airtel'],
        ]);

        $enabled = (bool) $data['enabled'];

        if ($enabled) {
            if (! $user->subscription_plan_id) {
                return back()->withErrors([
                    'auto_renew' => 'Choose and activate a subscription plan before enabling auto renewal.',
                ]);
            }

            if (! $user->subscription_expires_at) {
                return back()->withErrors([
                    'auto_renew' => 'Lifetime subscriptions do not need auto renewal.',
                ]);
            }

            $phone = trim((string) ($data['phone_number'] ?? ''));

            if ($phone === '') {
                return back()->withErrors([
                    'auto_renew' => 'Enter the Mobile Money number to use for renewal prompts.',
                ]);
            }

            $gateway = PaymentGateway::query()
                ->where('is_enabled', true)
                ->whereIn('type', ['mobile_money', 'aggregator'])
                ->get()
                ->first(fn ($candidate) => $candidate->collectsAutomatically());

            if (! $gateway) {
                return back()->withErrors([
                    'auto_renew' => 'Automatic Mobile Money collection is not configured yet.',
                ]);
            }

            $network = $data['network'] ?? 'mtn';

            if ($network === 'mtn' && ! $gateway->supports_mtn) {
                return back()->withErrors([
                    'auto_renew' => 'MTN Mobile Money is not enabled on the automatic payment gateway.',
                ]);
            }

            if ($network === 'airtel' && ! $gateway->supports_airtel) {
                return back()->withErrors([
                    'auto_renew' => 'Airtel Money is not enabled on the automatic payment gateway.',
                ]);
            }

            $user->forceFill([
                'auto_renew_subscription' => true,
                'auto_renew_phone' => $phone,
                'auto_renew_network' => $network,
                'auto_renew_payment_gateway_id' => $gateway->id,
                'auto_renew_disabled_at' => null,
            ])->save();

            // Keep the account payment phone in sync for normal checkout too.
            if (Schema::hasColumn('users', 'phone_number')) {
                $user->forceFill(['phone_number' => $phone])->save();
            }

            BillingEventLog::record('subscription_auto_renew_enabled', $user->id, [
                'payment_gateway_id' => $gateway->id,
                'network' => $network,
                'phone_number' => $phone,
                'subscription_plan_id' => $user->subscription_plan_id,
                'expires_at' => optional($user->subscription_expires_at)?->toDateTimeString(),
            ]);

            return redirect()
                ->route('subscription.show')
                ->with(
                    'success',
                    'Auto renewal enabled. On the expiry date, a Mobile Money payment prompt will be sent to your saved number.'
                );
        }

        $user->forceFill([
            'auto_renew_subscription' => false,
            'auto_renew_disabled_at' => now(),
        ])->save();

        BillingEventLog::record('subscription_auto_renew_disabled', $user->id);

        return redirect()
            ->route('subscription.show')
            ->with('success', 'Auto renewal disabled.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'subscription_status' => 'canceled',
            'auto_renew_subscription' => false,
            'auto_renew_disabled_at' => Schema::hasColumn('users', 'auto_renew_disabled_at')
                ? now()
                : null,
        ])->save();

        return redirect()->route('subscription.show')->with('status', 'Subscription canceled.');
    }

    /**
     * Creates a Stripe Checkout Session using the admin-configured card
     * gateway's own API keys, and redirects the user to Stripe's hosted
     * checkout page. Nothing in our own database changes yet — that only
     * happens once payCardCallback() confirms Stripe says it was paid.
     */
    public function payWithCard(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'beneficiary_email' => ['nullable', 'email', 'max:255'],
        ]);

        $beneficiary = app(\App\Services\SubscriptionBeneficiaryService::class)
            ->resolveForPayment($request->user(), $data['beneficiary_email'] ?? null);
        $plan = $this->findEnabledPlan($data['plan_id']);

        $gateway = PaymentGateway::where('type', 'card')->where('is_enabled', true)->first();

        if (! $gateway || ! $gateway->configValue('stripe_secret_key')) {
            return back()->withErrors(['payment' => 'Card payments are not configured yet.']);
        }

        $settings = SiteSetting::current();
        $price = $plan->computedPrice((float) $settings->monthly_price);
        $currencyCode = strtolower($settings->default_currency_code);

        // Stripe expects amounts in the currency's SMALLEST unit — cents
        // for USD, but for a handful of currencies Stripe treats as
        // "zero-decimal" (UGX among them), the amount is sent as-is with
        // NO ×100. Multiplying UGX by 100 here would have charged 100x
        // the intended amount. default_currency_decimals is the signal
        // used elsewhere in the app for display formatting, so this
        // stays consistent with however the admin has that configured —
        // if they ever set a currency with decimals=0 that Stripe does
        // NOT treat as zero-decimal (or vice versa), this would need
        // adjusting to match Stripe's own fixed list instead.
        $isZeroDecimal = $settings->default_currency_decimals == 0;
        $unitAmount = $isZeroDecimal ? (int) round($price) : (int) round($price * 100);

        $response = Http::asForm()
            ->withToken($gateway->configValue('stripe_secret_key'))
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => route('subscription.pay.card.callback') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('subscription.show'),
                'customer_email' => $request->user()->email,
                'line_items' => [
                    [
                        'quantity' => 1,
                        'price_data' => [
                            'currency' => $currencyCode,
                            'unit_amount' => $unitAmount,
                            'product_data' => [
                                'name' => $settings->site_name . ' Subscription — ' . $plan->name,
                            ],
                        ],
                    ],
                ],
            ]);

        if ($response->failed() || ! $response->json('url')) {
            Log::warning('Stripe checkout session creation failed', ['response' => $response->json()]);

            return back()->withErrors(['payment' => 'Could not start card checkout. Please try again or use another payment method.']);
        }

        // Record the attempt as pending now, so it shows up even if the
        // user abandons checkout — payWithCardCallback() flips it to
        // completed once Stripe confirms payment.
        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'beneficiary_user_id' => $beneficiary?->id,
            'payment_gateway_id' => $gateway->id,
            'subscription_plan_id' => $plan->id,
            'method' => 'card',
            'amount' => $price,
            'currency' => $settings->default_currency_code,
            'status' => 'pending',
            'reference' => $response->json('id'),
            'gateway_transaction_id' => $response->json('payment_intent') ?: $response->json('id'),
        ]);

        $this->createAndSendInvoice($payment, $plan, $request->user());

        return redirect()->away($response->json('url'));
    }

    /**
     * IoTec (or any other 'aggregator'-type gateway marked as default)
     * mobile money collection — unlike the card flow, this doesn't
     * redirect anywhere; IoTec pushes a payment prompt straight to the
     * phone number entered, and the subscription activates once
     * PaymentGatewayWebhookController receives their confirmation (or the status
     * check below catches it if the webhook hasn't landed yet).
     */
    public function payWithMobileMoney(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'phone_number' => ['required', 'string', 'max:20'],
            'network' => ['required', 'in:mtn,airtel'],
            'beneficiary_email' => ['nullable', 'email', 'max:255'],
        ]);

        $beneficiary = app(\App\Services\SubscriptionBeneficiaryService::class)
            ->resolveForPayment($request->user(), $data['beneficiary_email'] ?? null);

        $plan = $this->findEnabledPlan($data['plan_id']);

        $request->user()->update(['phone_number' => $data['phone_number']]);

        $gateway = PaymentGateway::whereIn('type', ['mobile_money', 'aggregator'])->where('is_default', true)->where('is_enabled', true)->first();

        if (! $gateway || ! $gateway->collectsAutomatically()) {
            return back()->withErrors(['payment' => 'Mobile money collection is not configured yet.']);
        }

        $settings = SiteSetting::current();
        $price = $plan->computedPrice((float) $settings->monthly_price);
        $reference = 'sub_' . $request->user()->id . '_' . now()->format('YmdHis') . '_' . random_int(1000, 9999);

        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'beneficiary_user_id' => $beneficiary?->id,
            'payment_gateway_id' => $gateway->id,
            'subscription_plan_id' => $plan->id,
            'method' => 'mobile_money',
            'amount' => $price,
            'currency' => $settings->default_currency_code,
            'status' => 'pending',
            'reference' => $reference,
        ]);

        $this->createAndSendInvoice($payment, $plan, $request->user());

        try {
            $driver = \App\PaymentGateways\PaymentGatewayDriverFactory::make($gateway);
            $log = $driver->initiateCollection(
                $reference,
                $price,
                $settings->default_currency_code,
                $data['phone_number'],
                $data['network'],
                $request->user()->id,
                $payment->id
            );

            if (Schema::hasColumn('payments', 'gateway_transaction_id') && $log->external_reference) {
                $payment->update(['gateway_transaction_id' => $log->external_reference]);
            }

            return redirect()->route('subscription.show')->with(
                'success',
                'A payment prompt has been sent to ' . $data['phone_number'] . ' — approve it on your phone to activate your subscription.'
            );
        } catch (\Throwable $e) {
            Log::warning('IoTec collection request failed', ['error' => $e->getMessage()]);
            $payment->update(['status' => 'failed']);

            return back()->withErrors(['payment' => 'Could not start the mobile money request: ' . $e->getMessage()]);
        }
    }

    /**
     * Re-send a mobile money prompt for an EXISTING pending invoice/payment.
     * The same payment/invoice row is reused so retrying never generates
     * duplicate invoices in the Invoices & Receipts history.
     */
    public function retryPendingMobileMoney(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 403);
        abort_unless(in_array($payment->status, ['pending', 'failed'], true), 422);

        $data = $request->validate([
            'phone_number' => ['required', 'string', 'max:30'],
            'network' => ['required', 'in:mtn,airtel'],
        ]);

        $gateway = PaymentGateway::whereIn('type', ['mobile_money', 'aggregator'])
            ->where('is_default', true)
            ->where('is_enabled', true)
            ->get()
            ->first(fn ($candidate) => $candidate->collectsAutomatically());

        if (! $gateway) {
            return back()->withErrors(['payment' => 'Mobile money collection is not configured yet.']);
        }

        $request->user()->update(['phone_number' => $data['phone_number']]);

        $reference = 'sub_retry_' . $request->user()->id . '_' . now()->format('YmdHis') . '_' . random_int(1000, 9999);

        $payment->update([
            'payment_gateway_id' => $gateway->id,
            'method' => 'mobile_money',
            'status' => 'pending',
            'reference' => $reference,
        ]);

        try {
            $driver = \App\PaymentGateways\PaymentGatewayDriverFactory::make($gateway);
            $log = $driver->initiateCollection(
                $reference,
                (float) $payment->amount,
                $payment->currency,
                $data['phone_number'],
                $data['network'],
                $request->user()->id,
                $payment->id
            );

            if (Schema::hasColumn('payments', 'gateway_transaction_id') && $log->external_reference) {
                $payment->update(['gateway_transaction_id' => $log->external_reference]);
            }

            BillingEventLog::record('payment_prompt_resent', $request->user()->id, [
                'payment_id' => $payment->id,
                'phone_number' => $data['phone_number'],
                'network' => $data['network'],
            ]);

            return back()->with('success', 'A new payment prompt was sent to ' . $data['phone_number'] . '. Approve it on your phone.');
        } catch (\Throwable $e) {
            Log::warning('Pending mobile money retry failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['payment' => 'Could not resend the payment prompt: ' . $e->getMessage()]);
        }
    }

    /**
     * Apply a bank-transfer reference to an EXISTING pending invoice.
     * The admin can then verify this same payment record instead of the user
     * creating a second payment/invoice just to change payment method.
     */
    public function submitPendingBankPayment(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 403);
        abort_unless(in_array($payment->status, ['pending', 'failed'], true), 422);

        $data = $request->validate([
            'payment_gateway_id' => ['required', 'exists:payment_gateways,id'],
            'reference' => ['required', 'string', 'max:255'],
        ]);

        $gateway = PaymentGateway::whereKey($data['payment_gateway_id'])
            ->where('is_enabled', true)
            ->where('type', 'bank')
            ->firstOrFail();

        $payment->update([
            'payment_gateway_id' => $gateway->id,
            'method' => 'bank',
            'status' => 'pending',
            'reference' => $data['reference'],
        ]);

        BillingEventLog::record('pending_payment_bank_reference_submitted', $request->user()->id, [
            'payment_id' => $payment->id,
            'payment_gateway_id' => $gateway->id,
            'reference' => $data['reference'],
        ]);

        return back()->with('success', 'Bank payment reference submitted. Your payment is awaiting verification.');
    }

    /**
     * Cancel an unpaid pending subscription payment/invoice.
     * Only the owner can cancel it, and completed payments can never be
     * cancelled from the self-service billing screen.
     */
    public function cancelPendingPayment(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 403);
        abort_unless($payment->status === 'pending', 422);

        $payment->update(['status' => 'cancelled']);

        if ($payment->invoice && $payment->invoice->status !== 'paid') {
            $payment->invoice->update(['status' => 'cancelled']);
        }

        BillingEventLog::record('pending_payment_cancelled', $request->user()->id, [
            'payment_id' => $payment->id,
            'invoice_id' => $payment->invoice?->id,
        ]);

        return redirect()->route('subscription.show', ['tab' => 'billing'])
            ->with('success', 'Pending subscription payment cancelled.');
    }

    /**
     * Stripe redirects here after checkout (success_url above). Verifies
     * the session directly with Stripe's API before trusting it — never
     * activates a subscription based solely on the redirect happening.
     */
    public function payWithCardCallback(Request $request): RedirectResponse
    {
        $sessionId = $request->query('session_id');
        // Looked up by the Stripe session ID alone, not tied to the
        // current request's session — Stripe redirects the BROWSER back
        // here, which has no session at all if checkout was started from
        // the mobile app (a Bearer-token API request, opened in an
        // external browser via a plain link). The session id itself is
        // unique and unguessable, so this is still safe.
        $payment = $sessionId ? Payment::where('reference', $sessionId)->first() : null;

        if (! $sessionId || ! $payment) {
            return redirect()->route('subscription.show')->withErrors(['payment' => 'Could not find that checkout session.']);
        }

        $gateway = $payment->gateway;

        $response = Http::withToken($gateway?->configValue('stripe_secret_key'))
            ->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}");

        if ($response->failed()) {
            return redirect()->route('subscription.show')->withErrors(['payment' => 'Could not verify payment with Stripe.']);
        }

        if ($response->json('payment_status') === 'paid') {
            $updates = ['status' => 'completed'];

            if (Schema::hasColumn('payments', 'gateway_transaction_id')) {
                $updates['gateway_transaction_id'] = $response->json('payment_intent') ?: $sessionId;
            }

            $payment->update($updates);
            $payment->assignReceiptNumber();
            $payment->invoice?->update(['status' => 'paid']);

            $user = $payment->user;
            // The subscription goes to the beneficiary when the payer paid
            // for someone else; notifications and the receipt stay with
            // the payer ($user).
            $subscriber = $payment->subscriptionRecipient();
            $plan = $payment->plan;

            $subscriber->forceFill([
                'subscription_status' => 'active',
                'subscribed_at' => $subscriber->subscribed_at ?? now(),
                'subscription_plan_id' => $plan?->id,
                'subscription_expires_at' => $plan ? $this->expiryFor($plan, $subscriber) : null,
                // Cleared so the NEXT expiry cycle's reminders start
                // fresh against the new expiry date, rather than
                // carrying over a milestone number from before this
                // renewal — this is also what stops any reminder in
                // flight for the old expiry date from being mistaken
                // for still relevant.
                'last_expiry_reminder_days' => null,
            ])->save();

            app(\App\Services\SubscriptionAdminNotificationService::class)->notify($subscriber);

            if ($plan) {
                $this->ensureOrganizationForPlan($subscriber, $plan);
            }

            BillingEventLog::record('payment_status_changed', $user->id, ['payment_id' => $payment->id, 'details' => 'completed via card']);

            $user->notify(new \App\Notifications\PaymentSuccessfulNotification($payment));

            try {
                Mail::to($user->email)->send(new \App\Mail\PaymentReceiptMail($payment));
                BillingEventLog::record('receipt_emailed', $user->id, ['payment_id' => $payment->id, 'recipient_email' => $user->email]);
            } catch (\Throwable $e) {
                BillingEventLog::record('receipt_emailed', $user->id, [
                    'payment_id' => $payment->id, 'recipient_email' => $user->email, 'status' => 'failed', 'details' => $e->getMessage(),
                ]);
            }

            return redirect()->route('dashboard')->with('success', 'Payment received — subscription activated!');
        }

        $payment->update(['status' => 'failed']);
        BillingEventLog::record('payment_status_changed', $payment->user_id, ['payment_id' => $payment->id, 'status' => 'failed', 'details' => 'card payment not paid']);

        return redirect()->route('subscription.show')->withErrors(['payment' => 'Payment was not completed.']);
    }

    /**
     * Bank / mobile money: the user has already paid OUTSIDE the app
     * (following the gateway's displayed instructions) and is submitting
     * proof — a transaction reference — for an admin to verify at
     * /admin/payments. This does NOT activate the subscription by itself —
     * AdminPaymentsController::approve() is what actually sets
     * subscription_status/expires_at, using this payment's own plan.
     */
    public function submitManualPayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payment_gateway_id' => ['required', 'exists:payment_gateways,id'],
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'reference' => ['required', 'string', 'max:255'],
            'beneficiary_email' => ['nullable', 'email', 'max:255'],
        ]);

        $beneficiary = app(\App\Services\SubscriptionBeneficiaryService::class)
            ->resolveForPayment($request->user(), $data['beneficiary_email'] ?? null);

        $gateway = PaymentGateway::where('id', $data['payment_gateway_id'])
            ->where('is_enabled', true)
            ->whereIn('type', ['bank', 'mobile_money'])
            ->firstOrFail();

        $plan = $this->findEnabledPlan($data['plan_id']);
        $settings = SiteSetting::current();
        $price = $plan->computedPrice((float) $settings->monthly_price);

        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'beneficiary_user_id' => $beneficiary?->id,
            'payment_gateway_id' => $gateway->id,
            'subscription_plan_id' => $plan->id,
            'method' => $gateway->type,
            'amount' => $price,
            'currency' => $settings->default_currency_code,
            'status' => 'pending',
            'reference' => $data['reference'],
        ]);

        $this->createAndSendInvoice($payment, $plan, $request->user());

        return back()->with('success', 'Thanks — your payment reference was submitted and is awaiting verification.');
    }

    public function downloadReceipt(Request $request, Payment $payment)
    {
        abort_unless($payment->user_id === $request->user()->id, 403);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('subscription.receipt-pdf', ['payment' => $payment])->setPaper('a4');

        return $pdf->download('receipt-' . $payment->id . '.pdf');
    }

    public function downloadInvoice(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('subscription.invoice-pdf', ['invoice' => $invoice])->setPaper('a4');

        return $pdf->download($invoice->invoice_number . '.pdf');
    }
}
