<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Expense;
use App\Services\BudgetExpenseService;
use App\Services\ReceiptExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ExpenseController extends CrudController
{
    protected string $model = Expense::class;
    protected string $routeName = 'expenses';
    protected string $title = 'Expense';
    protected string $icon = 'fa-solid fa-receipt';
    protected string $accent = 'rose';
    protected string $dateField = 'spent_at';

    /** Value of the budget picker's "+ Add new item" option. */
    private const NEW_BUDGET_ITEM = 'new';

    protected array $fields = [
        // Rendered by crud/_budget-picker.blade.php; its grouped options are
        // filled in per request by withBudgetOptions().
        ['tab' => 'Expense', 'name' => 'budget_id', 'label' => 'Budget item', 'type' => 'budget-picker', 'table' => 'hidden', 'placeholder' => 'Choose from budget or add new'],
        ['tab' => 'Expense', 'name' => 'category', 'label' => 'Category', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Groceries, Rent, Transport'],
        ['tab' => 'Expense', 'name' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => false, 'money' => true, 'placeholder' => '0.00, or add line items'],
        ['tab' => 'Expense', 'name' => 'spent_at', 'label' => 'Date', 'type' => 'date', 'required' => true],
        ['tab' => 'Details', 'name' => 'payment_method', 'label' => 'Payment Method', 'type' => 'text', 'placeholder' => 'e.g. Cash, Card, Mobile Money'],
        ['tab' => 'Details', 'name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'category' => 'required|string|max:255',
        'amount' => 'nullable|numeric|min:0',
        'spent_at' => 'required|date',
        'payment_method' => 'nullable|string|max:255',
        'notes' => 'nullable|string',
        'items' => 'nullable|array',
        'items.*.description' => 'required_with:items|string|max:255',
        'items.*.quantity' => 'required_with:items|numeric|min:0.01',
        'items.*.unit_price' => 'required_with:items|numeric|min:0',
    ];

    public function index(Request $request)
    {
        $query = Expense::query()
            ->where('user_id', $request->user()->id)
            ->with(['items', 'budget:id,category,month_year']);

        $view = $this->renderIndex($request, $query);

        // Budget items linked from the rows on this page are always
        // offered too, so editing an older expense keeps its link.
        $pageBudgetIds = collect($view->getData()['items']->items())
            ->pluck('budget_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $view->with('fields', $this->withBudgetOptions($request, $pageBudgetIds));
    }

    public function store(Request $request)
    {
        if ($request->boolean('receipt_extract')) {
            return $this->extractReceipt($request);
        }

        $addNewToBudget = $this->normaliseBudgetChoice($request);
        $data = $request->validate($this->rulesFor($request));
        $data['user_id'] = $request->user()->id;

        $items = $data['items'] ?? [];
        unset($data['items'], $data['add_to_budget']);

        if (! empty($items)) {
            $data['amount'] = $this->calculateItemsTotal($items);
        } elseif (! isset($data['amount']) || $data['amount'] === null || $data['amount'] === '') {
            return back()
                ->withErrors([
                    'amount' => 'Enter an amount, or add at least one line item.',
                ])
                ->withInput();
        }

        $expense = Expense::create($data);

        if (! empty($items)) {
            $this->syncItems($expense, $items);
        }

        if ($addNewToBudget && empty($expense->budget_id)) {
            $this->addToMonthBudget($expense);
        }

        $this->refreshBudgets($expense, [$expense->budget_id]);

        return redirect()
            ->route('expenses.index')
            ->with('success', $expense->budget_id ? 'Expense created and budget updated.' : 'Expense created.');
    }

    public function update(Request $request, int $id)
    {
        $expense = Expense::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $previousBudgetId = $expense->budget_id;
        $addNewToBudget = $this->normaliseBudgetChoice($request);
        $data = $request->validate($this->rulesFor($request));

        $items = $data['items'] ?? [];
        unset($data['items'], $data['add_to_budget']);

        if (! empty($items)) {
            $data['amount'] = $this->calculateItemsTotal($items);
        } elseif (! isset($data['amount']) || $data['amount'] === null || $data['amount'] === '') {
            return back()
                ->withErrors([
                    'amount' => 'Enter an amount, or add at least one line item.',
                ])
                ->withInput();
        }

        $expense->update($data);

        if ($request->has('items')) {
            $this->syncItems($expense, $items);
        }

        if ($addNewToBudget && empty($expense->budget_id)) {
            $this->addToMonthBudget($expense);
        }

        $this->refreshBudgets($expense, [$previousBudgetId, $expense->budget_id]);

        return redirect()
            ->route('expenses.index')
            ->with('success', 'Expense updated.');
    }

    /**
     * The budget picker submits a Budget id, '' (no link) or 'new'
     * ("+ Add new item"). 'new' means no link yet; returns whether the
     * user also asked to add the new item to that month's budget. A form
     * that doesn't send budget_id at all leaves an existing link alone.
     */
    private function normaliseBudgetChoice(Request $request): bool
    {
        if (! $request->has('budget_id')) {
            return false;
        }

        $choice = $request->input('budget_id');

        if ($choice === self::NEW_BUDGET_ITEM) {
            $request->merge(['budget_id' => null]);

            return $request->boolean('add_to_budget');
        }

        if ($choice === '') {
            $request->merge(['budget_id' => null]);
        }

        return false;
    }

    private function rulesFor(Request $request): array
    {
        $rules = $this->rules;
        $rules['add_to_budget'] = 'nullable|boolean';

        if (Schema::hasColumn('expenses', 'budget_id')) {
            // Only the user's own Budget items can be linked; another
            // user's id fails validation like a missing one.
            $rules['budget_id'] = [
                'nullable',
                'integer',
                Rule::exists('budgets', 'id')->where(
                    fn ($query) => $query->where('user_id', $request->user()->id)
                ),
            ];
        }

        return $rules;
    }

    /**
     * "+ Add new item" with "Also add to … budget" ticked: creates a
     * monthly Budget item for the expense's month with the expense's
     * category and amount, then links the expense to it.
     */
    private function addToMonthBudget(Expense $expense): void
    {
        if (! Schema::hasColumn('expenses', 'budget_id')) {
            return;
        }

        $month = Carbon::parse($expense->spent_at ?? now())->format('Y-m');

        $budget = Budget::create([
            'user_id' => $expense->user_id,
            'category' => $expense->category,
            'amount' => (float) $expense->amount,
            'period' => 'monthly',
            'month_year' => $month,
            'application_type' => 'expense',
            'debt_id' => null,
            'is_expensed' => false,
            'expensed_at' => null,
            'applied_amount' => 0,
        ]);

        $expense->forceFill(['budget_id' => $budget->id])->save();
    }

    /**
     * A Budget item already marked as paid keeps one automatic Expense
     * that tops it up to the full amount; recompute that top-up whenever
     * a manual Expense is linked to or unlinked from it, so the budget is
     * never double counted. The automatic Expense itself is left alone.
     */
    private function refreshBudgets(Expense $expense, array $budgetIds): void
    {
        $budgetIds = array_values(array_unique(array_filter($budgetIds)));

        if ($budgetIds === [] || $expense->payment_method === 'Budget') {
            return;
        }

        try {
            $service = app(BudgetExpenseService::class);

            Budget::query()
                ->where('user_id', $expense->user_id)
                ->whereIn('id', $budgetIds)
                ->get()
                ->each(function (Budget $budget) use ($service, $expense) {
                    if (! $budget->is_expensed
                        || ($budget->application_type ?: 'expense') !== 'expense'
                        || (int) ($budget->auto_expense_id ?? 0) === (int) $expense->id) {
                        return;
                    }

                    $service->refreshLinkedExpense($budget);
                });
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * The expense form's fields with the budget picker's options: the
     * user's expense-type Budget items for last month, this month and next
     * month (plus weekly/annual items and any ids in $alsoInclude), grouped
     * by budget, each with its planned and remaining amount. One query.
     */
    private function withBudgetOptions(Request $request, array $alsoInclude = []): array
    {
        $budgets = $this->budgetItemsFor($request, $alsoInclude);

        $groups = [];
        $options = [];
        foreach ($budgets as $budget) {
            $groupLabel = $this->budgetGroupLabel($budget);
            $planned = (float) $budget->amount;
            $remaining = (float) $budget->remaining_amount;

            $groups[$groupLabel][] = [
                'id' => $budget->id,
                'category' => (string) $budget->category,
                'planned' => $planned,
                'remaining' => $remaining,
                'label' => $budget->category . ' — ' . format_money($planned) . ' planned · '
                    . ($remaining >= 0 ? format_money($remaining) . ' left' : format_money(abs($remaining)) . ' over'),
            ];
            $options[$budget->id] = $budget->category . ' (' . $groupLabel . ')';
        }

        $currentMonthLabel = Carbon::now()->format('F Y');

        return $this->viewFields(array_map(function (array $field) use ($groups, $options, $currentMonthLabel) {
            if ($field['name'] === 'budget_id') {
                $field['groups'] = $groups;
                $field['options'] = $options;
                $field['current_month_label'] = $currentMonthLabel;
            }

            return $field;
        }, $this->fields));
    }

    private function budgetItemsFor(Request $request, array $alsoInclude): Collection
    {
        if (! Schema::hasTable('budgets') || ! Schema::hasColumn('expenses', 'budget_id')) {
            return collect();
        }

        try {
            $months = [
                now()->subMonthNoOverflow()->format('Y-m'),
                now()->format('Y-m'),
                now()->addMonthNoOverflow()->format('Y-m'),
            ];

            return Budget::query()
                ->where('user_id', $request->user()->id)
                ->when(Schema::hasColumn('budgets', 'is_archived'), fn ($q) => $q->where('is_archived', false))
                ->when(Schema::hasColumn('budgets', 'application_type'), fn ($q) => $q->where(
                    fn ($inner) => $inner->whereNull('application_type')->orWhere('application_type', 'expense')
                ))
                ->where(function ($q) use ($months, $alsoInclude) {
                    $q->where(fn ($monthly) => $monthly->where('period', 'monthly')->whereIn('month_year', $months))
                        ->orWhereIn('period', ['weekly', 'annually']);

                    if ($alsoInclude !== []) {
                        $q->orWhereIn('id', $alsoInclude);
                    }
                })
                ->withSpending()
                ->orderByDesc('month_year')
                ->orderBy('category')
                ->limit(200)
                ->get()
                ->each(fn (Budget $budget) => $budget->appendSpending())
                // This month first, then next month, last month, weekly/annual, older.
                ->sortBy(fn (Budget $budget) => match (true) {
                    $budget->period === 'monthly' && $budget->month_year === $months[1] => 0,
                    $budget->period === 'monthly' && $budget->month_year === $months[2] => 1,
                    $budget->period === 'monthly' && $budget->month_year === $months[0] => 2,
                    $budget->period !== 'monthly' => 3,
                    default => 4,
                })
                ->values();
        } catch (\Throwable $exception) {
            report($exception);

            return collect();
        }
    }

    private function budgetGroupLabel(Budget $budget): string
    {
        if ($budget->period === 'weekly') {
            return 'Weekly budget';
        }

        if ($budget->period === 'annually') {
            return 'Annual budget';
        }

        try {
            return Carbon::createFromFormat('Y-m', (string) $budget->month_year)->format('F Y') . ' budget';
        } catch (\Throwable) {
            return 'Budget';
        }
    }

    private function syncItems(Expense $expense, array $items): void
    {
        $expense->items()->delete();

        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];

            $expense->items()->create([
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $quantity * $unitPrice,
            ]);
        }
    }

    private function calculateItemsTotal(array $items): float
    {
        return (float) collect($items)->sum(
            static fn (array $item): float =>
                (float) $item['quantity'] * (float) $item['unit_price']
        );
    }

    private function extractReceipt(Request $request): JsonResponse
    {
        $request->validate([
            'receipt_file' => 'required|file|max:12288|mimes:pdf,jpg,jpeg,png,webp',
        ]);

        try {
            $data = app(ReceiptExtractionService::class)->extract(
                $request->user(),
                $request->file('receipt_file')
            );

            return response()->json([
                'message' => 'Receipt extracted. Review the details, then save the expense.',
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    protected function stats(Request $request): array
    {
        $userId = $request->user()->id;

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $yearStart = now()->startOfYear()->toDateString();
        $yearEnd = now()->endOfYear()->toDateString();

        $base = Expense::query()
            ->where('user_id', $userId);

        $thisMonth = (clone $base)
            ->whereDate('spent_at', '>=', $monthStart)
            ->whereDate('spent_at', '<=', $monthEnd)
            ->sum('amount');

        $thisYear = (clone $base)
            ->whereDate('spent_at', '>=', $yearStart)
            ->whereDate('spent_at', '<=', $yearEnd)
            ->sum('amount');

        $topCategory = (clone $base)
            ->whereDate('spent_at', '>=', $monthStart)
            ->whereDate('spent_at', '<=', $monthEnd)
            ->selectRaw('category, SUM(amount) AS total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->first();

        $monthBudget = $this->monthBudgetTotal($request);

        return [
            [
                'label' => 'This month',
                'value' => format_money($thisMonth),
                'icon' => 'fa-solid fa-receipt',
                'color' => 'rose',
            ],
            [
                'label' => 'Of this month\'s budget',
                'value' => $monthBudget > 0
                    ? round(((float) $thisMonth / $monthBudget) * 100) . '% of ' . format_money($monthBudget)
                    : 'No budget yet',
                'icon' => 'fa-solid fa-wallet',
                'color' => $monthBudget > 0 && (float) $thisMonth > $monthBudget ? 'amber' : 'emerald',
                'route' => route('budgets.index'),
            ],
            [
                'label' => 'Top category (this month)',
                'value' => $topCategory
                    ? $topCategory->category . ' (' . format_money($topCategory->total) . ')'
                    : '—',
                'icon' => 'fa-solid fa-chart-pie',
                'color' => 'orange',
            ],
            [
                'label' => 'This year',
                'value' => format_money($thisYear),
                'icon' => 'fa-solid fa-calendar-days',
                'color' => 'teal',
            ],
        ];
    }

    /** Planned total of this month's monthly expense Budget items (0 when none). */
    private function monthBudgetTotal(Request $request): float
    {
        try {
            if (! Schema::hasTable('budgets')) {
                return 0.0;
            }

            return (float) Budget::query()
                ->where('user_id', $request->user()->id)
                ->where('period', 'monthly')
                ->where('month_year', now()->format('Y-m'))
                ->when(Schema::hasColumn('budgets', 'is_archived'), fn ($q) => $q->where('is_archived', false))
                ->when(Schema::hasColumn('budgets', 'application_type'), fn ($q) => $q->where(
                    fn ($inner) => $inner->whereNull('application_type')->orWhere('application_type', 'expense')
                ))
                ->sum('amount');
        } catch (\Throwable $exception) {
            report($exception);

            return 0.0;
        }
    }

    protected function nudge(Request $request): ?string
    {
        $monthBudget = $this->monthBudgetTotal($request);
        $spent = (float) Expense::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('spent_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('amount');

        if ($monthBudget <= 0) {
            return $spent > 0
                ? 'Tip: set a monthly budget to see how your spending compares.'
                : 'No spending logged this month yet.';
        }

        $left = $monthBudget - $spent;

        return $left >= 0
            ? format_money($left) . ' left in this month\'s budget.'
            : 'You are ' . format_money(abs($left)) . ' over this month\'s budget.';
    }

    protected function chart(Request $request): ?array
    {
        $userId = $request->user()->id;

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $byCategory = Expense::query()
            ->where('user_id', $userId)
            ->whereDate('spent_at', '>=', $monthStart)
            ->whereDate('spent_at', '<=', $monthEnd)
            ->selectRaw('category, SUM(amount) AS total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->pluck('total', 'category');

        if ($byCategory->isEmpty()) {
            return null;
        }

        return [
            'type' => 'doughnut',
            'title' => 'Spending by Category (this month)',
            'labels' => $byCategory->keys()->all(),
            'datasets' => [
                [
                    'data' => $byCategory
                        ->values()
                        ->map(static fn ($value): float => (float) $value)
                        ->all(),
                ],
            ],
        ];
    }
}
