@component('mail::message')
@if ($siteSettings->logoUrl())
<img src="{{ $siteSettings->logoUrl() }}" alt="{{ $siteSettings->site_name }}" style="height: 40px; margin-bottom: 12px;">
@endif

# {{ $invoice->isQuote() ? 'Quotation' : 'Invoice' }} {{ $invoice->invoice_number }}

Hi **{{ $invoice->user->name ?? ($invoice->enterpriseInquiry->email ?? 'there') }}**,
@if ($invoice->isQuote())
here's the quotation you requested. A copy is attached as a PDF.
@else
here's your invoice for the plan you selected. A copy is attached as a PDF.
@endif

@component('mail::panel')
@if ($invoice->plan)
**Plan:** {{ $invoice->plan->name }}<br>
@elseif ($invoice->description)
**Description:** {{ $invoice->description }}<br>
@endif
**{{ $invoice->isQuote() ? 'Quoted Amount' : 'Amount Due' }}:** {{ format_money_in($invoice->amount, $invoice->currency) }}<br>
@if ($invoice->billing_period_start && $invoice->billing_period_end)
**Billing Period:** {{ $invoice->billing_period_start->format('M j, Y') }} - {{ $invoice->billing_period_end->format('M j, Y') }}<br>
@endif
@if ($invoice->due_date)
**{{ $invoice->isQuote() ? 'Valid Until' : 'Due Date' }}:** {{ $invoice->due_date->format('M j, Y') }}<br>
@endif
**Status:** {{ $invoice->isQuote() ? 'Quotation' : ucfirst($invoice->status) }}
@endcomponent

@if ($invoice->isQuote())
Reply to this email or get in touch if you'd like to move forward - we'll take care of the rest.
@elseif ($invoice->user)
@component('mail::button', ['url' => route('subscription.show')])
Complete Payment
@endcomponent
@endif

Thanks,<br>
{{ $siteSettings->site_name ?? config('app.name') }}
@endcomponent
