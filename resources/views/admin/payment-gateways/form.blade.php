@extends('layouts.app')

@section('title', ($gateway->exists ? 'Edit' : 'New') . ' Payment Gateway')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-credit-card text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">{{ $gateway->exists ? 'Edit' : 'New' }} Payment Gateway</h1>
    </div>

    <form method="POST"
          action="{{ $gateway->exists ? route('admin.payment-gateways.update', $gateway->id) : route('admin.payment-gateways.store') }}"
          class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 max-w-xl space-y-5">
        @csrf
        @if ($gateway->exists)
            @method('PUT')
        @endif

        <div>
            <label for="type" class="block text-sm font-medium text-slate-700 mb-1">Type</label>
            <select id="type" name="type" onchange="togglePaymentGatewayFields()"
                    class="pm-input">
                <option value="bank" @selected(old('type', $gateway->type) === 'bank')>Bank Transfer</option>
                <option value="mobile_money" @selected(old('type', $gateway->type) === 'mobile_money')>Mobile Money</option>
                <option value="card" @selected(old('type', $gateway->type) === 'card')>Card (Stripe)</option>
                <option value="aggregator" @selected(old('type', $gateway->type) === 'aggregator')>Collection Aggregator (IoTec, etc.)</option>
            </select>
        </div>

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 mb-1">
                Display Name <span class="text-red-500" aria-hidden="true">*</span>
            </label>
            <input type="text" id="name" name="name" value="{{ old('name', $gateway->name) }}"
                   placeholder="e.g. Zenith Bank, M-Pesa, Visa/Mastercard"
                   required aria-required="true"
                   class="pm-input">
            <p class="text-xs text-slate-400 mt-1">
                This gateway's own name, shown to users choosing a payment method — not your site's name.
            </p>
            @error('name')
                <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" id="is_enabled" name="is_enabled" value="1"
                   @checked(old('is_enabled', $gateway->exists ? $gateway->is_enabled : true))
                   class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
            <label for="is_enabled" class="text-sm text-slate-700">Enabled (visible to users)</label>
        </div>

        {{-- Bank fields --}}
        <fieldset id="fields-bank" class="space-y-4 border-t pt-4">
            <legend class="text-sm font-semibold text-slate-700 mb-1">Bank Details</legend>
            <div>
                <label for="bank_name" class="block text-sm font-medium text-slate-700 mb-1">Bank Name</label>
                <input type="text" id="bank_name" name="bank_name" value="{{ old('bank_name', $gateway->configValue('bank_name')) }}"
                       class="pm-input">
            </div>
            <div>
                <label for="account_name" class="block text-sm font-medium text-slate-700 mb-1">Account Name</label>
                <input type="text" id="account_name" name="account_name" value="{{ old('account_name', $gateway->configValue('account_name')) }}"
                       class="pm-input">
            </div>
            <div>
                <label for="account_number" class="block text-sm font-medium text-slate-700 mb-1">Account Number</label>
                <input type="text" id="account_number" name="account_number" value="{{ old('account_number', $gateway->configValue('account_number')) }}"
                       class="pm-input">
            </div>
            <div>
                <label for="routing_or_swift" class="block text-sm font-medium text-slate-700 mb-1">Routing / SWIFT Code</label>
                <input type="text" id="routing_or_swift" name="routing_or_swift" value="{{ old('routing_or_swift', $gateway->configValue('routing_or_swift')) }}"
                       placeholder="Optional" class="pm-input">
            </div>
        </fieldset>

        {{-- Mobile money fields --}}
        <fieldset id="fields-mobile_money" class="space-y-4 border-t pt-4">
            <legend class="text-sm font-semibold text-slate-700 mb-1">Mobile Money Details</legend>
            <div>
                <label for="provider_name" class="block text-sm font-medium text-slate-700 mb-1">Provider Name</label>
                <input type="text" id="provider_name" name="provider_name" value="{{ old('provider_name', $gateway->configValue('provider_name')) }}"
                       placeholder="e.g. M-Pesa, MTN Mobile Money"
                       class="pm-input">
            </div>
            <div>
                <label for="merchant_number" class="block text-sm font-medium text-slate-700 mb-1">Merchant / Phone Number</label>
                <input type="text" id="merchant_number" name="merchant_number" value="{{ old('merchant_number', $gateway->configValue('merchant_number')) }}"
                       class="pm-input">
            </div>
        </fieldset>

        {{-- Card (Stripe) fields --}}
        <fieldset id="fields-card" class="space-y-4 border-t pt-4">
            <legend class="text-sm font-semibold text-slate-700 mb-1">Stripe API Keys</legend>
            <div>
                <label for="stripe_publishable_key" class="block text-sm font-medium text-slate-700 mb-1">Publishable Key</label>
                <input type="text" id="stripe_publishable_key" name="stripe_publishable_key"
                       value="{{ old('stripe_publishable_key', $gateway->configValue('stripe_publishable_key')) }}"
                       placeholder="pk_live_..."
                       class="pm-input">
            </div>
            <div>
                <label for="stripe_secret_key" class="block text-sm font-medium text-slate-700 mb-1">Secret Key</label>
                <input type="password" id="stripe_secret_key" name="stripe_secret_key"
                       placeholder="{{ $gateway->configValue('stripe_secret_key') ? '•••••••• (saved — leave blank to keep it)' : 'sk_live_...' }}"
                       autocomplete="off"
                       class="pm-input">
                <p class="text-xs text-slate-400 mt-1">
                    Stored encrypted. Leave blank when editing to keep the current key.
                </p>
            </div>
        </fieldset>

        {{-- Aggregator fields (IoTec, etc.) --}}
        <fieldset id="fields-aggregator" class="space-y-4 border-t pt-4">
            <legend class="text-sm font-semibold text-slate-700 mb-1">Aggregator API (automated collection)</legend>
            <p class="text-xs text-slate-400 mb-2">
                Fill this in to collect payments automatically and instantly through a provider's API
                (e.g. IoTec) instead of the manual "customer submits a reference, admin verifies"
                flow above. Leave every field below blank to keep this gateway purely manual.
            </p>

            @if ($gateway->exists && $gateway->collectsAutomatically())
                <div class="flex items-center gap-3 mb-2">
                    @if ($gateway->is_default)
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full">
                            <i class="fa-solid fa-star" aria-hidden="true"></i> Default Mobile Money gateway
                        </span>
                    @else
                        <form method="POST" action="{{ route('admin.payment-gateways.set-default', $gateway->id) }}">
                            @csrf
                            <button type="submit" class="text-xs font-medium text-[var(--brand-1)] hover:underline">
                                Set as default Mobile Money gateway
                            </button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.payment-gateways.test-connection', $gateway->id) }}">
                        @csrf
                        <button type="submit" class="text-xs font-medium text-slate-600 hover:underline">
                            <i class="fa-solid fa-plug" aria-hidden="true"></i> Test Connection
                        </button>
                    </form>
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="gateway_code" class="block text-sm font-medium text-slate-700 mb-1">Gateway Code</label>
                    <input type="text" id="gateway_code" name="gateway_code" value="{{ old('gateway_code', $gateway->gateway_code) }}"
                           placeholder="iotec" class="pm-input">
                </div>
                <div>
                    <label for="display_name" class="block text-sm font-medium text-slate-700 mb-1">Display Name</label>
                    <input type="text" id="display_name" name="display_name" value="{{ old('display_name', $gateway->display_name) }}"
                           placeholder="IoTec Mobile Money" class="pm-input">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="provider_type" class="block text-sm font-medium text-slate-700 mb-1">Provider Type</label>
                    <input type="text" id="provider_type" name="provider_type" value="{{ old('provider_type', $gateway->provider_type) }}"
                           placeholder="Mobile Money Aggregator" class="pm-input">
                </div>
                <div class="flex items-center gap-2 pt-6">
                    <input type="checkbox" id="sandbox_mode" name="sandbox_mode" value="1"
                           @checked(old('sandbox_mode', $gateway->sandbox_mode ?? true))
                           class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                    <label for="sandbox_mode" class="text-sm text-slate-700">Sandbox mode</label>
                </div>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                <textarea id="description" name="description" rows="2" class="pm-input">{{ old('description', $gateway->description) }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="token_url" class="block text-sm font-medium text-slate-700 mb-1">Token URL</label>
                    <input type="text" id="token_url" name="token_url" value="{{ old('token_url', $gateway->token_url) }}" class="pm-input">
                </div>
                <div>
                    <label for="collect_url" class="block text-sm font-medium text-slate-700 mb-1">Collect URL</label>
                    <input type="text" id="collect_url" name="collect_url" value="{{ old('collect_url', $gateway->collect_url) }}" class="pm-input">
                </div>
                <div>
                    <label for="base_url" class="block text-sm font-medium text-slate-700 mb-1">API Base URL</label>
                    <input type="text" id="base_url" name="base_url" value="{{ old('base_url', $gateway->base_url) }}" class="pm-input">
                </div>
                <div>
                    <label for="status_url" class="block text-sm font-medium text-slate-700 mb-1">Status URL</label>
                    <input type="text" id="status_url" name="status_url" value="{{ old('status_url', $gateway->status_url) }}" class="pm-input">
                </div>
                <div>
                    <label for="callback_url" class="block text-sm font-medium text-slate-700 mb-1">Callback URL</label>
                    <input type="text" id="callback_url" name="callback_url"
                           value="{{ old('callback_url', $gateway->callback_url ?: url('/webhooks/' . ($gateway->gateway_code ?: 'iotec'))) }}" class="pm-input">
                    <p class="text-xs text-slate-400 mt-1">Where the provider sends payment status notifications — register this on their side.</p>
                </div>
                <div>
                    <label for="webhook_url" class="block text-sm font-medium text-slate-700 mb-1">Webhook URL (if separate from Callback)</label>
                    <input type="text" id="webhook_url" name="webhook_url" value="{{ old('webhook_url', $gateway->webhook_url) }}" class="pm-input">
                    <p class="text-xs text-slate-400 mt-1">
                        Some providers use one URL for both; leave blank to just use the Callback URL above for everything.
                    </p>
                </div>
                <div>
                    <label for="return_url" class="block text-sm font-medium text-slate-700 mb-1">Return URL</label>
                    <input type="text" id="return_url" name="return_url" value="{{ old('return_url', $gateway->return_url) }}" placeholder="Optional" class="pm-input">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="client_id" class="block text-sm font-medium text-slate-700 mb-1">API Key</label>
                    <input type="password" id="client_id" name="client_id" autocomplete="off"
                           placeholder="{{ $gateway->client_id ? '•••••••• (saved — leave blank to keep it)' : '' }}" class="pm-input">
                </div>
                <div>
                    <label for="client_secret" class="block text-sm font-medium text-slate-700 mb-1">API Secret</label>
                    <input type="password" id="client_secret" name="client_secret" autocomplete="off"
                           placeholder="{{ $gateway->client_secret ? '•••••••• (saved — leave blank to keep it)' : '' }}" class="pm-input">
                </div>
            </div>
            <div>
                <label for="wallet_guid" class="block text-sm font-medium text-slate-700 mb-1">Merchant / Account ID</label>
                <input type="password" id="wallet_guid" name="wallet_guid" autocomplete="off"
                       placeholder="{{ $gateway->wallet_guid ? '•••••••• (saved — leave blank to keep it)' : '' }}" class="pm-input">
                <p class="text-xs text-slate-400 mt-1">API key, secret, and merchant/account ID are stored encrypted and never shown again in full.</p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="supports_collection" value="1" @checked(old('supports_collection', $gateway->supports_collection ?? true)) class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                    Collection
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="supports_disbursement" value="1" @checked(old('supports_disbursement', $gateway->supports_disbursement ?? false)) class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                    Disbursement
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="supports_mtn" value="1" @checked(old('supports_mtn', $gateway->supports_mtn ?? true)) class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                    MTN
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="supports_airtel" value="1" @checked(old('supports_airtel', $gateway->supports_airtel ?? true)) class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                    Airtel
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label for="supported_payment_methods" class="block text-sm font-medium text-slate-700 mb-1">Supported Payment Methods</label>
                    <input type="text" id="supported_payment_methods" name="supported_payment_methods"
                           value="{{ old('supported_payment_methods', is_array($gateway->supported_payment_methods) ? implode(', ', $gateway->supported_payment_methods) : $gateway->supported_payment_methods) }}"
                           placeholder="mobile_money, card, visa, mastercard" class="pm-input text-sm">
                    <p class="text-xs text-slate-400 mt-1">
                        For ioTec use <strong>mobile_money, card, visa, mastercard</strong> when both Mobile Money and Visa/MasterCard are enabled.
                    </p>
                </div>
                <div>
                    <label for="supported_currencies" class="block text-sm font-medium text-slate-700 mb-1">Supported Currencies</label>
                    <input type="text" id="supported_currencies" name="supported_currencies"
                           value="{{ old('supported_currencies', is_array($gateway->supported_currencies) ? implode(', ', $gateway->supported_currencies) : '') }}"
                           placeholder="UGX, KES, USD" class="pm-input text-sm">
                </div>
            </div>
        </fieldset>

        <div>
            <label for="instructions" class="block text-sm font-medium text-slate-700 mb-1">
                Instructions shown to users (optional, bank/mobile money)
            </label>
            <textarea id="instructions" name="instructions" rows="3"
                      placeholder="e.g. Include your account email as the transfer reference."
                      class="pm-input">{{ old('instructions', $gateway->instructions) }}</textarea>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                Save
            </button>
            <a href="{{ route('admin.payment-gateways.index') }}" class="text-sm text-slate-500 hover:underline">Cancel</a>
        </div>
    </form>

    <script>
        function togglePaymentGatewayFields() {
            var type = document.getElementById('type').value;
            ['bank', 'mobile_money', 'card', 'aggregator'].forEach(function (t) {
                document.getElementById('fields-' + t).style.display = (t === type) ? 'block' : 'none';
            });
            // The aggregator fieldset is a second, OPTIONAL section for
            // mobile_money (automated collection on top of the manual
            // fields above) as well as the primary section for the pure
            // 'aggregator' type — shown for both, not mutually exclusive
            // with fields-mobile_money the way the others are.
            var showAggregatorFields = (type === 'mobile_money' || type === 'aggregator');
            document.getElementById('fields-aggregator').style.display = showAggregatorFields ? 'block' : 'none';
        }
        document.addEventListener('DOMContentLoaded', togglePaymentGatewayFields);
    </script>
@endsection
