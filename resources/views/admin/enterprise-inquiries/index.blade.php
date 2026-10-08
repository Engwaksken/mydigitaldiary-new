@extends('layouts.app')

@section('title', 'Enterprise Inquiries')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-violet-100 text-violet-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-handshake text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Enterprise Inquiries</h1>
    </div>

    {{-- ================= STATS ================= --}}
    <x-summary-strip class="mb-6" label="Enterprise inquiries summary" :stats="[
        ['label' => 'Today', 'value' => number_format((int) $stats['today']), 'icon' => 'fa-solid fa-calendar-day', 'color' => 'slate', 'href' => route('admin.enterprise-inquiries.index', ['period' => 'daily'])],
        ['label' => 'This week', 'value' => number_format((int) $stats['this_week']), 'icon' => 'fa-solid fa-calendar-week', 'color' => 'blue', 'href' => route('admin.enterprise-inquiries.index', ['period' => 'weekly']), 'title' => number_format((int) $stats['this_month']) . ' this month'],
        ['label' => 'New', 'value' => number_format((int) $stats['new']), 'icon' => 'fa-solid fa-envelope', 'color' => 'amber', 'href' => route('admin.enterprise-inquiries.index', ['status' => 'new'])],
        ['label' => 'Contacted', 'value' => number_format((int) $stats['contacted']), 'icon' => 'fa-solid fa-reply', 'color' => 'violet', 'href' => route('admin.enterprise-inquiries.index', ['status' => 'contacted'])],
        ['label' => 'Closed', 'value' => number_format((int) $stats['closed']), 'icon' => 'fa-solid fa-circle-check', 'color' => 'emerald', 'href' => route('admin.enterprise-inquiries.index', ['status' => 'closed'])],
    ]" />

    {{-- ================= FILTERS ================= --}}
    <form method="GET" action="{{ route('admin.enterprise-inquiries.index') }}" class="flex flex-wrap items-end gap-3 mb-4" id="pm-inquiry-filter-form">
        <div class="flex-1 min-w-[200px] max-w-xs">
            <label for="q" class="sr-only">Search</label>
            <input type="search" id="q" name="q" value="{{ $search }}" placeholder="Search email, phone, country, about..." class="pm-input text-sm">
        </div>
        <div>
            <label for="status" class="sr-only">Status</label>
            <select id="status" name="status" class="pm-input text-sm">
                <option value="">Any status</option>
                <option value="new" @selected($status === 'new')>New</option>
                <option value="contacted" @selected($status === 'contacted')>Contacted</option>
                <option value="closed" @selected($status === 'closed')>Closed</option>
            </select>
        </div>
        <div>
            <label for="period" class="sr-only">Period</label>
            <select id="period" name="period" onchange="pmToggleInquiryDateRange(this)" class="pm-input text-sm">
                <option value="">Any time</option>
                <option value="daily" @selected($period === 'daily')>Today</option>
                <option value="weekly" @selected($period === 'weekly')>This week</option>
                <option value="monthly" @selected($period === 'monthly')>This month</option>
                <option value="range" @selected($period === 'range')>Custom range...</option>
            </select>
        </div>
        <div id="pm-inquiry-date-range" class="flex items-center gap-2" style="{{ $period === 'range' ? '' : 'display: none;' }}">
            <input type="date" name="from" value="{{ $from }}" class="pm-input text-sm">
            <span class="text-slate-400 text-sm">to</span>
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
        @if ($search || $status || $period)
            <a href="{{ route('admin.enterprise-inquiries.index') }}" class="text-sm text-slate-500 hover:text-slate-700 pb-2.5">Clear</a>
        @endif
    </form>

    {{-- ================= TABLE ================= --}}
    <div class="md:hidden mb-2 text-[11px] font-medium text-slate-400">
        <i class="fa-solid fa-arrows-left-right mr-1"></i>
        Swipe sideways to view all enterprise inquiry columns.
    </div>

    <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl overflow-x-auto pm-horizontal-table-wrap">
        <table class="min-w-full text-sm pm-horizontal-data-table pm-enterprise-inquiries-horizontal-table pm-admin-horizontal-table">
            <caption class="sr-only">Sales inquiries submitted from the Enterprise pricing section.</caption>
            <thead class="bg-slate-50 text-left border-b border-slate-100">
                <tr>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Email</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Contact / Phone</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Country</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Employees</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">About</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Status</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Date</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($inquiries as $inquiry)
                    <tr>
                        <td class="px-4 py-3">{{ $inquiry->email }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if ($inquiry->phone)
                                <a href="tel:{{ $inquiry->phone }}" class="text-[var(--brand-1)] hover:underline">{{ $inquiry->phone }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $inquiry->country }}</td>
                        <td class="px-4 py-3">{{ $inquiry->employee_count }}</td>
                        <td class="px-4 py-3 pm-table-wrap-text" title="{{ $inquiry->about }}">
                            {{ $inquiry->about }}
                        </td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('admin.enterprise-inquiries.status', $inquiry->id) }}">
                                @csrf
                                @method('PATCH')
                                <select name="status" onchange="this.form.submit()" class="pm-input text-xs py-1">
                                    <option value="new" @selected($inquiry->status === 'new')>New</option>
                                    <option value="contacted" @selected($inquiry->status === 'contacted')>Contacted</option>
                                    <option value="closed" @selected($inquiry->status === 'closed')>Closed</option>
                                </select>
                            </form>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $inquiry->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <button type="button" onclick="document.getElementById('pm-reply-modal-{{ $inquiry->id }}').showModal()" class="text-[var(--brand-1)] hover:underline">
                                <i class="fa-solid fa-reply" aria-hidden="true"></i> Reply
                            </button>

                            <button type="button" onclick="document.getElementById('pm-docs-modal-{{ $inquiry->id }}').showModal()" class="text-[var(--brand-1)] hover:underline ms-3">
                                <i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Documents
                                @if ($inquiry->invoices->isNotEmpty())
                                    ({{ $inquiry->invoices->count() }})
                                @endif
                            </button>

                            <dialog id="pm-reply-modal-{{ $inquiry->id }}" class="rounded-2xl p-0 pm-dialog shadow-2xl backdrop:bg-slate-900/50">
                                <div class="p-6 text-left max-h-[85vh] overflow-y-auto">
                                    <div class="flex items-center justify-between mb-1">
                                        <h3 class="text-lg font-bold text-slate-800">Reply — {{ $inquiry->email }}</h3>
                                        <button type="button" onclick="document.getElementById('pm-reply-modal-{{ $inquiry->id }}').close()" class="text-slate-400 hover:text-slate-600" aria-label="Close">
                                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <p class="text-xs text-slate-500 mb-4">
                                        Sends an email to the lead. Their original request: “{{ $inquiry->about }}”
                                    </p>

                                    <form method="POST" action="{{ route('admin.enterprise-inquiries.reply', $inquiry->id) }}" class="space-y-3">
                                        @csrf
                                        <div>
                                            <label for="reply_subject_{{ $inquiry->id }}" class="block text-xs font-medium text-slate-700 mb-1">Subject</label>
                                            <input type="text" id="reply_subject_{{ $inquiry->id }}" name="subject" maxlength="255" placeholder="Re: your Digital Diary enquiry" class="pm-input text-sm" required>
                                        </div>
                                        <div>
                                            <label for="reply_body_{{ $inquiry->id }}" class="block text-xs font-medium text-slate-700 mb-1">Message</label>
                                            <textarea id="reply_body_{{ $inquiry->id }}" name="body" rows="6" class="pm-input text-sm" placeholder="Write your reply here..." required></textarea>
                                        </div>
                                        <button type="submit" class="btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium">
                                            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send Reply
                                        </button>
                                    </form>
                                </div>
                            </dialog>

                            <dialog id="pm-docs-modal-{{ $inquiry->id }}" class="rounded-2xl p-0 pm-dialog shadow-2xl backdrop:bg-slate-900/50">
                                <div class="p-6 text-left max-h-[85vh] overflow-y-auto">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-lg font-bold text-slate-800">Documents — {{ $inquiry->email }}</h3>
                                        <button type="button" onclick="document.getElementById('pm-docs-modal-{{ $inquiry->id }}').close()" class="text-slate-400 hover:text-slate-600" aria-label="Close">
                                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                        </button>
                                    </div>

                                    {{-- ---- Existing quotations/invoices ---- --}}
                                    @if ($inquiry->invoices->isNotEmpty())
                                        <div class="flex items-center justify-between mb-2">
                                            <h4 class="text-sm font-semibold text-slate-700">Sent so far (most recent 10)</h4>
                                            <a href="{{ route('admin.invoices.index', ['invoice_q' => $inquiry->email]) }}" class="text-xs text-[var(--brand-1)] hover:underline">
                                                View full history
                                            </a>
                                        </div>
                                        <div class="space-y-2 mb-5">
                                            @foreach ($inquiry->invoices as $doc)
                                                <div class="flex items-center justify-between border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                                    <div>
                                                        <span class="font-medium">{{ $doc->invoice_number }}</span>
                                                        <span class="text-xs text-slate-400 ms-1">{{ $doc->isQuote() ? 'Quotation' : ucfirst($doc->status) }}</span>
                                                        <span class="block text-xs text-slate-500">{{ format_money_in($doc->amount, $doc->currency) }} — {{ $doc->plan?->name ?? $doc->description }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-2 whitespace-nowrap">
                                                        <a href="{{ route('subscription.invoice', $doc->id) }}" class="text-[var(--brand-1)] hover:underline text-xs">
                                                            <i class="fa-solid fa-download" aria-hidden="true"></i>
                                                        </a>
                                                        <form method="POST" action="{{ route('admin.enterprise-inquiries.resend-invoice', [$inquiry->id, $doc->id]) }}">
                                                            @csrf
                                                            <button type="submit" class="text-xs text-slate-600 hover:underline">Resend</button>
                                                        </form>
                                                        @if ($doc->isQuote())
                                                            <form method="POST" action="{{ route('admin.enterprise-inquiries.convert-invoice', [$inquiry->id, $doc->id]) }}" data-confirm="Convert this quotation into a real invoice and send it?" data-confirm-title="Convert quotation?" data-confirm-text="Convert & send" data-confirm-danger="false">
                                                                @csrf
                                                                <button type="submit" class="text-xs text-emerald-700 hover:underline">Convert to Invoice</button>
                                                            </form>
                                                        @endif
                                                        @if ($doc->isDeletable())
                                                            <form method="POST" action="{{ route('admin.invoices.destroy', $doc->id) }}" data-confirm="Delete this {{ $doc->isQuote() ? 'quotation' : 'invoice' }}? This action cannot be undone." data-confirm-title="Delete document?" data-confirm-text="Delete">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="text-xs text-rose-600 hover:underline">Delete</button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- ---- New quotation ---- --}}
                                    <h4 class="text-sm font-semibold text-slate-700 mb-2">Send a Quotation</h4>
                                    <form method="POST" action="{{ route('admin.enterprise-inquiries.quotation', $inquiry->id) }}" class="space-y-3 mb-5">
                                        @csrf
                                        <div>
                                            <label for="plan_{{ $inquiry->id }}" class="block text-xs font-medium text-slate-700 mb-1">Based on a plan</label>
                                            <select id="plan_{{ $inquiry->id }}" name="subscription_plan_id" onchange="pmToggleQuoteCustomFields({{ $inquiry->id }}, this.value)" class="pm-input text-sm">
                                                <option value="">Custom quote instead...</option>
                                                @foreach ($plans as $plan)
                                                    <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div id="pm-quote-custom-{{ $inquiry->id }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label for="description_{{ $inquiry->id }}" class="block text-xs font-medium text-slate-700 mb-1">Description</label>
                                                <input type="text" id="description_{{ $inquiry->id }}" name="description" placeholder="What's being quoted" class="pm-input text-sm">
                                            </div>
                                            <div>
                                                <label for="amount_{{ $inquiry->id }}" class="block text-xs font-medium text-slate-700 mb-1">Amount</label>
                                                <input type="number" id="amount_{{ $inquiry->id }}" name="amount" min="0" step="0.01" class="pm-input text-sm">
                                            </div>
                                        </div>
                                        <div>
                                            <label for="valid_days_{{ $inquiry->id }}" class="block text-xs font-medium text-slate-700 mb-1">Valid for (days)</label>
                                            <input type="number" id="valid_days_{{ $inquiry->id }}" name="valid_days" value="30" min="1" max="365" class="pm-input text-sm w-24">
                                        </div>
                                        <button type="submit" class="btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium">Send Quotation</button>
                                    </form>

                                    {{-- ---- Send receipt (existing completed payments only) ---- --}}
                                    @if ($inquiry->user && $inquiry->user->payments->isNotEmpty())
                                        <h4 class="text-sm font-semibold text-slate-700 mb-2">Resend a Receipt</h4>
                                        <div class="space-y-2">
                                            @foreach ($inquiry->user->payments as $payment)
                                                <form method="POST" action="{{ route('admin.enterprise-inquiries.receipt', $inquiry->id) }}" class="flex items-center justify-between border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                                    @csrf
                                                    <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                                                    <span>{{ $payment->receipt_number ?? ('#' . $payment->id) }} — {{ format_money_in($payment->amount, $payment->currency) }}</span>
                                                    <button type="submit" class="text-xs text-[var(--brand-1)] hover:underline">Send Receipt</button>
                                                </form>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </dialog>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">No inquiries yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

@if ($inquiries->total() > 0)
        <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-sm text-slate-500">
                Showing <span class="font-medium text-slate-700">{{ $inquiries->firstItem() }}</span>
                to <span class="font-medium text-slate-700">{{ $inquiries->lastItem() }}</span>
                of <span class="font-medium text-slate-700">{{ $inquiries->total() }}</span> records
            </p>
            <div>{{ $inquiries->links() }}</div>
        </div>
    @endif

    <script>
        function pmToggleInquiryDateRange(select) {
            var wrapper = document.getElementById('pm-inquiry-date-range');
            if (wrapper) { wrapper.style.display = select.value === 'range' ? 'flex' : 'none'; }
        }

        function pmToggleQuoteCustomFields(inquiryId, planId) {
            var wrapper = document.getElementById('pm-quote-custom-' + inquiryId);
            if (wrapper) { wrapper.style.display = planId ? 'none' : 'grid'; }
        }
    </script>
@endsection
