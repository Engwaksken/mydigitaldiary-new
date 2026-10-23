@extends('layouts.app')

@section('title', 'Quotations, Invoices & Receipts')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-file-invoice-dollar text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Quotations, Invoices &amp; Receipts</h1>
    </div>

    <div role="tablist" aria-label="Document type" class="flex items-center gap-1 border-b border-slate-200 mb-6">
        <button type="button" role="tab" id="pm-doc-tab-invoices" aria-controls="pm-doc-panel-invoices" aria-selected="true" tabindex="0" data-tab="invoices"
                onclick="pmSelectDocTab('invoices')"
                class="pm-doc-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-[var(--brand-1)] text-[var(--brand-1)]">
            <i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Quotations &amp; Invoices
        </button>
        <button type="button" role="tab" id="pm-doc-tab-receipts" aria-controls="pm-doc-panel-receipts" aria-selected="false" tabindex="-1" data-tab="receipts"
                onclick="pmSelectDocTab('receipts')"
                class="pm-doc-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-receipt" aria-hidden="true"></i> Receipts
        </button>
    </div>

    {{-- ================= QUOTATIONS & INVOICES ================= --}}
    <div role="tabpanel" id="pm-doc-panel-invoices" aria-labelledby="pm-doc-tab-invoices" tabindex="0" class="pm-doc-panel space-y-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach ([
                ['label' => 'Quotations', 'value' => $invoiceStats['quotations'], 'color' => 'violet'],
                ['label' => 'Unpaid', 'value' => $invoiceStats['unpaid'], 'color' => 'amber'],
                ['label' => 'Paid', 'value' => $invoiceStats['paid'], 'color' => 'emerald'],
                ['label' => 'Cancelled', 'value' => $invoiceStats['cancelled'], 'color' => 'rose'],
            ] as $card)
                <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 border-l-4 border-l-{{ $card['color'] }}-400 p-3">
                    <p class="text-xs text-slate-500 uppercase tracking-wide">{{ $card['label'] }}</p>
                    <p class="text-xl font-bold text-slate-800">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.invoices.index') }}" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="tab" value="invoices">
            <div class="flex-1 min-w-[200px] max-w-xs">
                <label for="invoice_q" class="sr-only">Search</label>
                <input type="search" id="invoice_q" name="invoice_q" value="{{ $invoiceSearch }}" placeholder="Invoice #, email or phone..." class="pm-input text-sm">
            </div>
            <div>
                <label for="invoice_status" class="sr-only">Status</label>
                <select id="invoice_status" name="invoice_status" class="pm-input text-sm">
                    <option value="">Any status</option>
                    <option value="quote" @selected($invoiceStatus === 'quote')>Quotation</option>
                    <option value="unpaid" @selected($invoiceStatus === 'unpaid')>Unpaid</option>
                    <option value="paid" @selected($invoiceStatus === 'paid')>Paid</option>
                    <option value="cancelled" @selected($invoiceStatus === 'cancelled')>Cancelled</option>
                </select>
            </div>
            <div>
                <label for="invoice_period" class="sr-only">Period</label>
                <select id="invoice_period" name="invoice_period" onchange="pmToggleDateRange('invoice', this)" class="pm-input text-sm">
                    <option value="">Any time</option>
                    <option value="daily" @selected($invoicePeriod === 'daily')>Today</option>
                    <option value="weekly" @selected($invoicePeriod === 'weekly')>This week</option>
                    <option value="monthly" @selected($invoicePeriod === 'monthly')>This month</option>
                    <option value="range" @selected($invoicePeriod === 'range')>Custom range...</option>
                </select>
            </div>
            <div id="pm-invoice-date-range" class="flex items-center gap-2" style="{{ $invoicePeriod === 'range' ? '' : 'display: none;' }}">
                <input type="date" name="invoice_from" value="{{ $invoiceFrom }}" class="pm-input text-sm">
                <span class="text-slate-400 text-sm">to</span>
                <input type="date" name="invoice_to" value="{{ $invoiceTo }}" class="pm-input text-sm">
            </div>
    
            <div>
                <label for="invoice_per_page" class="sr-only">Invoices per page</label>
                <select id="invoice_per_page" name="invoice_per_page" class="pm-input text-sm" onchange="this.form.submit()">
                    @foreach ([10, 25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected($invoicePerPage === $size)>{{ $size }} / page</option>
                    @endforeach
                </select>
            </div>
        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">Filter</button>
            @if ($invoiceSearch || $invoiceStatus || $invoicePeriod)
                <a href="{{ route('admin.invoices.index') }}" class="text-sm text-slate-500 hover:text-slate-700 pb-2.5">Clear</a>
            @endif
        </form>

        <div id="pm-invoice-bulk-bar" class="flex items-center justify-between bg-rose-50 border border-rose-200 rounded-lg px-4 py-2.5 mb-3" style="display: none;">
            <span id="pm-invoice-bulk-count" class="text-sm text-rose-700 font-medium"></span>
            <button type="button" onclick="pmSubmitInvoiceBulkDelete()" class="bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
                <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i> Delete Selected
            </button>
        </div>

        <div class="md:hidden mb-2 text-[11px] font-medium text-slate-400">
            <i class="fa-solid fa-arrows-left-right mr-1"></i>
            Swipe sideways to view all invoice columns.
        </div>
        <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl overflow-x-auto pm-horizontal-table-wrap">
            <table class="min-w-full text-sm pm-horizontal-data-table pm-invoices-horizontal-table pm-admin-horizontal-table">
                <caption class="sr-only">Every quotation and invoice, site-wide.</caption>
                <thead class="bg-slate-50 text-left border-b border-slate-100">
                    <tr>
                        <th scope="col" class="px-4 py-3 w-8">
                            <input type="checkbox" id="pm-invoice-select-all" onclick="pmToggleAllInvoices(this)" aria-label="Select all deletable invoices">
                        </th>
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Number</th>
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">For</th>
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Contact / Phone</th>
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Plan / Description</th>
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Amount</th>
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Status</th>
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Date</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td class="px-4 py-3">
                                @if ($invoice->isDeletable())
                                    <input type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}" class="pm-invoice-checkbox" onclick="pmUpdateInvoiceBulkBar()" aria-label="Select {{ $invoice->invoice_number }}">
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $invoice->invoice_number }}</td>
                            <td class="px-4 py-3">{{ $invoice->user->email ?? $invoice->enterpriseInquiry?->email ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($invoice->contact_phone)
                                    <a href="tel:{{ $invoice->contact_phone }}" class="text-[var(--brand-1)] hover:underline">{{ $invoice->contact_phone }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 pm-table-wrap-text">{{ $invoice->plan?->name ?? $invoice->description ?? '—' }}</td>
                            <td class="px-4 py-3">{{ format_money_in($invoice->amount, $invoice->currency) }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $statusColor = match($invoice->status) {
                                        'quote' => 'text-violet-700',
                                        'paid' => 'text-emerald-700',
                                        'cancelled' => 'text-rose-600',
                                        default => 'text-amber-600',
                                    };
                                @endphp
                                <span class="{{ $statusColor }} font-medium">{{ $invoice->isQuote() ? 'Quotation' : ucfirst($invoice->status) }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $invoice->created_at->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('subscription.invoice', $invoice->id) }}" class="text-[var(--brand-1)] hover:underline mr-3">
                                    <i class="fa-solid fa-download" aria-hidden="true"></i>
                                </a>
                                @if ($invoice->isDeletable())
                                    <form method="POST" action="{{ route('admin.invoices.destroy', $invoice->id) }}" class="inline" data-confirm="Delete this {{ $invoice->isQuote() ? 'quotation' : 'invoice' }}? This action cannot be undone." data-confirm-title="Delete {{ $invoice->isQuote() ? 'quotation' : 'invoice' }}?" data-confirm-text="Delete">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-600 hover:underline">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-6 text-center text-slate-500">No quotations or invoices yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($invoices->total() > 0)
        <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-sm text-slate-500">
                Showing <span class="font-medium text-slate-700">{{ $invoices->firstItem() }}</span>
                to <span class="font-medium text-slate-700">{{ $invoices->lastItem() }}</span>
                of <span class="font-medium text-slate-700">{{ $invoices->total() }}</span> records
            </p>
            <div>{{ $invoices->links() }}</div>
        </div>
    @endif
    </div>

    {{-- ================= RECEIPTS ================= --}}
    <div role="tabpanel" id="pm-doc-panel-receipts" aria-labelledby="pm-doc-tab-receipts" tabindex="0" class="pm-doc-panel space-y-4" hidden>
        <form method="GET" action="{{ route('admin.invoices.index') }}" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="tab" value="receipts">
            <div class="flex-1 min-w-[200px] max-w-xs">
                <label for="receipt_q" class="sr-only">Search</label>
                <input type="search" id="receipt_q" name="receipt_q" value="{{ $receiptSearch }}" placeholder="Receipt #, reference, gateway txn, email or phone..." class="pm-input text-sm">
            </div>
            <div>
                <label for="receipt_period" class="sr-only">Period</label>
                <select id="receipt_period" name="receipt_period" onchange="pmToggleDateRange('receipt', this)" class="pm-input text-sm">
                    <option value="">Any time</option>
                    <option value="daily" @selected($receiptPeriod === 'daily')>Today</option>
                    <option value="weekly" @selected($receiptPeriod === 'weekly')>This week</option>
                    <option value="monthly" @selected($receiptPeriod === 'monthly')>This month</option>
                    <option value="range" @selected($receiptPeriod === 'range')>Custom range...</option>
                </select>
            </div>
            <div id="pm-receipt-date-range" class="flex items-center gap-2" style="{{ $receiptPeriod === 'range' ? '' : 'display: none;' }}">
                <input type="date" name="receipt_from" value="{{ $receiptFrom }}" class="pm-input text-sm">
                <span class="text-slate-400 text-sm">to</span>
                <input type="date" name="receipt_to" value="{{ $receiptTo }}" class="pm-input text-sm">
            </div>
    
            <div>
                <label for="receipt_per_page" class="sr-only">Receipts per page</label>
                <select id="receipt_per_page" name="receipt_per_page" class="pm-input text-sm" onchange="this.form.submit()">
                    @foreach ([10, 25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected($receiptPerPage === $size)>{{ $size }} / page</option>
                    @endforeach
                </select>
            </div>
        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">Filter</button>
            @if ($receiptSearch || $receiptPeriod)
                <a href="{{ route('admin.invoices.index') }}" class="text-sm text-slate-500 hover:text-slate-700 pb-2.5">Clear</a>
            @endif
        </form>

        <div class="md:hidden mb-2 text-[11px] font-medium text-slate-400">
            <i class="fa-solid fa-arrows-left-right mr-1"></i>
            Swipe sideways to view all receipt columns.
        </div>
        <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl overflow-x-auto pm-horizontal-table-wrap">
            <table class="min-w-full text-sm pm-horizontal-data-table pm-receipts-horizontal-table pm-admin-horizontal-table">
                <caption class="sr-only">Every completed payment (receipt), site-wide. Receipts are never deletable — they're a real financial record.</caption>
                <thead class="bg-slate-50 text-left border-b border-slate-100">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Receipt #</th>
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">User</th>
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Contact / Phone</th>
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Plan</th>
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Amount</th>
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Gateway Txn ID</th>
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Date</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Download</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($receipts as $payment)
                        <tr>
                            <td class="px-4 py-3">{{ $payment->receipt_number ?? ('#' . $payment->id) }}</td>
                            <td class="px-4 py-3">{{ $payment->user->email ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($payment->contact_phone)
                                    <a href="tel:{{ $payment->contact_phone }}" class="text-[var(--brand-1)] hover:underline">{{ $payment->contact_phone }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $payment->plan?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ format_money_in($payment->amount, $payment->currency) }}</td>
                            <td class="px-4 py-3 font-mono text-xs break-all">{{ $payment->gateway_transaction_id ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $payment->created_at->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-3">
                                    <a href="{{ route('subscription.receipt.view', $payment->id) }}" target="_blank" rel="noopener" class="text-[var(--brand-1)] hover:underline" title="View receipt PDF">
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i><span class="sr-only">View receipt PDF</span>
                                    </a>
                                    <a href="{{ route('subscription.receipt', $payment->id) }}" class="text-[var(--brand-1)] hover:underline" title="Download receipt PDF">
                                        <i class="fa-solid fa-download" aria-hidden="true"></i><span class="sr-only">Download receipt PDF</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-slate-500">No receipts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($receipts->total() > 0)
        <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-sm text-slate-500">
                Showing <span class="font-medium text-slate-700">{{ $receipts->firstItem() }}</span>
                to <span class="font-medium text-slate-700">{{ $receipts->lastItem() }}</span>
                of <span class="font-medium text-slate-700">{{ $receipts->total() }}</span> records
            </p>
            <div>{{ $receipts->links() }}</div>
        </div>
    @endif
    </div>

    <script>
        function pmSelectDocTab(key) {
            document.querySelectorAll('.pm-doc-tab').forEach(function (btn) {
                var isSelected = btn.dataset.tab === key;
                btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                btn.setAttribute('tabindex', isSelected ? '0' : '-1');
                btn.classList.toggle('border-[var(--brand-1)]', isSelected);
                btn.classList.toggle('text-[var(--brand-1)]', isSelected);
                btn.classList.toggle('border-transparent', !isSelected);
                btn.classList.toggle('text-slate-500', !isSelected);
            });
            document.querySelectorAll('.pm-doc-panel').forEach(function (panel) {
                panel.hidden = panel.id !== 'pm-doc-panel-' + key;
            });
        }

        function pmToggleDateRange(prefix, select) {
            var wrapper = document.getElementById('pm-' + prefix + '-date-range');
            if (wrapper) { wrapper.style.display = select.value === 'range' ? 'flex' : 'none'; }
        }

        function pmToggleAllInvoices(selectAllCheckbox) {
            document.querySelectorAll('.pm-invoice-checkbox').forEach(function (checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            });
            pmUpdateInvoiceBulkBar();
        }

        function pmUpdateInvoiceBulkBar() {
            var checked = document.querySelectorAll('.pm-invoice-checkbox:checked');
            var bar = document.getElementById('pm-invoice-bulk-bar');
            var count = document.getElementById('pm-invoice-bulk-count');
            bar.style.display = checked.length > 0 ? 'flex' : 'none';
            count.textContent = checked.length + (checked.length === 1 ? ' item selected' : ' items selected');

            // Keep "select all" in sync if the user unchecks rows individually.
            var all = document.querySelectorAll('.pm-invoice-checkbox');
            var selectAll = document.getElementById('pm-invoice-select-all');
            if (selectAll) { selectAll.checked = all.length > 0 && checked.length === all.length; }
        }

        // Builds a real form on the fly rather than wrapping the whole
        // table in one — a static wrapping form would have illegally
        // nested each row's own single-delete <form>, which browsers
        // handle unpredictably.
        function pmSubmitInvoiceBulkDelete() {
            var checked = document.querySelectorAll('.pm-invoice-checkbox:checked');
            if (checked.length === 0) { return; }
            pmConfirmAction({title:'Delete selected documents?',message:'Delete the selected quotations/invoices? This action cannot be undone.',confirmText:'Delete selected',onConfirm:function(){
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("admin.invoices.bulk-destroy") }}';

            var csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);

            var methodField = document.createElement('input');
            methodField.type = 'hidden';
            methodField.name = '_method';
            methodField.value = 'DELETE';
            form.appendChild(methodField);

            checked.forEach(function (checkbox) {
                var field = document.createElement('input');
                field.type = 'hidden';
                field.name = 'invoice_ids[]';
                field.value = checkbox.value;
                form.appendChild(field);
            });

            document.body.appendChild(form);
            form.submit();
            }});
        }

        // Land on the Receipts tab if that's what was just searched/
        // filtered/paginated, rather than resetting to the first tab.
        document.addEventListener('DOMContentLoaded', function () {
            var params = new URLSearchParams(window.location.search);
            if (params.get('tab') === 'receipts' || params.has('receipt_page') || params.has('receipt_q') || params.has('receipt_period')) {
                pmSelectDocTab('receipts');
            }
        });
    </script>
@endsection
