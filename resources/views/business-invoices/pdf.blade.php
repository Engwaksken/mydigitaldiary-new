<!doctype html>
<html><head><meta charset="utf-8"><style>
{{-- Wider top/bottom margins on PDF stationery keep invoice content clear of the letterhead and footer. --}}
@page{margin:{{ ($onStationery ?? false) ? '34mm 18mm 26mm' : '16mm 18mm' }}}
body{font-family:DejaVu Sans,sans-serif;color:#1e293b;font-size:11px;line-height:1.4}.head{border-bottom:3px solid #047857;padding:0 0 12px;margin-bottom:20px}h1{color:#047857;font-size:20px;margin:0 0 5px}h2{font-size:16px;margin:0 0 6px}.muted{color:#64748b}.columns{width:100%;border-collapse:collapse;margin:18px 0 0}.columns td{width:50%;padding:0;vertical-align:top}.num{text-align:right}
</style></head><body>
<div class="head"><h1>{{ $invoice->business_name }}</h1><div>{{ $invoice->business_address }}</div><div>{{ $invoice->business_email }} @if($invoice->business_phone) · {{ $invoice->business_phone }} @endif</div></div>
<h2>{{ $documentTitle ?? 'INVOICE' }}</h2><div>Invoice {{ $invoice->invoice_number }}</div><div>Issued {{ $invoice->issue_date->format('d M Y') }} · Due {{ $invoice->due_date?->format('d M Y') ?? 'On receipt' }}</div>@if($invoice->paid_at)<div>Paid {{ $invoice->paid_at->format('d M Y') }}</div>@endif
<table class="columns"><tr><td><strong>Bill to</strong><br>{{ $invoice->customer_name }}<br>{{ $invoice->customer_email }}</td><td class="num"><strong>Status</strong><br>{{ ucfirst($invoice->status) }}</td></tr></table>
@include('business-invoices.partials.line-items-table', ['invoice' => $invoice, 'showTotals' => true])
</body></html>
