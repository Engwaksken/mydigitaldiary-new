<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\ExerciseLog;
use App\Models\HealthCheckup;
use App\Models\Reminder;
use App\Models\SavingsGoal;
use App\Models\SleepLog;
use Illuminate\Http\Request;

class TipsController extends Controller
{
    public function index(Request $request)
    {
        return view('tips.index', ['dailyTips' => $this->buildDailyTips($request->user()->id)]);
    }

    /**
     * A small set of tips generated from the user's OWN current data state
     * - not static advice, but "here's something worth noticing about your
     * own tracked data right now." Each candidate has a condition; only
     * ones that are actually true for this user get shown, capped at 5.
     * An empty result (nothing looked concerning) shows an encouraging
     * default instead of an empty section.
     */
    private function buildDailyTips(int $userId): array
    {
        $tips = [];

        $overdueReminders = Reminder::where('user_id', $userId)
            ->where('is_active', true)
            ->where('next_run_at', '<', now()->subHours(1))
            ->count();
        if ($overdueReminders > 0) {
            $tips[] = [
                'icon' => 'fa-solid fa-bell',
                'color' => 'amber',
                'text' => "You have {$overdueReminders} overdue reminder(s) that haven't fired yet - worth checking your Reminders page and the scheduler is running.",
            ];
        }

        $lastSleep = SleepLog::where('user_id', $userId)->orderByDesc('sleep_date')->first();
        if (! $lastSleep || $lastSleep->sleep_date->lt(now()->subDays(3))) {
            $tips[] = [
                'icon' => 'fa-solid fa-bed',
                'color' => 'violet',
                'text' => $lastSleep
                    ? 'You last logged sleep on ' . $lastSleep->sleep_date->format('M j') . ' - logging most nights gives your averages more meaning.'
                    : "You haven't logged any sleep yet - even a few nights gives you a real 7-day average on your dashboard.",
            ];
        }

        $monthlyBudget = Budget::where('user_id', $userId)->where('period', 'monthly')->sum('amount');
        $monthlyExpenses = Expense::where('user_id', $userId)
            ->whereBetween('spent_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');
        if ($monthlyBudget > 0 && $monthlyExpenses > $monthlyBudget) {
            $over = $monthlyExpenses - $monthlyBudget;
            $tips[] = [
                'icon' => 'fa-solid fa-receipt',
                'color' => 'rose',
                'text' => "You're {$this->money($over)} over your monthly budget so far - check Expenses to see where.",
            ];
        }

        $upcomingCheckup = HealthCheckup::where('user_id', $userId)
            ->whereNotNull('next_due_date')
            ->whereBetween('next_due_date', [now(), now()->addDays(7)])
            ->orderBy('next_due_date')
            ->first();
        if ($upcomingCheckup) {
            $tips[] = [
                'icon' => 'fa-solid fa-stethoscope',
                'color' => 'pink',
                'text' => "Your {$upcomingCheckup->checkup_type} checkup is coming up on {$upcomingCheckup->next_due_date->format('M j, g:ia')} - a good time to confirm the appointment.",
            ];
        }

        $recentExercise = ExerciseLog::where('user_id', $userId)->where('performed_at', '>=', now()->subDays(7))->count();
        if ($recentExercise === 0) {
            $tips[] = [
                'icon' => 'fa-solid fa-person-running',
                'color' => 'lime',
                'text' => "No exercise logged in the last 7 days - even a short walk is worth logging to keep your streak visible.",
            ];
        }

        $goalWithNoProgress = SavingsGoal::where('user_id', $userId)
            ->where('status', 'in_progress')
            ->whereDoesntHave('contributions')
            ->first();
        if ($goalWithNoProgress) {
            $tips[] = [
                'icon' => 'fa-solid fa-piggy-bank',
                'color' => 'green',
                'text' => "Your \"{$goalWithNoProgress->name}\" savings goal has no contributions logged yet - even a small first one gets it moving.",
            ];
        }

        return array_slice($tips, 0, 5);
    }

    private function money(float $amount): string
    {
        return format_money($amount);
    }
}
