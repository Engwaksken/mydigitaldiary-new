<?php

use App\Models\SiteSetting;

if (! function_exists('format_money')) {
    /**
     * Formats an amount using the site's configured base currency (UGX by
     * default — see SiteSetting::formatMoney()) — the replacement for
     * every hardcoded "${{ number_format($x, 2) }}" that used to assume
     * USD throughout the app.
     */
    function format_money(float|int|string $amount): string
    {
        return SiteSetting::current()->formatMoneyForUser((float) $amount, auth()->user());
    }
}

if (! function_exists('format_money_in')) {
    /**
     * For a SPECIFIC record's own stored amount+currency (a Payment,
     * Invoice, etc.) — use this instead of format_money() whenever
     * you're displaying something that has its own currency field.
     * format_money() always assumes the site's CURRENT default
     * currency, which silently mislabels an old record made in a
     * currency the site has since switched away from.
     */
    function format_money_in(float|int|string $amount, ?string $currencyCode): string
    {
        return SiteSetting::current()->formatMoneyInCurrency((float) $amount, $currencyCode);
    }
}


if (! function_exists('format_short_money')) {
    function format_short_money(float|int|string $amount): string
    {
        $settings = SiteSetting::current();
        $user = auth()->user();
        $meta = $settings->currencyMeta($user?->preferredCurrencyCode());
        $value = $settings->convertBaseAmount((float) $amount, $meta['code']);
        $abs = abs($value);
        $suffix = '';
        $divisor = 1;
        if ($abs >= 1000000000) { $suffix='B'; $divisor=1000000000; }
        elseif ($abs >= 1000000) { $suffix='M'; $divisor=1000000; }
        elseif ($abs >= 1000) { $suffix='K'; $divisor=1000; }
        $shown = $value / $divisor;
        $decimals = $suffix === '' ? $meta['decimals'] : ($shown == (int)$shown ? 0 : 2);
        return trim($meta['symbol'].' '.rtrim(rtrim(number_format($shown,$decimals,'.',''), '0'), '.').$suffix);
    }
}


// Compatibility alias for older call sites that used format_money_short()
// instead of format_short_money(). No current call sites remain in-app.
if (! function_exists('format_money_short')) {
    function format_money_short(float|int|string $amount): string
    {
        return format_short_money($amount);
    }
}
