@extends('layouts.app')

@section('title', 'Site Settings')

@section('content')
@php
    /*
    |--------------------------------------------------------------------------
    | Defensive settings-page defaults
    |--------------------------------------------------------------------------
    |
    | AdminSettingsController now loads optional modules defensively.
    | Keep the Blade view defensive as well so one missing optional module
    | cannot take down /admin/settings.
    |
    */
    $aiProviders = $aiProviders ?? collect();
    $meetingPlatforms = $meetingPlatforms ?? collect();
    $backupHistory = $backupHistory ?? collect();
    $backupSetting = $backupSetting ?? null;
    $currencySetting = $currencySetting ?? null;

    $privacyRoute = \Illuminate\Support\Facades\Route::has('privacy-policy')
        ? route('privacy-policy')
        : url('/privacy-policy');

    $termsRoute = \Illuminate\Support\Facades\Route::has('terms-of-use')
        ? route('terms-of-use')
        : url('/terms-of-use');

    $hasCurrencyRoute = \Illuminate\Support\Facades\Route::has('admin.settings.currency.edit');
    $hasSocialProvidersRoute = \Illuminate\Support\Facades\Route::has('admin.social-media-providers.index');
    $hasAiProviderPartial = view()->exists('admin.settings._ai-provider-modal');

    $currentTab = request('tab');

    if (! in_array($currentTab, [
        'branding',
        'pricing',
        'ai',
        'privacy',
        'terms',
        'meetings',
        'backups',
        'weekly-email',
    ], true)) {
        if (
            old('privacy_policy_version') !== null
            || old('privacy_policy_content') !== null
        ) {
            $currentTab = 'privacy';
        } elseif (old('appreciation_weekly_day') !== null) {
            $currentTab = 'weekly-email';
        } elseif (
            old('terms_of_use_version') !== null
            || old('terms_of_use_content') !== null
        ) {
            $currentTab = 'terms';
        } else {
            $currentTab = 'branding';
        }
    }

    $oldCodes = old('currency_codes');

    if (is_array($oldCodes)) {
        $oldSymbols = old('currency_symbols', []);
        $oldRates = old('currency_rates', []);

        $currencies = collect($oldCodes)->map(
            function ($code, $index) use ($oldSymbols, $oldRates) {
                return [
                    'code' => $code,
                    'symbol' => $oldSymbols[$index] ?? '',
                    'rate' => $oldRates[$index] ?? '',
                ];
            }
        );
    } else {
        $currencies = collect($settings->supported_currencies ?? []);
    }
@endphp

<div class="max-w-7xl mx-auto">
    {{-- Header --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <div
                class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shadow-sm shrink-0">
                <i class="fa-solid fa-gear text-xl" aria-hidden="true"></i>
            </div>

            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">
                    Site Settings
                </h1>

                <p class="text-sm text-slate-500 mt-1">
                    Manage branding, pricing, currency, AI, policies, meetings and backups.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($hasCurrencyRoute)
                <a
                    href="{{ route('admin.settings.currency.edit') }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    <i class="fa-solid fa-coins text-[var(--brand-1)]" aria-hidden="true"></i>
                    Currency Settings
                </a>
            @endif

            @if ($hasSocialProvidersRoute)
                <a
                    href="{{ route('admin.social-media-providers.index') }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    <i class="fa-solid fa-share-nodes text-[var(--brand-1)]" aria-hidden="true"></i>
                    Social Media APIs
                </a>
            @endif
        </div>
    </div>

    {{-- Flash messages --}}
    @if (session('success'))
        <x-alert type="success" :message="session('success')" class="mb-5" />
    @endif

    @if (session('warning'))
        <div class="mb-5"><x-alert type="warning" :message="session('warning')" :dismissible="false" :autoDismiss="false" /></div>
    @endif

    @if ($errors->any())
        <x-alert type="error" :dismissible="false" :autoDismiss="false">
            <div class="font-semibold mb-1">
                Please correct the highlighted settings.
            </div>
            <div>
                {{ $errors->first() }}
            </div>
        </x-alert>
    @endif

    {{-- Tabs --}}
    <div
        role="tablist"
        aria-label="Settings sections"
        class="flex gap-1 overflow-x-auto border-b border-slate-200 mb-6 pb-px">

        @foreach ([
            ['key' => 'branding', 'icon' => 'fa-image', 'label' => 'Branding'],
            ['key' => 'pricing', 'icon' => 'fa-wallet', 'label' => 'Pricing & Currency'],
            ['key' => 'ai', 'icon' => 'fa-robot', 'label' => 'AI Configuration'],
            ['key' => 'privacy', 'icon' => 'fa-file-shield', 'label' => 'Privacy Policy'],
            ['key' => 'terms', 'icon' => 'fa-file-contract', 'label' => 'Terms of Use'],
            ['key' => 'meetings', 'icon' => 'fa-video', 'label' => 'Meeting Platforms'],
            ['key' => 'backups', 'icon' => 'fa-cloud-arrow-up', 'label' => 'Backups'],
            ['key' => 'weekly-email', 'icon' => 'fa-heart', 'label' => 'Weekly Email'],
        ] as $tab)
            <button
                type="button"
                role="tab"
                id="pm-settings-tab-{{ $tab['key'] }}"
                aria-controls="pm-settings-panel-{{ $tab['key'] }}"
                aria-selected="{{ $currentTab === $tab['key'] ? 'true' : 'false' }}"
                tabindex="{{ $currentTab === $tab['key'] ? '0' : '-1' }}"
                data-tab="{{ $tab['key'] }}"
                onclick="pmSelectSettingsTab('{{ $tab['key'] }}')"
                onkeydown="pmSettingsTabKeydown(event, '{{ $tab['key'] }}')"
                class="pm-settings-tab shrink-0 flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors
                    {{ $currentTab === $tab['key']
                        ? 'border-[var(--brand-1)] text-[var(--brand-1)]'
                        : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                <i class="fa-solid {{ $tab['icon'] }}" aria-hidden="true"></i>
                <span>{{ $tab['label'] }}</span>
            </button>
        @endforeach
    </div>

    {{--
    |--------------------------------------------------------------------------
    | MAIN SETTINGS FORM
    |--------------------------------------------------------------------------
    --}}
    <form
        method="POST"
        action="{{ route('admin.settings.update') }}"
        enctype="multipart/form-data">
        @csrf

        {{-- BRANDING --}}
        <section
            role="tabpanel"
            id="pm-settings-panel-branding"
            aria-labelledby="pm-settings-tab-branding"
            tabindex="0"
            class="pm-settings-panel"
            @if($currentTab !== 'branding') hidden @endif>

            <div class="max-w-4xl pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5 sm:p-6">
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-slate-800">
                        Branding
                    </h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Configure the name, logo and browser icon used throughout My Digital Diary.
                    </p>
                </div>

                <div class="space-y-6">
                    <div>
                        <label
                            for="site_name"
                            class="block text-sm font-medium text-slate-700 mb-1">
                            System Name
                        </label>

                        <input
                            type="text"
                            id="site_name"
                            name="site_name"
                            value="{{ old('site_name', $settings->site_name) }}"
                            required
                            @error('site_name') aria-invalid="true" @enderror
                            class="pm-input">

                        <p class="text-xs text-slate-400 mt-1">
                            Displayed in the sidebar, page titles and selected emails.
                        </p>

                        @error('site_name')
                            <p class="text-sm text-rose-600 mt-1">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                        <div class="rounded-xl border border-slate-200 p-4">
                            <label
                                for="logo"
                                class="block text-sm font-medium text-slate-700 mb-3">
                                Logo
                            </label>

                            <div class="min-h-16 flex items-center gap-4 mb-3">
                                @php
                                    try {
                                        $logoUrl = method_exists($settings, 'logoUrl')
                                            ? $settings->logoUrl()
                                            : null;
                                    } catch (\Throwable $e) {
                                        $logoUrl = null;
                                    }
                                @endphp

                                @if ($logoUrl)
                                    <img
                                        src="{{ $logoUrl }}"
                                        alt="Current logo"
                                        class="h-12 max-w-48 object-contain border border-slate-200 rounded-lg p-1"
                                        onerror="this.style.display='none';">
                                @else
                                    <div class="text-xs text-slate-400">
                                        No logo uploaded.
                                    </div>
                                @endif
                            </div>

                            <input
                                type="file"
                                id="logo"
                                name="logo"
                                accept="image/*"
                                @error('logo') aria-invalid="true" @enderror
                                class="block w-full text-sm">

                            <p class="text-xs text-slate-400 mt-2">
                                PNG, JPG, WEBP or another supported image. Maximum 1MB.
                            </p>

                            @error('logo')
                                <p class="text-sm text-rose-600 mt-1">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div class="rounded-xl border border-slate-200 p-4">
                            <label
                                for="favicon"
                                class="block text-sm font-medium text-slate-700 mb-3">
                                Favicon
                            </label>

                            <div class="min-h-16 flex items-center gap-4 mb-3">
                                @php
                                    try {
                                        $faviconUrl = method_exists($settings, 'faviconUrl')
                                            ? $settings->faviconUrl()
                                            : null;
                                    } catch (\Throwable $e) {
                                        $faviconUrl = null;
                                    }
                                @endphp

                                @if ($faviconUrl)
                                    <img
                                        src="{{ $faviconUrl }}"
                                        alt="Current favicon"
                                        class="h-10 w-10 object-contain border border-slate-200 rounded-lg p-1"
                                        onerror="this.style.display='none';">
                                @else
                                    <div class="text-xs text-slate-400">
                                        No favicon uploaded.
                                    </div>
                                @endif
                            </div>

                            <input
                                type="file"
                                id="favicon"
                                name="favicon"
                                accept="image/*"
                                @error('favicon') aria-invalid="true" @enderror
                                class="block w-full text-sm">

                            <p class="text-xs text-slate-400 mt-2">
                                A square image is recommended. Maximum 512KB.
                            </p>

                            @error('favicon')
                                <p class="text-sm text-rose-600 mt-1">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-5 mt-6 border-t border-slate-100">
                    <button
                        type="submit"
                        class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm">
                        <i class="fa-solid fa-floppy-disk mr-1" aria-hidden="true"></i>
                        Save Branding
                    </button>
                </div>
            </div>
        </section>

        {{-- PRICING / CURRENCY --}}
        <section
            role="tabpanel"
            id="pm-settings-panel-pricing"
            aria-labelledby="pm-settings-tab-pricing"
            tabindex="0"
            class="pm-settings-panel"
            @if($currentTab !== 'pricing') hidden @endif>

            <div class="max-w-5xl space-y-5">
                <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-6">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-800">
                                Pricing & Base Currency
                            </h2>
                            <p class="text-sm text-slate-500 mt-1">
                                Configure subscription pricing and the base currency in which stored amounts are denominated.
                            </p>
                        </div>

                        @if ($hasCurrencyRoute)
                            <a
                                href="{{ route('admin.settings.currency.edit') }}"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                                Live Currency Settings
                            </a>
                        @endif
                    </div>

                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 mb-6">
                        <div class="flex gap-3">
                            <i class="fa-solid fa-circle-info text-blue-600 mt-0.5" aria-hidden="true"></i>
                            <div class="text-sm text-blue-900">
                                <p class="font-semibold">
                                    Base currency and display currency are different.
                                </p>
                                <p class="mt-1 text-blue-800">
                                    The base currency identifies how existing stored monetary values are interpreted.
                                    Changing the code does not rewrite old amounts. User-selected display currencies are
                                    converted using the live/cached exchange-rate service.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label
                                for="default_currency_code"
                                class="block text-sm font-medium text-slate-700 mb-1">
                                Base Currency Code
                            </label>

                            <input
                                type="text"
                                id="default_currency_code"
                                name="default_currency_code"
                                maxlength="3"
                                value="{{ old('default_currency_code', $settings->default_currency_code) }}"
                                required
                                class="pm-input uppercase">

                            @error('default_currency_code')
                                <p class="text-sm text-rose-600 mt-1">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label
                                for="default_currency_symbol"
                                class="block text-sm font-medium text-slate-700 mb-1">
                                Symbol / Label
                            </label>

                            <input
                                type="text"
                                id="default_currency_symbol"
                                name="default_currency_symbol"
                                maxlength="10"
                                value="{{ old('default_currency_symbol', $settings->default_currency_symbol) }}"
                                required
                                class="pm-input">
                        </div>

                        <div>
                            <label
                                for="default_currency_decimals"
                                class="block text-sm font-medium text-slate-700 mb-1">
                                Decimal Places
                            </label>

                            <input
                                type="number"
                                id="default_currency_decimals"
                                name="default_currency_decimals"
                                min="0"
                                max="4"
                                value="{{ old('default_currency_decimals', $settings->default_currency_decimals) }}"
                                required
                                class="pm-input">
                        </div>
                    </div>

                    @if ($currencySetting)
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4 text-sm">
                            <div class="rounded-lg bg-slate-50 border border-slate-200 p-3">
                                <div class="text-xs text-slate-500">
                                    Live Rate Base
                                </div>
                                <div class="font-semibold text-slate-800 mt-1">
                                    {{ $currencySetting->base_currency ?? '—' }}
                                </div>
                            </div>

                            <div class="rounded-lg bg-slate-50 border border-slate-200 p-3">
                                <div class="text-xs text-slate-500">
                                    Default Display
                                </div>
                                <div class="font-semibold text-slate-800 mt-1">
                                    {{ $currencySetting->display_currency ?? '—' }}
                                </div>
                            </div>

                            <div class="rounded-lg bg-slate-50 border border-slate-200 p-3">
                                <div class="text-xs text-slate-500">
                                    User Selection
                                </div>
                                <div class="font-semibold mt-1 {{ $currencySetting->allow_user_selection ? 'text-emerald-700' : 'text-slate-600' }}">
                                    {{ $currencySetting->allow_user_selection ? 'Enabled' : 'Disabled' }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5 sm:p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label
                                for="monthly_price"
                                class="block text-sm font-medium text-slate-700 mb-1">
                                Monthly Subscription Price
                            </label>

                            <input
                                type="number"
                                id="monthly_price"
                                name="monthly_price"
                                step="0.01"
                                min="0"
                                value="{{ old('monthly_price', $settings->monthly_price) }}"
                                required
                                @error('monthly_price') aria-invalid="true" @enderror
                                class="pm-input">

                            <p class="text-xs text-slate-400 mt-1">
                                Enter the amount in {{ $settings->default_currency_code ?: 'the base currency' }}.
                            </p>

                            @error('monthly_price')
                                <p class="text-sm text-rose-600 mt-1">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label
                                for="trial_days"
                                class="block text-sm font-medium text-slate-700 mb-1">
                                Free Trial Period
                            </label>

                            <div class="relative">
                                <input
                                    type="number"
                                    id="trial_days"
                                    name="trial_days"
                                    min="0"
                                    max="365"
                                    value="{{ old('trial_days', $settings->trial_days) }}"
                                    required
                                    @error('trial_days') aria-invalid="true" @enderror
                                    class="pm-input pr-16">

                                <span class="absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">
                                    days
                                </span>
                            </div>

                            <p class="text-xs text-slate-400 mt-1">
                                Updating this setting recalculates the end date for users who are still trialing.
                            </p>

                            @error('trial_days')
                                <p class="text-sm text-rose-600 mt-1">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
                        <div>
                            <h3 class="font-semibold text-slate-800">
                                Manual Display-Currency Fallbacks
                            </h3>
                            <p class="text-xs text-slate-500 mt-1 max-w-2xl">
                                These legacy/manual rates remain available for web pages that still read
                                <code>SiteSetting::supported_currencies</code>. Mobile and live conversions should use
                                Currency Settings and the exchange-rate service.
                            </p>
                        </div>

                        <button
                            type="button"
                            onclick="pmAddCurrencyRow()"
                            class="inline-flex items-center gap-1 text-sm font-semibold text-[var(--brand-1)] hover:underline">
                            <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
                            Add Currency
                        </button>
                    </div>

                    <div class="hidden sm:grid grid-cols-12 gap-2 px-1 mb-1 text-[11px] uppercase tracking-wide text-slate-400">
                        <div class="col-span-3">Code</div>
                        <div class="col-span-3">Symbol</div>
                        <div class="col-span-5">Base units per 1 currency</div>
                        <div class="col-span-1"></div>
                    </div>

                    <div id="pm-currency-rows" class="space-y-2">
                        @forelse ($currencies as $currency)
                            @php
                                $code = is_array($currency)
                                    ? ($currency['code'] ?? '')
                                    : data_get($currency, 'code', '');

                                $symbol = is_array($currency)
                                    ? ($currency['symbol'] ?? '')
                                    : data_get($currency, 'symbol', '');

                                $rate = is_array($currency)
                                    ? ($currency['rate'] ?? '')
                                    : data_get($currency, 'rate', '');
                            @endphp

                            <div class="grid grid-cols-12 gap-2 pm-currency-row">
                                <input
                                    type="text"
                                    name="currency_codes[]"
                                    value="{{ $code }}"
                                    placeholder="USD"
                                    maxlength="3"
                                    class="col-span-4 sm:col-span-3 pm-input text-sm uppercase">

                                <input
                                    type="text"
                                    name="currency_symbols[]"
                                    value="{{ $symbol }}"
                                    placeholder="$"
                                    maxlength="10"
                                    class="col-span-4 sm:col-span-3 pm-input text-sm">

                                <input
                                    type="number"
                                    name="currency_rates[]"
                                    value="{{ $rate }}"
                                    placeholder="{{ $settings->default_currency_code ?: 'UGX' }} per 1"
                                    min="0"
                                    step="0.00000001"
                                    class="col-span-3 sm:col-span-5 pm-input text-sm">

                                <button
                                    type="button"
                                    onclick="this.closest('.pm-currency-row').remove()"
                                    class="col-span-1 flex items-center justify-center text-rose-500 hover:text-rose-700"
                                    aria-label="Remove currency">
                                    <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
                                </button>
                            </div>
                        @empty
                            <div
                                id="pm-no-currency-row"
                                class="rounded-lg border border-dashed border-slate-200 px-4 py-5 text-sm text-center text-slate-400">
                                No manual currency fallback rows have been added.
                            </div>
                        @endforelse
                    </div>

                    @error('currency_codes.*')
                        <p class="text-sm text-rose-600 mt-2">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm">
                        <i class="fa-solid fa-floppy-disk mr-1" aria-hidden="true"></i>
                        Save Pricing & Currency
                    </button>
                </div>
            </div>
        </section>

        {{-- AI --}}
        <section
            role="tabpanel"
            id="pm-settings-panel-ai"
            aria-labelledby="pm-settings-tab-ai"
            tabindex="0"
            class="pm-settings-panel"
            @if($currentTab !== 'ai') hidden @endif>

            <div class="max-w-5xl space-y-5">
                <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5 sm:p-6">
                    <h2 class="text-lg font-semibold text-slate-800">
                        Default AI Access
                    </h2>

                    <p class="text-sm text-slate-500 mt-1 mb-5">
                        Configure the shared AI provider and the monthly free-use limit.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label
                                for="default_ai_provider"
                                class="block text-sm font-medium text-slate-700 mb-1">
                                Default Provider
                            </label>

                            <select
                                id="default_ai_provider"
                                name="default_ai_provider"
                                class="pm-input">

                                <option
                                    value=""
                                    @selected(! old('default_ai_provider', $settings->default_ai_provider))>
                                    Not configured
                                </option>

                                @foreach ($aiProviders as $provider)
                                    @if ((bool) ($provider->is_enabled ?? false))
                                        <option
                                            value="{{ $provider->key }}"
                                            @selected(
                                                old(
                                                    'default_ai_provider',
                                                    $settings->default_ai_provider
                                                ) === $provider->key
                                            )>
                                            {{ $provider->name }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label
                                for="default_ai_free_limit_per_month"
                                class="block text-sm font-medium text-slate-700 mb-1">
                                Free Plans Per User / Month
                            </label>

                            <input
                                type="number"
                                id="default_ai_free_limit_per_month"
                                name="default_ai_free_limit_per_month"
                                min="0"
                                max="1000"
                                value="{{ old(
                                    'default_ai_free_limit_per_month',
                                    $settings->default_ai_free_limit_per_month
                                ) }}"
                                required
                                class="pm-input">

                            @error('default_ai_free_limit_per_month')
                                <p class="text-sm text-rose-600 mt-1">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4">
                        <label
                            for="default_ai_api_key"
                            class="block text-sm font-medium text-slate-700 mb-1">
                            Shared API Key
                        </label>

                        @php
                            try {
                                $hasDefaultAiKey = method_exists($settings, 'hasDefaultAiKey')
                                    ? $settings->hasDefaultAiKey()
                                    : false;
                            } catch (\Throwable $e) {
                                $hasDefaultAiKey = false;
                            }
                        @endphp

                        <div class="relative">
                            <input
                                type="password"
                                id="default_ai_api_key"
                                name="default_ai_api_key"
                                autocomplete="new-password"
                                placeholder="{{ $hasDefaultAiKey
                                    ? 'Configured — leave blank to keep the existing key'
                                    : 'Enter API key' }}"
                                class="pm-input pr-11">

                            <button
                                type="button"
                                onclick="pmTogglePassword('default_ai_api_key', this)"
                                class="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-600"
                                aria-label="Show or hide API key">
                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>

                        <p class="text-xs text-slate-400 mt-1">
                            Leave blank to keep the current stored key.
                        </p>
                    </div>

                    <div class="flex justify-end pt-5 mt-5 border-t border-slate-100">
                        <button
                            type="submit"
                            formaction="{{ route('admin.settings.ai.test-connection') }}"
                            formmethod="POST"
                            class="mr-auto inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                            <i class="fa-solid fa-plug" aria-hidden="true"></i>
                            Test AI Connection
                        </button>
                        <button
                            type="submit"
                            name="settings_section"
                            value="ai"
                            class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm">
                            <i class="fa-solid fa-floppy-disk mr-1" aria-hidden="true"></i>
                            Save AI Settings
                        </button>
                    </div>
                </div>

                <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div>
                            <h3 class="font-semibold text-slate-800">
                                AI Providers
                            </h3>
                            <p class="text-xs text-slate-500 mt-1">
                                Configure built-in and OpenAI-compatible providers.
                            </p>
                        </div>

                        @if ($hasAiProviderPartial && \Illuminate\Support\Facades\Route::has('admin.ai-providers.store'))
                            <button
                                type="button"
                                onclick="document.getElementById('ai-provider-create-modal')?.showModal()"
                                class="text-sm font-semibold text-[var(--brand-1)] hover:underline">
                                <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
                                Add Provider
                            </button>
                        @endif
                    </div>

                    @if ($aiProviders->isEmpty())
                        <div class="rounded-lg border border-dashed border-slate-200 p-5 text-sm text-center text-slate-400">
                            No AI providers are currently available.
                        </div>
                    @else
                        <div class="space-y-2">
                            @foreach ($aiProviders as $provider)
                                @php
                                    try {
                                        $isBuiltIn = method_exists($provider, 'isBuiltIn')
                                            ? $provider->isBuiltIn()
                                            : false;
                                    } catch (\Throwable $e) {
                                        $isBuiltIn = false;
                                    }
                                @endphp

                                <div
                                    class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border border-slate-200 rounded-lg px-3 py-3 text-sm">
                                    <div>
                                        <div class="font-semibold text-slate-700">
                                            {{ $provider->name }}
                                        </div>

                                        <div class="text-xs text-slate-400 mt-0.5">
                                            {{ $isBuiltIn
                                                ? 'Built-in provider'
                                                : ($provider->default_model ?: 'Custom provider') }}
                                            ·
                                            {{ $provider->is_enabled ? 'Enabled' : 'Disabled' }}
                                        </div>
                                    </div>

                                    @unless ($isBuiltIn)
                                        <div class="flex flex-wrap items-center gap-3">
                                            @if (\Illuminate\Support\Facades\Route::has('admin.ai-providers.toggle'))
                                                <form
                                                    action="{{ route('admin.ai-providers.toggle', $provider->id) }}"
                                                    method="POST">
                                                    @csrf
                                                    <button
                                                        type="submit"
                                                        class="text-[var(--brand-1)] hover:underline">
                                                        {{ $provider->is_enabled ? 'Disable' : 'Enable' }}
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($hasAiProviderPartial)
                                                <button
                                                    type="button"
                                                    onclick="document.getElementById('ai-provider-edit-modal-{{ $provider->id }}')?.showModal()"
                                                    class="text-[var(--brand-1)] hover:underline">
                                                    Edit
                                                </button>
                                            @endif

                                            @if (\Illuminate\Support\Facades\Route::has('admin.ai-providers.destroy'))
                                                <form
                                                    action="{{ route('admin.ai-providers.destroy', $provider->id) }}"
                                                    method="POST"
                                                    data-confirm="Remove {{ $provider->name }}?"
                                                    data-confirm-title="Remove AI provider?"
                                                    data-confirm-text="Remove">
                                                    @csrf
                                                    @method('DELETE')

                                                    <button
                                                        type="submit"
                                                        class="text-rose-600 hover:underline">
                                                        Delete
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @endunless
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </form>

    {{--
    |--------------------------------------------------------------------------
    | PRIVACY POLICY
    |--------------------------------------------------------------------------
    --}}
    <form
        method="POST"
        action="{{ route('admin.settings.privacy.update') }}">
        @csrf

        <section
            role="tabpanel"
            id="pm-settings-panel-privacy"
            aria-labelledby="pm-settings-tab-privacy"
            tabindex="0"
            class="pm-settings-panel"
            @if($currentTab !== 'privacy') hidden @endif>

            <div class="max-w-5xl pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5 sm:p-6 space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-800">
                            Privacy Policy
                        </h2>

                        <p class="text-sm text-slate-500 mt-1">
                            Edit the live Privacy Policy shown to users and public visitors.
                        </p>
                    </div>

                    <a
                        href="{{ $privacyRoute }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-sm font-semibold text-[var(--brand-1)] hover:underline">
                        View Live Page
                    </a>
                </div>

                <div>
                    <label
                        for="privacy_policy_version"
                        class="block text-sm font-medium text-slate-700 mb-1">
                        Version
                    </label>

                    <input
                        type="text"
                        id="privacy_policy_version"
                        name="privacy_policy_version"
                        value="{{ old(
                            'privacy_policy_version',
                            $settings->privacy_policy_version ?: '1.0'
                        ) }}"
                        required
                        @error('privacy_policy_version') aria-invalid="true" @enderror
                        class="pm-input max-w-xs">

                    @error('privacy_policy_version')
                        <p class="text-xs text-rose-600 mt-1">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="privacy_policy_content"
                        class="block text-sm font-medium text-slate-700 mb-1">
                        Content
                    </label>

                    @php
                        try {
                            $privacyFallback = method_exists($settings, 'privacyPolicyContent')
                                ? $settings->privacyPolicyContent()
                                : '';
                        } catch (\Throwable $e) {
                            $privacyFallback = '';
                        }
                    @endphp

                    <textarea
                        id="privacy_policy_content"
                        name="privacy_policy_content"
                        rows="18"
                        @error('privacy_policy_content') aria-invalid="true" @enderror
                        class="pm-input font-mono text-sm">{{ old(
                            'privacy_policy_content',
                            $settings->privacy_policy_content ?: $privacyFallback
                        ) }}</textarea>

                    @error('privacy_policy_content')
                        <p class="text-xs text-rose-600 mt-1">
                            {{ $message }}
                        </p>
                    @enderror

                    <p class="text-xs text-slate-400 mt-1">
                        Plain text is supported. You may also use <code>&lt;h2&gt;</code> through <code>&lt;h6&gt;</code>, <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>, <code>&lt;ul&gt;</code>, <code>&lt;ol&gt;</code> and <code>&lt;li&gt;</code>. All attributes and other HTML are removed.
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <a
                        href="{{ $privacyRoute }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-slate-700 text-sm font-medium hover:bg-slate-50">
                        Preview
                    </a>

                    <button
                        type="submit"
                        class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm">
                        <i class="fa-solid fa-floppy-disk mr-1" aria-hidden="true"></i>
                        Save Privacy Policy
                    </button>
                </div>
            </div>
        </section>
    </form>

    {{--
    |--------------------------------------------------------------------------
    | TERMS OF USE
    |--------------------------------------------------------------------------
    --}}
    <form
        method="POST"
        action="{{ route('admin.settings.terms.update') }}">
        @csrf

        <section
            role="tabpanel"
            id="pm-settings-panel-terms"
            aria-labelledby="pm-settings-tab-terms"
            tabindex="0"
            class="pm-settings-panel"
            @if($currentTab !== 'terms') hidden @endif>

            <div class="max-w-5xl pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5 sm:p-6 space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-800">
                            Terms of Use
                        </h2>

                        <p class="text-sm text-slate-500 mt-1">
                            Edit the Terms of Use shown throughout My Digital Diary.
                        </p>
                    </div>

                    <a
                        href="{{ $termsRoute }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-sm font-semibold text-[var(--brand-1)] hover:underline">
                        View Live Page
                    </a>
                </div>

                <div>
                    <label
                        for="terms_of_use_version"
                        class="block text-sm font-medium text-slate-700 mb-1">
                        Version
                    </label>

                    <input
                        type="text"
                        id="terms_of_use_version"
                        name="terms_of_use_version"
                        value="{{ old(
                            'terms_of_use_version',
                            $settings->terms_of_use_version ?: '1.0'
                        ) }}"
                        required
                        @error('terms_of_use_version') aria-invalid="true" @enderror
                        class="pm-input max-w-xs">

                    @error('terms_of_use_version')
                        <p class="text-xs text-rose-600 mt-1">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="terms_of_use_content"
                        class="block text-sm font-medium text-slate-700 mb-1">
                        Content
                    </label>

                    @php
                        try {
                            $termsFallback = method_exists($settings, 'termsOfUseContent')
                                ? $settings->termsOfUseContent()
                                : '';
                        } catch (\Throwable $e) {
                            $termsFallback = '';
                        }
                    @endphp

                    <textarea
                        id="terms_of_use_content"
                        name="terms_of_use_content"
                        rows="20"
                        @error('terms_of_use_content') aria-invalid="true" @enderror
                        class="pm-input font-mono text-sm">{{ old(
                            'terms_of_use_content',
                            $settings->terms_of_use_content ?: $termsFallback
                        ) }}</textarea>

                    @error('terms_of_use_content')
                        <p class="text-xs text-rose-600 mt-1">
                            {{ $message }}
                        </p>
                    @enderror

                    <p class="text-xs text-slate-400 mt-1">
                        Plain text is supported. You may also use <code>&lt;h2&gt;</code> through <code>&lt;h6&gt;</code>, <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>, <code>&lt;ul&gt;</code>, <code>&lt;ol&gt;</code> and <code>&lt;li&gt;</code>. All attributes and other HTML are removed.
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <a
                        href="{{ $termsRoute }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-slate-700 text-sm font-medium hover:bg-slate-50">
                        Preview
                    </a>

                    <button
                        type="submit"
                        class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm">
                        <i class="fa-solid fa-floppy-disk mr-1" aria-hidden="true"></i>
                        Save Terms of Use
                    </button>
                </div>
            </div>
        </section>
    </form>

    {{--
    |--------------------------------------------------------------------------
    | WEEKLY APPRECIATION EMAIL
    |--------------------------------------------------------------------------
    --}}
    @php
        $weeklyAppreciation = \App\Services\WeeklyAppreciationSchedule::settings();
        $weeklyEnabled = (bool) old('appreciation_weekly_enabled', $weeklyAppreciation['enabled']);
        $weeklyDay = (int) old('appreciation_weekly_day', $weeklyAppreciation['day']);
        $weeklyTime = (string) old('appreciation_weekly_time', $weeklyAppreciation['time']);
    @endphp
    <form
        method="POST"
        action="{{ route('admin.settings.weekly-appreciation.update') }}">
        @csrf

        <section
            role="tabpanel"
            id="pm-settings-panel-weekly-email"
            aria-labelledby="pm-settings-tab-weekly-email"
            tabindex="0"
            class="pm-settings-panel"
            @if($currentTab !== 'weekly-email') hidden @endif>

            <div class="max-w-3xl pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5 sm:p-6 space-y-5">
                <div>
                    <h2 class="text-lg font-semibold text-slate-800">Weekly appreciation email</h2>
                    <p class="text-sm text-slate-500 mt-1">
                        The automatic thank-you email to every eligible member. It runs at most once a week, and each person gets at most one appreciation email per week.
                    </p>
                </div>

                <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-4 text-sm text-slate-700 cursor-pointer">
                    <input type="hidden" name="appreciation_weekly_enabled" value="0">
                    <input type="checkbox" id="appreciation_weekly_enabled" name="appreciation_weekly_enabled" value="1" @checked($weeklyEnabled)
                           class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-1)]">
                    <span class="flex-1 font-medium">Send the weekly appreciation email automatically</span>
                </label>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="appreciation_weekly_day" class="block text-sm font-medium text-slate-700 mb-1">Day of the week</label>
                        <select id="appreciation_weekly_day" name="appreciation_weekly_day" class="pm-input w-full" required
                                @error('appreciation_weekly_day') aria-invalid="true" @enderror>
                            <option value="" disabled>Choose a day</option>
                            @foreach (\App\Services\WeeklyAppreciationSchedule::DAYS as $value => $label)
                                <option value="{{ $value }}" @selected($weeklyDay === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('appreciation_weekly_day')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="appreciation_weekly_time" class="block text-sm font-medium text-slate-700 mb-1">
                            Time <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">{{ config('app.timezone') }}</span>
                        </label>
                        <input type="time" id="appreciation_weekly_time" name="appreciation_weekly_time" step="60" required
                               value="{{ $weeklyTime }}" placeholder="09:00" class="pm-input w-full"
                               @error('appreciation_weekly_time') aria-invalid="true" @enderror>
                        @error('appreciation_weekly_time')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="rounded-lg bg-slate-50 border border-slate-100 p-3 text-sm text-slate-600">
                    <i class="fa-regular fa-clock mr-1" aria-hidden="true"></i>
                    Currently: <strong>{{ \App\Services\WeeklyAppreciationSchedule::describe() }}</strong>
                    @if ($weeklyAppreciation['last_run_week'])
                        · last run {{ $weeklyAppreciation['last_run_week'] }}
                    @endif
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    @if (\Illuminate\Support\Facades\Route::has('admin.appreciation.index'))
                        <a href="{{ route('admin.appreciation.index') }}" class="px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-slate-700 text-sm font-medium hover:bg-slate-50">
                            Appreciation history
                        </a>
                    @endif
                    <button type="submit" class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm">
                        <i class="fa-solid fa-floppy-disk mr-1" aria-hidden="true"></i>
                        Save weekly email
                    </button>
                </div>
            </div>
        </section>
    </form>

    {{--
    |--------------------------------------------------------------------------
    | MEETING PLATFORMS
    |--------------------------------------------------------------------------
    --}}
    <section
        role="tabpanel"
        id="pm-settings-panel-meetings"
        aria-labelledby="pm-settings-tab-meetings"
        tabindex="0"
        class="pm-settings-panel"
        @if($currentTab !== 'meetings') hidden @endif>

        <div class="max-w-5xl space-y-4">
            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-4">
                <h2 class="font-semibold text-slate-800">
                    Meeting Platform Integrations
                </h2>

                <p class="text-sm text-slate-500 mt-1">
                    Configure OAuth application credentials. Users connect their own meeting accounts separately.
                </p>
            </div>

            @forelse ($meetingPlatforms as $platform)
                @php
                    try {
                        $meetingConfigured = method_exists($platform, 'isConfigured')
                            ? $platform->isConfigured()
                            : filled($platform->client_id ?? null);
                    } catch (\Throwable $e) {
                        $meetingConfigured = false;
                    }
                @endphp

                <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                    @if (\Illuminate\Support\Facades\Route::has('admin.meeting-platforms.update'))
                        <form
                            method="POST"
                            action="{{ route('admin.meeting-platforms.update', $platform->id) }}">
                            @csrf

                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                                <div>
                                    <h3 class="font-semibold text-slate-800">
                                        {{ $platform->name }}
                                    </h3>

                                    <div class="flex flex-wrap gap-2 mt-1 text-xs">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-1 border
                                                {{ $meetingConfigured
                                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                    : 'bg-amber-50 text-amber-700 border-amber-200' }}">
                                            {{ $meetingConfigured
                                                ? 'Credentials configured'
                                                : 'Credentials incomplete' }}
                                        </span>

                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-1 border
                                                {{ $platform->is_enabled
                                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                    : 'bg-slate-50 text-slate-500 border-slate-200' }}">
                                            {{ $platform->is_enabled
                                                ? 'Enabled for users'
                                                : 'Disabled for users' }}
                                        </span>
                                    </div>
                                </div>

                                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                                    <input
                                        type="checkbox"
                                        name="is_enabled"
                                        value="1"
                                        @checked($platform->is_enabled)
                                        class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                                    Enable
                                </label>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label
                                        for="client_id_{{ $platform->id }}"
                                        class="block text-sm font-medium text-slate-700 mb-1">
                                        Client ID
                                    </label>

                                    <input
                                        type="text"
                                        id="client_id_{{ $platform->id }}"
                                        name="client_id"
                                        value="{{ old('client_id', $platform->client_id) }}"
                                        autocomplete="off"
                                        class="pm-input font-mono text-sm">
                                </div>

                                <div>
                                    <label
                                        for="client_secret_{{ $platform->id }}"
                                        class="block text-sm font-medium text-slate-700 mb-1">
                                        Client Secret
                                    </label>

                                    <div class="relative">
                                        <input
                                            type="password"
                                            id="client_secret_{{ $platform->id }}"
                                            name="client_secret"
                                            value=""
                                            autocomplete="new-password"
                                            placeholder="{{ filled($platform->client_secret ?? null)
                                                ? 'Configured — leave blank to keep it'
                                                : 'Enter Client Secret' }}"
                                            class="pm-input pr-11 text-sm">

                                        <button
                                            type="button"
                                            onclick="pmTogglePassword('client_secret_{{ $platform->id }}', this)"
                                            class="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-600"
                                            aria-label="Show or hide Client Secret">
                                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 mt-4 text-xs text-slate-500">
                                <span class="font-medium">Redirect URI:</span>
                                <code class="break-all">
                                    {{ url('/meetings/connect/' . $platform->platform . '/callback') }}
                                </code>
                            </div>

                            <div class="flex justify-end mt-4">
                                <button
                                    type="submit"
                                    class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-semibold">
                                    Save {{ $platform->name }}
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="text-sm text-slate-500">
                            {{ $platform->name }} is loaded, but the update route is unavailable.
                        </div>
                    @endif
                </div>
            @empty
                <div class="pm-card-bg shadow-sm border border-dashed border-slate-200 rounded-xl p-6 text-center">
                    <i class="fa-solid fa-video text-2xl text-slate-300" aria-hidden="true"></i>

                    <p class="text-sm text-slate-500 mt-2">
                        No meeting platforms are currently configured.
                    </p>
                </div>
            @endforelse
        </div>
    </section>

    {{--
    |--------------------------------------------------------------------------
    | BACKUPS
    |--------------------------------------------------------------------------
    --}}
    <section
        role="tabpanel"
        id="pm-settings-panel-backups"
        aria-labelledby="pm-settings-tab-backups"
        tabindex="0"
        class="pm-settings-panel"
        @if($currentTab !== 'backups') hidden @endif>

        <div class="max-w-6xl space-y-5">
            @if ($backupSetting)
                <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
                        <div>
                            <h2 class="font-semibold text-slate-800 flex items-center gap-2">
                                <i class="fa-solid fa-cloud-arrow-up text-[var(--brand-1)]" aria-hidden="true"></i>
                                Automatic Backups
                            </h2>

                            <p class="text-xs text-slate-500 mt-1">
                                Configure database and uploaded-file backups.
                            </p>
                        </div>

                        @if ($backupSetting->last_run_at)
                            <span class="text-xs text-slate-500">
                                Last backup:
                                {{ $backupSetting->last_run_at
                                    ->timezone($backupSetting->timezone ?: 'Africa/Kampala')
                                    ->format('d M Y, H:i') }}
                            </span>
                        @endif
                    </div>

                    @if (\Illuminate\Support\Facades\Route::has('admin.backups.update'))
                        <form
                            method="POST"
                            action="{{ route('admin.backups.update') }}"
                            class="space-y-4">
                            @csrf

                            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                <input
                                    type="checkbox"
                                    name="enabled"
                                    value="1"
                                    @checked($backupSetting->enabled)
                                    class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">

                                Enable automatic backups
                            </label>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">
                                        Frequency
                                    </label>

                                    <select
                                        name="frequency"
                                        class="pm-input">
                                        @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $value => $label)
                                            <option
                                                value="{{ $value }}"
                                                @selected($backupSetting->frequency === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">
                                        Weekly Day
                                    </label>

                                    <select
                                        name="day_of_week"
                                        class="pm-input">
                                        @foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $i => $day)
                                            <option
                                                value="{{ $i }}"
                                                @selected((int) $backupSetting->day_of_week === $i)>
                                                {{ $day }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">
                                        Monthly Day
                                    </label>

                                    <input
                                        type="number"
                                        min="1"
                                        max="28"
                                        name="day_of_month"
                                        value="{{ $backupSetting->day_of_month ?: 1 }}"
                                        class="pm-input">
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">
                                        Backup Time
                                    </label>

                                    <input
                                        type="time"
                                        name="run_time"
                                        value="{{ substr((string) $backupSetting->run_time, 0, 5) }}"
                                        class="pm-input">
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">
                                        Timezone
                                    </label>

                                    <input
                                        type="text"
                                        name="timezone"
                                        value="{{ $backupSetting->timezone ?: 'Africa/Kampala' }}"
                                        class="pm-input">
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">
                                        Backup Type
                                    </label>

                                    <select
                                        name="backup_type"
                                        class="pm-input">
                                        <option
                                            value="both"
                                            @selected($backupSetting->backup_type === 'both')>
                                            Database + files
                                        </option>

                                        <option
                                            value="database"
                                            @selected($backupSetting->backup_type === 'database')>
                                            Database only
                                        </option>

                                        <option
                                            value="files"
                                            @selected($backupSetting->backup_type === 'files')>
                                            Files only
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">
                                        Storage
                                    </label>

                                    <select
                                        name="provider"
                                        id="backup-provider"
                                        class="pm-input"
                                        onchange="pmToggleBackupCloudFields()">
                                        <option
                                            value="local"
                                            @selected($backupSetting->provider === 'local')>
                                            Local server
                                        </option>

                                        <option
                                            value="s3"
                                            @selected($backupSetting->provider === 's3')>
                                            S3 / compatible cloud
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">
                                        Retention
                                    </label>

                                    <select
                                        name="retention_days"
                                        class="pm-input">
                                        @foreach ([7,30,60,90,180,365,730] as $days)
                                            <option
                                                value="{{ $days }}"
                                                @selected((int) $backupSetting->retention_days === $days)>
                                                {{ $days }} days
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div
                                id="backup-s3-fields"
                                class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">
                                            Access Key
                                        </label>

                                        <input
                                            type="text"
                                            name="s3_key"
                                            value="{{ $backupSetting->s3_key }}"
                                            autocomplete="off"
                                            class="pm-input">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">
                                            Secret Key
                                        </label>

                                        <input
                                            type="password"
                                            name="s3_secret"
                                            value=""
                                            autocomplete="new-password"
                                            placeholder="{{ filled($backupSetting->s3_secret ?? null)
                                                ? 'Saved — enter only to replace'
                                                : 'Secret key' }}"
                                            class="pm-input">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">
                                            Bucket
                                        </label>

                                        <input
                                            type="text"
                                            name="s3_bucket"
                                            value="{{ $backupSetting->s3_bucket }}"
                                            class="pm-input">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">
                                            Region
                                        </label>

                                        <input
                                            type="text"
                                            name="s3_region"
                                            value="{{ $backupSetting->s3_region }}"
                                            placeholder="us-east-1"
                                            class="pm-input">
                                    </div>

                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-medium text-slate-600 mb-1">
                                            Endpoint
                                        </label>

                                        <input
                                            type="url"
                                            name="s3_endpoint"
                                            value="{{ $backupSetting->s3_endpoint }}"
                                            placeholder="https://..."
                                            class="pm-input">
                                    </div>

                                    <label class="sm:col-span-2 inline-flex items-center gap-2 text-sm text-slate-600">
                                        <input
                                            type="checkbox"
                                            name="s3_path_style"
                                            value="1"
                                            @checked($backupSetting->s3_path_style)
                                            class="rounded border-slate-300">

                                        Use path-style endpoint
                                    </label>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-semibold">
                                    Save Backup Settings
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                            Backup settings are loaded, but the update route is unavailable.
                        </div>
                    @endif
                </div>

                <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                        <div>
                            <h3 class="font-semibold text-slate-800">
                                Backup Now
                            </h3>

                            <p class="text-xs text-slate-500 mt-1">
                                Create an immediate backup using the configured destination.
                            </p>
                        </div>

                        @if (\Illuminate\Support\Facades\Route::has('admin.backups.run'))
                            <form
                                method="POST"
                                action="{{ route('admin.backups.run') }}"
                                class="flex flex-col sm:flex-row gap-2">
                                @csrf

                                <select
                                    name="backup_type"
                                    class="pm-input text-sm">
                                    <option value="both">
                                        Database + files
                                    </option>

                                    <option value="database">
                                        Database only
                                    </option>

                                    <option value="files">
                                        Files only
                                    </option>
                                </select>

                                <button
                                    class="btn-primary text-white px-4 py-2 rounded-lg text-sm font-semibold whitespace-nowrap">
                                    <i class="fa-solid fa-database mr-1" aria-hidden="true"></i>
                                    Backup Now
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="overflow-x-auto pm-admin-table-scroll">
                        <table class="min-w-[900px] w-full text-sm pm-admin-horizontal-table">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                                    <th class="py-2 pr-4">Date</th>
                                    <th class="py-2 pr-4">Type</th>
                                    <th class="py-2 pr-4">Storage</th>
                                    <th class="py-2 pr-4">Size</th>
                                    <th class="py-2 pr-4">Status</th>
                                    <th class="py-2">Triggered By</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100">
                                @forelse ($backupHistory as $backup)
                                    <tr>
                                        <td class="py-3 pr-4 whitespace-nowrap">
                                            {{ optional($backup->started_at)->format('d M Y H:i') ?: '—' }}
                                        </td>

                                        <td class="py-3 pr-4">
                                            {{ ucfirst((string) $backup->backup_type) }}
                                        </td>

                                        <td class="py-3 pr-4">
                                            {{ strtoupper((string) $backup->provider) }}
                                        </td>

                                        <td class="py-3 pr-4">
                                            {{ $backup->size_bytes
                                                ? number_format($backup->size_bytes / 1024 / 1024, 2) . ' MB'
                                                : '—' }}
                                        </td>

                                        <td class="py-3 pr-4">
                                            <span
                                                class="font-medium
                                                    {{ $backup->status === 'completed'
                                                        ? 'text-emerald-600'
                                                        : ($backup->status === 'failed'
                                                            ? 'text-rose-600'
                                                            : 'text-amber-600') }}">
                                                {{ ucfirst((string) $backup->status) }}
                                            </span>

                                            @if ($backup->error_message)
                                                <div
                                                    class="text-xs text-rose-500 max-w-xs truncate"
                                                    title="{{ $backup->error_message }}">
                                                    {{ $backup->error_message }}
                                                </div>
                                            @endif
                                        </td>

                                        <td class="py-3">
                                            @if ($backup->trigger === 'manual')
                                                {{ optional($backup->creator)->name ?: 'Admin' }}
                                            @else
                                                Scheduler
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td
                                            colspan="6"
                                            class="py-8 text-center text-slate-400">
                                            No backups have been created yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="pm-card-bg shadow-sm border border-amber-200 rounded-xl p-6">
                    <div class="flex gap-3">
                        <i class="fa-solid fa-triangle-exclamation text-amber-500 mt-0.5" aria-hidden="true"></i>

                        <div>
                            <h2 class="font-semibold text-slate-800">
                                Backup Settings Unavailable
                            </h2>

                            <p class="text-sm text-slate-500 mt-1">
                                The main Settings page remains available, but the backup configuration could not be loaded.
                                Check that the backup migrations have run and review the Laravel log for the exact backup error.
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- AI provider modals are optional. Missing partial must not crash the settings page. --}}
    @if ($hasAiProviderPartial)
        @if (\Illuminate\Support\Facades\Route::has('admin.ai-providers.store'))
            @include(
                'admin.settings._ai-provider-modal',
                [
                    'provider' => new \App\Models\AiProvider(),
                    'modalId' => 'ai-provider-create-modal',
                    'action' => route('admin.ai-providers.store'),
                    'method' => null,
                ]
            )
        @endif

        @foreach ($aiProviders as $provider)
            @php
                try {
                    $providerBuiltIn = method_exists($provider, 'isBuiltIn')
                        ? $provider->isBuiltIn()
                        : false;
                } catch (\Throwable $e) {
                    $providerBuiltIn = false;
                }
            @endphp

            @if (
                ! $providerBuiltIn
                && \Illuminate\Support\Facades\Route::has('admin.ai-providers.update')
            )
                @include(
                    'admin.settings._ai-provider-modal',
                    [
                        'provider' => $provider,
                        'modalId' => 'ai-provider-edit-modal-' . $provider->id,
                        'action' => route('admin.ai-providers.update', $provider->id),
                        'method' => 'PUT',
                    ]
                )
            @endif
        @endforeach
    @endif
</div>

<script>
    window.pmInitialSettingsTab = @json($currentTab);

    function pmAddCurrencyRow() {
        const container = document.getElementById('pm-currency-rows');

        if (!container) {
            return;
        }

        document.getElementById('pm-no-currency-row')?.remove();

        const baseCode =
            document.getElementById('default_currency_code')?.value || 'Base';

        const row = document.createElement('div');

        row.className = 'grid grid-cols-12 gap-2 pm-currency-row';

        row.innerHTML = `
            <input
                type="text"
                name="currency_codes[]"
                placeholder="USD"
                maxlength="3"
                class="col-span-4 sm:col-span-3 pm-input text-sm uppercase">

            <input
                type="text"
                name="currency_symbols[]"
                placeholder="$"
                maxlength="10"
                class="col-span-4 sm:col-span-3 pm-input text-sm">

            <input
                type="number"
                name="currency_rates[]"
                placeholder="${baseCode} per 1"
                min="0"
                step="0.00000001"
                class="col-span-3 sm:col-span-5 pm-input text-sm">

            <button
                type="button"
                onclick="this.closest('.pm-currency-row').remove()"
                class="col-span-1 flex items-center justify-center text-rose-500 hover:text-rose-700"
                aria-label="Remove currency">
                <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
            </button>
        `;

        container.appendChild(row);
    }

    function pmTogglePassword(inputId, button) {
        const input = document.getElementById(inputId);

        if (!input) {
            return;
        }

        const isVisible = input.type === 'text';

        input.type = isVisible ? 'password' : 'text';

        const icon = button?.querySelector('i');

        if (icon) {
            icon.classList.toggle('fa-eye', isVisible);
            icon.classList.toggle('fa-eye-slash', !isVisible);
        }
    }

    function pmSelectSettingsTab(key, focusTab = false) {
        document
            .querySelectorAll('.pm-settings-tab')
            .forEach(function (button) {
                const selected =
                    button.dataset.tab === key;

                button.setAttribute(
                    'aria-selected',
                    selected ? 'true' : 'false'
                );

                button.setAttribute(
                    'tabindex',
                    selected ? '0' : '-1'
                );

                button.classList.toggle(
                    'border-[var(--brand-1)]',
                    selected
                );

                button.classList.toggle(
                    'text-[var(--brand-1)]',
                    selected
                );

                button.classList.toggle(
                    'border-transparent',
                    !selected
                );

                button.classList.toggle(
                    'text-slate-500',
                    !selected
                );

                if (selected && focusTab) {
                    button.focus();
                }
            });

        document
            .querySelectorAll('.pm-settings-panel')
            .forEach(function (panel) {
                panel.hidden =
                    panel.id !==
                    'pm-settings-panel-' + key;
            });

        const url =
            new URL(window.location.href);

        url.searchParams.set(
            'tab',
            key
        );

        window.history.replaceState(
            {},
            '',
            url.toString()
        );
    }

    function pmSettingsTabKeydown(
        event,
        currentKey
    ) {
        const tabs =
            Array.from(
                document.querySelectorAll(
                    '.pm-settings-tab'
                )
            ).map(
                function (tab) {
                    return tab.dataset.tab;
                }
            );

        const index =
            tabs.indexOf(
                currentKey
            );

        let nextIndex = null;

        if (event.key === 'ArrowRight') {
            nextIndex =
                (index + 1)
                % tabs.length;
        } else if (
            event.key === 'ArrowLeft'
        ) {
            nextIndex =
                (
                    index - 1
                    + tabs.length
                )
                % tabs.length;
        } else if (
            event.key === 'Home'
        ) {
            nextIndex = 0;
        } else if (
            event.key === 'End'
        ) {
            nextIndex =
                tabs.length - 1;
        } else {
            return;
        }

        event.preventDefault();

        pmSelectSettingsTab(
            tabs[nextIndex],
            true
        );
    }

    function pmToggleBackupCloudFields() {
        const provider =
            document.getElementById(
                'backup-provider'
            );

        const fields =
            document.getElementById(
                'backup-s3-fields'
            );

        if (!provider || !fields) {
            return;
        }

        fields.hidden =
            provider.value !== 's3';

        fields.style.display =
            provider.value === 's3'
                ? 'block'
                : 'none';
    }

    document.addEventListener(
        'DOMContentLoaded',
        function () {
            pmToggleBackupCloudFields();

            const firstErrorField =
                document.querySelector(
                    '[aria-invalid="true"]'
                );

            if (firstErrorField) {
                const panel =
                    firstErrorField.closest(
                        '.pm-settings-panel'
                    );

                if (panel) {
                    const key =
                        panel.id.replace(
                            'pm-settings-panel-',
                            ''
                        );

                    pmSelectSettingsTab(
                        key
                    );

                    return;
                }
            }

            pmSelectSettingsTab(
                window.pmInitialSettingsTab
                || 'branding'
            );
        }
    );
</script>
@endsection
