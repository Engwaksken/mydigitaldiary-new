<!doctype html>
<html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;color:#1e293b;font-size:12px}.head{border-bottom:3px solid #047857;padding:0 0 14px;margin-bottom:24px}h1{color:#047857;margin:0 0 5px}.muted{color:#64748b}.columns{width:100%;margin:20px 0}.columns td{width:50%;vertical-align:top}table.items{width:100%;border-collapse:collapse;margin-top:24px}th,td{padding:10px;border-bottom:1px solid #e2e8f0;text-align:left}th{background:#f1f5f9}.num{text-align:right}.total{font-size:17px;font-weight:bold;color:#047857}
</style></head><body>
<div class="head"><h1>{{ $invoice->business_name }}</h1><div>{{ $invoice->business_address }}</div><div>{{ $invoice->business_email }} @if($invoice->business_phone) · {{ $invoice->business_phone }} @endif</div></div>
<h2>{{ $documentTitle ?? 'INVOICE' }}</h2><div>Invoice {{ $invoice->invoice_number }}</div><div>Issued {{ $invoice->issue_date->format('d M Y') }} · Due {{ $invoice->due_date?->format('d M Y') ?? 'On receipt' }}</div>@if($invoice->paid_at)<div>Paid {{ $invoice->paid_at->format('d M Y') }}</div>@endif
<table class="columns"><tr><td><strong>Bill to</strong><br>{{ $invoice->customer_name }}<br>{{ $invoice->customer_email }}</td><td class="num"><strong>Status</strong><br>{{ ucfirst($invoice->status) }}</td></tr></table>
<table class="items"><thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr></thead><tbody>
@foreach($invoice->line_items as $line)<tr><td>{{ $line['description'] }}</td><td class="num">{{ $line['quantity'] }}</td><td class="num">{{ $invoice->currency }} {{ number_format($line['unit_price'],2) }}</td><td class="num">{{ $invoice->currency }} {{ number_format($line['amount'],2) }}</td></tr>@endforeach
<tr><td colspan="3" class="num total">Total</td><td class="num total">{{ $invoice->currency }} {{ number_format($invoice->total,2) }}</td></tr></tbody></table>
</body></html>
