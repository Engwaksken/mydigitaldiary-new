<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $payment->receipt_number ?? $payment->id }}</title>
    <style>
        @page { margin: 42px 46px 54px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172b3a; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.55; }
        .watermark { position: fixed; top: 34%; left: 19%; width: 62%; text-align: center; z-index: -1; }
        .watermark img { max-width: 100%; max-height: 300px; opacity: .055; }
        .watermark-text { color: #0f8b7d; font-size: 52px; font-weight: bold; opacity: .055; }
        .header { width: 100%; border-bottom: 2px solid #0f8b7d; padding-bottom: 20px; }
        .header td { vertical-align: middle; }
        .brand { width: 68%; }
        .logo { width: 52px; height: 52px; object-fit: contain; margin-right: 12px; vertical-align: middle; }
        .brand-name { display: inline-block; vertical-align: middle; color: #123447; font-size: 17px; font-weight: bold; }
        .brand-caption { color: #647783; font-size: 9px; margin-top: 3px; }
        .receipt-heading { text-align: right; }
        .receipt-heading h1 { margin: 0; color: #123447; font-size: 23px; letter-spacing: 1px; }
        .receipt-heading p { margin: 4px 0 0; color: #647783; font-size: 9px; }
        .status { display: inline-block; margin-top: 7px; padding: 4px 10px; color: #08775e; background: #e6f6f0; border: 1px solid #b9e8d7; border-radius: 12px; font-size: 8px; font-weight: bold; letter-spacing: .8px; }
        .intro { margin: 24px 0 18px; color: #536875; }
        .intro strong { color: #173649; }
        .section-title { margin: 22px 0 8px; color: #123447; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .7px; }
        .info-grid { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px; }
        .info-card { width: 50%; padding: 12px 14px; background: #f4f8f8; border: 1px solid #e1ebeb; border-radius: 7px; vertical-align: top; }
        .label { display: block; margin-bottom: 3px; color: #73858e; font-size: 8px; text-transform: uppercase; letter-spacing: .6px; }
        .value { color: #1d3748; font-size: 10px; font-weight: bold; overflow-wrap: anywhere; }
        .details { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .details th { padding: 9px 10px; color: #607680; background: #f1f6f6; border-bottom: 1px solid #dce7e7; text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: .5px; }
        .details td { padding: 11px 10px; border-bottom: 1px solid #e5ecec; vertical-align: top; }
        .details .amount { text-align: right; white-space: nowrap; }
        .total-row td { padding-top: 14px; border-bottom: 0; }
        .total-label { color: #526873; text-align: right; font-weight: bold; }
        .total-value { color: #087e70; text-align: right; font-size: 17px; font-weight: bold; }
        .footer { position: fixed; right: 0; bottom: -30px; left: 0; padding-top: 10px; color: #83939a; border-top: 1px solid #e2e9e9; text-align: center; font-size: 8px; }
        .muted { color: #6b7f89; font-weight: normal; }
    </style>
</head>
<body>
    <div class="watermark">
        @if ($siteSettings->logoDataUri())
            <img src="{{ $siteSettings->logoDataUri() }}" alt="">
        @else
            <div class="watermark-text">{{ $siteSettings->site_name ?? config('app.name') }}</div>
        @endif
    </div>

    <table class="header">
        <tr>
            <td class="brand">
                @if ($siteSettings->logoDataUri())
                    <img class="logo" src="{{ $siteSettings->logoDataUri() }}" alt="">
                @endif
                <span class="brand-name">{{ $siteSettings->site_name ?? config('app.name') }}</span>
                <div class="brand-caption">Subscription payment receipt</div>
            </td>
            <td class="receipt-heading">
                <h1>RECEIPT</h1>
                <p>No. {{ $payment->receipt_number ?? ('PAY-' . $payment->id) }}</p>
                <span class="status">{{ strtoupper($payment->status) }}</span>
            </td>
        </tr>
    </table>

    <p class="intro">Thank you for your payment. This document confirms the subscription payment detailed below.</p>

    <table class="info-grid">
        <tr>
            <td class="info-card">
                <span class="label">Billed to</span>
                <span class="value">{{ $payment->user?->name ?? 'Account holder' }}</span><br>
                <span class="muted">{{ $payment->user?->email ?? '—' }}</span>
                @if ($payment->paymentContactPhone())<br><span class="muted">{{ $payment->paymentContactPhone() }}</span>@endif
            </td>
            <td class="info-card">
                <span class="label">Payment date</span>
                <span class="value">{{ $payment->created_at?->format('F j, Y') ?? '—' }}</span><br>
                <span class="muted">{{ $payment->created_at?->format('g:i A') }}</span>
            </td>
        </tr>
    </table>

    <div class="section-title">Payment details</div>
    <table class="details">
        <thead>
            <tr><th>Description</th><th>Method / reference</th><th class="amount">Amount</th></tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>{{ $payment->plan?->name ?? 'Subscription payment' }}</strong>
                    @if ($payment->invoice?->billing_period_start && $payment->invoice?->billing_period_end)
                        <br><span class="muted">{{ $payment->invoice->billing_period_start->format('M j, Y') }} – {{ $payment->invoice->billing_period_end->format('M j, Y') }}</span>
                    @endif
                </td>
                <td>{{ ucfirst(str_replace('_', ' ', $payment->method ?? 'payment')) }}<br><span class="muted">{{ $payment->reference ?: '—' }}</span>
                    @if ($payment->gateway_transaction_id)<br><span class="muted">Transaction: {{ $payment->gateway_transaction_id }}</span>@endif
                </td>
                <td class="amount">{{ format_money_in($payment->amount, $payment->currency) }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="2" class="total-label">TOTAL PAID</td>
                <td class="total-value">{{ format_money_in($payment->amount, $payment->currency) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">This is a computer-generated receipt. Receipt {{ $payment->receipt_number ?? ('PAY-' . $payment->id) }} · {{ $siteSettings->site_name ?? config('app.name') }}</div>
</body>
</html>
