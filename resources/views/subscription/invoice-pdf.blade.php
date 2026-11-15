<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; color: #1e293b; font-size: 13px; }
        .header { border-bottom: 2px solid #00897B; padding-bottom: 12px; margin-bottom: 20px; display: table; width: 100%; }
        .header-logo { display: table-cell; vertical-align: middle; width: 60px; }
        .header-logo img { height: 48px; }
        .header-text { display: table-cell; vertical-align: middle; }
        .header-text h1 { color: #00897B; font-size: 20px; margin: 0; }
        .header-text p { margin: 2px 0 0; color: #64748b; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: bold; }
        .status-unpaid { background: #fef3c7; color: #92400e; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-quote { background: #ede9fe; color: #5b21b6; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td { padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
        td:first-child { color: #64748b; width: 40%; }
        .total { font-size: 16px; font-weight: bold; color: #00897B; }
    </style>
</head>
<body>
    <div class="header">
        @if ($siteSettings->logoDataUri())
            <div class="header-logo"><img src="{{ $siteSettings->logoDataUri() }}" alt=""></div>
        @endif
        <div class="header-text">
            <h1>{{ $siteSettings->site_name ?? config('app.name') }}</h1>
            <p>{{ $invoice->isQuote() ? 'Quotation' : 'Invoice' }}</p>
        </div>
    </div>

    <table>
        <tr><td>{{ $invoice->isQuote() ? 'Quote' : 'Invoice' }} #</td><td>{{ $invoice->invoice_number }}</td></tr>
        <tr><td>Date Issued</td><td>{{ $invoice->created_at->format('F j, Y') }}</td></tr>
        <tr><td>{{ $invoice->isQuote() ? 'Valid Until' : 'Due Date' }}</td><td>{{ $invoice->due_date?->format('F j, Y') ?? 'On receipt' }}</td></tr>
        <tr>
            <td>{{ $invoice->isQuote() ? 'Prepared For' : 'Billed To' }}</td>
            <td>
                @if ($invoice->user)
                    {{ $invoice->user->name }} ({{ $invoice->user->email }})
                @elseif ($invoice->enterpriseInquiry)
                    {{ $invoice->enterpriseInquiry->email }}
                @else
                    -
                @endif
            </td>
        </tr>
        @php
            $invoicePhone = $invoice->payment?->paymentContactPhone()
                ?: $invoice->user?->phone_number
                ?: $invoice->enterpriseInquiry?->phone;
        @endphp
        @if ($invoicePhone)
            <tr><td>Contact / Phone</td><td>{{ $invoicePhone }}</td></tr>
        @endif
        <tr><td>{{ $invoice->plan ? 'Plan' : 'Description' }}</td><td>{{ $invoice->plan?->name ?? $invoice->description ?? '-' }}</td></tr>
        @if ($invoice->billing_period_start && $invoice->billing_period_end)
            <tr><td>Billing Period</td><td>{{ $invoice->billing_period_start->format('M j, Y') }} - {{ $invoice->billing_period_end->format('M j, Y') }}</td></tr>
        @endif
        <tr><td>Status</td>
            <td><span class="status-badge status-{{ $invoice->status }}">{{ $invoice->isQuote() ? 'Quotation' : ucfirst($invoice->status) }}</span></td>
        </tr>
        <tr><td class="total">{{ $invoice->isQuote() ? 'Quoted Amount' : 'Amount Due' }}</td><td class="total">{{ format_money_in($invoice->amount, $invoice->currency) }}</td></tr>
    </table>
</body>
</html>
