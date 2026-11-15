<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\DailyPlan;
use App\Models\Debt;
use App\Models\ExerciseLog;
use App\Models\Expense;
use App\Models\Income;
use App\Models\ProjectTask;
use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Support\Carbon;

class PersonalProgressService
{
    public function summary(User $user): array
    {
        $userId = (int) $user->id;
        $timezone = $user->timezone ?: 'Africa/Kampala';
        $localNow = Carbon::now($timezone);

        // Financial Health should not look "empty" simply because the user has
        // not entered a finance record in the current calendar month. Prefer the
        // current month when it has activity; otherwise summarise the most recent
        // month that actually contains an income, expense or savings contribution.
        $financeMonth = $this->resolveFinanceMonth($userId, $localNow, $timezone);
        $monthStart = $financeMonth->copy()->startOfMonth();
        $monthEnd = $financeMonth->copy()->endOfMonth();
        $monthStartDate = $monthStart->toDateString();
        $monthEndDate = $monthEnd->toDateString();

        $previousStart = $financeMonth->copy()->subMonthNoOverflow()->startOfMonth();
        $previousEnd = $previousStart->copy()->endOfMonth();

        $income = (float) Income::where('user_id', $userId)
            ->whereBetween('received_at', [$monthStartDate, $monthEndDate])
            ->sum('amount');

        $expenses = (float) Expense::where('user_id', $userId)
            ->whereBetween('spent_at', [$monthStartDate, $monthEndDate])
            ->sum('amount');

        $saved = (float) SavingsContribution::where('user_id', $userId)
            ->whereBetween('contributed_at', [$monthStartDate, $monthEndDate])
            ->sum('amount');

        $budget = (float) Budget::where('user_id', $userId)
            ->where('period', 'monthly')
            ->sum('amount');

        $outstandingDebt = (float) Debt::where('user_id', $userId)
            ->where('status', 'outstanding')
            ->sum('amount');

        $outstandingGoals = (float) SavingsGoal::where('user_id', $userId)
            ->sum('target_amount');

        $previousExpenses = (float) Expense::where('user_id', $userId)
            ->whereBetween('spent_at', [$previousStart->toDateString(), $previousEnd->toDateString()])
            ->sum('amount');

        $previousSavings = (float) SavingsContribution::where('user_id', $userId)
            ->whereBetween('contributed_at', [$previousStart->toDateString(), $previousEnd->toDateString()])
            ->sum('amount');

        $savingsRate = $income > 0 ? ($saved / $income) * 100 : 0;
        $expenseRate = $income > 0 ? ($expenses / $income) * 100 : 0;
        $budgetVariance = $budget > 0 ? (($budget - $expenses) / $budget) * 100 : null;
        $debtToIncome = $income > 0 ? ($outstandingDebt / $income) * 100 : null;
        $expenseChange = $previousExpenses > 0 ? (($expenses - $previousExpenses) / $previousExpenses) * 100 : null;
        $savingsChange = $previousSavings > 0 ? (($saved - $previousSavings) / $previousSavings) * 100 : null;

        $hasFinanceData = $income > 0 || $expenses > 0 || $saved > 0;
        $score = $hasFinanceData ? 40 : 0;
        $reasons = [];
        $actions = [];

        if ($income > 0) {
            $score += $savingsRate >= 20 ? 22 : ($savingsRate >= 10 ? 14 : ($savingsRate > 0 ? 7 : 0));
            $score += $expenseRate <= 70 ? 20 : ($expenseRate <= 90 ? 10 : ($expenseRate <= 100 ? 2 : -12));
            $reasons[] = 'Savings rate: '.number_format($savingsRate, 0).'%';
            $reasons[] = 'Expense-to-income: '.number_format($expenseRate, 0).'%';
            if ($savingsRate < 10) {
                $actions[] = 'Aim to move at least 10% of income towards savings when possible.';
            }
            if ($expenseRate > 90) {
                $actions[] = 'Review your largest expense categories and look for one realistic reduction.';
            }
        } elseif ($hasFinanceData) {
            $reasons[] = 'Add income records for this period to make the financial score more meaningful.';
            $actions[] = 'Record income for this period so spending and savings can be compared with earnings.';
        } else {
            $reasons[] = 'Add your first income, expense or savings record to start Financial Health.';
            $actions[] = 'Record a financial activity to start building your Financial Health score.';
        }

        if ($budget > 0 && $hasFinanceData) {
            $score += $expenses <= $budget ? 12 : -10;
            $reasons[] = $expenses <= $budget
                ? 'Spending is within your monthly budget.'
                : 'Spending is above your monthly budget.';
            if ($expenses > $budget) {
                $actions[] = 'Adjust either the budget or spending plan before the month ends.';
            }
        }

        if ($debtToIncome !== null && $hasFinanceData) {
            $score += $debtToIncome <= 30 ? 6 : ($debtToIncome <= 60 ? 0 : -8);
            if ($debtToIncome > 60) {
                $actions[] = 'Prioritise a manageable debt-reduction plan alongside essential expenses.';
            }
        }

        if ($expenseChange !== null && $expenseChange < -5) {
            $score += 4;
        }
        if ($savingsChange !== null && $savingsChange > 10) {
            $score += 4;
        }
        $score = max(0, min(100, (int) round($score)));

        // Weekly review uses the current local week when it has real activity.
        // If the current week is completely empty, show the most recent week
        // with activity rather than a misleading all-zero review.
        [$weekStart, $weekEnd] = $this->resolveReviewWeek($userId, $localNow, $timezone);
        $weekStartDate = $weekStart->toDateString();
        $weekEndDate = $weekEnd->toDateString();
        $weekStartUtc = $weekStart->copy()->startOfDay()->utc();
        $weekEndUtc = $weekEnd->copy()->endOfDay()->utc();

        $dailyPlans = DailyPlan::where('user_id', $userId)
            ->whereBetween('plan_date', [$weekStartDate, $weekEndDate])
            ->withCount([
                'items as completed_items_count' => fn ($q) => $q->where('is_completed', true),
                'items as total_items_count',
            ])->get();

        $dailyCompleted = (int) $dailyPlans->sum('completed_items_count');
        $dailyTotal = (int) $dailyPlans->sum('total_items_count');

        $projectCompleted = ProjectTask::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('updated_at', [$weekStartUtc, $weekEndUtc])
            ->count();

        $projectTotal = ProjectTask::where('user_id', $userId)
            ->whereBetween('due_date', [$weekStartDate, $weekEndDate])
            ->count();

        $completedTasks = $dailyCompleted + $projectCompleted;
        $totalTasks = $dailyTotal + $projectTotal;

        $exerciseSessions = ExerciseLog::where('user_id', $userId)
            ->whereBetween('performed_at', [$weekStartUtc, $weekEndUtc])
            ->count();

        $weeklyIncome = (float) Income::where('user_id', $userId)
            ->whereBetween('received_at', [$weekStartDate, $weekEndDate])
            ->sum('amount');

        $weeklyExpenses = (float) Expense::where('user_id', $userId)
            ->whereBetween('spent_at', [$weekStartDate, $weekEndDate])
            ->sum('amount');

        $weeklySaved = (float) SavingsContribution::where('user_id', $userId)
            ->whereBetween('contributed_at', [$weekStartDate, $weekEndDate])
            ->sum('amount');

        return [
            'financial_health' => [
                'score' => $score,
                'label' => ! $hasFinanceData
                    ? 'Getting started'
                    : ($score >= 80 ? 'Strong' : ($score >= 60 ? 'Good' : ($score >= 40 ? 'Needs attention' : 'At risk'))),
                'period' => $financeMonth->format('F Y'),
                'is_current_period' => $financeMonth->format('Y-m') === $localNow->format('Y-m'),
                'monthly_income' => $income,
                'monthly_expenses' => $expenses,
                'monthly_savings' => $saved,
                'monthly_budget' => $budget,
                'outstanding_debt' => $outstandingDebt,
                'savings_goal_target' => $outstandingGoals,
                'savings_rate' => round($savingsRate, 1),
                'expense_to_income' => round($expenseRate, 1),
                'budget_variance' => is_null($budgetVariance) ? null : round($budgetVariance, 1),
                'debt_to_income' => is_null($debtToIncome) ? null : round($debtToIncome, 1),
                'expense_change_percent' => is_null($expenseChange) ? null : round($expenseChange, 1),
                'savings_change_percent' => is_null($savingsChange) ? null : round($savingsChange, 1),
                'reasons' => array_slice($reasons, 0, 4),
                'recommended_actions' => array_slice($actions, 0, 3),
            ],
            'weekly_review' => [
                'period' => $weekStart->format('d M').' - '.$weekEnd->format('d M'),
                'is_current_period' => $weekStart->isSameDay($localNow->copy()->startOfWeek()),
                'completed_tasks' => $completedTasks,
                'total_tasks' => $totalTasks,
                'completion_percent' => $totalTasks > 0
                    ? min(100, (int) round(($completedTasks / $totalTasks) * 100))
                    : 0,
                'income' => $weeklyIncome,
                'expenses' => $weeklyExpenses,
                'saved' => $weeklySaved,
                'exercise_sessions' => $exerciseSessions,
            ],
        ];
    }

    private function resolveFinanceMonth(int $userId, Carbon $localNow, string $timezone): Carbon
    {
        $currentStart = $localNow->copy()->startOfMonth()->toDateString();
        $currentEnd = $localNow->copy()->endOfMonth()->toDateString();

        $hasCurrent = Income::where('user_id', $userId)->whereBetween('received_at', [$currentStart, $currentEnd])->exists()
            || Expense::where('user_id', $userId)->whereBetween('spent_at', [$currentStart, $currentEnd])->exists()
            || SavingsContribution::where('user_id', $userId)->whereBetween('contributed_at', [$currentStart, $currentEnd])->exists();

        if ($hasCurrent) {
            return $localNow->copy()->startOfMonth();
        }

        $latestDates = [
            Income::where('user_id', $userId)->max('received_at'),
            Expense::where('user_id', $userId)->max('spent_at'),
            SavingsContribution::where('user_id', $userId)->max('contributed_at'),
        ];

        $latest = collect($latestDates)
            ->filter()
            ->map(fn ($date) => Carbon::parse($date, $timezone))
            ->sortByDesc(fn (Carbon $date) => $date->timestamp)
            ->first();

        return $latest ? $latest->copy()->startOfMonth() : $localNow->copy()->startOfMonth();
    }

    private function resolveReviewWeek(int $userId, Carbon $localNow, string $timezone): array
    {
        $currentStart = $localNow->copy()->startOfWeek();
        $currentEnd = $localNow->copy()->endOfWeek();

        if ($this->weekHasActivity($userId, $currentStart, $currentEnd)) {
            return [$currentStart, $currentEnd];
        }

        // Look back up to 8 weeks for the most recent meaningful review period.
        for ($weeksAgo = 1; $weeksAgo <= 8; $weeksAgo++) {
            $start = $localNow->copy()->subWeeks($weeksAgo)->startOfWeek();
            $end = $start->copy()->endOfWeek();
            if ($this->weekHasActivity($userId, $start, $end)) {
                return [$start, $end];
            }
        }

        return [$currentStart, $currentEnd];
    }

    private function weekHasActivity(int $userId, Carbon $start, Carbon $end): bool
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $startUtc = $start->copy()->startOfDay()->utc();
        $endUtc = $end->copy()->endOfDay()->utc();

        return DailyPlan::where('user_id', $userId)->whereBetween('plan_date', [$startDate, $endDate])->exists()
            || ProjectTask::where('user_id', $userId)->whereBetween('due_date', [$startDate, $endDate])->exists()
            || ProjectTask::where('user_id', $userId)->where('status', 'completed')->whereBetween('updated_at', [$startUtc, $endUtc])->exists()
            || Income::where('user_id', $userId)->whereBetween('received_at', [$startDate, $endDate])->exists()
            || Expense::where('user_id', $userId)->whereBetween('spent_at', [$startDate, $endDate])->exists()
            || SavingsContribution::where('user_id', $userId)->whereBetween('contributed_at', [$startDate, $endDate])->exists()
            || ExerciseLog::where('user_id', $userId)->whereBetween('performed_at', [$startUtc, $endUtc])->exists();
    }
}
