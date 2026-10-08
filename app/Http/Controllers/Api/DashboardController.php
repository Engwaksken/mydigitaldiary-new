<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\BusinessCard;
use App\Models\Expense;
use App\Models\Income;
use App\Models\DailyPlan;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Reminder;
use App\Models\Debt;
use App\Models\SavingsGoal;
use App\Models\SavingsContribution;
use App\Models\SpiritualPractice;
use App\Services\DailyInsightService;
use App\Services\PersonalProgressService;
use App\Services\PeriodReviewMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * A lighter-weight version of the web DashboardController's stat cards
 * PLUS the same chart data and recent-activity feed — the Flutter app
 * renders its own native charts (fl_chart) and cards from this JSON
 * rather than any Blade view or Chart.js config.
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;
        $timezone = $user->timezone ?: config('app.timezone', 'Africa/Kampala');
        $localNow = Carbon::now($timezone);
        $startOfMonth = $localNow->copy()->startOfMonth();
        $endOfMonth = $localNow->copy()->endOfMonth();

        // ---- Spending by category (this month) — doughnut chart ----
        $expensesByCategory = Expense::where('user_id', $userId)
            ->whereBetween('spent_at', [$startOfMonth, $endOfMonth])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        // ---- Income vs Expenses, last 6 months — bar chart ----
        $trendMonths = collect(range(5, 0))->map(
            fn ($monthsAgo) => Carbon::now()->subMonthsNoOverflow($monthsAgo)->startOfMonth()
        );

        $trendLabels = $trendMonths->map(fn (Carbon $month) => $month->format('M Y'))->all();

        $incomeTrend = $trendMonths->map(function (Carbon $month) use ($userId) {
            return (float) Income::where('user_id', $userId)
                ->whereBetween('received_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->sum('amount');
        })->all();

        $expenseTrend = $trendMonths->map(function (Carbon $month) use ($userId) {
            return (float) Expense::where('user_id', $userId)
                ->whereBetween('spent_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->sum('amount');
        })->all();

        return response()->json([
            'monthly_income' => (float) Income::where('user_id', $userId)->whereBetween('received_at', [$startOfMonth, $endOfMonth])->sum('amount'),
            'monthly_expenses' => (float) Expense::where('user_id', $userId)->whereBetween('spent_at', [$startOfMonth, $endOfMonth])->sum('amount'),
            'monthly_budget' => (float) Budget::where('user_id', $userId)->where('period', 'monthly')->sum('amount'),
            'active_projects' => Project::where('user_id', $userId)->whereIn('status', ['planned', 'in_progress'])->count(),
            'upcoming_reminders' => Reminder::where('user_id', $userId)->where('is_active', true)->whereBetween('next_run_at', [now(), now()->addDays(7)])->count(),
            'expenses_by_category' => $expensesByCategory->map(fn ($row) => [
                'category' => $row->category,
                'total' => (float) $row->total,
            ]),
            'trend_labels' => $trendLabels,
            'income_trend' => $incomeTrend,
            'expense_trend' => $expenseTrend,
            'recent_activity' => $this->recentActivity($userId),
            'top_tasks' => $this->topTasksFor($request->user()),
            'finance_summary' => $this->financeSummary($userId, $startOfMonth, $endOfMonth),
            'today_insight' => $this->mobileInsight(app(DailyInsightService::class)->current($user)),
            'server_context' => [
                'timezone' => $timezone,
                'local_date' => $localNow->toDateString(),
                'local_time' => $localNow->format('H:i'),
            ],
            'personal_progress' => app(PersonalProgressService::class)->summary($request->user()),
            'goal_intelligence' => app(\App\Services\GoalIntelligenceService::class)->build($request->user()),
            'monthly_review_preview' => app(\App\Services\MonthlyReviewService::class)->build($request->user()),
            'onboarding' => [
                'completed' => ! is_null($request->user()->onboarding_completed_at),
                'focuses' => $request->user()->onboarding_focuses ?? [],
            ],
        ]);
    }

    /**
     * Same idea as the web DashboardController's buildRecentActivity() —
     * a handful of the most recent items across a few modules, newest
     * first. Kept intentionally smaller/simpler than the web version
     * (fewer source modules) since this is a quick glance list, not a
     * full activity log.
     */
    /**
     * Same logic as SendDailyTopTasksDigest::topTasksFor() (duplicated
     * rather than shared, per this project's usual pattern for small
     * self-contained queries) — but called live here so the Home
     * screen can show "top 3 upcoming" any time the dashboard loads,
     * not only once a day via the async email/notification digest.
     */
    private function topTasksFor(\App\Models\User $user): array
    {
        /*
         * IMPORTANT: Keep Mobile Today's Focus identical to Laravel Web.
         *
         * The web DashboardController uses the user's current DailyPlan and
         * PeriodReviewMetricsService::todayFocus() as the authoritative source.
         * The API previously duplicated this logic and also mixed in project
         * tasks, meetings and reminders. That meant Web could show focus cards
         * while Mobile received an empty/different list.
         *
         * Using the shared service removes that divergence.
         */
        return app(PeriodReviewMetricsService::class)
            ->todayFocus($user, 6)
            ->map(function ($item) use ($user) {
                $startTime = $item->start_time ?? null;

                $displayTime = null;
                if ($startTime) {
                    try {
                        $displayTime = Carbon::parse((string) $startTime)
                            ->format('g:i A');
                    } catch (\Throwable) {
                        $displayTime = (string) $startTime;
                    }
                }

                return [
                    'id' => (int) $item->id,
                    'title' => (string) ($item->title ?? 'Daily task'),
                    'source' => (string) ($item->source ?? 'Daily Planner'),
                    'module' => 'daily_planner',
                    'priority' => (string) ($item->priority ?? ''),
                    'start_time' => $startTime
                        ? substr((string) $startTime, 0, 8)
                        : null,
                    'time' => $displayTime,
                    'is_completed' => (bool) ($item->is_completed ?? false),
                    'repeat_type' => (string) ($item->repeat_type ?? 'once'),
                    'is_recurring' => (string) ($item->repeat_type ?? 'once') !== 'once',
                    'occurrence_date' => $item->occurrence_date
                        ? Carbon::parse($item->occurrence_date)->toDateString()
                        : null,
                    'plan_id' => $item->daily_plan_id
                        ?? $item->plan_id
                        ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Same logic as the compact recentActivity() shown inline on the
     * dashboard, just with a much higher cap — for a dedicated "View
     * All" screen rather than the home screen's quick-glance version.
     */
    public function recentActivityFull(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->recentActivity($request->user()->id, perModelLimit: 20, take: 50)]);
    }


    /**
     * The mobile "Today" hub in one payload (greeting, task progress,
     * streak + last 7 days, nudge, routine state, get-started checklist,
     * top tasks, coming up, progress rings, money, "On this day" and the
     * daily reminder settings). See TodayHubService.
     */
    public function todayHub(Request $request): JsonResponse
    {
        return response()->json([
            'data' => app(\App\Services\TodayHubService::class)->build($request->user()),
        ]);
    }

    /**
     * Dedicated mobile endpoint for Today's Focus. Keeping this separate from
     * the larger dashboard payload prevents stale/partially cached dashboard
     * responses from making the Home screen look empty.
     */
    public function todayFocus(Request $request): JsonResponse
    {
        $user = $request->user();
        $timezone = $user->timezone
            ?: config('app.timezone', 'Africa/Kampala');
        $items = $this->topTasksFor($user);

        return response()->json([
            'data' => $items,
            'top_tasks' => $items,
            'meta' => [
                'source' => 'daily_planner',
                'timezone' => $timezone,
                'local_date' => Carbon::now($timezone)->toDateString(),
                'count' => count($items),
            ],
        ]);
    }

    /**
     * Dedicated current insight endpoint for mobile. Insight selection is based
     * on the user's configured timezone and the active two-hour/daypart window.
     */
    public function todayInsight(Request $request): JsonResponse
    {
        $user=$request->user(); $timezone=$user->timezone ?: config('app.timezone','Africa/Kampala'); $localNow=Carbon::now($timezone);
        return response()->json(['data'=>$this->mobileInsight(app(DailyInsightService::class)->current($user)),'meta'=>['timezone'=>$timezone,'local_date'=>$localNow->toDateString(),'local_time'=>$localNow->format('H:i')]]);
    }

    public function refreshTodayInsight(Request $request): JsonResponse
    {
        $key='today-insight-manual:'.$request->user()->id;
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key,3)) return response()->json(['message'=>'Please wait before refreshing the insight again.'],429);
        \Illuminate\Support\Facades\RateLimiter::hit($key,3600);
        return response()->json(['data'=>$this->mobileInsight(app(DailyInsightService::class)->refresh($request->user()))]);
    }

    public function financeSummaryData(Request $request): JsonResponse
    {
        $timezone = $request->user()->timezone ?: config('app.timezone', 'Africa/Kampala');
        $now = Carbon::now($timezone);

        return response()->json([
            'data' => $this->financeSummary(
                $request->user()->id,
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ),
        ]);
    }

    private function financeSummary(int $userId, Carbon $startOfMonth, Carbon $endOfMonth): array
    {
        $incomeBase = Income::where('user_id', $userId)->where('is_archived', false);
        $expenseBase = Expense::where('user_id', $userId)->where('is_archived', false);
        $budgetBase = Budget::where('user_id', $userId)->where('is_archived', false);
        $debtBase = Debt::where('user_id', $userId)->where('is_archived', false);
        $savingBase = SavingsContribution::where('user_id', $userId)->where('is_archived', false);
        $goalBase = SavingsGoal::where('user_id', $userId)->where('is_archived', false);

        $latest = static fn ($query, string $dateColumn, string $labelColumn) => ($row = (clone $query)->orderByDesc($dateColumn)->first()) ? [
            'label' => (string) ($row->{$labelColumn} ?? 'Latest record'),
            'date' => optional($row->{$dateColumn})->toDateString() ?? (string) $row->{$dateColumn},
        ] : null;

        $monthIncome = (float) (clone $incomeBase)->whereBetween('received_at', [$startOfMonth, $endOfMonth])->sum('amount');
        $monthExpenses = (float) (clone $expenseBase)->whereBetween('spent_at', [$startOfMonth, $endOfMonth])->sum('amount');
        $allSavings = (float) (clone $savingBase)->sum('amount');

        return [
            'financial_planner' => [
                'title' => 'Financial Planner', 'endpoint' => 'financial-planner',
                'monthly_total' => $monthIncome - $monthExpenses,
                'overall_total' => $allSavings,
                'count' => (clone $incomeBase)->count() + (clone $expenseBase)->count(),
                'monthly_label' => 'Monthly surplus', 'overall_label' => 'Total savings', 'latest' => null,
            ],
            'income' => [
                'title' => 'Income', 'endpoint' => 'incomes',
                'monthly_total' => $monthIncome,
                'overall_total' => (float) (clone $incomeBase)->sum('amount'), 'count' => (clone $incomeBase)->count(),
                'latest' => $latest($incomeBase, 'received_at', 'source'),
            ],
            'expenses' => [
                'title' => 'Expenses', 'endpoint' => 'expenses',
                'monthly_total' => $monthExpenses,
                'overall_total' => (float) (clone $expenseBase)->sum('amount'), 'count' => (clone $expenseBase)->count(),
                'latest' => $latest($expenseBase, 'spent_at', 'category'),
            ],
            'budgets' => [
                'title' => 'Budgets', 'endpoint' => 'budgets',
                'monthly_total' => (float) (clone $budgetBase)->where('period', 'monthly')->sum('amount'),
                'overall_total' => (float) (clone $budgetBase)->sum('amount'), 'count' => (clone $budgetBase)->count(),
                'latest' => null,
            ],
            'debts' => [
                'title' => 'Debts', 'endpoint' => 'debts',
                'monthly_total' => (float) (clone $debtBase)->whereBetween('date', [$startOfMonth, $endOfMonth])->sum('amount'),
                'overall_total' => (float) (clone $debtBase)->where('status', 'outstanding')->sum('amount'), 'count' => (clone $debtBase)->count(),
                'latest' => $latest($debtBase, 'date', 'person_name'),
            ],
            'savings' => [
                'title' => 'Savings', 'endpoint' => 'savings-contributions',
                'monthly_total' => (float) (clone $savingBase)->whereBetween('contributed_at', [$startOfMonth, $endOfMonth])->sum('amount'),
                'overall_total' => $allSavings, 'count' => (clone $savingBase)->count(),
                'latest' => $latest($savingBase, 'contributed_at', 'amount'),
            ],
            'savings_goals' => [
                'title' => 'Savings Goals', 'endpoint' => 'savings-goals',
                'monthly_total' => (float) (clone $savingBase)->whereBetween('contributed_at', [$startOfMonth, $endOfMonth])->sum('amount'),
                'overall_total' => (float) (clone $goalBase)->sum('target_amount'), 'count' => (clone $goalBase)->count(),
                'latest' => null, 'overall_label' => 'Total target',
            ],
        ];
    }

    private function mobileInsight(array $insight): array
    {
        $destinations = [
            'expenses.index' => 'expenses', 'budgets.index' => 'budgets', 'savings-goals.index' => 'savings-goals',
            'project-tasks.index' => 'project-tasks', 'reminders.index' => 'reminders', 'daily-planner.index' => 'daily-planner',
            'sleep-logs.index' => 'sleep-logs', 'spiritual-practices.index' => 'spiritual-practices',
            'personal-goals.index' => 'personal-goals', 'wellbeing.index' => 'wellbeing',
        ];
        $insight['destination'] = $insight['destination'] ?? ($destinations[$insight['route_name'] ?? ''] ?? 'daily-planner');
        unset($insight['route_name'], $insight['key']);
        return $insight;
    }

    private function recentActivity(int $userId, int $perModelLimit = 5, int $take = 10): array
    {
        $items = collect();

        Expense::where('user_id', $userId)->latest()->limit($perModelLimit)->get()->each(function ($e) use (&$items) {
            $items->push(['icon' => 'receipt', 'text' => "Logged expense: {$e->category}", 'amount' => (float) $e->amount, 'time' => $e->created_at]);
        });

        Income::where('user_id', $userId)->latest()->limit($perModelLimit)->get()->each(function ($i) use (&$items) {
            $items->push(['icon' => 'income', 'text' => 'Logged income', 'amount' => (float) $i->amount, 'time' => $i->created_at]);
        });

        BusinessCard::where('user_id', $userId)->latest('updated_at')->limit($perModelLimit)->get()->each(function ($card) use (&$items) {
            $created = $card->created_at && $card->updated_at && $card->created_at->equalTo($card->updated_at);
            $items->push([
                'icon' => 'business_card',
                'text' => ($created ? 'Created business card: ' : 'Updated business card: ') . $card->name,
                'amount' => null,
                'time' => $card->updated_at ?? $card->created_at,
            ]);
        });

        Reminder::where('user_id', $userId)->latest()->limit($perModelLimit)->get()->each(function ($r) use (&$items) {
            $items->push(['icon' => 'reminder', 'text' => "Reminder: {$r->title}", 'amount' => null, 'time' => $r->created_at]);
        });

        return $items->sortByDesc('time')->take($take)->map(fn ($item) => [
            'icon' => $item['icon'],
            'text' => $item['text'],
            'amount' => $item['amount'],
            'time' => $item['time']->toIso8601String(),
        ])->values()->all();
    }
}
