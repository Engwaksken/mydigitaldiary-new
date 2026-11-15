<?php

namespace App\Services;

use App\Models\AiPlan;
use App\Models\DailyPlan;
use App\Models\Expense;
use App\Models\ExerciseLog;
use App\Models\Income;
use App\Models\Meeting;
use App\Models\Note;
use App\Models\Plan;
use App\Models\ProjectTask;
use App\Models\SavingsContribution;
use App\Models\SpiritualPractice;
use App\Models\User;
use Illuminate\Support\Carbon;

class MonthlyReviewService
{
    public function build(User $user, ?Carbon $month = null): array
    {
        $timezone = $user->timezone ?: 'Africa/Kampala';
        $month = ($month ?: Carbon::now($timezone))->copy()->timezone($timezone)->startOfMonth();
        $current = $this->monthStats($user, $month);
        $previous = $this->monthStats($user, $month->copy()->subMonthNoOverflow());

        $expenseChange = $this->percentChange($current['expenses'], $previous['expenses']);
        $savingChange = $this->percentChange($current['saved'], $previous['saved']);
        $incomeChange = $this->percentChange($current['income'], $previous['income']);
        $taskChange = $current['completion'] - $previous['completion'];
        $exerciseChange = $current['exercise'] - $previous['exercise'];

        $wins = [];
        $focus = [];
        if ($current['completion'] >= 75) {
            $wins[] = "You completed {$current['completion']}% of the tasks you planned.";
        } elseif ($current['total_tasks'] > 0) {
            $focus[] = "Task completion was {$current['completion']}%; choose fewer, clearer priorities next month.";
        }
        if ($current['saved'] > 0) {
            $wins[] = 'You saved '.number_format($current['saved'], 0).' this month.';
        } elseif ($current['income'] > 0) {
            $focus[] = 'No savings were recorded this month; consider setting a small automatic target.';
        }
        if ($expenseChange !== null && $expenseChange < 0) {
            $wins[] = 'Spending fell '.abs($expenseChange).'% compared with last month.';
        }
        if ($expenseChange !== null && $expenseChange > 10) {
            $focus[] = 'Spending increased '.$expenseChange.'% compared with last month; review your largest categories.';
        }
        if ($current['exercise'] >= 8) {
            $wins[] = "You recorded {$current['exercise']} exercise sessions.";
        } elseif ($current['exercise'] < 4) {
            $focus[] = 'Consider scheduling a few short movement sessions next month.';
        }
        if ($current['spiritual'] > 0) {
            $wins[] = "You recorded {$current['spiritual']} spiritual growth entr".($current['spiritual'] === 1 ? 'y' : 'ies').'.';
        }

        if (! $wins) {
            $wins[] = 'You kept building your personal record this month - that history makes future planning more useful.';
        }
        if (! $focus) {
            $focus[] = 'Keep the habits that worked and choose one meaningful improvement for next month.';
        }

        $momentumScore = $this->momentumScore($current);
        $previousMomentum = $this->momentumScore($previous);
        $momentumDelta = $momentumScore - $previousMomentum;

        $nextActions = $focus;
        if ($current['income'] > 0 && $current['savings_rate'] < 10) {
            $nextActions[] = 'Choose a realistic savings amount for next month and schedule it early in the month.';
        }
        if ($current['completion'] < 70 && $current['total_tasks'] > 0) {
            $nextActions[] = 'Start next month with only three high-priority tasks for the first week.';
        }
        if ($current['meetings'] > 8 && $current['completion'] < 70) {
            $nextActions[] = 'Protect one or two meeting-free focus blocks each week so important tasks still move forward.';
        }

        return [
            'month' => $month->format('F Y'),
            'month_key' => $month->format('Y-m'),
            'period' => $current['start']->format('d M').' - '.$current['end']->format('d M Y'),
            'money' => [
                'income' => $current['income'],
                'expenses' => $current['expenses'],
                'saved' => $current['saved'],
                'net' => $current['income'] - $current['expenses'],
                'savings_rate' => $current['savings_rate'],
                'expense_change_percent' => $expenseChange,
                'saving_change_percent' => $savingChange,
                'income_change_percent' => $incomeChange,
            ],
            'productivity' => [
                'completed_tasks' => $current['completed_tasks'],
                'total_tasks' => $current['total_tasks'],
                'completion_percent' => $current['completion'],
                'completion_change_points' => $taskChange,
                'plans_completed' => $current['annual_plans_completed'],
                'meetings' => $current['meetings'],
            ],
            'wellbeing' => [
                'exercise_sessions' => $current['exercise'],
                'exercise_change' => $exerciseChange,
                'spiritual_entries' => $current['spiritual'],
            ],
            'comparison' => [
                'previous_month' => $previous['month']->format('F Y'),
                'income_change_percent' => $incomeChange,
                'expense_change_percent' => $expenseChange,
                'saving_change_percent' => $savingChange,
                'task_completion_change_points' => $taskChange,
                'exercise_change' => $exerciseChange,
            ],
            'momentum' => [
                'score' => $momentumScore,
                'previous_score' => $previousMomentum,
                'change' => $momentumDelta,
                'label' => $momentumScore >= 80 ? 'Excellent momentum' : ($momentumScore >= 65 ? 'Moving well' : ($momentumScore >= 45 ? 'Building momentum' : 'Needs a reset')),
                'message' => $momentumDelta > 4
                    ? 'You improved compared with last month. Keep the habits that produced this progress.'
                    : ($momentumDelta < -4
                        ? 'This month was more difficult than the previous one. Choose one or two areas to reset rather than trying to fix everything at once.'
                        : 'Your overall momentum is fairly steady. A small improvement in one area can move the whole month forward.'),
            ],
            'value' => [
                'ai_plans' => $current['ai_plans'],
                'meetings' => $current['meetings'],
                'notes_created' => $current['notes_created'],
                'tasks_completed' => $current['completed_tasks'],
                'financial_records' => $current['financial_records'],
            ],
            'wins' => array_slice(array_values(array_unique($wins)), 0, 4),
            'focus_next_month' => array_slice(array_values(array_unique($focus)), 0, 4),
            'next_actions' => array_slice(array_values(array_unique($nextActions)), 0, 3),
        ];
    }

    private function monthStats(User $user, Carbon $month): array
    {
        $month = $month->copy()->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $income = (float) Income::where('user_id', $user->id)->whereBetween('received_at', [$start, $end])->sum('amount');
        $expenses = (float) Expense::where('user_id', $user->id)->whereBetween('spent_at', [$start, $end])->sum('amount');
        $saved = (float) SavingsContribution::where('user_id', $user->id)->whereBetween('contributed_at', [$start, $end])->sum('amount');

        $dailyPlans = DailyPlan::where('user_id', $user->id)
            ->whereBetween('plan_date', [$start->toDateString(), $end->toDateString()])
            ->withCount([
                'items as completed_items_count' => fn ($q) => $q->where('is_completed', true),
                'items as total_items_count',
            ])->get();

        $dailyCompleted = (int) $dailyPlans->sum('completed_items_count');
        $dailyTotal = (int) $dailyPlans->sum('total_items_count');
        $projectCompleted = ProjectTask::where('user_id', $user->id)->where('status', 'completed')->whereBetween('updated_at', [$start, $end])->count();
        $projectTotal = ProjectTask::where('user_id', $user->id)->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])->count();
        $completedTasks = $dailyCompleted + $projectCompleted;
        $totalTasks = $dailyTotal + $projectTotal;

        return [
            'month' => $month,
            'start' => $start,
            'end' => $end,
            'income' => $income,
            'expenses' => $expenses,
            'saved' => $saved,
            'savings_rate' => $income > 0 ? round(($saved / $income) * 100, 1) : 0,
            'completed_tasks' => $completedTasks,
            'total_tasks' => $totalTasks,
            'completion' => $totalTasks > 0 ? min(100, (int) round(($completedTasks / $totalTasks) * 100)) : 0,
            'annual_plans_completed' => Plan::where('user_id', $user->id)->where('status', 'completed')->whereBetween('updated_at', [$start, $end])->count(),
            'meetings' => Meeting::where('user_id', $user->id)->whereBetween('start_at', [$start, $end])->count(),
            'exercise' => ExerciseLog::where('user_id', $user->id)->whereBetween('performed_at', [$start, $end])->count(),
            'spiritual' => SpiritualPractice::where('user_id', $user->id)->whereBetween('practiced_at', [$start, $end])->count(),
            'ai_plans' => AiPlan::where('user_id', $user->id)->whereBetween('created_at', [$start, $end])->count(),
            'notes_created' => Note::where('user_id', $user->id)->whereBetween('created_at', [$start, $end])->count(),
            'financial_records' => Income::where('user_id', $user->id)->whereBetween('received_at', [$start, $end])->count()
                + Expense::where('user_id', $user->id)->whereBetween('spent_at', [$start, $end])->count()
                + SavingsContribution::where('user_id', $user->id)->whereBetween('contributed_at', [$start, $end])->count(),
        ];
    }

    private function percentChange(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return $current == 0.0 ? 0.0 : null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    private function momentumScore(array $stats): int
    {
        $taskPoints = min(35, max(0, $stats['completion'] * 0.35));
        $savingPoints = min(25, max(0, ($stats['savings_rate'] / 20) * 25));

        if ($stats['income'] > 0) {
            $expenseRatio = ($stats['expenses'] / $stats['income']) * 100;
            $moneyPoints = $expenseRatio <= 70 ? 25 : ($expenseRatio <= 90 ? 20 : ($expenseRatio <= 100 ? 12 : 4));
        } else {
            $moneyPoints = $stats['expenses'] <= 0 ? 12 : 5;
        }

        $wellbeingPoints = min(15, ($stats['exercise'] / 8) * 15);

        return max(0, min(100, (int) round($taskPoints + $savingPoints + $moneyPoints + $wellbeingPoints)));
    }
}
