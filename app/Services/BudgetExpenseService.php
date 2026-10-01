<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Debt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BudgetExpenseService
{
    public function apply(
        Budget $budget,
        bool $paid,
        Carbon|string|null $paidAt = null
    ): Budget {
        $this->assertSchema();

        $date = $paidAt
            ? Carbon::parse($paidAt)->toDateString()
            : now()->toDateString();

        DB::transaction(function () use ($budget, $paid, $date) {
            $fresh = Budget::query()
                ->where('user_id', $budget->user_id)
                ->lockForUpdate()
                ->findOrFail($budget->id);

            if (! $paid) {
                $this->reverseCurrentApplication($fresh);

                $fresh->forceFill([
                    'is_expensed' => false,
                    'expensed_at' => null,
                    'applied_amount' => 0,
                ])->save();

                return;
            }

            if ((bool) $fresh->is_expensed) {
                $this->reverseCurrentApplication($fresh);
                $fresh->refresh();
            }

            if ($this->normaliseType($fresh->application_type) === 'debt_payment') {
                // A debt payment never owns an automatic Expense. Remove a
                // stale one (e.g. the type changed) but leave user-entered
                // partial Expenses linked to this Budget untouched.
                $this->deleteAutoExpense($fresh);
                $this->applyDebtPayment($fresh);
            } else {
                $applied = $this->createOrUpdateExpense($fresh, $date);

                $fresh->forceFill([
                    'application_type' => 'expense',
                    'debt_id' => null,
                    'applied_amount' => $applied,
                ]);
            }

            $fresh->forceFill([
                'is_expensed' => true,
                'expensed_at' => $date,
            ])->save();
        });

        return Budget::query()
            ->where('user_id', $budget->user_id)
            ->findOrFail($budget->id);
    }

    public function setExpenseStatus(
        Budget $budget,
        bool $isExpensed,
        Carbon|string|null $spentAt = null
    ): Budget {
        return $this->apply($budget, $isExpensed, $spentAt);
    }

    public function refreshLinkedExpense(Budget $budget): Budget
    {
        if (! (bool) $budget->is_expensed) {
            return $budget;
        }

        return $this->apply(
            $budget,
            true,
            $budget->expensed_at ?: now()
        );
    }

    public function detachExpense(Budget $budget): void
    {
        DB::transaction(function () use ($budget) {
            $fresh = Budget::query()
                ->where('user_id', $budget->user_id)
                ->lockForUpdate()
                ->find($budget->id);

            if (! $fresh) {
                return;
            }

            if ($this->normaliseType($fresh->application_type) === 'debt_payment') {
                $this->reverseDebtPayment($fresh);
            } else {
                DB::table('expenses')
                    ->where('user_id', $fresh->user_id)
                    ->where('budget_id', $fresh->id)
                    ->update([
                        'budget_id' => null,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    private function applyDebtPayment(Budget $budget): void
    {
        if (! $budget->debt_id) {
            throw ValidationException::withMessages([
                'debt_id' => 'Select the debt this Budget payment should reduce.',
            ]);
        }

        $debt = Debt::query()
            ->where('user_id', $budget->user_id)
            ->where('type', 'borrowed')
            ->lockForUpdate()
            ->find($budget->debt_id);

        if (! $debt) {
            throw ValidationException::withMessages([
                'debt_id' => 'The selected outstanding debt could not be found.',
            ]);
        }

        $outstanding = max(0, (float) $debt->amount);

        if ($outstanding <= 0 || $debt->status === 'paid') {
            throw ValidationException::withMessages([
                'debt_id' => 'The selected debt is already fully paid.',
            ]);
        }

        $payment = min((float) $budget->amount, $outstanding);

        if ($payment <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'The debt payment must be greater than zero.',
            ]);
        }

        $remaining = max(0, $outstanding - $payment);

        $debt->forceFill([
            'amount' => $remaining,
            'status' => $remaining <= 0 ? 'paid' : 'outstanding',
        ])->save();

        $budget->forceFill([
            'application_type' => 'debt_payment',
            'applied_amount' => $payment,
        ])->save();
    }

    private function reverseCurrentApplication(Budget $budget): void
    {
        if ($this->normaliseType($budget->application_type) === 'debt_payment') {
            $this->reverseDebtPayment($budget);
        }

        // Only the single automatic Expense this Budget owns is removed.
        // Partial Expenses the user entered against the Budget stay.
        $this->deleteAutoExpense($budget);
    }

    /**
     * The id of the automatic Expense created by the "mark as paid"
     * checkbox, or null. Prefers the explicit auto_expense_id; falls back
     * to the legacy marker (payment_method 'Budget' + automatic note) for
     * rows created before auto_expense_id existed.
     */
    private function autoExpenseId(Budget $budget): ?int
    {
        if ($this->tracksAutoExpense() && $budget->auto_expense_id) {
            $id = DB::table('expenses')
                ->where('id', $budget->auto_expense_id)
                ->where('user_id', $budget->user_id)
                ->where('budget_id', $budget->id)
                ->value('id');

            return $id ? (int) $id : null;
        }

        $id = DB::table('expenses')
            ->where('user_id', $budget->user_id)
            ->where('budget_id', $budget->id)
            ->where('payment_method', 'Budget')
            ->where('notes', 'like', 'Created automatically from the %')
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function deleteAutoExpense(Budget $budget): void
    {
        $id = $this->autoExpenseId($budget);

        if ($id) {
            DB::table('expense_items')->where('expense_id', $id)->delete();
            DB::table('expenses')->where('id', $id)->delete();
        }

        if ($this->tracksAutoExpense() && $budget->auto_expense_id) {
            $budget->forceFill(['auto_expense_id' => null])->save();
        }
    }

    private function tracksAutoExpense(): bool
    {
        static $tracks = null;

        return $tracks ??= Schema::hasColumn('budgets', 'auto_expense_id');
    }

    private function reverseDebtPayment(Budget $budget): void
    {
        $applied = max(0, (float) $budget->applied_amount);

        if ($applied <= 0 || ! $budget->debt_id) {
            return;
        }

        $debt = Debt::query()
            ->where('user_id', $budget->user_id)
            ->lockForUpdate()
            ->find($budget->debt_id);

        if (! $debt) {
            return;
        }

        $debt->forceFill([
            'amount' => (float) $debt->amount + $applied,
            'status' => 'outstanding',
        ])->save();
    }

    /**
     * Creates/updates the single automatic Expense for a Budget marked as
     * paid. It tops the Budget up to its full amount: partial Expenses the
     * user already linked are subtracted so the Budget is not double
     * counted. Returns the amount the automatic Expense applied (0 when
     * partial Expenses already cover the full Budget, in which case no
     * automatic Expense is kept).
     */
    private function createOrUpdateExpense(Budget $budget, string $date): float
    {
        $existingId = $this->autoExpenseId($budget);

        $partialSpent = (float) DB::table('expenses')
            ->where('user_id', $budget->user_id)
            ->where('budget_id', $budget->id)
            ->when($existingId, fn ($query) => $query->where('id', '!=', $existingId))
            ->sum('amount');

        $topUp = round(max(0, (float) $budget->amount - $partialSpent), 2);

        if ($topUp <= 0) {
            $this->deleteAutoExpense($budget);

            return 0.0;
        }

        $payload = [
            'user_id' => $budget->user_id,
            'budget_id' => $budget->id,
            'category' => (string) $budget->category,
            'amount' => $topUp,
            'spent_at' => $date,
            'payment_method' => 'Budget',
            'notes' => $this->expenseNote($budget),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('expenses', 'is_archived')) {
            $payload['is_archived'] = false;
        }

        if ($existingId) {
            DB::table('expenses')
                ->where('id', $existingId)
                ->update($payload);
        } else {
            $payload['created_at'] = now();
            $existingId = (int) DB::table('expenses')->insertGetId($payload);
        }

        if ($this->tracksAutoExpense()) {
            $budget->forceFill(['auto_expense_id' => $existingId])->save();
        }

        return $topUp;
    }

    private function expenseNote(Budget $budget): string
    {
        $month = $budget->month_year ?: now()->format('Y-m');
        $notes = trim((string) $budget->notes);
        $automatic = "Created automatically from the {$month} budget.";

        return $notes !== ''
            ? "{$automatic}\n{$notes}"
            : $automatic;
    }

    private function normaliseType(?string $value): string
    {
        return $value === 'debt_payment'
            ? 'debt_payment'
            : 'expense';
    }

    private function assertSchema(): void
    {
        $required = [
            'budgets' => [
                'id',
                'user_id',
                'amount',
                'is_expensed',
                'expensed_at',
                'application_type',
                'debt_id',
                'applied_amount',
            ],
            'expenses' => [
                'id',
                'user_id',
                'budget_id',
                'category',
                'amount',
                'spent_at',
            ],
            'debts' => [
                'id',
                'user_id',
                'type',
                'amount',
                'status',
            ],
        ];

        foreach ($required as $table => $columns) {
            if (! Schema::hasTable($table)) {
                throw ValidationException::withMessages([
                    'budget' => "Missing database table: {$table}.",
                ]);
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw ValidationException::withMessages([
                        'budget' => "Missing database column: {$table}.{$column}. Run the latest migrations.",
                    ]);
                }
            }
        }
    }
}
