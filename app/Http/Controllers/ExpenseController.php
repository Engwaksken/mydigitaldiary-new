<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Services\ReceiptExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends CrudController
{
    protected string $model = Expense::class;
    protected string $routeName = 'expenses';
    protected string $title = 'Expense';
    protected string $icon = 'fa-solid fa-receipt';
    protected string $accent = 'rose';
    protected string $dateField = 'spent_at';

    protected array $fields = [
        ['name' => 'category', 'label' => 'Category', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Groceries, Rent, Transport'],
        ['name' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => false, 'placeholder' => '0.00', 'money' => true, 'placeholder' => '0.00, or add line items'],
        ['name' => 'spent_at', 'label' => 'Date', 'type' => 'date', 'required' => true],
        ['name' => 'payment_method', 'label' => 'Payment Method', 'type' => 'text', 'placeholder' => 'e.g. Cash, Card, Mobile Money'],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
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

    public function store(Request $request)
    {
        if ($request->boolean('receipt_extract')) {
            return $this->extractReceipt($request);
        }

        $data = $request->validate($this->rules);
        $data['user_id'] = $request->user()->id;

        $items = $data['items'] ?? [];
        unset($data['items']);

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

        return redirect()
            ->route('expenses.index')
            ->with('success', 'Expense created.');
    }

    public function update(Request $request, int $id)
    {
        $expense = Expense::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $data = $request->validate($this->rules);

        $items = $data['items'] ?? [];
        unset($data['items']);

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

        return redirect()
            ->route('expenses.index')
            ->with('success', 'Expense updated.');
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

        return [
            [
                'label' => 'This month',
                'value' => format_money($thisMonth),
                'icon' => 'fa-solid fa-receipt',
                'color' => 'rose',
            ],
            [
                'label' => 'This year',
                'value' => format_money($thisYear),
                'icon' => 'fa-solid fa-calendar-days',
                'color' => 'teal',
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
                'label' => 'Entries',
                'value' => (string) $base->count(),
                'icon' => 'fa-solid fa-list-ol',
                'color' => 'slate',
            ],
        ];
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
