<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Linking an Expense to one of the user's Budget items — shared by the web
 * ExpenseController (budget picker on the Add Expense form) and the mobile
 * Api\ExpenseController, so both offer the same items, labels and
 * "Also add to <Month Year> budget" behaviour.
 */
class ExpenseBudgetLinkService
{
    /** Value of the budget picker's "+ Add new item" option. */
    public const NEW_BUDGET_ITEM = 'new';

    public function supported(): bool
    {
        return Schema::hasTable('budgets') && Schema::hasColumn('expenses', 'budget_id');
    }

    /**
     * The user's expense-type Budget items for last month, this month and
     * next month (plus weekly/annual items and any ids in $alsoInclude),
     * each with spent/remaining amounts. This month first. One query.
     */
    public function budgetItemsFor(User $user, array $alsoInclude = []): Collection
    {
        if (! $this->supported()) {
            return collect();
        }

        try {
            $months = [
                now()->subMonthNoOverflow()->format('Y-m'),
                now()->format('Y-m'),
                now()->addMonthNoOverflow()->format('Y-m'),
            ];

            return Budget::query()
                ->where('user_id', $user->id)
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

    public function groupLabel(Budget $budget): string
    {
        if ($budget->period === 'weekly') {
            return 'Weekly budget';
        }

        if ($budget->period === 'annually') {
            return 'Annual budget';
        }

        try {
            return Carbon::createFromFormat('Y-m', (string) $budget->month_year)->format('F Y').' budget';
        } catch (\Throwable) {
            return 'Budget';
        }
    }

    /** "Groceries — UGX 1,000 planned · UGX 700 left" */
    public function optionLabel(Budget $budget): string
    {
        $planned = (float) $budget->amount;
        $remaining = (float) $budget->remaining_amount;

        return $budget->category.' — '.format_money($planned).' planned · '
            .($remaining >= 0 ? format_money($remaining).' left' : format_money(abs($remaining)).' over');
    }

    /**
     * Picker options grouped by budget, in display order.
     *
     * @return array<string, list<array{id:int,category:string,planned:float,remaining:float,label:string}>>
     */
    public function groupedOptions(User $user, array $alsoInclude = []): array
    {
        $groups = [];

        foreach ($this->budgetItemsFor($user, $alsoInclude) as $budget) {
            $groups[$this->groupLabel($budget)][] = [
                'id' => (int) $budget->id,
                'category' => (string) $budget->category,
                'planned' => (float) $budget->amount,
                'remaining' => (float) $budget->remaining_amount,
                'label' => $this->optionLabel($budget),
            ];
        }

        return $groups;
    }

    /**
     * "+ Add new item" with "Also add to … budget" ticked: creates a
     * monthly Budget item for the expense's month with the expense's
     * category and amount, then links the expense to it.
     */
    public function addToMonthBudget(Expense $expense): void
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
    public function refreshBudgets(Expense $expense, array $budgetIds): void
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

    /** Planned total of this month's monthly expense Budget items (0 when none). */
    public function monthBudgetTotal(User $user): float
    {
        try {
            if (! Schema::hasTable('budgets')) {
                return 0.0;
            }

            return (float) Budget::query()
                ->where('user_id', $user->id)
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

    /** One-line nudge for the expense list (same copy as the web list). */
    public function nudge(User $user): string
    {
        $monthBudget = $this->monthBudgetTotal($user);
        $spent = (float) Expense::query()
            ->where('user_id', $user->id)
            ->whereBetween('spent_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('amount');

        if ($monthBudget <= 0) {
            return $spent > 0
                ? 'Tip: set a monthly budget to see how your spending compares.'
                : 'No spending logged this month yet.';
        }

        $left = $monthBudget - $spent;

        return $left >= 0
            ? format_money($left).' left in this month\'s budget.'
            : 'You are '.format_money(abs($left)).' over this month\'s budget.';
    }
}
