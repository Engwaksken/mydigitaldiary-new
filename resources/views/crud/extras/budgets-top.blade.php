@php
    $selectedBudgetMonth = $selectedBudgetMonth ?? now()->format('Y-m');
    $monthlyBudgetItems = $monthlyBudgetItems ?? collect();
    $availableBudgetMonths = $availableBudgetMonths ?? collect([$selectedBudgetMonth]);
    $nextMonth = \Illuminate\Support\Carbon::createFromFormat('Y-m', $selectedBudgetMonth)
        ->addMonthNoOverflow()
        ->format('Y-m');

    $budgetExpenseLinkingReady =
        \Illuminate\Support\Facades\Schema::hasColumn('budgets', 'is_expensed') &&
        \Illuminate\Support\Facades\Schema::hasColumn('expenses', 'budget_id');
@endphp

@unless($budgetExpenseLinkingReady)
    <div class="mb-4">
        <x-alert type="warning" :dismissible="false" :autoDismiss="false">
        Budget expense linking is waiting for the database migration.
        Run <code class="font-mono">php artisan migrate --force</code>.
        The Budget page remains usable for normal entries and imports.
        </x-alert>
    </div>
@endunless

<div id="pm-budget-toggle-error" class="hidden mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"></div>

<div class="mb-5 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="px-4 sm:px-5 py-4 border-b border-slate-100 flex flex-col lg:flex-row lg:items-center gap-3 justify-between">
        <div>
            <h2 class="font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-calendar-check text-emerald-600"></i>
                Monthly Budget
            </h2>
            <p class="text-xs text-slate-500 mt-1">
                Duplicate a previous month, edit only what changed, then tick items as you actually spend them.
            </p>
        </div>

        <div class="flex flex-wrap gap-2 items-center">
            <form method="GET" action="{{ route('budgets.index') }}" class="flex items-center gap-2">
                <input
                    type="month"
                    name="budget_month"
                    value="{{ $selectedBudgetMonth }}"
                    class="rounded-lg border-slate-300 text-sm"
                    onchange="this.form.submit()"
                >
                @if(request('q'))
                    <input type="hidden" name="q" value="{{ request('q') }}">
                @endif
            </form>

            <button
                type="button"
                onclick="document.getElementById('pm-budget-duplicate-modal').showModal()"
                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2.5 text-sm font-semibold"
            >
                <i class="fa-solid fa-copy"></i>
                Duplicate Month
            </button>
        </div>
    </div>

    @if($monthlyBudgetItems->isEmpty())
        <x-empty-state
            icon="fa-regular fa-calendar-xmark"
            title="No budget items for {{ $selectedBudgetMonth }}"
            message="Add a budget item, import one, or duplicate a previous month."
        />
    @else
        <div class="overflow-x-auto">
            <table class="min-w-[820px] w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left w-20">Spent?</th>
                        <th class="px-4 py-3 text-left">Category</th>
                        <th class="px-4 py-3 text-right">Budgeted</th>
                        <th class="px-4 py-3 text-left">Notes</th>
                        <th class="px-4 py-3 text-left">Expense status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($monthlyBudgetItems as $budgetItem)
                        @php
                            $editValues = [];
                            foreach ($fields as $field) {
                                $value = data_get($budgetItem, $field['name']);
                                if ($value instanceof \DateTimeInterface) {
                                    $value = $value->format('Y-m-d');
                                }
                                $editValues[$field['name']] = $value;
                            }
                        @endphp
                        <tr id="pm-budget-row-{{ $budgetItem->id }}">
                            <td class="px-4 py-3">
                                <input
                                    type="checkbox"
                                    class="pm-budget-expense-checkbox rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                    data-id="{{ $budgetItem->id }}"
                                    @checked(data_get($budgetItem, 'is_expensed', false))
                                    onchange="pmToggleBudgetExpense(this)"
                                    @disabled(!$budgetExpenseLinkingReady)
                                    aria-label="Mark {{ $budgetItem->category }} as expensed"
                                >
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800">{{ $budgetItem->category }}</div>
                                @if(data_get($budgetItem, 'source_budget_id'))
                                    <div class="text-[11px] text-slate-400 mt-0.5">
                                        <i class="fa-solid fa-copy mr-1"></i>Copied from previous budget
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-slate-800">
                                {{ format_money($budgetItem->amount) }}
                            </td>
                            <td class="px-4 py-3 text-slate-600 max-w-sm">
                                {{ \Illuminate\Support\Str::limit((string) $budgetItem->notes, 80) ?: '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    id="pm-budget-expense-status-{{ $budgetItem->id }}"
                                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold {{ data_get($budgetItem, 'is_expensed', false) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}"
                                >
                                    <i class="fa-solid {{ data_get($budgetItem, 'is_expensed', false) ? 'fa-circle-check' : 'fa-circle' }}"></i>
                                    {{ data_get($budgetItem, 'is_expensed', false) ? 'In Expenses' : 'Not expensed' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    onclick='openCrudEditModal(@json(route("budgets.update", $budgetItem->id)), @json($editValues))'
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold text-xs"
                                >
                                    <i class="fa-solid fa-pen"></i>
                                    Edit
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50">
                    <tr>
                        <td colspan="2" class="px-4 py-3 font-bold text-slate-700">
                            Total for {{ $selectedBudgetMonth }}
                        </td>
                        <td class="px-4 py-3 text-right font-black text-slate-900">
                            {{ format_money($monthlyBudgetItems->sum('amount')) }}
                        </td>
                        <td colspan="3" class="px-4 py-3 text-right text-xs text-slate-500">
                            {{ $budgetExpenseLinkingReady ? $monthlyBudgetItems->where('is_expensed', true)->count() : 0 }}
                            of {{ $monthlyBudgetItems->count() }} item(s) expensed
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>

<dialog id="pm-budget-duplicate-modal" class="w-[94vw] max-w-lg rounded-2xl p-0 backdrop:bg-slate-950/55 shadow-2xl">
    <div class="bg-white rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <div>
                <h2 class="font-bold text-lg text-slate-800">Duplicate Monthly Budget</h2>
                <p class="text-xs text-slate-500 mt-1">
                    Copy every item, then edit only the few values that changed.
                </p>
            </div>
            <button
                type="button"
                onclick="document.getElementById('pm-budget-duplicate-modal').close()"
                class="w-9 h-9 rounded-full hover:bg-slate-100"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('budgets.duplicate-month') }}" class="p-5 space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Copy from</label>
                <input
                    type="month"
                    name="source_month"
                    value="{{ $selectedBudgetMonth }}"
                    required
                    class="w-full rounded-lg border-slate-300"
                >
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Copy to</label>
                <input
                    type="month"
                    name="target_month"
                    value="{{ $nextMonth }}"
                    required
                    class="w-full rounded-lg border-slate-300"
                >
            </div>

            <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
                Expense checkboxes are reset in the new month so previous spending is never copied accidentally.
            </div>

            <button class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-3 font-bold">
                <i class="fa-solid fa-copy mr-1"></i>
                Duplicate Budget
            </button>
        </form>
    </div>
</dialog>

<script>
async function pmToggleBudgetExpense(checkbox) {
    const id = checkbox.dataset.id;
    const wanted = checkbox.checked;
    const status = document.getElementById('pm-budget-expense-status-' + id);
    const message = document.getElementById('pm-budget-toggle-error');

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
                },
                body: JSON.stringify({
                    is_expensed: wanted ? 1 : 0,
                }),
            }
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Could not update expense status.');
        }

        if (status) {
            status.className =
                'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ' +
                (wanted
                    ? 'bg-emerald-50 text-emerald-700'
                    : 'bg-slate-100 text-slate-500');

            status.innerHTML =
                '<i class="fa-solid ' +
                (wanted ? 'fa-circle-check' : 'fa-circle') +
                '"></i>' +
                (wanted ? 'In Expenses' : 'Not expensed');
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
}
</script>
