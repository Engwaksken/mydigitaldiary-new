@component('mail::message')
@if ($siteSettings->logoUrl())
<img src="{{ $siteSettings->logoUrl() }}" alt="{{ $siteSettings->site_name }}" style="height: 40px; margin-bottom: 12px;">
@endif

# Payment Received

Thanks, **{{ $payment->user->name }}** - this confirms your payment.

@component('mail::panel')
**Receipt #:** {{ $payment->receipt_number ?? $payment->id }}<br>
**Plan:** {{ $payment->plan?->name ?? '-' }}<br>
@if ($payment->invoice?->billing_period_start && $payment->invoice?->billing_period_end)
**Subscription Period:** {{ $payment->invoice->billing_period_start->format('M j, Y') }} - {{ $payment->invoice->billing_period_end->format('M j, Y') }}<br>
@endif
**Amount:** {{ format_money_in($payment->amount, $payment->currency) }}<br>
**Method:** {{ ucfirst(str_replace('_', ' ', $payment->method)) }}<br>
**Reference:** {{ $payment->reference }}<br>
**Date:** {{ $payment->created_at->format('F j, Y g:i A') }}
@endcomponent

Your subscription is now active.

@component('mail::button', ['url' => route('subscription.show')])
View Subscription
@endcomponent

Thanks,<br>
{{ $siteSettings->site_name ?? config('app.name') }}
@endcomponent
