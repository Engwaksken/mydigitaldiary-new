{{--
    Invoice line items for dompdf. Styles are inline so the columns stay aligned
    inside user HTML templates and on top of uploaded PDF stationery.
--}}
@php
    $lineItems = $invoice->line_items ?? [];
    // Show quantities as whole numbers unless any line uses a fraction, so the column reads consistently.
    $fractionalQuantities = collect($lineItems)->contains(fn ($line) => fmod((float) ($line['quantity'] ?? 0), 1) !== 0.0);
    $formatQuantity = fn ($quantity) => number_format((float) $quantity, $fractionalQuantities ? 2 : 0);
    $cell = 'padding:8px 10px;border-bottom:1px solid #e2e8f0;vertical-align:top;line-height:1.4;';
    $head = 'padding:8px 10px;border-bottom:1px solid #cbd5e1;background:#f1f5f9;color:#334155;font-size:10px;font-weight:bold;text-transform:uppercase;letter-spacing:.04em;vertical-align:bottom;';
    $number = 'text-align:right;white-space:nowrap;';
@endphp
<table class="invoice-items" style="width:100%;border-collapse:collapse;table-layout:fixed;margin-top:20px;font-size:11px">
    <thead>
        <tr>
            <th style="{{ $head }}width:46%;text-align:left">Description</th>
            <th style="{{ $head }}{{ $number }}width:12%">Qty</th>
            <th style="{{ $head }}{{ $number }}width:21%">Unit price</th>
            <th style="{{ $head }}{{ $number }}width:21%">Amount ({{ $invoice->currency }})</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lineItems as $line)
            <tr>
                <td style="{{ $cell }}text-align:left;word-wrap:break-word;overflow-wrap:break-word">{{ $line['description'] ?? '' }}</td>
                <td style="{{ $cell }}{{ $number }}">{{ $formatQuantity($line['quantity'] ?? 0) }}</td>
                <td style="{{ $cell }}{{ $number }}">{{ number_format((float) ($line['unit_price'] ?? 0), 2) }}</td>
                <td style="{{ $cell }}{{ $number }}">{{ number_format((float) ($line['amount'] ?? 0), 2) }}</td>
            </tr>
        @endforeach
    </tbody>
    @if ($showTotals ?? false)
        <tfoot>
            <tr>
                <td style="padding:10px 10px 4px"></td>
                <td colspan="2" style="padding:10px 10px 4px;text-align:right;color:#64748b">Subtotal</td>
                <td style="padding:10px 10px 4px;{{ $number }}">{{ number_format((float) $invoice->total, 2) }}</td>
            </tr>
            <tr>
                <td style="padding:6px 10px"></td>
                <td colspan="2" style="padding:6px 10px;text-align:right;border-top:2px solid #047857;font-size:13px;font-weight:bold;color:#047857">Total ({{ $invoice->currency }})</td>
                <td style="padding:6px 10px;{{ $number }}border-top:2px solid #047857;font-size:13px;font-weight:bold;color:#047857">{{ number_format((float) $invoice->total, 2) }}</td>
            </tr>
        </tfoot>
    @endif
</table>
