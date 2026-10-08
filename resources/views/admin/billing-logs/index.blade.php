@extends('layouts.app')

@section('title', 'Billing Activity')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-file-invoice-dollar text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Billing Activity</h1>
    </div>

    <x-summary-strip class="mb-6" label="Billing activity summary" :stats="[
        ['label' => 'Total events', 'value' => number_format((int) $stats['total']), 'icon' => 'fa-solid fa-list', 'color' => 'slate', 'href' => route('admin.billing-logs.index')],
        ['label' => 'Failed deliveries', 'value' => number_format((int) $stats['failed']), 'icon' => 'fa-solid fa-triangle-exclamation', 'color' => 'rose', 'href' => route('admin.billing-logs.index', ['status' => 'failed'])],
        ['label' => 'Reminders sent', 'value' => number_format((int) $stats['reminders_sent']), 'icon' => 'fa-solid fa-bell', 'color' => 'amber', 'href' => route('admin.billing-logs.index', ['event_type' => 'reminder_sent', 'status' => 'success'])],
    ]" />

    <form method="GET" action="{{ route('admin.billing-logs.index') }}" class="flex flex-wrap items-end gap-3 mb-4">
        <div class="flex-1 min-w-[200px] max-w-xs">
            <label for="q" class="sr-only">Search</label>
            <input type="search" id="q" name="q" value="{{ $search }}" placeholder="Search name, email or phone..." class="pm-input text-sm">
        </div>
        <div>
            <label for="event_type" class="sr-only">Event type</label>
            <select id="event_type" name="event_type" class="pm-input text-sm">
                <option value="">All event types</option>
                @foreach (['invoice_generated' => 'Invoice generated', 'invoice_emailed' => 'Invoice emailed', 'receipt_emailed' => 'Receipt emailed', 'reminder_sent' => 'Reminder sent', 'payment_status_changed' => 'Payment status changed'] as $value => $label)
                    <option value="{{ $value }}" @selected($eventType === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="sr-only">Status</label>
            <select id="status" name="status" class="pm-input text-sm">
                <option value="">Any status</option>
                <option value="success" @selected($status === 'success')>Success</option>
                <option value="failed" @selected($status === 'failed')>Failed</option>
            </select>
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
        @if ($search || $eventType || $status)
            <a href="{{ route('admin.billing-logs.index') }}" class="text-sm text-slate-500 hover:text-slate-700 pb-2.5">Clear</a>
        @endif
    </form>

    <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl overflow-x-auto pm-admin-table-scroll">
        <table class="min-w-full text-sm pm-admin-horizontal-table">
            <caption class="sr-only">Billing-related events — invoices, receipts, reminders, and payment status changes.</caption>
            <thead class="bg-slate-50 text-left border-b border-slate-100">
                <tr>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Event</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">User</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Recipient</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Contact / Phone</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Status</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Details</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-4 py-3">{{ str_replace('_', ' ', ucfirst($log->event_type)) }}</td>
                        <td class="px-4 py-3">{{ $log->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $log->recipient_email ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if ($log->contact_phone)
                                <a href="tel:{{ $log->contact_phone }}" class="text-[var(--brand-1)] hover:underline">{{ $log->contact_phone }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="{{ $log->status === 'failed' ? 'text-rose-600' : 'text-emerald-700' }} font-medium">
                                {{ ucfirst($log->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 max-w-xs truncate" title="{{ $log->details }}">{{ $log->details ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-slate-500">No billing events recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

@if ($logs->total() > 0)
        <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-sm text-slate-500">
                Showing <span class="font-medium text-slate-700">{{ $logs->firstItem() }}</span>
                to <span class="font-medium text-slate-700">{{ $logs->lastItem() }}</span>
                of <span class="font-medium text-slate-700">{{ $logs->total() }}</span> records
            </p>
            <div>{{ $logs->links() }}</div>
        </div>
    @endif
@endsection
