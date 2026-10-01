<?php

namespace App\Http\Controllers\Api;

use App\Models\Expense;
use App\Services\ReceiptExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            ->with('items')
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

        $data = $request->validate($this->rulesFor($request));
        $data['user_id'] = $request->user()->id;
        $items = $data['items'] ?? [];
        unset($data['items']);

        if (! empty($items)) {
            $data['amount'] = collect($items)->sum(fn ($i) => $i['quantity'] * $i['unit_price']);
        } elseif (! isset($data['amount'])) {
            return response()->json(['message' => 'Enter an amount, or add at least one line item.'], 422);
        }

        $expense = Expense::create($data);

        if (! empty($items)) {
            $this->syncItems($expense, $items);
        }

        return response()->json($expense->load('items'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $expense = Expense::where('user_id', $request->user()->id)->findOrFail($id);
        if ($conflict = OfflineConflictGuard::check($request, $expense)) return $conflict;

        $data = $request->validate($this->rulesFor($request));
        $items = $data['items'] ?? [];
        unset($data['items']);

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

        return response()->json($expense->fresh()->load('items'));
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
