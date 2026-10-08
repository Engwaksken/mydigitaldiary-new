@extends('layouts.app')

@section('title', 'Payments')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-money-check-dollar text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Payments</h1>
    </div>

    <div class="flex gap-2 mb-4 text-sm">
        <a href="{{ route('admin.payments.index', request()->except(['status', 'page'])) }}"
           class="px-3 py-1 rounded-md {{ !$status ? 'bg-[var(--brand-1)] text-white' : 'bg-white border border-slate-300 text-slate-600' }}">
            All
        </a>
        @foreach (['pending' => 'Pending', 'completed' => 'Completed', 'failed' => 'Failed', 'rejected' => 'Rejected'] as $value => $label)
            <a href="{{ route('admin.payments.index', array_merge(request()->except(['status', 'page']), ['status' => $value])) }}"
               class="px-3 py-1 rounded-md {{ $status === $value ? 'bg-[var(--brand-1)] text-white' : 'bg-white border border-slate-300 text-slate-600' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @php
        // Same period filter kept; each cell narrows the status like the pills above.
        $pmPaymentsBase = request()->except(['status', 'page']);
        $pmPaymentLinks = [
            route('admin.payments.index', array_merge($pmPaymentsBase, ['status' => 'completed'])),
            route('admin.payments.index', $pmPaymentsBase),
            route('admin.payments.index', array_merge($pmPaymentsBase, ['status' => 'pending'])),
        ];
        $pmPaymentStats = collect($stats)->values()
            ->map(fn ($stat, $i) => $stat + (isset($pmPaymentLinks[$i]) ? ['href' => $pmPaymentLinks[$i]] : []))
            ->all();
    @endphp
    <x-summary-strip :stats="$pmPaymentStats" accent="emerald" icon="fa-solid fa-money-check-dollar" class="mb-6" label="Payments summary" />

    <form method="GET" action="{{ route('admin.payments.index') }}" class="flex flex-wrap items-end gap-3 mb-4">
        @if ($status)
            <input type="hidden" name="status" value="{{ $status }}">
        @endif

        <div class="flex-1 min-w-[220px] max-w-sm">
            <label for="payment-q" class="sr-only">Search payments</label>
            <input type="search" id="payment-q" name="q" value="{{ $search }}"
                   placeholder="Search user, email, phone, reference or gateway transaction..." class="pm-input text-sm">
        </div>

        <div>
            <label for="pay-period" class="sr-only">Filter by date</label>
            <select id="pay-period" name="period" onchange="pmTogglePaymentsDateRange(this)" class="pm-input text-sm">
                <option value="" @selected(!$period)>All time</option>
                <option value="daily" @selected($period === 'daily')>Today</option>
                <option value="weekly" @selected($period === 'weekly')>This week</option>
                <option value="monthly" @selected($period === 'monthly')>This month</option>
                <option value="annual" @selected($period === 'annual')>This year</option>
                <option value="range" @selected($period === 'range')>Custom range...</option>
            </select>
        </div>

        <div id="pay-date-range" class="flex items-end gap-2" style="{{ $period === 'range' ? '' : 'display: none;' }}">
            <input type="date" name="from" value="{{ $from }}" class="pm-input text-sm">
            <span class="text-slate-400 text-sm pb-2">to</span>
            <input type="date" name="to" value="{{ $to }}" class="pm-input text-sm">
        </div>


        <div>
            <label for="per_page" class="sr-only">Records per page</label>
            <select id="per_page" name="per_page" class="pm-input text-sm" onchange="this.form.submit()">
                @foreach ([10, 25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} / page</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">Filter</button>
        @if ($search || $period)
            <a href="{{ route('admin.payments.index', $status ? ['status' => $status] : []) }}" class="text-sm text-slate-500 hover:text-slate-700 transition-colors pb-2.5">Clear</a>
        @endif
    </form>

    <form id="payments-bulk-delete-form" method="POST" action="{{ route('admin.payments.bulk-destroy') }}" class="mb-3 flex flex-wrap items-center gap-3">
        @csrf
        @method('DELETE')
        <button type="submit"
                class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-50"
                data-confirm="Delete selected failed/rejected payments older than 5 days? Paid/completed and recent payments cannot be deleted."
                data-confirm-title="Delete selected payments?"
                data-confirm-text="Delete selected"
                id="payments-bulk-delete-button"
                disabled>
            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
            Delete selected failed/rejected
        </button>
        <p class="text-xs text-slate-500">Only failed or rejected payments older than 5 days can be selected.</p>
    </form>

    <script>
        function pmTogglePaymentsDateRange(select) {
            var wrapper = document.getElementById('pay-date-range');
            if (wrapper) { wrapper.style.display = select.value === 'range' ? 'flex' : 'none'; }
        }

        function pmSyncPaymentBulkDelete() {
            var boxes = Array.prototype.slice.call(document.querySelectorAll('.pm-payment-delete-checkbox'));
            var checked = boxes.filter(function (box) { return box.checked; });
            var button = document.getElementById('payments-bulk-delete-button');
            var all = document.getElementById('payments-select-all');

            if (button) { button.disabled = checked.length === 0; }
            if (all) {
                all.checked = boxes.length > 0 && checked.length === boxes.length;
                all.indeterminate = checked.length > 0 && checked.length < boxes.length;
            }
        }

        function pmToggleAllEligiblePayments(source) {
            document.querySelectorAll('.pm-payment-delete-checkbox').forEach(function (box) {
                box.checked = source.checked;
            });
            pmSyncPaymentBulkDelete();
        }

        document.addEventListener('DOMContentLoaded', pmSyncPaymentBulkDelete);
    </script>

    <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl overflow-x-auto pm-admin-table-scroll" role="region" aria-label="Payments table" tabindex="0">
        <table class="min-w-full text-sm pm-admin-horizontal-table">
            <caption class="sr-only">Payment submissions with approve/reject actions for pending bank and mobile money payments.</caption>
            <thead class="bg-slate-50 text-left border-b border-slate-100">
                <tr>
                    <th scope="col" class="px-4 py-3">
                        <input type="checkbox" id="payments-select-all" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500" onchange="pmToggleAllEligiblePayments(this)" aria-label="Select all eligible failed or rejected payments">
                    </th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">User</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Contact / Phone</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Method</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Amount</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Reference</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Gateway Txn ID</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Status</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Date</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($payments as $payment)
                    @php
                        $canDeletePayment = in_array($payment->status, ['failed', 'rejected'], true)
                            && $payment->created_at?->lte(now()->subDays(5));
                    @endphp
                    <tr>
                        <td class="px-4 py-3 align-top">
                            @if ($canDeletePayment)
                                <input type="checkbox"
                                       name="payment_ids[]"
                                       value="{{ $payment->id }}"
                                       form="payments-bulk-delete-form"
                                       class="pm-payment-delete-checkbox rounded border-slate-300 text-rose-600 focus:ring-rose-500"
                                       onchange="pmSyncPaymentBulkDelete()"
                                       aria-label="Select payment {{ $payment->reference ?? ('#' . $payment->id) }} for deletion">
                            @else
                                <span class="text-slate-300" title="Only failed/rejected payments older than 5 days can be deleted">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $payment->user->name ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if ($payment->contact_phone)
                                <a href="tel:{{ $payment->contact_phone }}" class="text-[var(--brand-1)] hover:underline">{{ $payment->contact_phone }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            {{ $payment->gateway->name ?? ucfirst(str_replace('_', ' ', $payment->method)) }}
                        </td>
                        <td class="px-4 py-3">{{ format_money_in($payment->amount, $payment->currency) }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $payment->reference ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs break-all">{{ $payment->gateway_transaction_id ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @php
                                $badgeColor = match($payment->status) {
                                    'completed' => 'text-emerald-700',
                                    'pending' => 'text-amber-600',
                                    'failed', 'rejected' => 'text-rose-600',
                                    default => 'text-slate-500',
                                };
                            @endphp
                            <span class="{{ $badgeColor }} font-medium">{{ ucfirst($payment->status) }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $payment->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($payment->status === 'pending' && $payment->method !== 'card')
                                <form action="{{ route('admin.payments.approve', $payment->id) }}" method="POST" class="inline"
                                      data-confirm="Approve this payment and activate the subscription?" data-confirm-title="Approve payment?" data-confirm-text="Approve" data-confirm-danger="false">
                                    @csrf
                                    <button type="submit" class="text-emerald-700 hover:underline mr-3">Approve</button>
                                </form>
                                <button type="button" onclick="document.getElementById('reject-modal-{{ $payment->id }}').showModal()"
                                        class="text-rose-600 hover:underline">Reject</button>

                                <dialog id="reject-modal-{{ $payment->id }}" class="rounded-lg p-6 max-w-sm backdrop:bg-black/40">
                                    <h2 class="text-lg font-bold mb-2">Reject payment?</h2>
                                    <form action="{{ route('admin.payments.reject', $payment->id) }}" method="POST">
                                        @csrf
                                        <label for="notes-{{ $payment->id }}" class="block text-sm font-medium text-slate-700 mb-1">
                                            Reason (optional, visible to admins only)
                                        </label>
                                        <textarea id="notes-{{ $payment->id }}" name="notes" rows="2"
                                                  class="pm-input mb-4"></textarea>
                                        <div class="flex justify-end gap-3">
                                            <button type="button" onclick="document.getElementById('reject-modal-{{ $payment->id }}').close()"
                                                    class="text-sm text-slate-500 hover:underline">Cancel</button>
                                            <button type="submit" class="bg-rose-600 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:shadow-md hover:bg-rose-700 transition-all">Reject</button>
                                        </div>
                                    </form>
                                </dialog>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-6 text-center text-slate-500">No payments yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

@if ($payments->total() > 0)
        <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-sm text-slate-500">
                Showing <span class="font-medium text-slate-700">{{ $payments->firstItem() }}</span>
                to <span class="font-medium text-slate-700">{{ $payments->lastItem() }}</span>
                of <span class="font-medium text-slate-700">{{ $payments->total() }}</span> records
            </p>
            <div>{{ $payments->links() }}</div>
        </div>
    @endif
@endsection
