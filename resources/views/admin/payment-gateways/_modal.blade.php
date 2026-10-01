@php
    $pmRawMethods = old('supported_payment_methods', $gateway->supported_payment_methods ?? []);

    if (is_string($pmRawMethods)) {
        $pmDecodedMethods = json_decode($pmRawMethods, true);
        $pmRawMethods = is_array($pmDecodedMethods)
            ? $pmDecodedMethods
            : preg_split('/\s*,\s*/', trim($pmRawMethods), -1, PREG_SPLIT_NO_EMPTY);
    }

    $pmMethodSet = collect(is_array($pmRawMethods) ? $pmRawMethods : [])
        ->map(fn ($method) => strtolower(trim((string) $method)))
        ->filter()
        ->values()
        ->all();

    $pmSupportsMobileMoney = in_array('mobile_money', $pmMethodSet, true)
        || ($pmMethodSet === [] && in_array($gateway->type, ['mobile_money', 'aggregator'], true));
    $pmSupportsVisa = in_array('visa', $pmMethodSet, true) || in_array('card', $pmMethodSet, true);
    $pmSupportsMastercard = in_array('mastercard', $pmMethodSet, true) || in_array('card', $pmMethodSet, true);
@endphp

{{--
    Shared markup for both the create modal and each gateway's own edit
    modal (see index.blade.php's @foreach) — $gateway is either a fresh
    `new PaymentGateway` (create) or an existing row (edit), $modalId is
    unique per dialog, $action/$method drive the form target.
--}}
<dialog id="{{ $modalId }}" data-gateway-action="{{ $action }}" aria-labelledby="{{ $modalId }}-title" class="pm-gateway-dialog rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/50">
    <form method="POST" action="{{ $action }}" class="pm-gateway-dialog-form">
        @csrf
        @if ($method)
            @method($method)
        @endif
        <input type="hidden" name="_dialog_action" value="{{ $action }}">

        <div class="pm-gateway-dialog-header flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-credit-card text-sm" aria-hidden="true"></i>
                </div>
                <h2 id="{{ $modalId }}-title" class="text-lg font-bold text-slate-800">{{ $gateway->exists ? 'Edit' : 'New' }} Payment Gateway</h2>
            </div>
            <button type="button" onclick="document.getElementById('{{ $modalId }}').close()"
                    class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" aria-label="Close dialog">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <div class="pm-gateway-dialog-body space-y-5">
        <div class="pm-form-tabs" role="tablist" aria-label="Gateway sections" data-pm-form-tabs>
            <button type="button" role="tab" id="{{ $modalId }}-tab-0" aria-controls="{{ $modalId }}-panel-0" aria-selected="true" tabindex="0" data-pm-form-tab="0" class="pm-form-tab is-active">General</button>
            <button type="button" role="tab" id="{{ $modalId }}-tab-1" aria-controls="{{ $modalId }}-panel-1" aria-selected="false" tabindex="-1" data-pm-form-tab="1" data-details-tab class="pm-form-tab">Payment Details</button>
            <button type="button" role="tab" id="{{ $modalId }}-tab-2" aria-controls="{{ $modalId }}-panel-2" aria-selected="false" tabindex="-1" data-pm-form-tab="2" data-aggregator-tab class="pm-form-tab">API Connection</button>
            <button type="button" role="tab" id="{{ $modalId }}-tab-3" aria-controls="{{ $modalId }}-panel-3" aria-selected="false" tabindex="-1" data-pm-form-tab="3" data-aggregator-tab class="pm-form-tab">Credentials &amp; Channels</button>
        </div>

        <div class="pm-form-panel" role="tabpanel" id="{{ $modalId }}-panel-0" aria-labelledby="{{ $modalId }}-tab-0" data-pm-form-panel="0">
            <div>
                <label for="type-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                <select id="type-{{ $modalId }}" name="type" onchange="pmToggleGatewayFields('{{ $modalId }}')" class="pm-input">
                    <option value="bank" @selected(old('type', $gateway->type) === 'bank')>Bank Transfer</option>
                    <option value="mobile_money" @selected(old('type', $gateway->type) === 'mobile_money')>Mobile Money</option>
                    <option value="card" @selected(old('type', $gateway->type) === 'card')>Card (Stripe)</option>
                    <option value="aggregator" @selected(old('type', $gateway->type) === 'aggregator')>Collection Aggregator (IoTec, etc.)</option>
                </select>
            </div>

            <div>
                <label for="name-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">
                    Display Name <span class="text-rose-500" aria-hidden="true">*</span>
                </label>
                <input type="text" id="name-{{ $modalId }}" name="name" value="{{ old('name', $gateway->name) }}"
                       placeholder="e.g. Zenith Bank, M-Pesa, Visa/Mastercard"
                       required aria-required="true"
                       class="pm-input">
                @error('name')
                    <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" id="is_enabled-{{ $modalId }}" name="is_enabled" value="1"
                       @checked(old('is_enabled', $gateway->exists ? $gateway->is_enabled : true))
                       class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                <label for="is_enabled-{{ $modalId }}" class="text-sm text-slate-700">Enabled (visible to users)</label>
            </div>

                <label for="instructions-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">
                    Instructions shown to users
                </label>
                <textarea id="instructions-{{ $modalId }}" name="instructions" rows="3"
                          placeholder="e.g. Use your account email as the transfer reference (optional)"
                          class="pm-input">{{ old('instructions', $gateway->instructions) }}</textarea>
            </div>
        </div>

        <div class="pm-form-panel" role="tabpanel" id="{{ $modalId }}-panel-1" aria-labelledby="{{ $modalId }}-tab-1" data-pm-form-panel="1" hidden>
            <div id="fields-bank-{{ $modalId }}" class="pm-gateway-fields space-y-4" data-type="bank">
                <p class="text-sm font-semibold text-slate-700 mb-1">Bank Details</p>
                <div>
                    <label for="bank_name-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Bank Name</label>
                    <input type="text" id="bank_name-{{ $modalId }}" name="bank_name" value="{{ old('bank_name', $gateway->configValue('bank_name')) }}" class="pm-input">
                </div>
                <div>
                    <label for="account_name-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Account Name</label>
                    <input type="text" id="account_name-{{ $modalId }}" name="account_name" value="{{ old('account_name', $gateway->configValue('account_name')) }}" class="pm-input">
                </div>
                <div>
                    <label for="account_number-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Account Number</label>
                    <input type="text" id="account_number-{{ $modalId }}" name="account_number" value="{{ old('account_number', $gateway->configValue('account_number')) }}" class="pm-input">
                </div>
                <div>
                    <label for="routing_or_swift-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Routing / SWIFT Code</label>
                    <input type="text" id="routing_or_swift-{{ $modalId }}" name="routing_or_swift" value="{{ old('routing_or_swift', $gateway->configValue('routing_or_swift')) }}" placeholder="Optional" class="pm-input">
                </div>
            </div>

            <div id="fields-mobile_money-{{ $modalId }}" class="pm-gateway-fields space-y-4" data-type="mobile_money">
                <p class="text-sm font-semibold text-slate-700 mb-1">Mobile Money Details</p>
                <div>
                    <label for="provider_name-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Provider Name</label>
                    <input type="text" id="provider_name-{{ $modalId }}" name="provider_name" value="{{ old('provider_name', $gateway->configValue('provider_name')) }}"
                           placeholder="e.g. M-Pesa, MTN Mobile Money" class="pm-input">
                </div>
                <div>
                    <label for="merchant_number-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Merchant / Phone Number</label>
                    <input type="text" id="merchant_number-{{ $modalId }}" name="merchant_number" value="{{ old('merchant_number', $gateway->configValue('merchant_number')) }}" class="pm-input">
                </div>
            </div>

            <div id="fields-card-{{ $modalId }}" class="pm-gateway-fields space-y-4" data-type="card">
                <p class="text-sm font-semibold text-slate-700 mb-1">Stripe API Keys</p>
                <div>
                    <label for="stripe_publishable_key-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Publishable Key</label>
                    <input type="text" id="stripe_publishable_key-{{ $modalId }}" name="stripe_publishable_key"
                           value="{{ old('stripe_publishable_key', $gateway->configValue('stripe_publishable_key')) }}"
                           placeholder="pk_live_..." class="pm-input">
                </div>
                <div>
                    <label for="stripe_secret_key-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Secret Key</label>
                    <input type="password" id="stripe_secret_key-{{ $modalId }}" name="stripe_secret_key"
                           placeholder="{{ $gateway->configValue('stripe_secret_key') ? '•••••••• (saved — leave blank to keep it)' : 'sk_live_...' }}"
                           autocomplete="off" class="pm-input">
                </div>
            </div>

            <div>
        </div>

        <div class="pm-form-panel" role="tabpanel" id="{{ $modalId }}-panel-2" aria-labelledby="{{ $modalId }}-tab-2" data-pm-form-panel="2" hidden>
            {{--
                Shown for BOTH mobile_money and aggregator types (see
                pmToggleGatewayFields() below, which treats this fieldset
                specially rather than mutually-exclusively like the others)
                — mobile_money can OPTIONALLY be upgraded from the purely
                manual flow above to automated collection through an
                aggregator's API, without that being a separate gateway
                "type" of its own.
            --}}
            <div id="fields-aggregator-{{ $modalId }}" class="pm-gateway-fields space-y-4" data-type="aggregator">
                <div class="pm-aggregator-warning rounded-lg bg-amber-50 border border-amber-200 text-amber-900 px-3 py-2 text-xs mb-2" style="display: none;">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    Some but not all of Token URL, Collect URL, API Key, and API Secret are filled in.
                    <strong>All four</strong> are required for automated collection to actually activate —
                    with only some set, this gateway will silently stay in manual mode.
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="gateway_code-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Gateway Code</label>
                        <input type="text" id="gateway_code-{{ $modalId }}" name="gateway_code" value="{{ old('gateway_code', $gateway->gateway_code) }}"
                               placeholder="iotec" class="pm-input">
                    </div>
                    <div>
                        <label for="agg_display_name-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Aggregator Display Name</label>
                        <input type="text" id="agg_display_name-{{ $modalId }}" name="display_name" value="{{ old('display_name', $gateway->display_name) }}"
                               placeholder="IoTec Mobile Money" class="pm-input">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="provider_type-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Provider Type</label>
                        <input type="text" id="provider_type-{{ $modalId }}" name="provider_type" value="{{ old('provider_type', $gateway->provider_type) }}"
                               placeholder="Mobile Money Aggregator" class="pm-input">
                    </div>
                    <div class="flex items-center gap-2 pt-6">
                        <input type="checkbox" id="sandbox_mode-{{ $modalId }}" name="sandbox_mode" value="1"
                               @checked(old('sandbox_mode', $gateway->sandbox_mode ?? true))
                               class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                        <label for="sandbox_mode-{{ $modalId }}" class="text-sm text-slate-700">Sandbox mode</label>
                    </div>
                </div>

                <div>
                    <label for="agg_description-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea id="agg_description-{{ $modalId }}" name="description" rows="2" class="pm-input">{{ old('description', $gateway->description) }}</textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="token_url-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Token URL</label>
                        <input type="text" id="token_url-{{ $modalId }}" name="token_url" value="{{ old('token_url', $gateway->token_url) }}" class="pm-input">
                    </div>
                    <div>
                        <label for="collect_url-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Collect URL</label>
                        <input type="text" id="collect_url-{{ $modalId }}" name="collect_url" value="{{ old('collect_url', $gateway->collect_url) }}" class="pm-input">
                    </div>
                    <div>
                        <label for="base_url-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">API Base URL</label>
                        <input type="text" id="base_url-{{ $modalId }}" name="base_url" value="{{ old('base_url', $gateway->base_url) }}" class="pm-input">
                    </div>
                    <div>
                        <label for="status_url-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Status URL</label>
                        <input type="text" id="status_url-{{ $modalId }}" name="status_url" value="{{ old('status_url', $gateway->status_url) }}" class="pm-input">
                    </div>
                    <div>
                        <label for="callback_url-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Callback URL</label>
                        <input type="text" id="callback_url-{{ $modalId }}" name="callback_url" title="Register this URL with the provider"
                               value="{{ old('callback_url', $gateway->callback_url ?: url('/webhooks/' . ($gateway->gateway_code ?: 'iotec'))) }}" class="pm-input">
                    </div>
                    <div>
                        <label for="webhook_url-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Webhook URL</label>
                        <input type="text" id="webhook_url-{{ $modalId }}" name="webhook_url" value="{{ old('webhook_url', $gateway->webhook_url) }}" placeholder="Blank = use the Callback URL" class="pm-input">
                    </div>
                    <div>
                        <label for="return_url-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Return URL</label>
                        <input type="text" id="return_url-{{ $modalId }}" name="return_url" value="{{ old('return_url', $gateway->return_url) }}" placeholder="Optional" class="pm-input">
                    </div>
                </div>
            </div>
        </div>

        <div class="pm-form-panel" role="tabpanel" id="{{ $modalId }}-panel-3" aria-labelledby="{{ $modalId }}-tab-3" data-pm-form-panel="3" hidden>
            <div class="pm-gateway-fields space-y-4" data-type="aggregator" data-gateway-part="credentials">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="client_id-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">API Key</label>
                        <input type="password" id="client_id-{{ $modalId }}" name="client_id" autocomplete="off"
                               placeholder="{{ $gateway->client_id ? '•••••••• (saved — leave blank to keep it)' : '' }}" class="pm-input">
                    </div>
                    <div>
                        <label for="client_secret-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">API Secret</label>
                        <input type="password" id="client_secret-{{ $modalId }}" name="client_secret" autocomplete="off"
                               placeholder="{{ $gateway->client_secret ? '•••••••• (saved — leave blank to keep it)' : '' }}" class="pm-input">
                    </div>
                </div>
                <div>
                    <label for="wallet_guid-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Merchant / Account ID</label>
                    <input type="password" id="wallet_guid-{{ $modalId }}" name="wallet_guid" autocomplete="off"
                           placeholder="{{ $gateway->wallet_guid ? '•••••••• (saved — leave blank to keep it)' : '' }}" class="pm-input">
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

                <div class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 mt-2">
                    <div class="flex items-start gap-3 mb-3">
                        <div class="w-9 h-9 rounded-lg bg-white text-indigo-600 border border-indigo-100 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">ioTec Payment Channels</p>
                        </div>
                    </div>

                    <input type="hidden" id="supported_payment_methods-{{ $modalId }}" name="supported_payment_methods" value="">

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <label class="pm-payment-channel-option flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 cursor-pointer">
                            <input type="checkbox" class="pm-method-checkbox rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]" data-method="mobile_money" @checked($pmSupportsMobileMoney)>
                            <span class="flex items-center gap-2 text-sm font-medium text-slate-700">
                                <i class="fa-solid fa-mobile-screen-button text-amber-500" aria-hidden="true"></i> Mobile Money
                            </span>
                        </label>
                        <label class="pm-payment-channel-option flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 cursor-pointer">
                            <input type="checkbox" class="pm-method-checkbox rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]" data-method="visa" @checked($pmSupportsVisa)>
                            <span class="flex items-center gap-2 text-sm font-medium text-slate-700">
                                <i class="fa-brands fa-cc-visa text-blue-700 text-xl" aria-hidden="true"></i> Visa
                            </span>
                        </label>
                        <label class="pm-payment-channel-option flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 cursor-pointer">
                            <input type="checkbox" class="pm-method-checkbox rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]" data-method="mastercard" @checked($pmSupportsMastercard)>
                            <span class="flex items-center gap-2 text-sm font-medium text-slate-700">
                                <i class="fa-brands fa-cc-mastercard text-orange-600 text-xl" aria-hidden="true"></i> MasterCard
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        </div>

        <div class="pm-gateway-dialog-footer flex items-center justify-end gap-3">
            <button type="button" onclick="document.getElementById('{{ $modalId }}').close()" class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 transition-colors">
                Cancel
            </button>
            <button type="submit" class="inline-flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <span>Save</span>
            </button>
        </div>
    </form>
</dialog>

@include('partials.form-tabs')

<script>
    function pmSyncPaymentMethods(modalId) {
        var dialog = document.getElementById(modalId);
        if (!dialog) return;

        var hidden = dialog.querySelector('#supported_payment_methods-' + modalId);
        if (!hidden) return;

        var methods = [];
        dialog.querySelectorAll('.pm-method-checkbox:checked').forEach(function (checkbox) {
            var method = checkbox.dataset.method;
            if (method && methods.indexOf(method) === -1) methods.push(method);
        });

        if ((methods.indexOf('visa') !== -1 || methods.indexOf('mastercard') !== -1) && methods.indexOf('card') === -1) {
            methods.push('card');
        }

        hidden.value = methods.join(', ');
    }

    function pmToggleGatewayFields(modalId) {
        var dialog = document.getElementById(modalId);
        var type = dialog.querySelector('[id^="type-"]').value;
        dialog.querySelectorAll('.pm-gateway-fields').forEach(function (el) {
            el.style.display = (el.dataset.type === type) ? 'block' : 'none';
        });
        // The aggregator fieldset is a second, OPTIONAL section for
        // mobile_money (automated collection on top of the manual
        // fields above) as well as the primary section for the pure
        // 'aggregator' type — shown for both, unlike the loop above
        // which otherwise only shows the one exactly-matching type.
        var usesAggregator = type === 'mobile_money' || type === 'aggregator';
        dialog.querySelectorAll('[data-type="aggregator"]').forEach(function (el) {
            el.style.display = usesAggregator ? 'block' : 'none';
        });

        // The API tabs only apply to aggregator-capable gateway types, and a
        // pure aggregator has no manual Payment Details of its own.
        dialog.querySelectorAll('[data-aggregator-tab], [data-details-tab]').forEach(function (tab) {
            var applies = tab.hasAttribute('data-details-tab') ? type !== 'aggregator' : usesAggregator;
            tab.hidden = !applies;
            if (!applies && tab.classList.contains('is-active')) {
                var first = dialog.querySelector('[data-pm-form-tab="0"]');
                if (first) { first.click(); }
            }
        });
        pmCheckAggregatorCompleteness(modalId);
    }

    // Warns if the aggregator section is PARTIALLY filled — e.g.
    // token_url set but collect_url left blank — since
    // PaymentGateway::isConfigured() requires ALL FOUR fields
    // (client_id, client_secret, token_url, collect_url) before
    // collectsAutomatically() ever returns true, and silently falling
    // back to the manual flow with no explanation is confusing.
    function pmCheckAggregatorCompleteness(modalId) {
        var dialog = document.getElementById(modalId);
        var warning = dialog.querySelector('.pm-aggregator-warning');
        if (!warning) return;

        var fieldIds = ['token_url-', 'collect_url-', 'client_id-', 'client_secret-'];
        var isSet = fieldIds.map(function (prefix) {
            var el = dialog.querySelector('#' + prefix + modalId);
            if (!el) return false;
            // A password field showing the "saved" placeholder already
            // has a value server-side even though it's visually blank
            // right now — only a genuinely never-set field with no
            // "saved" placeholder AND no typed value counts as missing.
            var hasSavedPlaceholder = (el.placeholder || '').indexOf('saved') !== -1;
            return el.value.trim() !== '' || hasSavedPlaceholder;
        });

        var filledCount = isSet.filter(Boolean).length;
        var isPartial = filledCount > 0 && filledCount < fieldIds.length;
        warning.style.display = isPartial ? 'block' : 'none';
    }

    document.addEventListener('DOMContentLoaded', function () {
        pmToggleGatewayFields('{{ $modalId }}');
        pmSyncPaymentMethods('{{ $modalId }}');
        document.getElementById('{{ $modalId }}')?.querySelectorAll('.pm-method-checkbox').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () { pmSyncPaymentMethods('{{ $modalId }}'); });
        });

        ['token_url-{{ $modalId }}', 'collect_url-{{ $modalId }}', 'client_id-{{ $modalId }}', 'client_secret-{{ $modalId }}'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('input', function () { pmCheckAggregatorCompleteness('{{ $modalId }}'); });
        });
    });
</script>
