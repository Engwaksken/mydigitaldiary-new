<?php

namespace App\Http\Controllers\Api;

use App\Models\Expense;
use App\Services\ExpenseBudgetLinkService;
use App\Services\ReceiptExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use App\Services\OfflineConflictGuard;

/**
 * Mobile equivalent of the web app's ExpenseController overrides —
 * same itemized-expense logic (multiple description/quantity/unit-
 * price rows, total computed from them) rather than the generic
 * single-model create/update ApiCrudController provides.
 */
class ExpenseController extends ApiCrudController
{
    protected string $model = Expense::class;

    protected array $rules = [
        'category' => 'required|string|max:255',
        'amount' => 'nullable|numeric|min:0',
        'spent_at' => 'required|date',
        'payment_method' => 'nullable|string|max:255',
        'notes' => 'nullable|string',
        'items' => 'nullable|array',
        'items.*.description' => 'required_with:items|string|max:255',
        'items.*.quantity' => 'required_with:items|numeric|min:0.01',
        'items.*.unit_price' => 'required_with:items|numeric',
    ];

    /**
     * Same as the base class, plus eager-loading items — needed so
     * editing an existing itemized expense can pre-populate its line
     * items, and so the list can show "3 items" instead of nothing.
     */
    public function index(Request $request): JsonResponse
    {
        $items = $this->filteredIndexQuery($request)
            ->with(Schema::hasColumn('expenses', 'budget_id')
                ? ['items', 'budget:id,category,month_year,period']
                : ['items'])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
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
            $data['amount'] = collect($items)->sum(fn ($i) => $i['quantity'] * $i['unit_price']);
        } elseif (! isset($data['amount'])) {
            return response()->json(['message' => 'Enter an amount, or add at least one line item.'], 422);
        }

        $expense = Expense::create($data);

        if (! empty($items)) {
            $this->syncItems($expense, $items);
        }

        if ($addNewToBudget && empty($expense->budget_id)) {
            $this->links()->addToMonthBudget($expense);
        }

        $this->links()->refreshBudgets($expense, [$expense->budget_id]);

        return response()->json($expense->fresh()->load('items'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $expense = Expense::where('user_id', $request->user()->id)->findOrFail($id);
        if ($conflict = OfflineConflictGuard::check($request, $expense)) return $conflict;

        $previousBudgetId = $expense->budget_id;
        $addNewToBudget = $this->normaliseBudgetChoice($request);
        $data = $request->validate($this->rulesFor($request));
        $items = $data['items'] ?? [];
        unset($data['items'], $data['add_to_budget']);

        if (! empty($items)) {
            $data['amount'] = collect($items)->sum(fn ($i) => $i['quantity'] * $i['unit_price']);
        } elseif (! isset($data['amount'])) {
            return response()->json(['message' => 'Enter an amount, or add at least one line item.'], 422);
        }

        $expense->update($data);

        // Same rule as web: only touches items if the request actually
        // submitted an `items` key at all — a plain edit that doesn't
        // include one (e.g. just changing notes) must NOT be treated as
        // "clear every existing item."
        if ($request->has('items')) {
            $this->syncItems($expense, $items);
        }

        if ($addNewToBudget && empty($expense->budget_id)) {
            $this->links()->addToMonthBudget($expense);
        }

        $this->links()->refreshBudgets($expense, [$previousBudgetId, $expense->budget_id]);

        return response()->json($expense->fresh()->load('items'));
    }

    /**
     * Budget items the Add Expense picker offers, grouped by budget, with
     * planned and remaining amounts — same items and labels as the web
     * form's picker. Pass ?include[]=<id> so an expense being edited keeps
     * its (possibly older) linked item in the list.
     */
    public function budgetOptions(Request $request): JsonResponse
    {
        $include = array_values(array_filter(
            array_map('intval', (array) $request->input('include', [])),
            fn (int $id) => $id > 0
        ));

        $groups = [];
        foreach ($this->links()->groupedOptions($request->user(), $include) as $label => $items) {
            $groups[] = ['label' => $label, 'items' => $items];
        }

        return response()->json([
            'data' => [
                'groups' => $groups,
                'current_month' => Carbon::now()->format('Y-m'),
                'current_month_label' => Carbon::now()->format('F Y'),
            ],
        ]);
    }

    /**
     * The compact summary strip and one-line nudge shown above the
     * expense list (same figures and copy as the web list page).
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $base = Expense::query()->where('user_id', $user->id);

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $thisMonth = (float) (clone $base)
            ->whereDate('spent_at', '>=', $monthStart)
            ->whereDate('spent_at', '<=', $monthEnd)
            ->sum('amount');

        $thisYear = (float) (clone $base)
            ->whereDate('spent_at', '>=', now()->startOfYear()->toDateString())
            ->whereDate('spent_at', '<=', now()->endOfYear()->toDateString())
            ->sum('amount');

        $topCategory = (clone $base)
            ->whereDate('spent_at', '>=', $monthStart)
            ->whereDate('spent_at', '<=', $monthEnd)
            ->selectRaw('category, SUM(amount) AS total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->first();

        $monthBudget = $this->links()->monthBudgetTotal($user);

        return response()->json([
            'data' => [
                'stats' => [
                    ['key' => 'month', 'label' => 'This month', 'value' => format_money($thisMonth)],
                    [
                        'key' => 'budget',
                        'label' => 'Of budget',
                        'value' => $monthBudget > 0
                            ? round(($thisMonth / $monthBudget) * 100).'% of '.format_money($monthBudget)
                            : 'No budget yet',
                        'tone' => $monthBudget > 0 && $thisMonth > $monthBudget ? 'warning' : 'success',
                    ],
                    ['key' => 'top', 'label' => 'Top category', 'value' => $topCategory?->category ?? '—'],
                    ['key' => 'year', 'label' => 'This year', 'value' => format_money($thisYear)],
                ],
                'nudge' => $this->links()->nudge($user),
            ],
        ]);
    }

    private function links(): ExpenseBudgetLinkService
    {
        return app(ExpenseBudgetLinkService::class);
    }

    /**
     * budget_id is a Budget id, null/'' (no link) or 'new' ("+ Add new
     * item", same as the web picker). 'new' means no link yet; returns
     * whether the user also asked to add the new item to that month's
     * budget (add_to_budget). Omitting budget_id leaves a link unchanged.
     */
    private function normaliseBudgetChoice(Request $request): bool
    {
        if (! $request->has('budget_id')) {
            return false;
        }

        $choice = $request->input('budget_id');

        if ($choice === ExpenseBudgetLinkService::NEW_BUDGET_ITEM) {
            $request->merge(['budget_id' => null]);

            return $request->boolean('add_to_budget');
        }

        if ($choice === '') {
            $request->merge(['budget_id' => null]);
        }

        return false;
    }

    /**
     * budget_id links an Expense to one of the user's own Budgets as
     * partial spending. Omit the key to leave an existing link unchanged;
     * send null to unlink. Another user's Budget id fails with 422.
     */
    private function rulesFor(Request $request): array
    {
        return [
            ...$this->rules,
            'add_to_budget' => 'nullable|boolean',
            'budget_id' => [
                'nullable',
                'integer',
                Rule::exists('budgets', 'id')->where(
                    fn ($query) => $query->where('user_id', $request->user()->id)
                ),
            ],
        ];
    }

    private function extractReceipt(Request $request): JsonResponse
    {
        $request->validate([
            'receipt_file' => 'required|file|max:12288|mimes:pdf,jpg,jpeg,png,webp',
        ]);

        try {
            $data = app(ReceiptExtractionService::class)->extract($request->user(), $request->file('receipt_file'));
            return response()->json(['message' => 'Receipt extracted. Review the details, then save the expense.', 'data' => $data]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function syncItems(Expense $expense, array $items): void
    {
        $expense->items()->delete();

        foreach ($items as $item) {
            $expense->items()->create([
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['quantity'] * $item['unit_price'],
            ]);
        }
    }
}
