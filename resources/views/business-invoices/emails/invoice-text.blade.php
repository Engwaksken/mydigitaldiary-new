Hello {{ $invoice->customer_name }},

@if ($isReceipt)
Thank you for your payment. Please find attached the receipt for invoice {{ $invoice->invoice_number }} from {{ $businessName }}.
@else
Please find attached invoice {{ $invoice->invoice_number }} from {{ $businessName }} for {{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}.@if ($invoice->due_date) Payment is due by {{ $invoice->due_date->format('d M Y') }}.@endif

@endif

Kind regards,
{{ $businessName }}
@if ($invoice->business_email || $invoice->business_phone)
{{ collect([$invoice->business_email, $invoice->business_phone])->filter()->implode(' · ') }}
@endif
