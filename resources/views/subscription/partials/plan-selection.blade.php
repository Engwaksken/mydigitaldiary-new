@if ($plans->isEmpty())
    <div class="border border-slate-200 rounded-xl p-5 mb-6 bg-gradient-to-br from-slate-50 to-white">
        <p class="text-3xl font-bold text-slate-800">{{ format_money($settings->monthly_price) }}<span class="text-base font-normal text-slate-500">/month</span></p>
        <p class="text-sm text-slate-500 mt-1">Full access to every module, the AI Planner, and reminders.</p>
    </div>
@else
    {{-- Plan selection --}}
    <div class="mb-6">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="font-semibold text-slate-800">
                    {{ $planSelectionTitle ?? '1. Choose a plan' }}
                </h2>
                @if (!empty($planSelectionSubtitle))
                    <p class="text-xs text-slate-500 mt-1">
                        {{ $planSelectionSubtitle }}
                    </p>
                @endif
            </div>
            @if (!empty($settings->supported_currencies))
                <div class="flex items-center gap-2">
                    <label for="pm-currency-select" class="text-xs text-slate-500">Show prices in</label>
                    <select id="pm-currency-select" onchange="pmConvertPlanPrices(this.value)" class="pm-input text-sm py-1.5">
                        <option value="{{ $settings->default_currency_code }}">{{ $settings->default_currency_code }} ({{ $settings->default_currency_symbol }})</option>
                        @foreach ($settings->supported_currencies as $currency)
                            <option value="{{ $currency['code'] }}" data-rate="{{ $currency['rate'] }}" data-symbol="{{ $currency['symbol'] }}">
                                {{ $currency['code'] }} ({{ $currency['symbol'] }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
        <p class="text-xs text-slate-400 mb-3">
            @if (!empty($settings->supported_currencies))
                Prices convert for viewing only you'll still be charged the {{ $settings->default_currency_code }} amount shown by default.
            @endif
        </p>
        @php
            $plansByCategory = $plans->groupBy('category');
        @endphp

        {{-- Individual plans are grouped by their Starter/Gold/Premium/Custom tier. --}}
        @if ($plansByCategory->get('individual', collect())->isNotEmpty())
            @php $individualPlans = $plansByCategory->get('individual'); @endphp
            @foreach (['starter' => 'Starter', 'gold' => 'Gold', 'premium' => 'Premium', 'custom' => 'Custom'] as $tierKey => $tierLabel)
                @php $tierPlans = $individualPlans->where('individual_tier', $tierKey); @endphp
                @if ($tierPlans->isNotEmpty())
                    <section class="mt-5" aria-label="{{ $tierLabel }} individual plans">
                        <h3 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">{{ $tierLabel }}</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                            @foreach ($tierPlans as $plan)
                                @include('subscription.partials.plan-card', ['plan' => $plan])
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach
            @php $untieredPlans = $individualPlans->whereNull('individual_tier'); @endphp
            @if ($untieredPlans->isNotEmpty())
                <section class="mt-5">
                    <h3 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">Starter</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                        @foreach ($untieredPlans as $plan)
                            @include('subscription.partials.plan-card', ['plan' => $plan])
                        @endforeach
                    </div>
                </section>
            @endif
        @endif

        {{-- Family & Small Team and Enterprise share one row each is
             typically just one card/prompt, so giving them a full-width
             row each (like Individual, which usually has several) left a
             lot of empty space. --}}
        @php
            $familyTeamPlans = $plansByCategory->get('family_team', collect());
            $hasEnterprise = $plansByCategory->get('organization', collect())->isNotEmpty();
        @endphp
        @if ($familyTeamPlans->isNotEmpty() || $hasEnterprise)
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-5 items-start">
                @if ($familyTeamPlans->isNotEmpty())
                    <div>
                        <h3 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">Family &amp; Small Team</h3>
                        <div class="grid grid-cols-1 gap-3">
                            @foreach ($familyTeamPlans as $plan)
                                @include('subscription.partials.plan-card', ['plan' => $plan])
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($hasEnterprise)
                    <div>
                        <h3 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">Enterprise</h3>
                        {{-- Sales-assisted, not self-serve no pricing cards
                             shown here; "Contact Sales" collects a few details
                             and an admin follows up manually. The underlying
                             'organization'-category plans still exist and are
                             used once a deal closes (see Admin -> Subscription
                             Plans), just never displayed as purchasable cards. --}}
                        <div class="rounded-xl p-5 border border-violet-200 bg-violet-50 h-full">
                            <p class="text-sm text-violet-900 font-medium mb-1">Built for larger teams</p>
                            <p class="text-sm text-violet-700 mb-3">
                                Custom seats, pricing, and onboarding for bigger organizations talk to us and
                                we'll put together something that fits.
                            </p>
                            <a href="{{ route('enterprise.contact') }}"
                               class="inline-flex items-center gap-2 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all"
                               style="background-color: #6D28D9;">
                                <i class="fa-solid fa-handshake" aria-hidden="true"></i>
                                Contact Sales
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <div id="pm-selected-plan-summary" class="text-sm text-slate-500 mb-4 hidden">
        Selected: <strong id="pm-selected-plan-label"></strong>
        <button type="button" onclick="document.getElementById('pm-checkout-modal').showModal()" class="ms-2 text-[var(--brand-1)] hover:underline">
            Reopen checkout
        </button>
    </div>
@endif
