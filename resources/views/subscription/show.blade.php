@extends('layouts.app')

@section('title', 'Subscription')

@section('content')

@php
    $subscriptionUser = auth()->user();
    $teamPlan = $subscriptionUser?->subscriptionPlan;
    $teamSeatCount = (int) ($teamPlan?->included_seats ?? 0);
    $teamPlanName = strtolower((string) ($teamPlan?->name ?? ''));
    $teamCategory = strtolower((string) ($teamPlan?->category ?? ''));

    $canManageTeam =
        $teamSeatCount > 1
        || in_array($teamCategory, ['family','team','small_team','organization','organisation','enterprise'], true)
        || str_contains($teamPlanName, 'family')
        || str_contains($teamPlanName, 'team')
        || str_contains($teamPlanName, 'organization')
        || str_contains($teamPlanName, 'organisation')
        || str_contains($teamPlanName, 'enterprise');
@endphp

@if($canManageTeam && Route::has('organization.show'))
    <div class="mb-4 rounded-2xl border border-teal-200 bg-teal-50 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-sm font-black text-slate-900">
                    Manage your members
                </div>
                <div class="mt-1 text-xs text-slate-600">
                    {{ $teamPlan?->name ?? 'Team plan' }}
                    @if($teamSeatCount > 0)
                        includes up to {{ $teamSeatCount }} member seats.
                    @endif
                </div>
            </div>
            <a
                href="{{ route('organization.show') }}"
                class="btn-primary rounded-xl px-4 py-2.5 text-sm font-bold text-white"
            >
                <i class="fa-solid fa-users-gear mr-1"></i>
                Add & Assign Members
            </a>
        </div>
    </div>
@endif

    <div class="max-w-4xl mx-auto space-y-6">
        @if (session('error'))
            <x-alert type="error" :message="session('error')" :dismissible="false" :autoDismiss="false" />
        @endif

        @if (session('success'))
            <x-alert type="success" :message="session('success')" :dismissible="false" :autoDismiss="false" />
        @endif

        @if ($errors->has('payment'))
            <x-alert type="error" :message="$errors->first('payment')" :dismissible="false" :autoDismiss="false" />
        @endif

        @if ($errors->has('beneficiary_email'))
            <x-alert type="error" :message="$errors->first('beneficiary_email')" :dismissible="false" :autoDismiss="false" />
        @endif

        <div role="tablist" aria-label="Subscription sections" class="flex items-center gap-1 border-b border-slate-200 mb-2">
            <button type="button" role="tab" id="pm-sub-tab-subscription" aria-controls="pm-sub-panel-subscription" aria-selected="true" tabindex="0" data-tab="subscription"
                    onclick="pmSelectSubTab('subscription')" onkeydown="pmSubTabKeydown(event, 'subscription')"
                    class="pm-sub-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-[var(--brand-1)] text-[var(--brand-1)]">
                <i class="fa-solid fa-crown" aria-hidden="true"></i> Your Subscription
            </button>
            <button type="button" role="tab" id="pm-sub-tab-billing" aria-controls="pm-sub-panel-billing" aria-selected="false" tabindex="-1" data-tab="billing"
                    onclick="pmSelectSubTab('billing')" onkeydown="pmSubTabKeydown(event, 'billing')"
                    class="pm-sub-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Invoices &amp; Receipts
            </button>
        </div>

        <div role="tabpanel" id="pm-sub-panel-subscription" aria-labelledby="pm-sub-tab-subscription" tabindex="0" class="pm-sub-panel space-y-6">
        <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-8">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shadow-sm shrink-0">
                    <i class="fa-solid fa-crown text-lg" aria-hidden="true"></i>
                </div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Your Subscription</h1>
            </div>

            @if ($user->isSuspended())
                <p class="text-rose-700 mb-2">Your account has been suspended.</p>
                <p class="text-sm text-slate-500">Contact support if you believe this is a mistake.</p>
            @elseif ($user->subscription_status === 'active'
                && (! $user->subscription_expires_at || $user->subscription_expires_at->isFuture()))
                <p class="text-emerald-700 mb-2">You're subscribed thanks for being a member!</p>
                <p class="text-sm text-slate-500 mb-6">
                    @if ($user->subscriptionPlan)
                        Plan: <strong>{{ $user->subscriptionPlan->name }}</strong>.
                    @endif
                    @if ($user->subscription_expires_at)
                        Renews/expires <strong>{{ $user->subscription_expires_at->format('Y-m-d') }}</strong>
                        (<x-countdown :date="$user->subscription_expires_at" />).
                    @elseif ($user->subscriptionPlan && $user->subscriptionPlan->isLifetime())
                        Lifetime access never expires.
                    @endif
                </p>

                @if ($user->subscription_expires_at && $user->subscriptionPlan && ! $user->subscriptionPlan->isLifetime())
                    <div class="mt-5 mb-5 rounded-2xl border {{ $autoRenewEnabled ? 'border-emerald-200 bg-emerald-50/70' : 'border-slate-200 bg-slate-50' }} overflow-hidden">
                        <div class="p-4 sm:p-5">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                                <div class="flex gap-3">
                                    <div class="w-10 h-10 rounded-xl {{ $autoRenewEnabled ? 'bg-emerald-100 text-emerald-700' : 'bg-white text-slate-500' }} border border-slate-200 flex items-center justify-center shrink-0">
                                        <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                                    </div>
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h2 class="font-bold text-slate-800">Auto Renewal</h2>
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold {{ $autoRenewEnabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                                {{ $autoRenewEnabled ? 'ON' : 'OFF' }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-slate-600 mt-1">
                                            When your subscription reaches its expiry date, My Digital Diary can automatically start the next renewal payment.
                                        </p>
                                        <p class="text-xs text-slate-500 mt-2">
                                            For Mobile Money, a payment prompt is sent to your saved number. You approve it on your phone; your PIN is never stored here.
                                            Your subscription is renewed only after the payment gateway confirms success.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            @if ($errors->has('auto_renew'))
                                <div class="mt-3">
                                    <x-alert type="error" :message="$errors->first('auto_renew')" :dismissible="false" :autoDismiss="false" />
                                </div>
                            @endif

                            @if ($autoRenewGateway)
                                <form method="POST" action="{{ route('subscription.auto-renew') }}" class="mt-4">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="enabled" value="{{ $autoRenewEnabled ? 0 : 1 }}">

                                    @unless ($autoRenewEnabled)
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                                            <div>
                                                <label for="auto-renew-network" class="block text-xs font-semibold text-slate-600 mb-1">
                                                    Mobile Money network
                                                </label>
                                                <select id="auto-renew-network" name="network" class="pm-input" required>
                                                    @if ($autoRenewGateway->supports_mtn)
                                                        <option value="mtn" @selected($autoRenewNetwork === 'mtn')>MTN Mobile Money</option>
                                                    @endif
                                                    @if ($autoRenewGateway->supports_airtel)
                                                        <option value="airtel" @selected($autoRenewNetwork === 'airtel')>Airtel Money</option>
                                                    @endif
                                                </select>
                                            </div>
                                            <div>
                                                <label for="auto-renew-phone" class="block text-xs font-semibold text-slate-600 mb-1">
                                                    Renewal phone number
                                                </label>
                                                <input
                                                    id="auto-renew-phone"
                                                    type="text"
                                                    name="phone_number"
                                                    value="{{ old('phone_number', $autoRenewPhone) }}"
                                                    placeholder="e.g. 0700000000"
                                                    class="pm-input"
                                                    required
                                                >
                                            </div>
                                        </div>
                                    @else
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3 text-sm">
                                            <div class="rounded-xl bg-white/80 border border-emerald-100 p-3">
                                                <p class="text-xs text-slate-400 uppercase tracking-wide">Next renewal</p>
                                                <p class="font-semibold text-slate-800 mt-1">{{ $user->subscription_expires_at->format('Y-m-d') }}</p>
                                                <x-countdown :date="$user->subscription_expires_at" />
                                            </div>
                                            <div class="rounded-xl bg-white/80 border border-emerald-100 p-3">
                                                <p class="text-xs text-slate-400 uppercase tracking-wide">Network</p>
                                                <p class="font-semibold text-slate-800 mt-1">{{ strtoupper($autoRenewNetwork) }}</p>
                                            </div>
                                            <div class="rounded-xl bg-white/80 border border-emerald-100 p-3">
                                                <p class="text-xs text-slate-400 uppercase tracking-wide">Renewal phone</p>
                                                <p class="font-semibold text-slate-800 mt-1">{{ $autoRenewPhone ?: '—' }}</p>
                                            </div>
                                        </div>
                                    @endunless

                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors
                                               {{ $autoRenewEnabled
                                                    ? 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-100'
                                                    : 'bg-emerald-600 text-white hover:bg-emerald-700' }}"
                                    >
                                        <i class="fa-solid {{ $autoRenewEnabled ? 'fa-toggle-off' : 'fa-toggle-on' }}" aria-hidden="true"></i>
                                        {{ $autoRenewEnabled ? 'Turn Off Auto Renewal' : 'Enable Auto Renewal' }}
                                    </button>
                                </form>
                            @else
                                <div class="mt-4">
                                    <x-alert type="warning" message="Auto renewal will become available when an automatic Mobile Money gateway is enabled by the administrator." :dismissible="false" :autoDismiss="false" />
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($plans->isNotEmpty())
                    <div class="mt-6 mb-6 rounded-2xl border border-blue-200 bg-blue-50/70 p-4 sm:p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-3">
                                <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white text-blue-600 shadow-sm">
                                    <i class="fa-solid fa-arrow-up-right-dots" aria-hidden="true"></i>
                                </div>

                                <div>
                                    <h2 class="font-bold text-slate-800">
                                        Upgrade or renew anytime
                                    </h2>
                                    <p class="mt-1 text-sm text-slate-600">
                                        Your current subscription remains active while you choose and pay for another plan.
                                        A new plan is applied only after payment succeeds.
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        When renewing before expiry, your remaining paid time is preserved and the new plan period
                                        is added from your current expiry date.
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                onclick="document.getElementById('pm-active-upgrade-plans').classList.toggle('hidden')"
                                class="btn-primary inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white"
                            >
                                <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                                Renew / Change Plan
                            </button>
                        </div>

                        <div id="pm-active-upgrade-plans" class="hidden mt-5 rounded-xl border border-blue-100 bg-white p-4">
                            @include('subscription.partials.plan-selection', [
                                'planSelectionTitle' => 'Choose your renewal or upgrade plan',
                                'planSelectionSubtitle' => 'Select any enabled plan. Checkout will show Mobile Money and Visa / MasterCard when ioTec supports them.',
                            ])
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('subscription.cancel') }}"
                      data-confirm="Cancel your subscription?" data-confirm-title="Cancel subscription?" data-confirm-text="Cancel subscription">
                    @csrf
                    <button type="submit" class="text-sm text-rose-600 hover:text-rose-700 font-semibold">Cancel subscription</button>
                </form>
            @elseif ($user->subscription_status === 'active')
                <p class="text-rose-700 mb-2">
                    Your {{ $user->subscriptionPlan?->name ?? 'plan' }} plan lapsed on
                    <strong>{{ $user->subscription_expires_at?->format('Y-m-d') }}</strong>.
                </p>
                <p class="text-sm text-slate-500 mb-6">
                    Renew to restore full access to your dashboard and all modules.
                </p>

                @include('subscription.partials.plan-selection', [
                    'planSelectionTitle' => 'Renew your plan',
                    'planSelectionSubtitle' => null,
                ])
            @else
                @if ($user->onTrial())
                    <p class="text-slate-600 mb-6">
                        You have <span class="font-semibold">{{ $user->trialDaysLeft() }} day(s)</span> left in your free trial.
                        Subscribe any time to keep uninterrupted access.
                    </p>
                @else
                    <p class="text-rose-700 mb-6">
                        Your free trial has ended. Subscribe to keep access to your dashboard and all modules.
                    </p>
                @endif

                <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 mb-6">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div><p class="text-xs uppercase tracking-wide font-bold text-emerald-700">Your value this month</p><p class="text-sm text-slate-600">See what My Digital Diary is already helping you manage.</p></div>
                        <i class="fa-solid fa-sparkles text-emerald-600"></i>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-sm">
                        <div><p class="text-slate-400 text-xs">Tasks completed</p><p class="font-bold text-slate-800">{{ $valueSummary['tasks_completed'] ?? 0 }}</p></div>
                        <div><p class="text-slate-400 text-xs">Expenses tracked</p><p class="font-bold text-slate-800">{{ format_money($valueSummary['expenses_tracked'] ?? 0) }}</p></div>
                        <div><p class="text-slate-400 text-xs">Saved</p><p class="font-bold text-slate-800">{{ format_money($valueSummary['saved'] ?? 0) }}</p></div>
                        <div><p class="text-slate-400 text-xs">AI plans</p><p class="font-bold text-slate-800">{{ $valueSummary['ai_plans'] ?? 0 }}</p></div>
                        <div><p class="text-slate-400 text-xs">Meetings</p><p class="font-bold text-slate-800">{{ $valueSummary['meetings'] ?? 0 }}</p></div>
                    </div>
                </div>

                @include('subscription.partials.plan-selection', [
                    'planSelectionTitle' => '1. Choose a plan',
                    'planSelectionSubtitle' => null,
                ])
            @endif
        </div>

        {{-- ================= CHECKOUT MODAL ================= --}}
        <dialog
            id="pm-checkout-modal"
            class="pm-checkout-dialog rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/50"
        >
            <div class="pm-checkout-scroll p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-slate-800">Checkout</h2>
                    <button type="button" onclick="document.getElementById('pm-checkout-modal').close()" class="text-slate-400 hover:text-slate-600" aria-label="Close">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                {{-- Order summary --}}
                <div class="bg-slate-50 border border-slate-100 rounded-xl p-4 mb-5 text-sm space-y-1.5">
                    <div class="flex justify-between"><span class="text-slate-500">Package</span><strong id="pm-checkout-plan-name" class="text-slate-800"></strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">Billing Period</span><strong id="pm-checkout-plan-period" class="text-slate-800"></strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">Payment Method</span><strong id="pm-checkout-method-label" class="text-slate-800">—</strong></div>
                    <div class="flex justify-between pt-1.5 border-t border-slate-200 mt-1.5">
                        <span class="text-slate-700 font-medium">Total Payable</span>
                        <strong id="pm-checkout-plan-amount" class="text-[var(--brand-1)] text-base"></strong>
                    </div>
                </div>

                {{-- Optional: pay for another existing user. The payer keeps the
                     invoice and receipt; the subscription is activated on them. --}}
                <div class="mb-5 rounded-xl border border-slate-200 p-4 text-sm">
                    <label class="flex items-center gap-2 font-medium text-slate-700 cursor-pointer">
                        <input type="checkbox" id="pm-beneficiary-toggle" onchange="pmToggleBeneficiary(this.checked)">
                        <span>I'm paying for someone else</span>
                    </label>

                    <div id="pm-beneficiary-fields" class="mt-3 space-y-2" hidden>
                        <label for="pm-beneficiary-email" class="block text-slate-600">
                            Their My Digital Diary account email
                        </label>
                        <div class="flex gap-2">
                            <input
                                type="email"
                                id="pm-beneficiary-email"
                                value="{{ old('beneficiary_email') }}"
                                placeholder="name@example.com"
                                class="pm-input flex-1"
                                autocomplete="off"
                                oninput="pmBeneficiaryChanged()"
                            >
                            <button type="button" onclick="pmCheckBeneficiary()" class="px-3 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50">
                                Check
                            </button>
                        </div>
                        <p id="pm-beneficiary-result" class="text-xs text-slate-500" aria-live="polite">
                            Their subscription is activated once payment completes. You keep the invoice and receipt.
                        </p>
                    </div>
                </div>

                @if ($plans->isEmpty() || $gateways->isEmpty())
                    {{-- No payment gateways configured demo fallback, unchanged from before. --}}
                    <form method="POST" action="{{ route('subscription.subscribe') }}">
                        @csrf
                        <input type="hidden" name="plan_id" class="pm-plan-id-input" value="{{ $plans->first()?->id }}">
                        <button type="submit" class="inline-flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all w-full justify-center">
                            <i class="fa-solid fa-rocket" aria-hidden="true"></i>
                            <span>Subscribe</span>
                        </button>
                    </form>
                    <p class="text-xs text-slate-400 mt-4">
                        Demo checkout this flips your account to "active" immediately with no real charge.
                        An admin can configure real payment gateways at Admin &rarr; Payment Gateways.
                    </p>
                @else
                    @php
                        /*
                         * Build checkout choices from enabled gateways.
                         *
                         * ioTec is one aggregator gateway, but it can expose
                         * more than one customer-facing payment channel.
                         * supported_payment_methods may be stored as JSON,
                         * CSV or an array depending on the gateway record.
                         */
                        $normaliseGatewayMethods = static function ($gateway): array {
                            $raw = $gateway->supported_payment_methods ?? [];

                            if (is_array($raw)) {
                                $methods = $raw;
                            } elseif (is_string($raw)) {
                                $decoded = json_decode($raw, true);
                                $methods = is_array($decoded)
                                    ? $decoded
                                    : preg_split('/[\s,;|]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
                            } else {
                                $methods = [];
                            }

                            return collect($methods)
                                ->map(fn ($method) => strtolower(trim((string) $method)))
                                ->filter()
                                ->unique()
                                ->values()
                                ->all();
                        };

                        $gatewayCode = static fn ($gateway): string =>
                            strtolower(trim((string) ($gateway->gateway_code ?? '')));

                        $isIoTecGateway = static fn ($gateway): bool =>
                            $gatewayCode($gateway) === 'iotec';

                        $supportsIoTecMobileMoney = static function ($gateway) use ($normaliseGatewayMethods): bool {
                            $methods = $normaliseGatewayMethods($gateway);

                            return in_array('mobile_money', $methods, true)
                                || in_array('mtn', $methods, true)
                                || in_array('airtel', $methods, true)
                                || (bool) ($gateway->supports_mtn ?? false)
                                || (bool) ($gateway->supports_airtel ?? false);
                        };

                        $supportsIoTecCard = static function ($gateway) use ($normaliseGatewayMethods): bool {
                            $methods = $normaliseGatewayMethods($gateway);

                            return count(array_intersect(
                                $methods,
                                ['card', 'visa', 'mastercard', 'visa_mastercard']
                            )) > 0;
                        };

                        $checkoutChoices = collect();

                        foreach ($gateways as $gateway) {
                            if ($isIoTecGateway($gateway)) {
                                if ($supportsIoTecMobileMoney($gateway)) {
                                    $checkoutChoices->push([
                                        'key' => 'iotec-mobile-' . $gateway->id,
                                        'gateway' => $gateway,
                                        'channel' => 'mobile_money',
                                        'label' => 'Mobile Money',
                                        'description' => 'MTN / Airtel instant payment prompt',
                                        'icon' => 'fa-mobile-screen-button',
                                        'icon_class' => 'text-amber-500',
                                    ]);
                                }

                                if ($supportsIoTecCard($gateway)) {
                                    $checkoutChoices->push([
                                        'key' => 'iotec-card-' . $gateway->id,
                                        'gateway' => $gateway,
                                        'channel' => 'card',
                                        'label' => 'Visa / MasterCard',
                                        'description' => 'Secure card payment via ioTec',
                                        'icon' => 'fa-credit-card',
                                        'icon_class' => 'text-indigo-500',
                                    ]);
                                }

                                continue;
                            }

                            $checkoutChoices->push([
                                'key' => 'gateway-' . $gateway->id,
                                'gateway' => $gateway,
                                'channel' => null,
                                'label' => $gateway->display_name ?: $gateway->name,
                                'description' => match(true) {
                                    $gateway->isCard() => 'Card payment',
                                    $gateway->collectsAutomatically() => 'Mobile Money instant',
                                    $gateway->type === 'bank' => 'Bank transfer',
                                    default => 'Mobile Money (manual)',
                                },
                                'icon' => $gateway->isCard()
                                    ? 'fa-credit-card'
                                    : ($gateway->type === 'bank'
                                        ? 'fa-building-columns'
                                        : 'fa-mobile-screen-button'),
                                'icon_class' => $gateway->isCard()
                                    ? 'text-indigo-500'
                                    : ($gateway->type === 'bank'
                                        ? 'text-emerald-500'
                                        : 'text-amber-500'),
                            ]);
                        }
                    @endphp

                    @if ($checkoutChoices->isEmpty())
                        <x-alert type="warning" message="No enabled payment channel is available. Please contact support or ask an administrator to review Payment Gateway settings." :dismissible="false" :autoDismiss="false" />
                    @else
                        <fieldset class="mb-4">
                            <legend class="text-sm font-medium text-slate-700 mb-2">
                                Choose a payment method
                            </legend>

                            <div class="pm-checkout-method-grid">
                                @foreach ($checkoutChoices as $choice)
                                    <label class="pm-checkout-method">
                                        <input
                                            type="radio"
                                            name="checkout_method"
                                            value="{{ $choice['key'] }}"
                                            class="pm-checkout-method-input"
                                            onchange="pmSelectPaymentMethod(
                                                {{ json_encode($choice['key']) }},
                                                {{ json_encode($choice['label']) }}
                                            )"
                                            @checked($loop->first)
                                        >

                                        <span class="pm-checkout-method-card">
                                            <span class="pm-checkout-method-icon">
                                                <i
                                                    class="fa-solid {{ $choice['icon'] }} {{ $choice['icon_class'] }}"
                                                    aria-hidden="true"
                                                ></i>
                                            </span>

                                            <span class="pm-checkout-method-copy">
                                                <span class="pm-checkout-method-title">
                                                    {{ $choice['label'] }}
                                                </span>

                                                <span class="pm-checkout-method-description">
                                                    {{ $choice['description'] }}
                                                </span>

                                                @if ($choice['channel'] === 'card')
                                                    <span class="pm-checkout-card-brands" aria-label="Visa and MasterCard">
                                                        <i class="fa-brands fa-cc-visa" aria-hidden="true"></i>
                                                        <i class="fa-brands fa-cc-mastercard" aria-hidden="true"></i>
                                                    </span>
                                                @endif
                                            </span>

                                            <span class="pm-checkout-method-check" aria-hidden="true">
                                                <i class="fa-solid fa-check"></i>
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="space-y-4">
                            @foreach ($checkoutChoices as $choice)
                                @php
                                    $gateway = $choice['gateway'];
                                    $checkoutKey = $choice['key'];
                                @endphp

                                <div
                                    class="pm-gateway-section rounded-xl border border-slate-200 p-4 sm:p-5"
                                    data-checkout-key="{{ $checkoutKey }}"
                                    @if (! $loop->first) hidden @endif
                                >
                                    {{-- ioTec Mobile Money --}}
                                    @if ($choice['channel'] === 'mobile_money' && $isIoTecGateway($gateway))
                                        <div class="pm-gateway-intro pm-gateway-intro-mobile">
                                            <div class="pm-gateway-intro-icon">
                                                <i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i>
                                            </div>

                                            <div class="pm-gateway-intro-copy">
                                                <h3 class="pm-gateway-intro-title">
                                                    ioTec Mobile Money
                                                </h3>
                                                <p class="pm-gateway-intro-text">
                                                    Enter the number that should receive the payment approval prompt.
                                                </p>
                                            </div>
                                        </div>

                                        <form
                                            method="POST"
                                            action="{{ route('subscription.pay.iotec') }}"
                                            class="pm-gateway-form pm-iotec-payment-form"
                                            data-iotec-payment-form
                                        >
                                            @csrf
                                            <input type="hidden" name="subscription_plan_id" class="pm-plan-id-input" value="">
                                            <input type="hidden" name="beneficiary_email" class="pm-beneficiary-input" value="{{ old('beneficiary_email') }}">
                                            <input type="hidden" name="payment_channel" value="mobile_money">

                                            <div class="pm-gateway-field">
                                                <label for="iotec-phone-{{ $gateway->id }}" class="pm-gateway-label">
                                                    Mobile Money Number
                                                </label>
                                                <input
                                                    type="tel"
                                                    id="iotec-phone-{{ $gateway->id }}"
                                                    name="phone"
                                                    value="{{ old('phone', $accountPhone) }}"
                                                    placeholder="e.g. 2567XXXXXXXX"
                                                    class="pm-input pm-gateway-input"
                                                    required
                                                >
                                            </div>

                                            <button
                                                type="submit"
                                                class="pm-pay-btn btn-primary inline-flex w-full items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                                disabled
                                            >
                                                <i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i>
                                                Pay with Mobile Money
                                            </button>

                                            <p class="pm-gateway-help">
                                                <i class="fa-solid fa-shield-halved text-emerald-500" aria-hidden="true"></i>
                                                <span>
                                                    Approve the request on your phone. We never ask for or store your Mobile Money PIN.
                                                </span>
                                            </p>
                                        </form>

                                    {{-- ioTec Visa / MasterCard --}}
                                    @elseif ($choice['channel'] === 'card' && $isIoTecGateway($gateway))
                                        <div class="pm-gateway-intro pm-gateway-intro-card">
                                            <div class="pm-gateway-intro-icon">
                                                <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                                            </div>

                                            <div class="pm-gateway-intro-copy">
                                                <h3 class="pm-gateway-intro-title">
                                                    Visa / MasterCard
                                                </h3>

                                                <p class="pm-gateway-intro-text">
                                                    Confirm the payer details below, then continue to ioTec's secure card page.
                                                    Your actual card number, expiry date, CVV and bank OTP are entered securely on ioTec,
                                                    not stored by My Digital Diary.
                                                </p>

                                                <div class="pm-gateway-card-brands" aria-label="Visa and MasterCard supported">
                                                    <i class="fa-brands fa-cc-visa" aria-hidden="true"></i>
                                                    <i class="fa-brands fa-cc-mastercard" aria-hidden="true"></i>
                                                </div>
                                            </div>
                                        </div>

                                        <form
                                            method="POST"
                                            action="{{ route('subscription.pay.iotec') }}"
                                            class="pm-gateway-form pm-iotec-card-form pm-iotec-payment-form"
                                            data-iotec-payment-form
                                        >
                                            @csrf

                                            <input
                                                type="hidden"
                                                name="subscription_plan_id"
                                                class="pm-plan-id-input"
                                                value=""
                                            >
                                            <input type="hidden" name="beneficiary_email" class="pm-beneficiary-input" value="{{ old('beneficiary_email') }}">

                                            <input
                                                type="hidden"
                                                name="payment_channel"
                                                value="card"
                                            >

                                            <div class="pm-card-payer-grid">
                                                <div class="pm-gateway-field">
                                                    <label
                                                        for="iotec-card-name-{{ $gateway->id }}"
                                                        class="pm-gateway-label"
                                                    >
                                                        Cardholder / Payer Name
                                                    </label>

                                                    <input
                                                        type="text"
                                                        id="iotec-card-name-{{ $gateway->id }}"
                                                        name="payer_name"
                                                        value="{{ old('payer_name', $user->name ?? '') }}"
                                                        autocomplete="cc-name"
                                                        placeholder="Name on the card"
                                                        class="pm-input pm-gateway-input"
                                                        required
                                                    >
                                                </div>

                                                <div class="pm-gateway-field">
                                                    <label
                                                        for="iotec-card-email-{{ $gateway->id }}"
                                                        class="pm-gateway-label"
                                                    >
                                                        Billing Email
                                                    </label>

                                                    <input
                                                        type="email"
                                                        id="iotec-card-email-{{ $gateway->id }}"
                                                        name="payer_email"
                                                        value="{{ old('payer_email', $user->email ?? '') }}"
                                                        autocomplete="email"
                                                        placeholder="Email for payment confirmation"
                                                        class="pm-input pm-gateway-input"
                                                        required
                                                    >
                                                </div>

                                                <div class="pm-gateway-field pm-card-payer-phone">
                                                    <label
                                                        for="iotec-card-phone-{{ $gateway->id }}"
                                                        class="pm-gateway-label"
                                                    >
                                                        Billing Phone Number
                                                    </label>

                                                    <input
                                                        type="tel"
                                                        id="iotec-card-phone-{{ $gateway->id }}"
                                                        name="payer_phone"
                                                        value="{{ old('payer_phone', $accountPhone) }}"
                                                        autocomplete="tel"
                                                        placeholder="e.g. 0784 000 000"
                                                        class="pm-input pm-gateway-input"
                                                    >
                                                </div>
                                            </div>

                                            <div class="pm-secure-card-note">
                                                <div class="pm-secure-card-note-icon">
                                                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                                </div>

                                                <div>
                                                    <p class="pm-secure-card-note-title">
                                                        Enter your bank card details on ioTec
                                                    </p>

                                                    <p class="pm-secure-card-note-text">
                                                        After clicking Continue, ioTec will securely ask for your
                                                        Visa/MasterCard number, expiry date, CVV and any bank OTP or
                                                        3-D Secure verification required to complete the payment.
                                                    </p>
                                                </div>
                                            </div>

                                            <button
                                                type="submit"
                                                class="pm-pay-btn btn-primary inline-flex w-full items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                                disabled
                                            >
                                                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                                Continue to Secure Card Payment
                                            </button>

                                            <p class="pm-gateway-help">
                                                <i class="fa-solid fa-shield-halved text-emerald-500" aria-hidden="true"></i>
                                                <span>
                                                    Your subscription is activated or upgraded only after ioTec confirms the payment was successful.
                                                </span>
                                            </p>
                                        </form>

                                    {{-- Legacy Stripe card gateway --}}
                                    @elseif ($gateway->isCard())
                                        <p class="text-sm text-slate-600 mb-3">
                                            Pay securely by card via Stripe.
                                        </p>

                                        <form method="POST" action="{{ route('subscription.pay.card') }}">
                                            @csrf
                                            <input type="hidden" name="plan_id" class="pm-plan-id-input" value="">
                                            <input type="hidden" name="beneficiary_email" class="pm-beneficiary-input" value="{{ old('beneficiary_email') }}">

                                            <button
                                                type="submit"
                                                class="pm-pay-btn btn-primary inline-flex w-full items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
                                                disabled
                                            >
                                                <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                                                Pay with Card
                                            </button>
                                        </form>

                                    {{-- Other automatic Mobile Money --}}
                                    @elseif ($gateway->collectsAutomatically())
                                        <p class="text-sm text-slate-600 mb-3">
                                            {{ $gateway->description ?: 'Pay instantly by Mobile Money. A payment prompt will be sent to your phone.' }}
                                        </p>

                                        <form
                                            method="POST"
                                            action="{{ route('subscription.pay.mobile-money') }}"
                                            class="flex flex-wrap items-end gap-3"
                                        >
                                            @csrf
                                            <input type="hidden" name="plan_id" class="pm-plan-id-input" value="">
                                            <input type="hidden" name="beneficiary_email" class="pm-beneficiary-input" value="{{ old('beneficiary_email') }}">

                                            <div class="min-w-[10rem]">
                                                <label for="network-{{ $gateway->id }}" class="block text-sm font-medium text-slate-700 mb-1">
                                                    Network
                                                </label>
                                                <select id="network-{{ $gateway->id }}" name="network" required class="pm-input">
                                                    @if ($gateway->supports_mtn)
                                                        <option value="mtn">MTN Mobile Money</option>
                                                    @endif
                                                    @if ($gateway->supports_airtel)
                                                        <option value="airtel">Airtel Money</option>
                                                    @endif
                                                </select>
                                            </div>

                                            <div class="min-w-[12rem] flex-1">
                                                <label for="phone-{{ $gateway->id }}" class="block text-sm font-medium text-slate-700 mb-1">
                                                    Phone Number
                                                </label>
                                                <input
                                                    type="text"
                                                    id="phone-{{ $gateway->id }}"
                                                    name="phone_number"
                                                    required
                                                    placeholder="e.g. 0700000000"
                                                    value="{{ old('phone_number', $accountPhone) }}"
                                                    class="pm-input"
                                                >
                                            </div>

                                            <button
                                                type="submit"
                                                class="pm-pay-btn btn-primary inline-flex w-full items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
                                                disabled
                                            >
                                                <i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i>
                                                Pay with Mobile Money
                                            </button>
                                        </form>

                                    {{-- Bank/manual Mobile Money --}}
                                    @else
                                        @if ($gateway->instructions)
                                            <p class="text-sm text-slate-600 mb-3 whitespace-pre-line">
                                                {{ $gateway->instructions }}
                                            </p>
                                        @endif

                                        <dl class="text-sm mb-4 space-y-1">
                                            @if ($gateway->type === 'bank')
                                                <div class="flex justify-between gap-4">
                                                    <dt class="text-slate-500">Bank</dt>
                                                    <dd class="text-right">{{ $gateway->configValue('bank_name') }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-4">
                                                    <dt class="text-slate-500">Account Name</dt>
                                                    <dd class="text-right">{{ $gateway->configValue('account_name') }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-4">
                                                    <dt class="text-slate-500">Account Number</dt>
                                                    <dd class="font-mono text-right">{{ $gateway->configValue('account_number') }}</dd>
                                                </div>
                                                @if ($gateway->configValue('routing_or_swift'))
                                                    <div class="flex justify-between gap-4">
                                                        <dt class="text-slate-500">Routing / SWIFT</dt>
                                                        <dd class="font-mono text-right">{{ $gateway->configValue('routing_or_swift') }}</dd>
                                                    </div>
                                                @endif
                                            @else
                                                <div class="flex justify-between gap-4">
                                                    <dt class="text-slate-500">Provider</dt>
                                                    <dd class="text-right">{{ $gateway->configValue('provider_name') }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-4">
                                                    <dt class="text-slate-500">Number</dt>
                                                    <dd class="font-mono text-right">{{ $gateway->configValue('merchant_number') }}</dd>
                                                </div>
                                            @endif
                                        </dl>

                                        <form
                                            method="POST"
                                            action="{{ route('subscription.pay.manual') }}"
                                            class="flex flex-wrap items-end gap-3"
                                        >
                                            @csrf
                                            <input type="hidden" name="payment_gateway_id" value="{{ $gateway->id }}">
                                            <input type="hidden" name="plan_id" class="pm-plan-id-input" value="">
                                            <input type="hidden" name="beneficiary_email" class="pm-beneficiary-input" value="{{ old('beneficiary_email') }}">

                                            <div class="min-w-[12rem] flex-1">
                                                <label for="reference-{{ $gateway->id }}" class="block text-sm font-medium text-slate-700 mb-1">
                                                    Transaction Reference
                                                </label>
                                                <input
                                                    type="text"
                                                    id="reference-{{ $gateway->id }}"
                                                    name="reference"
                                                    required
                                                    placeholder="e.g. transaction ID from your bank/mobile app"
                                                    class="pm-input"
                                                >
                                            </div>

                                            <button
                                                type="submit"
                                                class="pm-pay-btn btn-primary inline-flex w-full items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
                                                disabled
                                            >
                                                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                                                I've Paid — Submit for Verification
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </dialog>
        </div>
        {{-- ============= end pm-sub-panel-subscription ============= --}}

        <div role="tabpanel" id="pm-sub-panel-billing" aria-labelledby="pm-sub-tab-billing" tabindex="0" class="pm-sub-panel space-y-6" hidden>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach ([
                    ['label' => 'Total Invoices', 'value' => $billingStats['total_invoices'], 'color' => 'slate'],
                    ['label' => 'Completed', 'value' => $billingStats['completed'], 'color' => 'emerald'],
                    ['label' => 'Pending', 'value' => $billingStats['pending'], 'color' => 'amber'],
                    ['label' => 'Failed', 'value' => $billingStats['failed'], 'color' => 'rose'],
                ] as $card)
                    <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 border-l-4 border-l-{{ $card['color'] }}-400 p-3">
                        <p class="text-xs text-slate-500 uppercase tracking-wide">{{ $card['label'] }}</p>
                        <p class="text-xl font-bold text-slate-800">{{ $card['value'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-4 sm:p-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-500">Account payment phone</p>
                        <p class="font-semibold text-slate-800">
                            {{ $accountPhone ?: 'Not set yet' }}
                        </p>
                        <p class="text-xs text-slate-500 mt-1">
                            This number is pre-filled for Mobile Money prompts. When you use a different number, it becomes your new payment phone.
                        </p>
                    </div>
                    @if ($accountPhone)
                        <span class="inline-flex items-center gap-2 text-sm text-[var(--brand-1)]">
                            <i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i>
                            Ready for payment prompts
                        </span>
                    @endif
                </div>
            </div>

            <form method="GET" action="{{ route('subscription.show') }}" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="tab" value="billing">
                <div class="flex-1 min-w-[180px] max-w-xs">
                    <label for="billing_q" class="sr-only">Search</label>
                    <input type="search" id="billing_q" name="billing_q" value="{{ $billingSearch }}" placeholder="Search plan, reference or gateway transaction..." class="pm-input text-sm">
                </div>
                <div>
                    <label for="billing_period" class="sr-only">Period</label>
                    <select id="billing_period" name="billing_period" onchange="pmToggleBillingDateRange(this)" class="pm-input text-sm">
                        <option value="">Any time</option>
                        <option value="daily" @selected($billingPeriod === 'daily')>Today</option>
                        <option value="weekly" @selected($billingPeriod === 'weekly')>This week</option>
                        <option value="monthly" @selected($billingPeriod === 'monthly')>This month</option>
                        <option value="range" @selected($billingPeriod === 'range')>Custom range...</option>
                    </select>
                </div>
                <div id="pm-billing-date-range" class="flex items-center gap-2" style="{{ $billingPeriod === 'range' ? '' : 'display: none;' }}">
                    <input type="date" name="billing_from" value="{{ $billingFrom }}" class="pm-input text-sm">
                    <span class="text-slate-400 text-sm">to</span>
                    <input type="date" name="billing_to" value="{{ $billingTo }}" class="pm-input text-sm">
                </div>
                <div>
                    <label for="billing_per_page" class="sr-only">Records per page</label>
                    <select id="billing_per_page" name="billing_per_page" class="pm-input text-sm">
                        @foreach ([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected($billingPerPage === $size)>{{ $size }} / page</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">Filter</button>
                @if ($billingSearch || $billingPeriod || $billingPerPage !== 10)
                    <a href="{{ route('subscription.show', ['tab' => 'billing']) }}" class="text-sm text-slate-500 hover:text-slate-700 pb-2.5">Clear</a>
                @endif
            </form>

            <div>
                <div class="pm-dt-wrap">
                    <table class="pm-dt">
                        <caption class="sr-only">Your invoices, receipts and pending subscription payments.</caption>
                        <thead>
                            <tr>
                                <th scope="col">Payment</th>
                                <th scope="col">Status</th>
                                <th scope="col">Date</th>
                                <th scope="col" class="pm-dt-num">Amount</th>
                                <th scope="col" class="pm-dt-actions"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                @php
                                    $paymentPhone = $payment->paymentContactPhone() ?: $accountPhone;
                                    $statusPill = match($payment->status) {
                                        'completed' => 'is-green',
                                        'pending' => 'is-amber',
                                        default => 'is-rose',
                                    };
                                    $paymentMethodLabel = $payment->gateway->display_name ?? $payment->gateway->name ?? ucfirst(str_replace('_', ' ', $payment->method));
                                    $hasPaymentDocs = $payment->invoice || $payment->status === 'completed';
                                @endphp
                                <tr>
                                    <td class="pm-dt-main">
                                        <span class="pm-dt-title">{{ $payment->plan->name ?? '—' }}</span>
                                        <span class="pm-dt-sub">
                                            <span>{{ $paymentMethodLabel }}</span>
                                            @if ($paymentPhone)
                                                <span><a href="tel:{{ preg_replace('/\s+/', '', $paymentPhone) }}" class="text-[var(--brand-1)] hover:underline font-medium" title="Mobile Money phone">{{ $paymentPhone }}</a></span>
                                            @elseif ($payment->method === 'bank' && filled($payment->reference))
                                                <span class="font-mono" title="Bank reference">Ref {{ $payment->reference }}</span>
                                            @endif
                                            @if ($payment->gateway_transaction_id)
                                                <span class="font-mono" title="Gateway transaction ID">Txn {{ $payment->gateway_transaction_id }}</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td class="pm-dt-aux">
                                        <span class="pm-dt-pill {{ $statusPill }}">{{ ucfirst($payment->status) }}</span>
                                    </td>
                                    <td class="pm-dt-aux">{{ $payment->created_at->format('d M Y') }}</td>
                                    <td class="pm-dt-num">{{ format_money_in($payment->amount, $payment->currency) }}</td>
                                    <td class="pm-dt-actions">
                                        <div class="inline-flex items-center gap-1">
                                            @if ($payment->status === 'pending')
                                                <button type="button"
                                                        onclick="document.getElementById('pm-pending-payment-{{ $payment->id }}').showModal()"
                                                        class="btn-primary text-white px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap">
                                                    <i class="fa-solid fa-wallet mr-1" aria-hidden="true"></i> Pay
                                                </button>
                                            @endif
                                            @if ($hasPaymentDocs || $payment->status === 'pending')
                                                <details class="pm-dt-menu">
                                                    <summary class="pm-dt-icon-btn" aria-label="More actions for {{ $payment->plan->name ?? 'this payment' }}" title="More actions">
                                                        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                                    </summary>
                                                    <div class="pm-dt-menu-list">
                                                        @if ($payment->invoice)
                                                            <a href="{{ route('subscription.invoice', $payment->invoice->id) }}" class="pm-dt-menu-item">
                                                                <i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Invoice
                                                            </a>
                                                        @endif
                                                        @if ($payment->status === 'completed')
                                                            <a href="{{ route('subscription.receipt', $payment->id) }}" class="pm-dt-menu-item">
                                                                <i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Receipt
                                                            </a>
                                                        @endif
                                                        @if ($payment->status === 'pending')
                                                            <button
                                                                type="button"
                                                                onclick="document.getElementById('pm-cancel-payment-{{ $payment->id }}').showModal()"
                                                                class="pm-dt-menu-item is-danger"
                                                            >
                                                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                                                Cancel payment
                                                            </button>
                                                        @endif
                                                    </div>
                                                </details>
                                            @endif
                                        </div>
                                    </td>
                                </tr>

                                @if ($payment->status === 'pending')
                                    <dialog
                                        id="pm-cancel-payment-{{ $payment->id }}"
                                        class="rounded-2xl p-0 w-[min(92vw,520px)] shadow-2xl backdrop:bg-slate-900/55"
                                    >
                                        <div class="overflow-hidden rounded-2xl bg-white">
                                            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                                                <div class="flex items-start gap-3">
                                                    <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-600">
                                                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                                    </div>
                                                    <div>
                                                        <h3 class="text-lg font-bold text-slate-900">
                                                            Cancel pending payment?
                                                        </h3>
                                                        <p class="mt-1 text-sm text-slate-500">
                                                            {{ $payment->plan->name ?? 'Subscription' }}
                                                            · {{ format_money_in($payment->amount, $payment->currency) }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <button
                                                    type="button"
                                                    onclick="this.closest('dialog').close()"
                                                    class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                                    aria-label="Close"
                                                >
                                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                                </button>
                                            </div>

                                            <div class="px-5 py-5">
                                                <p class="text-sm leading-6 text-slate-600">
                                                    Cancel this pending subscription invoice/payment?
                                                    This will stop this unpaid billing attempt. It will not affect any completed payment or receipt.
                                                </p>
                                            </div>

                                            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end">
                                                <button
                                                    type="button"
                                                    onclick="this.closest('dialog').close()"
                                                    class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                                >
                                                    Keep Payment
                                                </button>

                                                <form
                                                    method="POST"
                                                    action="{{ route('subscription.payment.cancel', $payment) }}"
                                                >
                                                    @csrf
                                                    <button
                                                        type="submit"
                                                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-700 sm:w-auto"
                                                    >
                                                        <i class="fa-solid fa-ban" aria-hidden="true"></i>
                                                        Cancel Payment
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </dialog>

                                    <dialog id="pm-pending-payment-{{ $payment->id }}" class="rounded-2xl p-0 w-[min(94vw,620px)] backdrop:bg-slate-900/50">
                                        <div class="bg-white rounded-2xl overflow-hidden">
                                            <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-slate-100">
                                                <div>
                                                    <h3 class="font-bold text-lg text-slate-900">Complete pending payment</h3>
                                                    <p class="text-sm text-slate-500 mt-1">
                                                        {{ $payment->plan->name ?? 'Subscription' }} · {{ format_money_in($payment->amount, $payment->currency) }}
                                                    </p>
                                                </div>
                                                <button type="button" onclick="this.closest('dialog').close()" class="text-slate-400 hover:text-slate-700 p-1" aria-label="Close">
                                                    <i class="fa-solid fa-xmark text-xl"></i>
                                                </button>
                                            </div>

                                            <div class="p-5 space-y-5">
                                                @if ($automaticMobileGateway)
                                                    <section class="border border-slate-200 rounded-xl p-4">
                                                        <h4 class="font-semibold text-slate-800 flex items-center gap-2">
                                                            <i class="fa-solid fa-mobile-screen-button text-[var(--brand-1)]"></i>
                                                            Mobile Money prompt
                                                        </h4>
                                                        <p class="text-sm text-slate-500 mt-1 mb-3">
                                                            Resend a payment prompt to your account phone or enter another number.
                                                        </p>
                                                        <form method="POST" action="{{ route('subscription.payment.mobile-money', $payment) }}" class="grid sm:grid-cols-3 gap-3 items-end">
                                                            @csrf
                                                            <div>
                                                                <label class="block text-xs font-medium text-slate-600 mb-1">Network</label>
                                                                <select name="network" required class="pm-input text-sm">
                                                                    @if ($automaticMobileGateway->supports_mtn)<option value="mtn">MTN Mobile Money</option>@endif
                                                                    @if ($automaticMobileGateway->supports_airtel)<option value="airtel">Airtel Money</option>@endif
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label class="block text-xs font-medium text-slate-600 mb-1">Phone number</label>
                                                                <input type="text" name="phone_number" required value="{{ $paymentPhone }}" placeholder="0700000000" class="pm-input text-sm">
                                                            </div>
                                                            <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-semibold">
                                                                Send Prompt
                                                            </button>
                                                        </form>
                                                    </section>
                                                @endif

                                                @if ($bankGateways->isNotEmpty())
                                                    <section class="space-y-3">
                                                        <div>
                                                            <h4 class="font-semibold text-slate-800 flex items-center gap-2">
                                                                <i class="fa-solid fa-building-columns text-[var(--brand-1)]"></i>
                                                                Pay by bank transfer
                                                            </h4>
                                                            <p class="text-sm text-slate-500 mt-1">Transfer the invoice amount, then enter the transaction/reference number below.</p>
                                                        </div>

                                                        @foreach ($bankGateways as $bank)
                                                            <div class="border border-slate-200 rounded-xl p-4">
                                                                <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-2 text-sm mb-4">
                                                                    <div><dt class="text-slate-500">Bank</dt><dd class="font-medium">{{ $bank->configValue('bank_name') ?: $bank->name }}</dd></div>
                                                                    <div><dt class="text-slate-500">Account Name</dt><dd class="font-medium">{{ $bank->configValue('account_name') ?: '—' }}</dd></div>
                                                                    <div><dt class="text-slate-500">Account Number</dt><dd class="font-mono font-medium">{{ $bank->configValue('account_number') ?: '—' }}</dd></div>
                                                                    @if ($bank->configValue('routing_or_swift'))
                                                                        <div><dt class="text-slate-500">Routing / SWIFT</dt><dd class="font-mono">{{ $bank->configValue('routing_or_swift') }}</dd></div>
                                                                    @endif
                                                                </dl>
                                                                @if ($bank->instructions)
                                                                    <p class="text-xs text-slate-500 whitespace-pre-line mb-3">{{ $bank->instructions }}</p>
                                                                @endif
                                                                <form method="POST" action="{{ route('subscription.payment.bank', $payment) }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                                                                    @csrf
                                                                    <input type="hidden" name="payment_gateway_id" value="{{ $bank->id }}">
                                                                    <div class="flex-1">
                                                                        <label class="block text-xs font-medium text-slate-600 mb-1">Bank transaction reference</label>
                                                                        <input type="text" name="reference" required placeholder="Enter transaction / bank reference" class="pm-input text-sm">
                                                                    </div>
                                                                    <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-semibold">
                                                                        Submit Payment
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        @endforeach
                                                    </section>
                                                @endif

                                                @if (! $automaticMobileGateway && $bankGateways->isEmpty())
                                                    <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
                                                        No Mobile Money collection or bank-transfer gateway is currently enabled. Please contact support.
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </dialog>
                                @endif
                            @empty
                                <tr class="pm-dt-empty">
                                     <td colspan="5">No invoices or payments yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($payments->total() > 0)
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-4">
                        <p class="text-sm text-slate-500">
                            Showing {{ $payments->firstItem() }} to {{ $payments->lastItem() }} of {{ $payments->total() }} records
                        </p>
                        <div>{{ $payments->links() }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>


    @include('subscription.partials.iotec-confirmation')

    <style>
        /*
         * Subscription checkout is deliberately isolated from global form
         * label/span styling. This prevents method names from collapsing into
         * one-character-wide vertical text.
         */
        .pm-checkout-dialog {
            width: min(94vw, 760px);
            max-width: 760px;
            max-height: calc(100dvh - 28px);
            overflow: hidden;
            border: 0;
            background: #fff;
        }

        .pm-checkout-dialog::backdrop {
            background: rgba(15, 23, 42, 0.58);
        }

        .pm-checkout-scroll {
            max-height: calc(100dvh - 28px);
            overflow-y: auto;
            overflow-x: hidden;
            overscroll-behavior: contain;
        }

        .pm-checkout-method-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            width: 100%;
        }

        .pm-checkout-method {
            display: block !important;
            position: relative;
            width: 100% !important;
            min-width: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            cursor: pointer;
        }

        .pm-checkout-method-input {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            opacity: 0;
            pointer-events: none;
        }

        .pm-checkout-method-card {
            position: relative;
            display: grid !important;
            grid-template-columns: 44px minmax(0, 1fr) 24px;
            align-items: center;
            gap: 12px;
            width: 100% !important;
            min-width: 0 !important;
            min-height: 88px;
            padding: 14px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            background: #fff;
            transition:
                border-color .16s ease,
                background-color .16s ease,
                box-shadow .16s ease,
                transform .16s ease;
            box-sizing: border-box;
        }

        .pm-checkout-method:hover .pm-checkout-method-card {
            border-color: #cbd5e1;
            box-shadow: 0 4px 14px rgba(15, 23, 42, .06);
        }

        .pm-checkout-method-input:checked + .pm-checkout-method-card {
            border-color: var(--brand-1);
            background: color-mix(
                in srgb,
                var(--brand-1) 8%,
                white
            );
            box-shadow: 0 0 0 1px var(--brand-1);
        }

        .pm-checkout-method-input:focus-visible + .pm-checkout-method-card {
            outline: 3px solid color-mix(
                in srgb,
                var(--brand-1) 24%,
                transparent
            );
            outline-offset: 2px;
        }

        .pm-checkout-method-icon {
            display: grid !important;
            place-items: center;
            width: 44px;
            height: 44px;
            min-width: 44px;
            border-radius: 12px;
            background: #f8fafc;
            font-size: 18px;
        }

        .pm-checkout-method-copy {
            display: block !important;
            width: 100% !important;
            min-width: 0 !important;
            max-width: none !important;
            white-space: normal !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            line-height: 1.35;
        }

        .pm-checkout-method-title {
            display: block !important;
            width: 100% !important;
            white-space: normal !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
        }

        .pm-checkout-method-description {
            display: block !important;
            width: 100% !important;
            margin-top: 3px;
            white-space: normal !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
            font-size: 12px;
            color: #94a3b8;
        }

        .pm-checkout-card-brands {
            display: flex !important;
            align-items: center;
            gap: 8px;
            width: auto !important;
            margin-top: 6px;
            font-size: 20px;
            color: #475569;
        }

        .pm-checkout-method-check {
            display: grid !important;
            place-items: center;
            width: 22px;
            height: 22px;
            min-width: 22px;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            color: transparent;
            background: #fff;
            font-size: 10px;
        }

        .pm-checkout-method-input:checked
            + .pm-checkout-method-card
            .pm-checkout-method-check {
            border-color: var(--brand-1);
            background: var(--brand-1);
            color: #fff;
        }

        .pm-gateway-section {
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }

        .pm-gateway-section form,
        .pm-gateway-section input,
        .pm-gateway-section select,
        .pm-gateway-section textarea {
            min-width: 0;
            box-sizing: border-box;
        }

        .pm-gateway-section {
            display: block !important;
            overflow: hidden;
            padding: 18px !important;
        }

        .pm-gateway-section[hidden] {
            display: none !important;
        }

        .pm-gateway-section > * {
            max-width: 100% !important;
        }

        .pm-gateway-intro {
            display: grid !important;
            grid-template-columns: 44px minmax(0, 1fr) !important;
            align-items: start !important;
            gap: 12px !important;
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
            margin: 0 0 16px !important;
            padding: 14px !important;
            border-radius: 12px !important;
            box-sizing: border-box !important;
        }

        .pm-gateway-intro-mobile {
            background: #fffbeb !important;
        }

        .pm-gateway-intro-card {
            background: #f8fafc !important;
        }

        .pm-gateway-intro-icon {
            display: grid !important;
            place-items: center !important;
            width: 44px !important;
            height: 44px !important;
            min-width: 44px !important;
            max-width: 44px !important;
            border-radius: 10px !important;
            background: #ffffff !important;
            font-size: 18px !important;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .06);
        }

        .pm-gateway-intro-mobile .pm-gateway-intro-icon {
            color: #d97706 !important;
        }

        .pm-gateway-intro-card .pm-gateway-intro-icon {
            color: #4f46e5 !important;
        }

        .pm-gateway-intro-copy {
            display: block !important;
            width: 100% !important;
            min-width: 0 !important;
            max-width: none !important;
            white-space: normal !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
        }

        .pm-gateway-intro-title {
            display: block !important;
            width: 100% !important;
            min-width: 0 !important;
            margin: 0 !important;
            white-space: normal !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
            writing-mode: horizontal-tb !important;
            font-size: 14px !important;
            line-height: 1.35 !important;
            font-weight: 700 !important;
            color: #1e293b !important;
        }

        .pm-gateway-intro-text {
            display: block !important;
            width: 100% !important;
            min-width: 0 !important;
            margin: 4px 0 0 !important;
            white-space: normal !important;
            word-break: normal !important;
            overflow-wrap: anywhere !important;
            writing-mode: horizontal-tb !important;
            font-size: 13px !important;
            line-height: 1.55 !important;
            color: #64748b !important;
        }

        .pm-gateway-card-brands {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            width: auto !important;
            margin-top: 8px !important;
            font-size: 22px !important;
            color: #475569 !important;
        }

        .pm-gateway-form {
            display: block !important;
            width: 100% !important;
            min-width: 0 !important;
            margin: 0 !important;
        }

        .pm-gateway-form > * + * {
            margin-top: 12px !important;
        }

        .pm-gateway-field {
            display: block !important;
            width: 100% !important;
            min-width: 0 !important;
        }

        .pm-gateway-label {
            display: block !important;
            width: 100% !important;
            margin: 0 0 6px !important;
            white-space: normal !important;
            word-break: normal !important;
            writing-mode: horizontal-tb !important;
            font-size: 13px !important;
            line-height: 1.4 !important;
            font-weight: 600 !important;
            color: #475569 !important;
        }

        .pm-gateway-input {
            display: block !important;
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
        }

        .pm-gateway-help {
            display: flex !important;
            align-items: flex-start !important;
            gap: 6px !important;
            width: 100% !important;
            min-width: 0 !important;
            margin: 0 !important;
            font-size: 12px !important;
            line-height: 1.5 !important;
            color: #94a3b8 !important;
        }

        .pm-gateway-help span {
            display: inline !important;
            width: auto !important;
            min-width: 0 !important;
            white-space: normal !important;
            word-break: normal !important;
            writing-mode: horizontal-tb !important;
        }

        .pm-card-payer-grid {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 12px !important;
            width: 100% !important;
        }

        .pm-card-payer-phone {
            grid-column: 1 / -1;
        }

        .pm-secure-card-note {
            display: grid !important;
            grid-template-columns: 38px minmax(0, 1fr) !important;
            gap: 10px !important;
            align-items: start !important;
            width: 100% !important;
            padding: 12px 14px !important;
            border: 1px solid #c7d2fe !important;
            border-radius: 12px !important;
            background: #eef2ff !important;
            box-sizing: border-box !important;
        }

        .pm-secure-card-note-icon {
            display: grid !important;
            place-items: center !important;
            width: 38px !important;
            height: 38px !important;
            min-width: 38px !important;
            border-radius: 10px !important;
            background: #ffffff !important;
            color: #4f46e5 !important;
        }

        .pm-secure-card-note-title {
            display: block !important;
            margin: 0 !important;
            font-size: 13px !important;
            line-height: 1.4 !important;
            font-weight: 700 !important;
            color: #312e81 !important;
        }

        .pm-secure-card-note-text {
            display: block !important;
            margin: 3px 0 0 !important;
            font-size: 12px !important;
            line-height: 1.5 !important;
            color: #4f46e5 !important;
            white-space: normal !important;
            word-break: normal !important;
            overflow-wrap: anywhere !important;
        }

        @media (max-width: 640px) {
            .pm-checkout-dialog {
                width: calc(100vw - 16px);
                max-width: calc(100vw - 16px);
                max-height: calc(100dvh - 16px);
                border-radius: 18px;
            }

            .pm-checkout-scroll {
                max-height: calc(100dvh - 16px);
                padding: 18px !important;
            }

            .pm-checkout-method-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .pm-checkout-method-card {
                min-height: 78px;
                grid-template-columns: 40px minmax(0, 1fr) 22px;
                gap: 10px;
                padding: 12px;
            }

            .pm-checkout-method-icon {
                width: 40px;
                height: 40px;
                min-width: 40px;
            }

            .pm-gateway-section {
                padding: 14px !important;
            }

            .pm-gateway-intro {
                grid-template-columns: 40px minmax(0, 1fr) !important;
                gap: 10px !important;
                padding: 12px !important;
            }

            .pm-gateway-intro-icon {
                width: 40px !important;
                height: 40px !important;
                min-width: 40px !important;
                max-width: 40px !important;
            }

            .pm-card-payer-grid {
                grid-template-columns: 1fr !important;
            }

            .pm-card-payer-phone {
                grid-column: auto;
            }

            .pm-secure-card-note {
                grid-template-columns: 34px minmax(0, 1fr) !important;
                padding: 11px !important;
            }

            .pm-secure-card-note-icon {
                width: 34px !important;
                height: 34px !important;
                min-width: 34px !important;
            }
        }
    </style>

    <script>
        /*
         * ioTec Mobile Money submission is handled by
         * subscription.partials.iotec-confirmation.
         * Card checkout remains a normal browser submission so Laravel can
         * redirect to ioTec's hosted Visa / MasterCard page.
         */

        // Display-only conversion recalculates what's SHOWN using the
        // rate an admin set (units of that currency per 1 base-currency
        // unit), never touches what's actually charged. Reads the
        // rate/symbol straight off the selected <option>'s data
        // attributes rather than a separate lookup table in JS.
        var pmBaseCurrencyCode = @json($settings->default_currency_code);
        var pmBaseCurrencySymbol = @json($settings->default_currency_symbol);
        var pmBaseCurrencyDecimals = {{ $settings->default_currency_decimals }};

        function pmConvertPlanPrices(currencyCode) {
            var select = document.getElementById('pm-currency-select');
            var selectedOption = select.options[select.selectedIndex];
            var isBase = currencyCode === pmBaseCurrencyCode;
            var rate = isBase ? 1 : parseFloat(selectedOption.dataset.rate || '1');
            var symbol = isBase ? pmBaseCurrencySymbol : (selectedOption.dataset.symbol || currencyCode);
            var decimals = isBase ? pmBaseCurrencyDecimals : 2;

            document.querySelectorAll('.pm-plan-price').forEach(function (el) {
                var basePrice = parseFloat(el.dataset.basePrice);
                var converted = isBase ? basePrice : (rate > 0 ? basePrice / rate : basePrice);
                el.textContent = symbol + ' ' + converted.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
            });
        }

        function pmSelectPlan(planId, price, planName, periodLabel, cardEl) {
            document.querySelectorAll('.pm-plan-id-input').forEach(function (input) {
                input.value = planId;
            });
            document.querySelectorAll('.pm-pay-btn').forEach(function (btn) {
                btn.disabled = false;
            });
            document.querySelectorAll('.pm-plan-card').forEach(function (card) {
                var selected = card === cardEl;
                // A ring (not a border-color swap) since these cards now
                // have colored gradient backgrounds a border-color
                // change wouldn't read clearly against every gradient,
                // but a gold ring stands out consistently on all of them.
                card.classList.toggle('ring-[#FFBA00]', selected);
                card.classList.toggle('ring-transparent', !selected);
                card.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });

            var summary = document.getElementById('pm-selected-plan-summary');
            var label = document.getElementById('pm-selected-plan-label');
            if (summary && label) {
                summary.classList.remove('hidden');
                label.textContent = planName + ' ' + pmBaseCurrencySymbol + ' ' + price.toLocaleString(undefined, { minimumFractionDigits: pmBaseCurrencyDecimals, maximumFractionDigits: pmBaseCurrencyDecimals });
            }

            // Populate and open the checkout modal this is now where
            // the actual payment method choice + confirmation happens,
            // not inline on the page.
            var amountFormatted = pmBaseCurrencySymbol + ' ' + price.toLocaleString(undefined, { minimumFractionDigits: pmBaseCurrencyDecimals, maximumFractionDigits: pmBaseCurrencyDecimals });
            var planNameEl = document.getElementById('pm-checkout-plan-name');
            var periodEl = document.getElementById('pm-checkout-plan-period');
            var amountEl = document.getElementById('pm-checkout-plan-amount');
            if (planNameEl) { planNameEl.textContent = planName; }
            if (periodEl) { periodEl.textContent = periodLabel; }
            if (amountEl) { amountEl.textContent = amountFormatted; }

            // Default to whichever payment method radio is already
            // checked (the first one, server-rendered) so the summary's
            // method label and visible gateway section are correct even
            // if the user never touches the radios themselves.
            var checkedMethod = document.querySelector('input[name="checkout_method"]:checked');
            if (checkedMethod) {
                var checkedCard = checkedMethod.closest('.pm-checkout-method');
                var checkedTitle = checkedCard
                    ? checkedCard.querySelector('.pm-checkout-method-title')
                    : null;

                pmSelectPaymentMethod(
                    checkedMethod.value,
                    checkedTitle
                        ? checkedTitle.textContent.trim()
                        : checkedMethod.value
                );
            }

            var modal = document.getElementById('pm-checkout-modal');
            if (modal) { modal.showModal(); }
        }

        // "Pay for someone else": the checkout-modal email is copied into
        // every payment form's hidden beneficiary_email field. An empty
        // value (checkbox off) pays for the signed-in user as before.
        function pmSyncBeneficiary() {
            var toggle = document.getElementById('pm-beneficiary-toggle');
            var emailEl = document.getElementById('pm-beneficiary-email');
            var value = toggle && toggle.checked && emailEl ? emailEl.value.trim() : '';
            document.querySelectorAll('.pm-beneficiary-input').forEach(function (input) {
                input.value = value;
            });
        }

        function pmToggleBeneficiary(checked) {
            var fields = document.getElementById('pm-beneficiary-fields');
            if (fields) { fields.hidden = !checked; }
            pmSyncBeneficiary();
        }

        function pmBeneficiaryChanged() {
            var result = document.getElementById('pm-beneficiary-result');
            if (result) {
                result.className = 'text-xs text-slate-500';
                result.textContent = 'Their subscription is activated once payment completes. You keep the invoice and receipt.';
            }
            pmSyncBeneficiary();
        }

        async function pmCheckBeneficiary() {
            var emailEl = document.getElementById('pm-beneficiary-email');
            var result = document.getElementById('pm-beneficiary-result');
            var email = emailEl ? emailEl.value.trim() : '';
            if (!result || email === '') { return; }

            result.className = 'text-xs text-slate-500';
            result.textContent = 'Checking…';

            try {
                var response = await fetch(@json(route('subscription.beneficiary')) + '?email=' + encodeURIComponent(email), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                var data = await response.json().catch(function () { return {}; });

                if (response.ok && data.data) {
                    result.className = 'text-xs text-emerald-700';
                    result.textContent = 'Paying for ' + data.data.name + '.';
                } else {
                    result.className = 'text-xs text-red-600';
                    result.textContent = (data.errors && data.errors.email && data.errors.email[0])
                        || data.message
                        || 'Could not check that email.';
                }
            } catch (e) {
                result.className = 'text-xs text-red-600';
                result.textContent = 'Could not check that email. Please try again.';
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            var emailEl = document.getElementById('pm-beneficiary-email');
            if (emailEl && emailEl.value.trim() !== '') {
                var toggle = document.getElementById('pm-beneficiary-toggle');
                if (toggle) { toggle.checked = true; }
                pmToggleBeneficiary(true);
            }
        });

        function pmSelectPaymentMethod(gatewayId, methodLabel) {
            document.querySelectorAll('.pm-gateway-section').forEach(function (section) {
                section.hidden = section.dataset.checkoutKey !== String(gatewayId);
            });
            var methodLabelEl = document.getElementById('pm-checkout-method-label');
            if (methodLabelEl && methodLabel) { methodLabelEl.textContent = methodLabel; }
        }

        function pmSelectSubTab(key) {
            document.querySelectorAll('.pm-sub-tab').forEach(function (btn) {
                var isSelected = btn.dataset.tab === key;
                btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                btn.setAttribute('tabindex', isSelected ? '0' : '-1');
                btn.classList.toggle('border-[var(--brand-1)]', isSelected);
                btn.classList.toggle('text-[var(--brand-1)]', isSelected);
                btn.classList.toggle('border-transparent', !isSelected);
                btn.classList.toggle('text-slate-500', !isSelected);
            });
            document.querySelectorAll('.pm-sub-panel').forEach(function (panel) {
                panel.hidden = panel.id !== 'pm-sub-panel-' + key;
            });
        }

        function pmSubTabKeydown(event, currentKey) {
            var tabs = Array.prototype.map.call(document.querySelectorAll('.pm-sub-tab'), function (t) { return t.dataset.tab; });
            var index = tabs.indexOf(currentKey);
            var nextIndex = null;

            if (event.key === 'ArrowRight') { nextIndex = (index + 1) % tabs.length; }
            else if (event.key === 'ArrowLeft') { nextIndex = (index - 1 + tabs.length) % tabs.length; }
            else { return; }

            event.preventDefault();
            pmSelectSubTab(tabs[nextIndex]);
            document.getElementById('pm-sub-tab-' + tabs[nextIndex]).focus();
        }

        function pmToggleBillingDateRange(select) {
            var wrapper = document.getElementById('pm-billing-date-range');
            if (wrapper) { wrapper.style.display = select.value === 'range' ? 'flex' : 'none'; }
        }

        // Land on the Invoices & Receipts tab after searching/filtering/
        // paginating there, rather than resetting back to "Your
        // Subscription" on every reload.
        document.addEventListener('DOMContentLoaded', function () {
            var params = new URLSearchParams(window.location.search);
            if (params.get('tab') === 'billing' || params.has('billing_page') || params.has('billing_q') || params.has('billing_period')) {
                pmSelectSubTab('billing');
            }
        });
    </script>
@endsection
