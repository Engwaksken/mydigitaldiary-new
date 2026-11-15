@extends('layouts.app')

@section('title', 'Payment Gateways')

@section('content')

    <style>
        /* Payment Gateway dialog: roomy, responsive, header/footer stay visible. */
        .pm-gateway-dialog {
            width: min(960px, calc(100vw - 28px));
            max-width: 960px;
            max-height: calc(100dvh - 28px);
            overflow: hidden;
            border: 0;
            background: #fff;
        }
        .pm-gateway-dialog::backdrop { background: rgba(15, 23, 42, .58); }
        .pm-gateway-dialog-form {
            display: flex;
            flex-direction: column;
            max-height: calc(100dvh - 28px);
            min-height: 0;
            background: #fff;
        }
        .pm-gateway-dialog-header {
            flex: 0 0 auto;
            padding: 20px 28px;
            border-bottom: 1px solid #e2e8f0;
            background: #fff;
        }
        .pm-gateway-dialog-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 24px 28px 30px;
            scrollbar-width: thin;
            scrollbar-color: #94a3b8 transparent;
        }
        .pm-gateway-dialog-body::-webkit-scrollbar { width: 7px; }
        .pm-gateway-dialog-body::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 999px; }
        .pm-gateway-dialog-footer {
            flex: 0 0 auto;
            padding: 15px 28px;
            border-top: 1px solid #e2e8f0;
            background: #fff;
            box-shadow: 0 -8px 18px rgba(15, 23, 42, .04);
        }
        .pm-gateway-dialog-body .pm-input { width: 100%; min-width: 0; }
        .pm-payment-channel-option:has(input:checked) {
            border-color: var(--brand-1);
            box-shadow: 0 0 0 1px var(--brand-1);
        }
        @media (max-width: 640px) {
            .pm-gateway-dialog {
                width: calc(100vw - 14px);
                max-height: calc(100dvh - 14px);
                border-radius: 16px;
            }
            .pm-gateway-dialog-form { max-height: calc(100dvh - 14px); }
            .pm-gateway-dialog-header { padding: 15px 17px; }
            .pm-gateway-dialog-body { padding: 17px; }
            .pm-gateway-dialog-footer { padding: 12px 17px; }
            .pm-gateway-dialog-footer > button { flex: 1 1 0; }
        }
    </style>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shadow-sm shrink-0">
                <i class="fa-solid fa-credit-card text-xl" aria-hidden="true"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Payment Gateways</h1>
        </div>
        <button type="button" onclick="document.getElementById('gateway-create-modal').showModal()"
                class="inline-flex items-center justify-center gap-2 btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            <span>Add Gateway</span>
        </button>
    </div>

    <p class="text-sm text-slate-500 mb-4">
        Enabled gateways appear as payment options on every user's Subscription page.
        Automatic collection gateways such as ioTec can also be used for user-enabled subscription auto renewal:
        on the expiry date the system starts the renewal collection and activates the next period only after payment succeeds.
        Manual bank and Mobile Money payments are verified at
        <a href="{{ route('admin.payments.index') }}" class="text-[var(--brand-1)] hover:underline">Payments</a>.
        ioTec can expose Mobile Money plus Visa/MasterCard from the same aggregator configuration. Card details are handled by the provider's hosted secure checkout and are never stored by My Digital Diary.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        @foreach ($stats as $stat)
            <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 border-l-4 border-l-{{ $stat['color'] }}-400 p-4">
                <div class="w-9 h-9 rounded-lg bg-{{ $stat['color'] }}-50 text-{{ $stat['color'] }}-600 flex items-center justify-center mb-2">
                    <i class="{{ $stat['icon'] }} text-sm" aria-hidden="true"></i>
                </div>
                <p class="text-xs text-slate-500 uppercase tracking-wide truncate">{{ $stat['label'] }}</p>
                <p class="text-xl font-bold text-slate-800 truncate">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.payment-gateways.index') }}" class="flex flex-wrap items-end gap-3 mb-4">
        <div class="flex-1 min-w-[180px] max-w-xs">
            <label for="pg-q" class="sr-only">Search gateways</label>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm" aria-hidden="true"></i>
                <input type="search" id="pg-q" name="q" value="{{ $search }}" placeholder="Search by name..." class="pm-input pl-9 text-sm">
            </div>
        </div>

        <div>
            <label for="pg-period" class="sr-only">Filter by date added</label>
            <select id="pg-period" name="period" onchange="pmTogglePgDateRange(this)" class="pm-input text-sm">
                <option value="" @selected(!$period)>All time</option>
                <option value="daily" @selected($period === 'daily')>Added today</option>
                <option value="weekly" @selected($period === 'weekly')>Added this week</option>
                <option value="monthly" @selected($period === 'monthly')>Added this month</option>
                <option value="range" @selected($period === 'range')>Custom range...</option>
            </select>
        </div>

        <div id="pg-date-range" class="flex items-end gap-2" style="{{ $period === 'range' ? '' : 'display: none;' }}">
            <input type="date" name="from" value="{{ $from }}" class="pm-input text-sm">
            <span class="text-slate-400 text-sm pb-2">to</span>
            <input type="date" name="to" value="{{ $to }}" class="pm-input text-sm">
        </div>

        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">Filter</button>
        @if ($search || $period)
            <a href="{{ route('admin.payment-gateways.index') }}" class="text-sm text-slate-500 hover:text-slate-700 transition-colors pb-2.5">Clear</a>
        @endif
    </form>

    <script>
        function pmTogglePgDateRange(select) {
            var wrapper = document.getElementById('pg-date-range');
            if (wrapper) { wrapper.style.display = select.value === 'range' ? 'flex' : 'none'; }
        }
    </script>

    <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl overflow-x-auto pm-admin-table-scroll" role="region" aria-label="Payment gateways table" tabindex="0">
        <table class="min-w-full text-sm pm-admin-horizontal-table">
            <caption class="sr-only">Configured payment gateways, with enable/disable and edit actions.</caption>
            <thead class="bg-slate-50 text-left border-b border-slate-100">
                <tr>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Name</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Type</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Status</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($gateways as $gateway)
                    <tr>
                        <td class="px-4 py-3">
                            {{ $gateway->name }}
                            @if ($gateway->is_default)
                                <span class="ms-1 inline-flex items-center gap-1 text-xs font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">
                                    <i class="fa-solid fa-star" aria-hidden="true"></i> Default
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($gateway->type === 'bank') Bank Transfer
                            @elseif ($gateway->type === 'mobile_money') Mobile Money
                            @elseif ($gateway->type === 'aggregator') {{ $gateway->display_name ?: 'Aggregator' }}
                            @else Card (Stripe)
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($gateway->is_enabled)
                                <span class="text-emerald-700 font-medium">Enabled</span>
                            @else
                                <span class="text-slate-400">Disabled</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button" onclick="document.getElementById('gateway-view-modal-{{ $gateway->id }}').showModal()" class="text-slate-500 hover:text-slate-800 mr-3" title="View gateway"><i class="fa-solid fa-eye"></i><span class="sr-only">View {{ $gateway->name }}</span></button>
                            <dialog id="gateway-view-modal-{{ $gateway->id }}" class="rounded-2xl p-0 pm-dialog-sm shadow-2xl backdrop:bg-slate-900/50 text-left">
                                <div class="p-6">
                                    <div class="flex items-center justify-between mb-4"><h3 class="text-lg font-bold text-slate-800">{{ $gateway->name }}</h3><button type="button" onclick="this.closest('dialog').close()" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark"></i></button></div>
                                    <dl class="grid grid-cols-2 gap-4 text-sm">
                                        <div><dt class="text-xs uppercase text-slate-400">Type</dt><dd>{{ ucfirst(str_replace('_', ' ', $gateway->type)) }}</dd></div>
                                        <div><dt class="text-xs uppercase text-slate-400">Status</dt><dd>{{ $gateway->is_enabled ? 'Enabled' : 'Disabled' }}</dd></div>
                                        <div><dt class="text-xs uppercase text-slate-400">Default</dt><dd>{{ $gateway->is_default ? 'Yes' : 'No' }}</dd></div>
                                        <div><dt class="text-xs uppercase text-slate-400">Mode</dt><dd>{{ $gateway->sandbox_mode ? 'Test / Sandbox' : 'Live' }}</dd></div>
                                    </dl>
                                </div>
                            </dialog>
                            @unless ($gateway->is_default)
                                <form action="{{ route('admin.payment-gateways.set-default', $gateway->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-[var(--brand-1)] hover:underline mr-3">Set as Default</button>
                                </form>
                            @endunless
                            <form action="{{ route('admin.payment-gateways.toggle', $gateway->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-[var(--brand-1)] hover:underline mr-3">
                                    {{ $gateway->is_enabled ? 'Disable' : 'Enable' }}
                                </button>
                            </form>
                            <button type="button" onclick="document.getElementById('gateway-edit-modal-{{ $gateway->id }}').showModal()" class="text-[var(--brand-1)] hover:underline mr-3">Edit</button>
                            <form action="{{ route('admin.payment-gateways.destroy', $gateway->id) }}" method="POST" class="inline"
                                  data-confirm="Remove this payment gateway?" data-confirm-title="Delete payment gateway?" data-confirm-text="Delete">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-slate-500">
                            No payment gateways configured yet - users will see a demo "Subscribe" button instead.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <nav aria-label="Pagination" class="mt-4">
        {{ $gateways->links() }}
    </nav>

    {{-- Create modal --}}
    @include('admin.payment-gateways._modal', ['gateway' => new \App\Models\PaymentGateway, 'modalId' => 'gateway-create-modal', 'action' => route('admin.payment-gateways.store'), 'method' => null])

    {{-- One server-rendered edit modal per existing gateway - deliberately
         NOT a single shared modal populated via JS with json_encode()'d
         row data, since that would mean putting each gateway's Stripe
         secret-key placeholder logic (and potentially the config values
         of OTHER gateway types) through client-side JavaScript. With only
         a handful of gateways ever configured (bank/mobile money/card),
         rendering one dialog per row server-side is simpler and keeps
         sensitive fields exactly as server-controlled as the old
         full-page edit form was. --}}
    @foreach ($gateways as $gateway)
        @include('admin.payment-gateways._modal', ['gateway' => $gateway, 'modalId' => 'gateway-edit-modal-' . $gateway->id, 'action' => route('admin.payment-gateways.update', $gateway->id), 'method' => 'PUT'])
    @endforeach

    <script>
        // If a create/edit submission failed validation, reopen whichever
        // modal it came from rather than leaving the error silently at
        // the top of an otherwise-normal index page.
        document.addEventListener('DOMContentLoaded', function () {
            @if ($errors->any())
                var dialogAction = @json(old('_dialog_action', ''));
                if (dialogAction) {
                    document.querySelectorAll('dialog[data-gateway-action]').forEach(function (dialog) {
                        if (dialog.dataset.gatewayAction === dialogAction) {
                            dialog.showModal();
                        }
                    });
                }
            @endif
        });
    </script>
@endsection
