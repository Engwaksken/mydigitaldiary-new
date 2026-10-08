@extends('layouts.app')

@section('title', 'Budgets')

@section('content')
<style>
    /* The budget table sits inside the month card: no second border/shadow. */
    .pm-dt-wrap.budget-dt-flat { border: 0; border-radius: 0 0 1rem 1rem; box-shadow: none; }
    .pm-dt-wrap.budget-dt-flat .pm-dt thead th { border-radius: 0 !important; }
    .budget-import-dt .pm-dt tbody td { vertical-align: top; }
    @media (max-width: 767.98px) {
        /* Import review rows: inputs share the second line evenly. */
        .budget-import-dt .pm-dt tbody td.pm-dt-aux { flex: 1 1 40%; }
    }
</style>
@php
    $tableExists = $tableExists ?? false;
    $columns = is_array($columns ?? null) ? $columns : [];
    $ownerColumn = $ownerColumn ?? (in_array('user_id', $columns, true) ? 'user_id' : null);
    $items = collect($items ?? []);
    $statistics = is_array($statistics ?? null) ? $statistics : [];
    $debts = collect($debts ?? []);
    $selectedMonth = preg_match('/^\d{4}-\d{2}$/', (string) ($selectedMonth ?? ''))
        ? (string) $selectedMonth
        : now()->format('Y-m');
    $availableMonths = collect($availableMonths ?? [$selectedMonth]);
    $expenseLinkingReady = (bool) ($expenseLinkingReady ?? false);

    $previousMonth = $previousMonth ?? \Carbon\Carbon::createFromFormat('Y-m', $selectedMonth)->subMonthNoOverflow()->format('Y-m');
    $nextMonth = $nextMonth ?? \Carbon\Carbon::createFromFormat('Y-m', $selectedMonth)->addMonthNoOverflow()->format('Y-m');
    $total = (float) ($total ?? $items->sum(fn($item) => (float) data_get($item, 'amount', 0)));
    $expensedCount = (int) ($expensedCount ?? 0);
    $pageLoadWarning = $pageLoadWarning ?? null;
    $money = function ($value) {
        if (function_exists('format_money')) {
            return format_money((float) $value);
        }

        return number_format((float) $value, 0);
    };

    $total = $items->sum('amount');
    $expensedCount = in_array('is_expensed', $columns, true)
        ? $items->filter(fn ($item) => (bool) data_get($item, 'is_expensed'))->count()
        : 0;

    $previousMonth = \Illuminate\Support\Carbon::createFromFormat('Y-m', $selectedMonth)
        ->subMonthNoOverflow()
        ->format('Y-m');

    $nextMonth = \Illuminate\Support\Carbon::createFromFormat('Y-m', $selectedMonth)
        ->addMonthNoOverflow()
        ->format('Y-m');
@endphp

<div class="max-w-7xl mx-auto px-3 sm:px-5 py-5">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-5">
        <div>
            <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-wallet text-emerald-600"></i>
                Budgets
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Plan monthly spending, duplicate previous budgets, import files or photos, and mark items as expensed.
            </p>
        </div>

        @if($tableExists)
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    onclick="document.getElementById('budget-import-modal').showModal()"
                    class="px-4 py-2.5 rounded-lg border border-slate-200 bg-white font-semibold text-sm hover:bg-slate-50"
                >
                    <i class="fa-solid fa-file-import mr-1"></i>
                    Upload / Scan
                </button>

                <button
                    type="button"
                    onclick="document.getElementById('budget-duplicate-modal').showModal()"
                    class="px-4 py-2.5 rounded-lg border border-slate-200 bg-white font-semibold text-sm hover:bg-slate-50"
                >
                    <i class="fa-solid fa-copy mr-1"></i>
                    Duplicate Month
                </button>

                <button
                    type="button"
                    onclick="openBudgetForm()"
                    class="px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm"
                >
                    <i class="fa-solid fa-plus mr-1"></i>
                    New Budget
                </button>
            </div>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-4">
            <x-alert type="success" :message="session('success')" :dismissible="false" :autoDismiss="false" />
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4">
            <x-alert type="error" :message="session('error')" :dismissible="false" :autoDismiss="false" />
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4">
            <x-alert type="error" :dismissible="false" :autoDismiss="false">
                <div class="font-bold mb-1">Please correct the following:</div>
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        </div>
    @endif

    <div id="budget-status-message" class="hidden mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"></div>

    @if($pageLoadWarning)
        <div class="mb-4">
            <x-alert type="warning" :message="$pageLoadWarning" :dismissible="false" :autoDismiss="false" />
        </div>
    @endif

    @unless($tableExists)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6">
            <h2 class="font-black text-amber-900">Budget database table is missing</h2>
            <p class="text-sm text-amber-800 mt-2">
                Run <code class="font-mono">php artisan migrate --force</code>, then reload this page.
            </p>
        </div>
    @else

        @if(!$ownerColumn)
            <div class="mb-4">
                <x-alert type="error" :dismissible="false" :autoDismiss="false">
                The budgets table has no supported ownership column.
                Expected one of <code>user_id</code>, <code>owner_id</code>,
                <code>created_by</code> or <code>account_id</code>.
                No budget data is displayed until ownership is configured safely.
                </x-alert>
            </div>
        @endif
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-emerald-500">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Total Budget</div>
                        <div class="mt-1 text-xl font-black text-slate-900">{{ $money($statistics['total'] ?? 0) }}</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center"><i class="fa-solid fa-wallet"></i></div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-blue-500">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">This Month</div>
                        <div class="mt-1 text-xl font-black text-slate-900">{{ $money($statistics['monthly'] ?? 0) }}</div>
                        <div class="text-[11px] text-slate-400 mt-1">{{ $selectedMonth }}</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center"><i class="fa-solid fa-calendar-days"></i></div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-amber-500">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Weekly Budget</div>
                        <div class="mt-1 text-xl font-black text-slate-900">{{ $money($statistics['weekly'] ?? 0) }}</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center"><i class="fa-solid fa-calendar-week"></i></div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-violet-500">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Total Items</div>
                        <div class="mt-1 text-xl font-black text-slate-900">{{ number_format((int) ($statistics['items'] ?? 0)) }}</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-violet-50 text-violet-700 flex items-center justify-center"><i class="fa-solid fa-list-check"></i></div>
                </div>
            </div>
        </div>

        @if($tableExists && !empty(array_diff(['user_id','category','amount','period','month_year'], $columns)))
            <div class="mb-4">
                <x-alert type="warning" :dismissible="false" :autoDismiss="false">
                The Budgets table is using an older database structure. Run
                <code class="font-mono">php artisan migrate --force</code>
                to enable all Budget features.
                </x-alert>
            </div>
        @endif

        @unless($expenseLinkingReady)
            <div class="mb-4">
                <x-alert type="warning" :dismissible="false" :autoDismiss="false">
                Expense checkboxes are temporarily disabled until the latest migration is applied:
                <code class="font-mono">php artisan migrate --force</code>.
                </x-alert>
            </div>
        @endunless

        {{-- No overflow-hidden here: row action menus must not be clipped. --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm mb-5">
            <div class="p-4 flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <a
                        href="{{ url('/budgets').'?budget_month='.urlencode($previousMonth) }}"
                        class="w-9 h-9 rounded-lg border border-slate-200 flex items-center justify-center hover:bg-slate-50"
                    >
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>

                    <form method="GET" action="{{ url('/budgets') }}">
                        <input
                            type="month"
                            name="budget_month"
                            value="{{ $selectedMonth }}"
                            onchange="this.form.submit()"
                            class="rounded-lg border-slate-300 font-bold"
                        >
                    </form>

                    <a
                        href="{{ url('/budgets').'?budget_month='.urlencode($nextMonth) }}"
                        class="w-9 h-9 rounded-lg border border-slate-200 flex items-center justify-center hover:bg-slate-50"
                    >
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>

                <div class="flex flex-wrap gap-4 text-sm">
                    <div>
                        <span class="text-slate-500">Budgeted</span>
                        <strong class="ml-1 text-slate-900">{{ $money($total) }}</strong>
                    </div>
                    <div>
                        <span class="text-slate-500">Items</span>
                        <strong class="ml-1 text-slate-900">{{ $items->count() }}</strong>
                    </div>
                    <div>
                        <span class="text-slate-500">Expensed</span>
                        <strong class="ml-1 text-emerald-700">{{ $expensedCount }}/{{ $items->count() }}</strong>
                    </div>
                </div>
            </div>

            @if($items->isEmpty())
                <x-empty-state
                    icon="fa-regular fa-calendar-xmark"
                    title="No budget items for {{ $selectedMonth }}"
                    message="Add one manually, duplicate a previous month, or upload/scan a budget."
                />
            @else
                <div class="pm-dt-wrap budget-dt-flat">
                    <table class="pm-dt">
                        <caption class="sr-only">Budget items for {{ $selectedMonth }}. Tick an item once it has been spent.</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="w-16">Spent?</th>
                                <th scope="col">Budget item</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="pm-dt-num">Budgeted</th>
                                <th scope="col" class="pm-dt-actions"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                                @php
                                    $isExpensed = (bool) data_get($item, 'is_expensed', false);
                                    $isDebtPayment = data_get($item, 'application_type') === 'debt_payment';
                                    $budgetNotes = trim((string) $item->notes);
                                @endphp
                                <tr class="has-check">
                                    <td class="pm-dt-check">
                                        <input
                                            type="checkbox"
                                            class="rounded border-slate-300 text-emerald-600"
                                            aria-label="Mark {{ $item->category }} as spent"
                                            @checked($isExpensed)
                                            @disabled(!$expenseLinkingReady)
                                            onchange="toggleBudgetExpense({{ $item->id }}, this)"
                                        >
                                    </td>
                                    <td class="pm-dt-main">
                                        <span class="pm-dt-title" title="{{ $item->category }}">{{ $item->category }}</span>
                                        <span class="pm-dt-sub">
                                            @if($isDebtPayment)
                                                <span><i class="fa-solid fa-hand-holding-dollar text-[10px]" aria-hidden="true"></i> Debt payment</span>
                                            @endif
                                            @if(filled($item->period) && $item->period !== 'monthly')
                                                <span>{{ ucfirst((string) $item->period) }}</span>
                                            @endif
                                            @if($budgetNotes !== '')
                                                <span class="pm-dt-note" title="{{ $budgetNotes }}">{{ \Illuminate\Support\Str::limit($budgetNotes, 90) }}</span>
                                            @elseif(! $isDebtPayment && (! filled($item->period) || $item->period === 'monthly'))
                                                <span>Monthly</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td class="pm-dt-aux">
                                        <span
                                            id="budget-status-{{ $item->id }}"
                                            class="pm-dt-pill {{ $isExpensed ? 'is-green' : 'is-slate' }}"
                                        >{{ $isExpensed ? ($isDebtPayment ? 'Debt Paid' : 'In Expenses') : 'Not paid' }}</span>
                                    </td>
                                    <td class="pm-dt-num">
                                        {{ $money($item->amount) }}
                                    </td>
                                    <td class="pm-dt-actions">
                                        <details class="pm-dt-menu">
                                            <summary class="pm-dt-icon-btn" title="More actions" aria-label="More actions for {{ $item->category }}">
                                                <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                            </summary>
                                            <div class="pm-dt-menu-list">
                                                <button
                                                    type="button"
                                                    data-budget-id="{{ $item->id }}"
                                                    data-budget-category="{{ e($item->category) }}"
                                                    data-budget-amount="{{ $item->amount }}"
                                                    data-budget-period="{{ $item->period }}"
                                                    data-budget-month="{{ $item->month_year }}"
                                                    data-budget-notes="{{ e((string) $item->notes) }}"
                                                    data-budget-expensed="{{ $isExpensed ? '1' : '0' }}"
                                                    data-budget-application="{{ data_get($item, 'application_type', 'expense') }}"
                                                    data-budget-debt-id="{{ data_get($item, 'debt_id') }}"
                                                    onclick="openBudgetFormFromButton(this)"
                                                    class="pm-dt-menu-item"
                                                >
                                                    <i class="fa-solid fa-pen" aria-hidden="true"></i>Edit
                                                </button>

                                                <form method="POST" action="{{ url('/budgets/'.data_get($item, 'id')) }}" data-confirm="Delete this budget item?" data-confirm-title="Delete budget item?" data-confirm-text="Delete">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="pm-dt-menu-item is-danger">
                                                        <i class="fa-solid fa-trash" aria-hidden="true"></i>Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endunless
</div>

<dialog id="budget-form-modal" class="w-[94vw] max-w-xl rounded-2xl p-0 backdrop:bg-slate-950/50">
    <div class="bg-white rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b flex justify-between items-center">
            <h2 id="budget-form-title" class="text-lg font-black">New Budget</h2>
            <button type="button" onclick="closeBudgetForm()" class="w-9 h-9 rounded-full hover:bg-slate-100">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="budget-form" method="POST" class="p-5 space-y-4">
            @csrf
            <input id="budget-method" type="hidden" name="_method" value="POST">

            <div>
                <label class="block text-sm font-bold mb-1">Category</label>
                <input id="budget-category" name="category" required class="w-full rounded-lg border-slate-300">
            </div>

            <div>
                <label class="block text-sm font-bold mb-1">Budgeted Amount</label>
                <input id="budget-amount" name="amount" type="number" min="0" step="0.01" required class="w-full rounded-lg border-slate-300">
            </div>

            <div>
                <label class="block text-sm font-bold mb-1">Period</label>
                <select id="budget-period" name="period" class="w-full rounded-lg border-slate-300">
                    <option value="monthly">Monthly</option>
                    <option value="weekly">Weekly</option>
                    <option value="annually">Annually</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-bold mb-1">Month</label>
                <input id="budget-month-year" name="month_year" type="month" value="{{ $selectedMonth }}" required class="w-full rounded-lg border-slate-300">
            </div>

            @if(in_array('application_type', $columns, true))
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Apply as</label>
                    <select id="budget-application-type" name="application_type" class="w-full rounded-lg border-slate-300">
                        <option value="expense">Expense</option>
                        <option value="debt_payment">Debt Payment</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">
                        Expense creates or updates Expenses. Debt Payment reduces the selected outstanding debt.
                    </p>
                </div>

                <div id="budget-debt-wrap" class="hidden">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Debt to pay</label>
                    <select id="budget-debt-id" name="debt_id" class="w-full rounded-lg border-slate-300">
                        <option value="">Select outstanding debt</option>
                        @foreach(($debts ?? collect()) as $debt)
                            <option value="{{ $debt->id }}">
                                {{ data_get($debt, 'person_name', 'Debt #'.data_get($debt, 'id')) }} — {{ $money(data_get($debt, 'amount', 0)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if(in_array('is_expensed', $columns, true))
                <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3">
                    <input id="budget-is-expensed" name="is_expensed" type="checkbox" value="1" class="mt-1 rounded border-slate-300 text-emerald-600" @disabled(!$expenseLinkingReady)>
                    <span>
                        <strong class="block text-sm">This item has been paid / spent</strong>
                        <small class="text-slate-500">
                            Expense creates a matching Expense. Debt Payment reduces the selected outstanding debt.
                        </small>
                    </span>
                </label>
            @endif

            <div>
                <label class="block text-sm font-bold mb-1">Notes</label>
                <textarea id="budget-notes" name="notes" rows="3" class="w-full rounded-lg border-slate-300"></textarea>
            </div>

            <button class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white py-3 font-black">
                Save Budget
            </button>
        </form>
    </div>
</dialog>

<dialog id="budget-duplicate-modal" class="w-[94vw] max-w-lg rounded-2xl p-0 backdrop:bg-slate-950/50">
    <div class="bg-white rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b flex justify-between items-center">
            <div>
                <h2 class="text-lg font-black">Duplicate Monthly Budget</h2>
                <p class="text-xs text-slate-500 mt-1">Copy a month and edit only what changed.</p>
            </div>
            <button type="button" onclick="document.getElementById('budget-duplicate-modal').close()" class="w-9 h-9 rounded-full hover:bg-slate-100">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="{{ url('/budgets/duplicate-month') }}" class="p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-bold mb-1">Copy from</label>
                <input name="source_month" type="month" value="{{ $previousMonth }}" required class="w-full rounded-lg border-slate-300">
            </div>
            <div>
                <label class="block text-sm font-bold mb-1">Copy to</label>
                <input name="target_month" type="month" value="{{ $selectedMonth }}" required class="w-full rounded-lg border-slate-300">
            </div>

            <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
                Expense checkboxes are reset in the copied month.
            </div>

            <button class="w-full rounded-lg bg-emerald-600 text-white py-3 font-black">
                Duplicate Budget
            </button>
        </form>
    </div>
</dialog>

<dialog id="budget-import-modal" class="w-[96vw] max-w-5xl rounded-2xl p-0 backdrop:bg-slate-950/50">
    <div class="bg-white rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b flex justify-between items-center">
            <div>
                <h2 class="text-lg font-black">Upload / Scan Budget</h2>
                <p class="text-xs text-slate-500 mt-1">Upload Excel, CSV, PDF, Word or a budget image, then review each extracted item before saving.</p>
            </div>
            <button type="button" onclick="document.getElementById('budget-import-modal').close()" class="w-9 h-9 rounded-full hover:bg-slate-100">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="p-5">
            <div id="budget-import-error" class="hidden mb-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700"></div>

            <form id="budget-extract-form" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-[1fr_auto]">
                @csrf
                <input
                    name="file"
                    type="file"
                    required
                    accept=".xlsx,.xls,.csv,.pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,image/*"
                    capture="environment"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5"
                >
                <button id="budget-extract-btn" class="rounded-lg bg-slate-900 text-white px-4 py-2.5 font-bold">
                    <i class="fa-solid fa-wand-magic-sparkles mr-1"></i>
                    Extract Items
                </button>
            </form>

            <form id="budget-import-save-form" method="POST" action="{{ url('/budgets/import/confirm') }}" class="hidden mt-5">
                @csrf
                <input type="hidden" name="items_json" id="budget-items-json">
                <input type="hidden" name="filename" id="budget-import-filename">
                <input type="hidden" name="source" id="budget-import-source">
                <input type="hidden" name="confidence" id="budget-import-confidence">

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                    <div>
                        <h3 class="font-black">Review Budget Items</h3>
                        <p id="budget-import-meta" class="text-xs text-slate-500"></p>
                    </div>

                    <button type="button" id="budget-add-import-line" class="px-3 py-2 rounded-lg border border-slate-200 text-sm font-semibold">
                        <i class="fa-solid fa-plus mr-1"></i>Add budget item
                    </button>
                </div>

                <div class="pm-dt-wrap budget-import-dt">
                    <table class="pm-dt">
                        <thead>
                            <tr>
                                <th scope="col">Item</th>
                                <th scope="col" class="w-36">Amount</th>
                                <th scope="col" class="w-40">Period</th>
                                <th scope="col" class="pm-dt-actions"><span class="sr-only">Remove</span></th>
                            </tr>
                        </thead>
                        <tbody id="budget-import-lines"></tbody>
                    </table>
                </div>

                <button class="mt-4 w-full rounded-lg bg-emerald-600 text-white py-3 font-black">
                    Save Imported Budget Items
                </button>
            </form>
        </div>
    </div>
</dialog>

<script>
(function () {
    'use strict';

    const selectedMonth = @json($selectedMonth);
    let importItems = [];

    window.updateDebtVisibility = function () {
        const type = document.getElementById('budget-application-type');
        const wrap = document.getElementById('budget-debt-wrap');
        const debt = document.getElementById('budget-debt-id');
        const paid = document.getElementById('budget-is-expensed');

        if (!type || !wrap) {
            return;
        }

        const isDebtPayment = type.value === 'debt_payment';

        wrap.classList.toggle('hidden', !isDebtPayment);

        if (debt) {
            debt.disabled = !isDebtPayment;
            debt.required = isDebtPayment && Boolean(paid?.checked);

            if (!isDebtPayment) {
                debt.value = '';
            }
        }
    };

    window.closeBudgetForm = function () {
        const modal = document.getElementById('budget-form-modal');

        if (modal?.open) {
            modal.close();
        }
    };

    window.openBudgetFormFromButton = function (button) {
        const dataset = button.dataset;

        window.openBudgetForm({
            id: Number(dataset.budgetId || 0),
            category: dataset.budgetCategory || '',
            amount: dataset.budgetAmount || '',
            period: dataset.budgetPeriod || 'monthly',
            month_year: dataset.budgetMonth || selectedMonth,
            notes: dataset.budgetNotes || '',
            is_expensed: dataset.budgetExpensed === '1',
            application_type: dataset.budgetApplication || 'expense',
            debt_id: dataset.budgetDebtId || '',
        });
    };

    window.openBudgetForm = function (item = null) {
        const modal = document.getElementById('budget-form-modal');
        const form = document.getElementById('budget-form');

        if (!modal || !form) {
            console.error('Budget form modal could not be found.');
            return;
        }

        const isEdit = Boolean(item && item.id);

        const title = document.getElementById('budget-form-title');
        const method = document.getElementById('budget-method');
        const category = document.getElementById('budget-category');
        const amount = document.getElementById('budget-amount');
        const period = document.getElementById('budget-period');
        const month = document.getElementById('budget-month-year');
        const notes = document.getElementById('budget-notes');
        const paid = document.getElementById('budget-is-expensed');
        const application =
            document.getElementById('budget-application-type');
        const debt = document.getElementById('budget-debt-id');

        if (title) {
            title.textContent = isEdit
                ? 'Edit Budget'
                : 'New Budget';
        }

        form.action = isEdit
            ? @json(url('/budgets')) + '/' + item.id
            : @json(url('/budgets'));

        if (method) {
            method.value = isEdit
                ? 'PUT'
                : 'POST';
        }

        if (category) {
            category.value = isEdit
                ? String(item.category || '')
                : '';
        }

        if (amount) {
            amount.value = isEdit
                ? String(item.amount || '')
                : '';
        }

        if (period) {
            period.value = isEdit
                ? String(item.period || 'monthly')
                : 'monthly';
        }

        if (month) {
            month.value = isEdit
                ? String(item.month_year || selectedMonth)
                : selectedMonth;
        }

        if (notes) {
            notes.value = isEdit
                ? String(item.notes || '')
                : '';
        }

        if (paid) {
            paid.checked = isEdit
                ? Boolean(item.is_expensed)
                : false;
        }

        if (application) {
            const type = isEdit
                ? String(item.application_type || 'expense')
                : 'expense';

            application.value =
                type === 'debt_payment'
                    ? 'debt_payment'
                    : 'expense';
        }

        if (debt) {
            debt.value =
                isEdit && item.debt_id
                    ? String(item.debt_id)
                    : '';
        }

        window.updateDebtVisibility();

        if (typeof modal.showModal === 'function') {
            if (!modal.open) {
                modal.showModal();
            }
        } else {
            modal.setAttribute('open', 'open');
        }
    };

    const applicationTypeInput =
        document.getElementById('budget-application-type');

    const paidInput =
        document.getElementById('budget-is-expensed');

    if (applicationTypeInput) {
        applicationTypeInput.addEventListener(
            'change',
            window.updateDebtVisibility
        );
    }

    if (paidInput) {
        paidInput.addEventListener(
            'change',
            window.updateDebtVisibility
        );
    }

    window.toggleBudgetExpense = async function (id, checkbox) {
        const wanted = checkbox.checked;
        const status = document.getElementById('budget-status-' + id);
        const message = document.getElementById('budget-status-message');

        checkbox.disabled = true;
        if (message) {
            message.classList.add('hidden');
            message.textContent = '';
        }

        try {
            const response = await fetch(
                @json(url('/budgets')) + '/' + id + '/expense-status',
                {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': @json(csrf_token()),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        is_expensed: wanted ? 1 : 0,
                    }),
                }
            );

            const raw = await response.text();
            let data = {};

            try {
                data = raw ? JSON.parse(raw) : {};
            } catch (parseError) {
                data = {
                    message: raw
                        .replace(/<[^>]*>/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim()
                        .slice(0, 300),
                };
            }

            if (!response.ok) {
                throw new Error(
                    data.message ||
                    'Could not update expense status.'
                );
            }

            if (status) {
                status.className =
                    'pm-dt-pill ' + (wanted ? 'is-green' : 'is-slate');

                const appliedType =
                    data?.data?.application_type || 'expense';

                status.textContent =
                    (
                        wanted
                            ? (
                                appliedType === 'debt_payment'
                                    ? 'Debt Paid'
                                    : 'In Expenses'
                            )
                            : 'Not paid'
                    );
            }
        } catch (error) {
            checkbox.checked = !wanted;
            if (message) {
                message.textContent = error.message || 'Could not update expense status.';
                message.classList.remove('hidden');
                message.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } finally {
            checkbox.disabled = false;
        }
    };

    function blankImportItem() {
        return {
            category: 'General',
            description: '',
            planned_amount: 0,
            period: 'monthly',
            month_year: selectedMonth,
            notes: '',
        };
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            }[char];
        });
    }

    function syncImportJson() {
        document.getElementById('budget-items-json').value =
            JSON.stringify(importItems);
    }

    function renderImportItems() {
        const body = document.getElementById('budget-import-lines');
        body.innerHTML = '';

        if (!importItems.length) {
            importItems.push(blankImportItem());
        }

        importItems.forEach(function (item, index) {
            const row = document.createElement('tr');
            // Control order (category, description, amount, period, month,
            // remove) is relied on by the index-based handlers below.
            row.innerHTML = `
                <td class="pm-dt-main">
                    <input class="w-full rounded border-slate-300 text-sm" placeholder="Category" aria-label="Category" value="${escapeHtml(item.category || 'General')}">
                    <input class="w-full rounded border-slate-300 text-sm mt-1.5" placeholder="Description (optional)" aria-label="Description" value="${escapeHtml(item.description || '')}">
                </td>
                <td class="pm-dt-aux">
                    <input type="number" min="0" step="0.01" class="w-full rounded border-slate-300 text-sm text-right" placeholder="Amount" aria-label="Amount" value="${Number(item.planned_amount || 0)}">
                </td>
                <td class="pm-dt-aux">
                    <select class="w-full rounded border-slate-300 text-sm" aria-label="Period">
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="annually">Annually</option>
                    </select>
                    <input type="month" class="w-full rounded border-slate-300 text-sm mt-1.5" aria-label="Month" value="${escapeHtml(item.month_year || selectedMonth)}">
                </td>
                <td class="pm-dt-actions">
                    <button type="button" class="pm-dt-icon-btn text-rose-600" aria-label="Remove item" title="Remove item">
                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                    </button>
                </td>
            `;

            const controls = row.querySelectorAll('input, select, button');
            controls[3].value = item.period || 'monthly';

            controls[0].oninput = function (event) {
                item.category = event.target.value;
                syncImportJson();
            };

            controls[1].oninput = function (event) {
                item.description = event.target.value;
                syncImportJson();
            };

            controls[2].oninput = function (event) {
                item.planned_amount = Number(event.target.value || 0);
                syncImportJson();
            };

            controls[3].onchange = function (event) {
                item.period = event.target.value;
                syncImportJson();
            };

            controls[4].onchange = function (event) {
                item.month_year = event.target.value;
                syncImportJson();
            };

            controls[5].onclick = function () {
                importItems.splice(index, 1);
                renderImportItems();
            };

            body.appendChild(row);
        });

        syncImportJson();
    }

    document.getElementById('budget-add-import-line')?.addEventListener(
        'click',
        function () {
            importItems.push(blankImportItem());
            renderImportItems();
        }
    );

    document.getElementById('budget-extract-form')?.addEventListener(
        'submit',
        async function (event) {
            event.preventDefault();

            const error = document.getElementById('budget-import-error');
            const button = document.getElementById('budget-extract-btn');

            error.classList.add('hidden');
            button.disabled = true;
            button.textContent = 'Extracting…';

            try {
                const response = await fetch(
                    @json(url('/budgets/extract')),
                    {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                        },
                        body: new FormData(event.target),
                    }
                );

                const payload = await response.json();

                if (!response.ok) {
                    const validation = payload.errors
                        ? Object.values(payload.errors).flat().join(' ')
                        : '';

                    throw new Error(
                        validation ||
                        payload.message ||
                        'Could not extract budget items.'
                    );
                }

                const data = payload.data || {};

                importItems = Array.isArray(data.items)
                    ? data.items.map(function (item) {
                        return {
                            category: item.category || 'General',
                            description: item.description || '',
                            planned_amount: Number(
                                item.planned_amount ||
                                item.amount ||
                                0
                            ),
                            period: ['weekly', 'monthly', 'annually'].includes(item.period)
                                ? item.period
                                : 'monthly',
                            month_year: item.month_year || selectedMonth,
                            notes: item.notes || '',
                        };
                    })
                    : [];

                document.getElementById('budget-import-filename').value =
                    data.filename || '';

                document.getElementById('budget-import-source').value =
                    data.source || 'file';

                document.getElementById('budget-import-confidence').value =
                    Number(data.confidence || 0);

                document.getElementById('budget-import-meta').textContent =
                    `${importItems.length} detected item${importItems.length === 1 ? '' : 's'}. Review before saving.`;

                document.getElementById('budget-import-save-form')
                    .classList.remove('hidden');

                renderImportItems();
            } catch (exception) {
                error.textContent = exception.message;
                error.classList.remove('hidden');
            } finally {
                button.disabled = false;
                button.innerHTML =
                    '<i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Extract Items';
            }
        }
    );

    document.getElementById('budget-import-save-form')?.addEventListener(
        'submit',
        function (event) {
            const error = document.getElementById('budget-import-error');

            if (!importItems.length) {
                event.preventDefault();
                error.textContent = 'Add at least one budget item before saving.';
                error.classList.remove('hidden');
                return;
            }

            const invalid = importItems.find(function (item) {
                return !String(item.category || '').trim() ||
                    Number(item.planned_amount || 0) < 0;
            });

            if (invalid) {
                event.preventDefault();
                error.textContent =
                    'Every budget item needs a category and a valid amount.';
                error.classList.remove('hidden');
                return;
            }

            syncImportJson();
        }
    );
})();
</script>
@endsection
