<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Not a CrudController subclass - bank/mobile_money/card each need a
 * different set of config fields (account details vs. API keys), so the
 * form and validation are hand-written per type rather than driven by a
 * single generic $fields array.
 */
class AdminPaymentGatewayController extends Controller
{
    public function index(Request $request): View
    {
        $query = PaymentGateway::query();

        $search = $request->query('q');
        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $period = $request->query('period');
        $from = $request->query('from');
        $to = $request->query('to');

        match ($period) {
            'daily' => $query->whereDate('created_at', now()->toDateString()),
            'weekly' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'monthly' => $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
            'range' => ($from && $to)
                ? $query->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                : null,
            default => null,
        };

        $gateways = $query->orderBy('type')->orderBy('name')->paginate(10)->withQueryString();

        $stats = [
            ['label' => 'Total gateways', 'value' => (string) PaymentGateway::count(), 'icon' => 'fa-solid fa-credit-card', 'color' => 'emerald'],
            ['label' => 'Enabled', 'value' => (string) PaymentGateway::where('is_enabled', true)->count(), 'icon' => 'fa-solid fa-circle-check', 'color' => 'blue'],
            ['label' => 'Disabled', 'value' => (string) PaymentGateway::where('is_enabled', false)->count(), 'icon' => 'fa-solid fa-circle-xmark', 'color' => 'slate'],
        ];

        return view('admin.payment-gateways.index', compact('gateways', 'stats', 'search', 'period', 'from', 'to'));
    }

    public function create(): View
    {
        return view('admin.payment-gateways.form', ['gateway' => new PaymentGateway]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        PaymentGateway::create($data);

        return redirect()->route('admin.payment-gateways.index')->with('success', 'Payment gateway added.');
    }

    public function edit(PaymentGateway $paymentGateway): View
    {
        return view('admin.payment-gateways.form', ['gateway' => $paymentGateway]);
    }

    public function update(Request $request, PaymentGateway $paymentGateway): RedirectResponse
    {
        $data = $this->validated($request, $paymentGateway);
        $data['updated_by'] = $request->user()->id;

        $paymentGateway->update($data);

        return redirect()->route('admin.payment-gateways.index')->with('success', 'Payment gateway updated.');
    }

    public function toggle(PaymentGateway $paymentGateway): RedirectResponse
    {
        $paymentGateway->update(['is_enabled' => ! $paymentGateway->is_enabled]);

        return back()->with('success', $paymentGateway->name . ' is now ' . ($paymentGateway->is_enabled ? 'enabled' : 'disabled') . '.');
    }

    /**
     * Only one gateway can be "the default" used to actually collect
     * subscription payments - setting one clears the flag on every
     * other row first, so there's never ambiguity about which one
     * SubscriptionController::payWithMobileMoney() will use.
     */
    public function setDefault(Request $request, PaymentGateway $paymentGateway): RedirectResponse
    {
        PaymentGateway::where('is_default', true)->update(['is_default' => false]);
        $paymentGateway->update(['is_default' => true, 'is_enabled' => true, 'updated_by' => $request->user()->id]);

        return back()->with('success', $paymentGateway->display_name . ' is now the default payment gateway for collecting subscriptions.');
    }

    public function testConnection(PaymentGateway $paymentGateway): RedirectResponse
    {
        if (! $paymentGateway->isAggregator()) {
            return back()->withErrors(['gateway' => 'Test Connection is only available for aggregator-type gateways.']);
        }

        if (! $paymentGateway->isConfigured()) {
            return back()->withErrors(['gateway' => 'Fill in the token URL, client ID, and client secret first.']);
        }

        try {
            $result = \App\PaymentGateways\PaymentGatewayDriverFactory::make($paymentGateway)->testConnection();
        } catch (\Throwable $e) {
            return back()->withErrors(['gateway' => $e->getMessage()]);
        }

        return $result['success']
            ? back()->with('success', $result['message'])
            : back()->withErrors(['gateway' => $result['message']]);
    }

    public function destroy(PaymentGateway $paymentGateway): RedirectResponse
    {
        $paymentGateway->delete();

        return redirect()->route('admin.payment-gateways.index')->with('success', 'Payment gateway removed.');
    }

    private function validated(Request $request, ?PaymentGateway $existing = null): array
    {
        $validated = $request->validate([
            'type' => ['required', 'in:bank,mobile_money,card,aggregator'],
            'name' => ['required', 'string', 'max:255'],
            'is_enabled' => ['nullable', 'boolean'],
            'instructions' => ['nullable', 'string'],

            // Bank
            'bank_name' => ['nullable', 'required_if:type,bank', 'string', 'max:255'],
            'account_name' => ['nullable', 'required_if:type,bank', 'string', 'max:255'],
            'account_number' => ['nullable', 'required_if:type,bank', 'string', 'max:255'],
            'routing_or_swift' => ['nullable', 'string', 'max:255'],

            // Mobile money (manual) - only actually required when the
            // admin ISN'T also filling in the aggregator API section
            // below for automated collection instead; a gateway_code
            // present means they're doing that, so these purely-manual
            // fields become optional (the API handles it instead).
            'provider_name' => ['nullable', Rule::requiredIf(fn () => $request->input('type') === 'mobile_money' && ! $request->filled('gateway_code')), 'string', 'max:255'],
            'merchant_number' => ['nullable', Rule::requiredIf(fn () => $request->input('type') === 'mobile_money' && ! $request->filled('gateway_code')), 'string', 'max:255'],

            // Card (Stripe)
            'stripe_publishable_key' => ['nullable', 'required_if:type,card', 'string', 'max:255'],
            'stripe_secret_key' => ['nullable', 'string', 'max:255'],

            // Aggregator (IoTec, etc.)
            'gateway_code' => [
                'nullable', 'required_if:type,aggregator', 'required_with:token_url', 'string', 'max:100',
                Rule::unique('payment_gateways', 'gateway_code')->ignore($existing?->id),
            ],
            'display_name' => ['nullable', 'required_if:type,aggregator', 'string', 'max:255'],
            'provider_type' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'sandbox_mode' => ['nullable', 'boolean'],
            'token_url' => ['nullable', 'required_if:type,aggregator', 'string', 'max:255'],
            'collect_url' => ['nullable', 'required_if:type,aggregator', 'string', 'max:255'],
            'base_url' => ['nullable', 'string', 'max:255'],
            'status_url' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'wallet_guid' => ['nullable', 'string', 'max:255'],
            'callback_url' => ['nullable', 'string', 'max:255'],
            'webhook_url' => ['nullable', 'string', 'max:255'],
            'return_url' => ['nullable', 'string', 'max:255'],
            'supports_collection' => ['nullable', 'boolean'],
            'supports_disbursement' => ['nullable', 'boolean'],
            'supports_mtn' => ['nullable', 'boolean'],
            'supports_airtel' => ['nullable', 'boolean'],
            'supported_payment_methods' => ['nullable', 'string', 'max:500'],
            'supported_currencies' => ['nullable', 'string', 'max:500'],
        ]);

        $config = match ($validated['type']) {
            'bank' => [
                'bank_name' => $validated['bank_name'] ?? null,
                'account_name' => $validated['account_name'] ?? null,
                'account_number' => $validated['account_number'] ?? null,
                'routing_or_swift' => $validated['routing_or_swift'] ?? null,
            ],
            'mobile_money' => [
                'provider_name' => $validated['provider_name'] ?? null,
                'merchant_number' => $validated['merchant_number'] ?? null,
            ],
            'card' => [
                'stripe_publishable_key' => $validated['stripe_publishable_key'] ?? null,
                // Don't overwrite a previously-saved secret key with a blank
                // resubmission - the edit form never re-displays it in full.
                'stripe_secret_key' => ! empty($validated['stripe_secret_key'])
                    ? $validated['stripe_secret_key']
                    : $existing?->configValue('stripe_secret_key'),
            ],
            default => [],
        };

        $payload = [
            'type' => $validated['type'],
            'name' => $validated['name'],
            'is_enabled' => $request->boolean('is_enabled'),
            'instructions' => $validated['instructions'] ?? null,
            'config' => $config,
        ];

        if (in_array($validated['type'], ['aggregator', 'mobile_money'], true)) {
            // gateway_code/display_name/token_url/collect_url are only
            // actually REQUIRED (validation-wise) for type=aggregator,
            // not mobile_money - but this block runs for both types,
            // and previously accessed all four unconditionally, which
            // threw "Undefined array key" the moment anyone edited an
            // existing mobile_money gateway that had never had these
            // set. Falls back to whatever was already saved (or null,
            // for a brand new one) instead.
            $payload = array_merge($payload, [
                'gateway_code' => $validated['gateway_code'] ?? $existing?->gateway_code,
                'display_name' => $validated['display_name'] ?? $existing?->display_name,
                'provider_type' => $validated['provider_type'] ?? null,
                'description' => $validated['description'] ?? null,
                'sandbox_mode' => $request->boolean('sandbox_mode'),
                'token_url' => $validated['token_url'] ?? $existing?->token_url,
                'collect_url' => $validated['collect_url'] ?? $existing?->collect_url,
                'base_url' => $validated['base_url'] ?? null,
                'status_url' => $validated['status_url'] ?? null,
                'callback_url' => $validated['callback_url'] ?? null,
                'webhook_url' => $validated['webhook_url'] ?? null,
                'return_url' => $validated['return_url'] ?? null,
                'supports_collection' => $request->boolean('supports_collection'),
                'supports_disbursement' => $request->boolean('supports_disbursement'),
                'supports_mtn' => $request->boolean('supports_mtn'),
                'supports_airtel' => $request->boolean('supports_airtel'),
                'supported_payment_methods' => $this->parseCommaList($validated['supported_payment_methods'] ?? null),
                'supported_currencies' => $this->parseCommaList($validated['supported_currencies'] ?? null),
                // Leave blank on edit to keep the existing encrypted value -
                // same "don't overwrite with blank" pattern as Stripe above.
                'client_id' => ! empty($validated['client_id']) ? $validated['client_id'] : $existing?->client_id,
                'client_secret' => ! empty($validated['client_secret']) ? $validated['client_secret'] : $existing?->client_secret,
                'wallet_guid' => ! empty($validated['wallet_guid']) ? $validated['wallet_guid'] : $existing?->wallet_guid,
            ]);
        }

        return $payload;
    }

    private function parseCommaList(?string $value): ?array
    {
        if (empty($value)) {
            return null;
        }

        $items = array_filter(array_map('trim', explode(',', $value)));

        return empty($items) ? null : array_values($items);
    }
}
