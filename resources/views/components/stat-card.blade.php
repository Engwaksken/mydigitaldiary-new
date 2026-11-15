{{--
    Dashboard statistic card.

    Props:
        label      : card label (required)
        value      : big value text (required)
        icon       : Font Awesome icon class (required)
        color      : brand | green | amber | red | blue | purple (default: brand)
        supporting : optional supporting text below the value
        href       : optional URL - renders the card as a link

    Design: icon chip, colored left border, clear label, big value,
    optional supporting text, equal heights, responsive grid (4/2/1 cols).

    Usage:
        <x-stat-card label="Income" value="$1,200" icon="fa-solid fa-money-bill-trend-up" color="green" supporting="+12% this month" />
--}}
@props([
    'label',
    'value',
    'icon',
    'color' => 'brand',
    'supporting' => null,
    'href' => null,
])

@php
    $colors = [
        'brand' => [
            'border' => 'border-l-[var(--brand-1)]',
            'chip' => 'bg-[var(--brand-1-tint-10)] text-[var(--brand-1)]',
        ],
        'green' => [
            'border' => 'border-l-emerald-500',
            'chip' => 'bg-emerald-50 text-emerald-600',
        ],
        'amber' => [
            'border' => 'border-l-amber-500',
            'chip' => 'bg-amber-50 text-amber-600',
        ],
        'red' => [
            'border' => 'border-l-red-500',
            'chip' => 'bg-red-50 text-red-600',
        ],
        'blue' => [
            'border' => 'border-l-blue-500',
            'chip' => 'bg-blue-50 text-blue-600',
        ],
        'purple' => [
            'border' => 'border-l-purple-500',
            'chip' => 'bg-purple-50 text-purple-600',
        ],
    ][$color] ?? [
        'border' => 'border-l-[var(--brand-1)]',
        'chip' => 'bg-[var(--brand-1-tint-10)] text-[var(--brand-1)]',
    ];

    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    class="pm-stat-card group relative flex flex-col justify-between h-full rounded-2xl border border-slate-200/80 border-l-4 {{ $colors['border'] }} bg-white p-5 shadow-sm hover:shadow-md transition-shadow {{ $href ? 'hover:-translate-y-0.5 transition-transform' : '' }}"
>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</p>
            <p class="mt-2 text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 tabular-nums">{{ $value }}</p>
        </div>

        <div class="shrink-0 w-11 h-11 rounded-xl {{ $colors['chip'] }} flex items-center justify-center text-lg" aria-hidden="true">
            <i class="{{ $icon }}"></i>
        </div>
    </div>

    @if ($supporting)
        <p class="mt-3 text-xs text-slate-500">{{ $supporting }}</p>
    @endif
</{{ $tag }}>
