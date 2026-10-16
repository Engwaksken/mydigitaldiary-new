@php
    $price = $plan->computedPrice((float) $settings->monthly_price);
    $sticky = match(true) {
        $plan->duration_months <= 1 => ['bg'=>'#FFF8C5','border'=>'#E7CF62','text'=>'#5F5112'],
        $plan->duration_months <= 3 => ['bg'=>'#DFF4FF','border'=>'#8BC9E8','text'=>'#155B7A'],
        $plan->duration_months <= 6 => ['bg'=>'#F3E5FF','border'=>'#C9A5ED','text'=>'#63398A'],
        $plan->duration_months <= 12 => ['bg'=>'#DFF6E8','border'=>'#8BCDA5','text'=>'#16613A'],
        default => ['bg'=>'#FFE8E2','border'=>'#EAB0A0','text'=>'#7D3E30'],
    };
@endphp
<button type="button"
        onclick="pmSelectPlan({{ $plan->id }}, {{ $price }}, {{ json_encode($plan->name) }}, {{ json_encode($plan->isLifetime() ? 'One-time payment' : 'Every ' . $plan->duration_months . ' month(s)') }}, this)"
        data-plan-id="{{ $plan->id }}"
        style="background: {{ $sticky['bg'] }}; border:2px solid {{ $sticky['border'] }}; box-shadow: 0 3px 0 rgba(15,23,42,.08);"
        class="pm-plan-card relative text-left rounded-xl p-4 hover:shadow-lg transition-shadow ring-2 ring-transparent w-full">
    @if ($plan->is_recommended)
        <span class="absolute -top-2.5 left-3 text-[10px] font-bold uppercase tracking-wide text-white px-2 py-0.5 rounded-full" style="background: {{ $sticky['text'] }}">Recommended</span>
    @elseif ($plan->is_best_value)
        <span class="absolute -top-2.5 left-3 text-[10px] font-bold uppercase tracking-wide text-white px-2 py-0.5 rounded-full" style="background: {{ $sticky['text'] }}">Best Value</span>
    @endif

    <div class="flex items-center justify-between mb-1 mt-1">
        <span class="font-semibold" style="color: {{ $sticky['text'] }}">{{ $plan->name }}</span>
        @if ($plan->badge)
            <span class="text-xs font-medium text-white px-2 py-0.5 rounded-full" style="background: {{ $sticky['text'] }}">{{ $plan->badge }}</span>
        @elseif ($plan->savingsLabel())
            <span class="text-xs font-medium text-white px-2 py-0.5 rounded-full" style="background: {{ $sticky['text'] }}">{{ $plan->savingsLabel() }}</span>
        @endif
    </div>
    <p class="text-2xl font-bold pm-plan-price" data-base-price="{{ $price }}" style="color: {{ $sticky['text'] }}">{{ format_money($price) }}</p>
    <p class="text-xs mt-0.5 text-slate-600">
        {{ $plan->isLifetime() ? 'One-time payment' : 'every ' . $plan->duration_months . ' month(s)' }}
    </p>
    @if (($plan->max_transcription_minutes_per_meeting ?? 0) > 0)
        <p class="text-xs mt-2 pt-2 border-t text-slate-600" style="border-color: {{ $plan->normalizedHex() }}40;">
            <i class="fa-solid fa-microphone-lines" aria-hidden="true"></i>
            Up to {{ number_format($plan->max_transcription_minutes_per_meeting) }} minutes per meeting recording
        </p>
    @endif
    @if ($plan->category !== 'individual')
        <p class="text-xs mt-2 pt-2 border-t text-slate-600" style="border-color: {{ $plan->normalizedHex() }}40;">
            <i class="fa-solid fa-users" aria-hidden="true"></i>
            {{ $plan->included_seats }} members included
            ({{ format_money($plan->pricePerSeat((float) $settings->monthly_price)) }}/member)
        </p>
        @if ($plan->additional_user_price)
            <p class="text-xs text-slate-500">+{{ format_money($plan->additional_user_price) }}/member beyond that</p>
        @endif
    @endif
</button>
