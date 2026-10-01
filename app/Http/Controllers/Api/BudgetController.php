<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Debt;
use App\Services\BudgetExpenseService;
use App\Services\BudgetImportService;
use App\Services\DailyInsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BudgetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $month = (string) $request->query(
            'month',
            now()->format('Y-m')
        );

        $items = Budget::query()
            ->withSpending()
            ->where('user_id', $request->user()->id)
            ->where('is_archived', false)
            ->when(
                preg_match('/^\d{4}-\d{2}$/', $month),
                fn ($query) => $query->where(
                    'month_year',
                    $month
                )
            )
            ->orderByDesc('id')
            ->paginate(50)
            ->through(fn (Budget $budget) => $budget->appendSpending());

        return response()->json($items);
    }

    public function debts(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Debt::query()
                ->where('user_id', $request->user()->id)
                ->where('type', 'borrowed')
                ->where('status', 'outstanding')
                ->where('is_archived', false)
                ->orderBy('person_name')
                ->get([
                    'id',
                    'person_name',
                    'amount',
                    'due_date',
                ]),
        ]);
    }

    public function store(
        Request $request,
        BudgetExpenseService $flow
    ): JsonResponse {
        $data = $this->validated($request);

        $budget = Budget::create([
            'user_id' => $request->user()->id,
            ...$this->payload($data),
            'is_expensed' => false,
            'expensed_at' => null,
            'applied_amount' => 0,
        ]);

        if ($data['is_expensed']) {
            $budget = $flow->apply($budget, true);
        }

        $this->invalidate($request);

        return response()->json($budget->appendSpending(), 201);
    }

    public function show(
        Request $request,
        int $id
    ): JsonResponse {
        return response()->json(
            $this->owned($request, $id)->appendSpending()
        );
    }

    public function update(
        Request $request,
        int $id,
        BudgetExpenseService $flow
    ): JsonResponse {
        $budget = $this->owned($request, $id);

        if ($budget->is_expensed) {
            $flow->apply($budget, false);
            $budget->refresh();
        }

        $data = $this->validated($request);

        $budget->update([
            ...$this->payload($data),
            'is_expensed' => false,
            'expensed_at' => null,
            'applied_amount' => 0,
        ]);

        if ($data['is_expensed']) {
            $budget = $flow->apply(
                $budget->fresh(),
                true
            );
        }

        $this->invalidate($request);

        return response()->json($budget->appendSpending());
    }

    public function destroy(
        Request $request,
        int $id,
        BudgetExpenseService $flow
    ): JsonResponse {
        $budget = $this->owned($request, $id);

        $flow->detachExpense($budget);
        $budget->delete();

        $this->invalidate($request);

        return response()->json([
            'message' => 'Deleted.',
        ]);
    }

    public function setExpenseStatus(
        Request $request,
        int $id,
        BudgetExpenseService $flow
    ): JsonResponse {
        $data = $request->validate([
            'is_expensed' => ['required', 'boolean'],
            'spent_at' => ['nullable', 'date'],
        ]);

        $budget = $flow->apply(
            $this->owned($request, $id),
            (bool) $data['is_expensed'],
            $data['spent_at'] ?? null
        );

        $this->invalidate($request);

        return response()->json([
            'success' => true,
            'message' => ! $budget->is_expensed
                ? 'Budget payment reversed.'
                : (
                    $budget->application_type === 'debt_payment'
                        ? 'Debt reduced and Income balance updated.'
                        : 'Added to Expenses and Income balance updated.'
                ),
            'data' => $budget->appendSpending(),
        ]);
    }

    public function duplicateMonth(
        Request $request
    ): JsonResponse {
        $data = $request->validate([
            'source_month' => [
                'required',
                'regex:/^\d{4}-\d{2}$/',
            ],
            'target_month' => [
                'required',
                'regex:/^\d{4}-\d{2}$/',
            ],
        ]);

        if ($data['source_month'] === $data['target_month']) {
            throw ValidationException::withMessages([
                'target_month' => 'Choose a different target month.',
            ]);
        }

        $source = Budget::query()
            ->where('user_id', $request->user()->id)
            ->where('period', 'monthly')
            ->where('month_year', $data['source_month'])
            ->where('is_archived', false)
            ->get();

        if ($source->isEmpty()) {
            throw ValidationException::withMessages([
                'source_month' => 'No Budget items were found in the source month.',
            ]);
        }

        $targetExists = Budget::query()
            ->where('user_id', $request->user()->id)
            ->where('period', 'monthly')
            ->where('month_year', $data['target_month'])
            ->where('is_archived', false)
            ->exists();

        if ($targetExists) {
            throw ValidationException::withMessages([
                'target_month' => 'The target month already has Budget items.',
            ]);
        }

        $created = [];

        foreach ($source as $item) {
            $created[] = Budget::create([
                'user_id' => $request->user()->id,
                'source_budget_id' => $item->id,
                'category' => $item->category,
                'amount' => $item->amount,
                'period' => 'monthly',
                'month_year' => $data['target_month'],
                'notes' => $item->notes,
                'application_type' => $item->application_type ?: 'expense',
                'debt_id' => $item->debt_id,
                'is_expensed' => false,
                'expensed_at' => null,
                'applied_amount' => 0,
                'is_archived' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $created,
        ], 201);
    }

    public function extractImport(
        Request $request,
        BudgetImportService $service
    ): JsonResponse {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:20480',
                'mimes:xlsx,xls,csv,pdf,doc,docx,jpg,jpeg,png,webp',
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $service->extract(
                $request->user(),
                $request->file('file')
            ),
        ]);
    }

    public function confirmImport(
        Request $request
    ): JsonResponse {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.category' => ['required', 'string', 'max:255'],
            'items.*.planned_amount' => ['required', 'numeric', 'min:0'],
            'items.*.period' => ['nullable', 'in:weekly,monthly,annually'],
            'items.*.month_year' => ['nullable', 'string', 'max:20'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.notes' => ['nullable', 'string'],
            'filename' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:30'],
            'confidence' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $created = [];

        foreach ($data['items'] as $item) {
            $created[] = Budget::create([
                'user_id' => $request->user()->id,
                'category' => $item['category'],
                'amount' => $item['planned_amount'],
                'period' => $item['period'] ?? 'monthly',
                'month_year' => $item['month_year'] ?? null,
                'notes' => trim(
                    ($item['description'] ?? '') .
                    (
                        ($item['notes'] ?? '')
                            ? "\n".$item['notes']
                            : ''
                    )
                ) ?: null,
                'application_type' => 'expense',
                'debt_id' => null,
                'is_expensed' => false,
                'applied_amount' => 0,
                'is_archived' => false,
                'import_source' => $data['source'] ?? null,
                'import_filename' => $data['filename'] ?? null,
                'import_confidence' => $data['confidence'] ?? null,
                'import_metadata' => [
                    'source_item' => $item,
                ],
            ]);
        }

        $this->invalidate($request);

        return response()->json([
            'success' => true,
            'data' => $created,
        ], 201);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'period' => [
                'required',
                Rule::in([
                    'weekly',
                    'monthly',
                    'annually',
                ]),
            ],
            'month_year' => [
                'required',
                'regex:/^\d{4}-\d{2}$/',
            ],
            'notes' => ['nullable', 'string'],
            'application_type' => [
                'nullable',
                Rule::in([
                    'expense',
                    'debt_payment',
                ]),
            ],
            'debt_id' => [
                'nullable',
                'integer',
                Rule::exists('debts', 'id')->where(
                    fn ($query) => $query
                        ->where('user_id', $request->user()->id)
                        ->where('type', 'borrowed')
                ),
            ],
            'is_expensed' => ['nullable', 'boolean'],
        ]);

        $data['application_type'] = $data['application_type'] ?? 'expense';

        $data['is_expensed'] =
            $request->boolean('is_expensed');

        if (
            $data['application_type'] === 'debt_payment' &&
            $data['is_expensed'] &&
            empty($data['debt_id'])
        ) {
            throw ValidationException::withMessages([
                'debt_id' => 'Select which debt this payment should reduce.',
            ]);
        }

        return $data;
    }

    private function payload(array $data): array
    {
        return [
            'category' => $data['category'],
            'amount' => $data['amount'],
            'period' => $data['period'],
            'month_year' => $data['month_year'],
            'notes' => $data['notes'] ?? null,
            'application_type' => $data['application_type'],
            'debt_id' => $data['application_type'] === 'debt_payment'
                ? ($data['debt_id'] ?? null)
                : null,
        ];
    }

    private function owned(
        Request $request,
        int $id
    ): Budget {
        return Budget::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }

    private function invalidate(Request $request): void
    {
        try {
            app(DailyInsightService::class)
                ->invalidateFor($request->user());
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
