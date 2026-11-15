{{--
    Drop-in replacement for Breeze's default checkbox component (used by
    "Remember me" on the login form) - reskinned to the brand palette.
--}}
@props(['disabled' => false])

<input @disabled($disabled) type="checkbox" {{ $attributes->merge(['class' => 'rounded border-slate-300 text-[var(--brand-1)] shadow-sm focus:ring-[var(--brand-2)]']) }}>
