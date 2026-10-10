<?php

namespace App\Http\Controllers;

use App\Models\DietLog;
use App\Models\DailyFoodJournal;
use App\Services\HealthAiCoachService;
use App\Services\DailyWellbeingSyncService;
use App\Services\NutritionEstimateService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DietLogController extends CrudController
{
    protected string $model = DietLog::class;
    protected string $routeName = 'diet-logs';
    protected string $title = 'Meal Log';
    protected string $icon = 'fa-solid fa-utensils';
    protected string $accent = 'orange';
    protected string $dateField = 'logged_at';

    protected array $fields = [
        ['name' => 'meal_type', 'label' => 'Meal', 'type' => 'select', 'required' => true, 'options' => [
            'breakfast' => 'Breakfast',
            'lunch' => 'Lunch',
            'snack' => 'Snacks',
            'dinner' => 'Dinner',
            'supper' => 'Supper',
        ]],
        [
            'name' => 'food_items',
            'label' => 'Food Items & Portions',
            'type' => 'textarea',
            'required' => true,
            'placeholder' => 'e.g. 1 cup matooke, 1 ladle beans, 1 avocado slice, water',
        ],
        [
            'name' => 'calories',
            'label' => 'Estimated Calories',
            'type' => 'readonly',
        ],
        ['name' => 'logged_at', 'label' => 'Date', 'type' => 'date', 'required' => true],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'meal_type' => 'required|in:breakfast,lunch,snack,dinner,supper',
        'food_items' => 'required|string|max:4000',
        'logged_at' => 'required|date',
        'notes' => 'nullable|string|max:3000',
    ];

    public function store(Request $request)
    {
        $this->normaliseSaveRequest($request);

        return parent::store($request);
    }

    public function update(Request $request, int $id)
    {
        $this->normaliseSaveRequest($request);

        return parent::update($request, $id);
    }

    private function normaliseSaveRequest(Request $request): void
    {
        $food = trim((string) (
            $request->input('food_items')
            ?? $request->input('food')
            ?? $request->input('description')
            ?? ''
        ));

        $timezone = $request->user()->timezone
            ?: config('app.timezone', 'Africa/Kampala');

        $loggedAt = $request->input('logged_at')
            ?: now($timezone)->toDateString();

        $mealType = strtolower(trim((string) $request->input('meal_type', '')));

        if (! in_array($mealType, ['breakfast', 'lunch', 'snack', 'dinner', 'supper'], true)) {
            $hour = now($timezone)->hour;

            $mealType = match (true) {
                $hour < 11 => 'breakfast',
                $hour < 15 => 'lunch',
                $hour < 18 => 'snack',
                $hour < 21 => 'dinner',
                default => 'supper',
            };
        }

        $request->merge([
            'meal_type' => $mealType,
            'food_items' => $food,
            'logged_at' => $loggedAt,
        ]);
    }

    public function index(Request $request)
    {
        $requestedDietTab = (string) $request->query('diet_tab', '');

        $dietTab = in_array(
            $requestedDietTab,
            ['today', 'profile', 'guidance', 'history', 'stats'],
            true
        )
            ? $requestedDietTab
            : (
                $request->filled('q')
                || $request->filled('period')
                || $request->filled('page')
                    ? 'stats'
                    : 'today'
            );

        $coach = app(HealthAiCoachService::class);
        $healthProfile = $coach->profile($request->user());

        // Only guidance waits for AI. Stats/history remain fast.
        $dietAiAdvice = $dietTab === 'guidance'
            ? $coach->dietAdvice($request->user())
            : null;

        $timezone = $request->user()->timezone
            ?: config('app.timezone', 'Africa/Kampala');

        $today = Carbon::now($timezone)->toDateString();

        $todayJournalEntries = collect();
        $dailyFoodHistory = collect();

        if ($dietTab === 'today') {
            $todayJournalEntries = DailyFoodJournal::query()
                ->where('user_id', $request->user()->id)
                ->whereDate('journal_date', $today)
                ->orderByDesc('created_at')
                ->get();

            $dailyFoodHistory = $todayJournalEntries;
        }

        $allowedHistoryPeriods = [
            'today', 'week', 'month', 'three_months', 'range', 'all',
        ];

        /*
         * When opening Statistics & logs, default eating-history scope to ALL.
         * This avoids "No eating history..." merely because the default month
         * has no journal entries while older records exist.
         */
        $defaultHistoryPeriod = $dietTab === 'stats' ? 'all' : 'month';

        $historyPeriod = in_array(
            (string) $request->query('history_period'),
            $allowedHistoryPeriods,
            true
        )
            ? (string) $request->query('history_period')
            : $defaultHistoryPeriod;

        $historyFrom = $request->query('history_from');
        $historyTo = $request->query('history_to');

        $historyPerPage = (int) $request->query('history_per_page', 10);
        if (! in_array($historyPerPage, [10, 25, 50], true)) {
            $historyPerPage = 10;
        }

        /*
         * Daily eating history is based directly on diet_logs.
         *
         * One history row = one calendar day.
         * The meals eaten on that day are displayed inside the day row
         * (Breakfast, Lunch, Snacks, Dinner and Supper).
         *
         * This avoids showing every meal log as a separate "history day".
         */
        $dailyEatingHistory = null;
        $historyFilteredEntries = 0;
        $historyDaysRecorded = 0;

        if (in_array($dietTab, ['history', 'stats'], true)) {
            $historyToday = Carbon::now($timezone)->startOfDay();

            $historyBase = DietLog::query()
                ->where('user_id', $request->user()->id);

            if (Schema::hasColumn('diet_logs', 'is_archived')) {
                $historyBase->where('is_archived', false);
            }

            switch ($historyPeriod) {
                case 'today':
                    $historyBase->whereDate(
                        'logged_at',
                        $historyToday->toDateString()
                    );
                    break;

                case 'week':
                    $historyBase->whereBetween('logged_at', [
                        $historyToday->copy()->subDays(6)->startOfDay(),
                        $historyToday->copy()->endOfDay(),
                    ]);
                    break;

                case 'month':
                    $historyBase->whereBetween('logged_at', [
                        $historyToday->copy()->startOfMonth()->startOfDay(),
                        $historyToday->copy()->endOfMonth()->endOfDay(),
                    ]);
                    break;

                case 'three_months':
                    $historyBase->whereBetween('logged_at', [
                        $historyToday->copy()->subMonths(3)->startOfDay(),
                        $historyToday->copy()->endOfDay(),
                    ]);
                    break;

                case 'range':
                    if ($historyFrom && $historyTo) {
                        $historyBase->whereBetween('logged_at', [
                            Carbon::parse($historyFrom, $timezone)->startOfDay(),
                            Carbon::parse($historyTo, $timezone)->endOfDay(),
                        ]);
                    }
                    break;

                case 'all':
                default:
                    break;
            }

            $historySummaryQuery = clone $historyBase;

            /*
             * Paginate DAYS, not meals.
             * Each result represents one calendar date.
             */
            $dailyEatingHistory = (clone $historyBase)
                ->selectRaw('DATE(logged_at) AS history_date')
                ->selectRaw('COUNT(*) AS meal_count')
                ->selectRaw('COALESCE(SUM(calories), 0) AS total_calories')
                ->groupByRaw('DATE(logged_at)')
                ->orderByDesc('history_date')
                ->paginate(
                    $historyPerPage,
                    ['*'],
                    'history_page'
                )
                ->withQueryString();

            $historyDates = collect($dailyEatingHistory->items())
                ->pluck('history_date')
                ->filter()
                ->map(fn ($date) => (string) $date)
                ->values();

            $mealsByDate = collect();

            if ($historyDates->isNotEmpty()) {
                $mealQuery = DietLog::query()
                    ->where('user_id', $request->user()->id)
                    ->whereIn(
                        \DB::raw('DATE(logged_at)'),
                        $historyDates->all()
                    );

                if (Schema::hasColumn('diet_logs', 'is_archived')) {
                    $mealQuery->where('is_archived', false);
                }

                $mealOrderSql = "
                    CASE meal_type
                        WHEN 'breakfast' THEN 1
                        WHEN 'lunch' THEN 2
                        WHEN 'snack' THEN 3
                        WHEN 'dinner' THEN 4
                        WHEN 'supper' THEN 5
                        ELSE 6
                    END
                ";

                $mealsByDate = $mealQuery
                    ->orderByRaw($mealOrderSql)
                    ->orderBy('logged_at')
                    ->orderBy('id')
                    ->get()
                    ->groupBy(
                        fn (DietLog $meal) => optional($meal->logged_at)
                            ?->timezone($timezone)
                            ?->toDateString()
                            ?? Carbon::parse($meal->logged_at, $timezone)
                                ->toDateString()
                    );
            }

            /*
             * Attach the meals to each paginated day without changing the
             * paginator structure used by the Blade view.
             */
            $dailyEatingHistory->setCollection(
                $dailyEatingHistory->getCollection()->map(
                    function ($day) use ($mealsByDate) {
                        $date = (string) $day->history_date;
                        $day->meals = $mealsByDate->get($date, collect());
                        return $day;
                    }
                )
            );

            $historyFilteredEntries = (clone $historySummaryQuery)->count();

            $historyDaysRecorded = (clone $historySummaryQuery)
                ->selectRaw('DATE(logged_at) AS history_date')
                ->distinct()
                ->count(\DB::raw('DATE(logged_at)'));
        }

        /*
         * Stats use DietLog because structured journal entries are mirrored
         * there. A single aggregate query supplies today's totals.
         */
        $todaySummary = DietLog::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('logged_at', $today)
            ->selectRaw(
                'COUNT(*) as meal_count, COALESCE(SUM(calories), 0) as calories'
            )
            ->first();

        $todayCalories = (int) ($todaySummary->calories ?? 0);
        $todayMealsCount = (int) ($todaySummary->meal_count ?? 0);

        $query = DietLog::where('user_id', $request->user()->id);

        return $this->renderIndex($request, $query, compact(
            'healthProfile',
            'dietAiAdvice',
            'dietTab',
            'dailyFoodHistory',
            'todayJournalEntries',
            'dailyEatingHistory',
            'historyPeriod',
            'historyFrom',
            'historyTo',
            'historyPerPage',
            'historyFilteredEntries',
            'historyDaysRecorded',
            'todayCalories',
            'todayMealsCount'
        ));
    }

    public function updateEatingHistory(
        Request $request,
        DailyFoodJournal $entry
    ) {
        abort_unless(
            (int) $entry->user_id === (int) $request->user()->id,
            403
        );

        $request->merge([
            'food_items' => collect($request->input('food_items', []))
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->values()
                ->all(),
        ]);

        $data = $request->validate([
            'journal_date' => ['required', 'date'],
            'meal_type' => ['required', 'in:breakfast,lunch,snack,dinner,supper'],
            'food_items' => ['required', 'array', 'min:1', 'max:25'],
            'food_items.*' => ['required', 'string', 'max:500'],
        ]);

        $entry->forceFill([
            'journal_date' => $data['journal_date'],
            'meal_type' => $data['meal_type'],
            'food_items' => $data['food_items'],
            'daily_food_notes' => ucfirst($data['meal_type'])
                .' — '.implode(', ', $data['food_items']),
        ])->save();

        app(HealthAiCoachService::class)
            ->invalidateDiet($request->user());

        return redirect()
            ->route('diet-logs.index', ['diet_tab' => 'history'])
            ->with('success', 'Daily eating history updated.');
    }

    protected function afterSave(Request $request, $item, bool $wasCreated): void
    {
        /*
         * The Meal Log itself has already been saved by CrudController.
         * Everything below is secondary enrichment/synchronisation and must
         * never turn a successful meal save into the generic Temporary
         * Problem page.
         */
        try {
            $estimate = app(NutritionEstimateService::class)
                ->estimateCalories((string) $item->food_items);

            if ($estimate) {
                $note = trim((string) ($estimate['assumptions'] ?? ''));

                $item->forceFill([
                    'calories' => $estimate['calories'],
                    'notes' => $this->appendEstimateNote(
                        $item->notes,
                        $note,
                        $estimate['confidence'] ?? 'medium'
                    ),
                ])->saveQuietly();
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        /*
         * A record created from "New Meal Log" must also appear in the
         * structured Daily Eating Journal/History. Previously the generic
         * Meal Log saved only to diet_logs while the Today/History tabs read
         * daily_food_journals, which made a successful save look missing.
         */
        try {
            $this->syncDailyFoodJournal($request, $item);
        } catch (\Throwable $exception) {
            report($exception);
        }

        try {
            app(HealthAiCoachService::class)
                ->invalidateDiet($request->user());
        } catch (\Throwable $exception) {
            report($exception);
        }

        try {
            app(DailyWellbeingSyncService::class)->sync(
                $request->user(),
                $item->logged_at ?? now()
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function syncDailyFoodJournal(Request $request, DietLog $item): void
    {
        if (! Schema::hasTable('daily_food_journals')) {
            return;
        }

        $mealType = strtolower(trim((string) $item->meal_type));

        if (! in_array(
            $mealType,
            ['breakfast', 'lunch', 'snack', 'dinner', 'supper'],
            true
        )) {
            return;
        }

        $foodItems = collect(
            preg_split(
                '/(?:\r?\n|,\s*)/',
                trim((string) $item->food_items)
            ) ?: []
        )
            ->map(fn ($food) => trim((string) $food))
            ->filter()
            ->take(25)
            ->values()
            ->all();

        if (empty($foodItems)) {
            $foodItems = [trim((string) $item->food_items)];
        }

        $payload = [
            'user_id' => $request->user()->id,
            'journal_date' => optional($item->logged_at)->toDateString()
                ?: now(
                    $request->user()->timezone
                    ?: config('app.timezone', 'Africa/Kampala')
                )->toDateString(),
            'daily_food_notes' => ucfirst($mealType)
                .' — '.implode(', ', $foodItems),
        ];

        if (Schema::hasColumn('daily_food_journals', 'meal_type')) {
            $payload['meal_type'] = $mealType;
        }

        if (Schema::hasColumn('daily_food_journals', 'food_items')) {
            $payload['food_items'] = $foodItems;
        }

        $journal = null;

        if (
            Schema::hasColumn('diet_logs', 'daily_food_journal_id')
            && filled($item->daily_food_journal_id)
        ) {
            $journal = DailyFoodJournal::query()
                ->where('user_id', $request->user()->id)
                ->find($item->daily_food_journal_id);
        }

        if ($journal) {
            $journal->forceFill($payload)->save();
        } else {
            $journal = DailyFoodJournal::create($payload);

            if (Schema::hasColumn('diet_logs', 'daily_food_journal_id')) {
                $item->forceFill([
                    'daily_food_journal_id' => $journal->id,
                ])->saveQuietly();
            }
        }
    }

    public function destroy(Request $request, int $id)
    {
        $item=DietLog::where('user_id',$request->user()->id)->findOrFail($id);
        $loggedAt=$item->logged_at;
        $journalId=$item->daily_food_journal_id ?? null;
        $item->delete();

        if ($journalId && Schema::hasTable('daily_food_journals')) {
            DailyFoodJournal::where('user_id',$request->user()->id)->whereKey($journalId)->delete();
        }

        try {
            app(DailyWellbeingSyncService::class)->sync($request->user(),$loggedAt??now());
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('success','Meal Log deleted.');
    }

    private function appendEstimateNote(?string $existing, string $assumptions, string $confidence): ?string
    {
        $existing = trim((string) $existing);

        if ($assumptions === '') {
            return $existing !== '' ? $existing : null;
        }

        $aiLine = 'AI calorie estimate (' . $confidence . ' confidence): ' . $assumptions;

        // Do not keep stacking the same generated note after every edit.
        $existing = preg_replace('/(?:\r?\n)?AI calorie estimate \([^)]*\):.*$/s', '', $existing) ?? $existing;
        $existing = trim($existing);

        return $existing === '' ? $aiLine : $existing . "\n" . $aiLine;
    }

    protected function stats(Request $request): array
    {
        $userId = $request->user()->id;
        $base = DietLog::where('user_id', $userId);

        $daily = (clone $base)
            ->where('logged_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(logged_at) as day, SUM(calories) as calories')
            ->groupByRaw('DATE(logged_at)')
            ->get();

        $avgDailyCalories = $daily->whereNotNull('calories')->avg('calories');

        return [
            ['label' => 'Avg calories/day (7d)', 'value' => $avgDailyCalories ? (string) round($avgDailyCalories) : '—', 'icon' => 'fa-solid fa-bolt', 'color' => 'orange'],
            ['label' => 'Meals logged (7d)', 'value' => (string) (clone $base)->where('logged_at', '>=', now()->subDays(7))->count(), 'icon' => 'fa-solid fa-utensils', 'color' => 'amber'],
            ['label' => 'Today calories', 'value' => (string) ((clone $base)->whereDate('logged_at', today())->sum('calories') ?: '—'), 'icon' => 'fa-solid fa-bowl-food', 'color' => 'emerald'],
            ['label' => 'Total meals logged', 'value' => (string) $base->count(), 'icon' => 'fa-solid fa-list-ol', 'color' => 'slate'],
        ];
    }

    protected function chart(Request $request): ?array
    {
        $userId = $request->user()->id;
        $days = collect(range(13, 0))->map(fn ($d) => now()->subDays($d)->startOfDay());

        $totals = $days->map(function ($day) use ($userId) {
            return (int) DietLog::where('user_id', $userId)
                ->whereDate('logged_at', $day->toDateString())
                ->sum('calories');
        });

        if ($totals->sum() <= 0) {
            return null;
        }

        return [
            'type' => 'line',
            'title' => 'Estimated Calories (last 14 days)',
            'labels' => $days->map(fn ($d) => $d->format('M j'))->all(),
            'datasets' => [['label' => 'Estimated calories', 'data' => $totals->all()]],
        ];
    }
}
