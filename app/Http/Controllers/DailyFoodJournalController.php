<?php

namespace App\Http\Controllers;

use App\Models\DailyFoodJournal;
use App\Models\DietLog;
use App\Services\DailyWellbeingSyncService;
use App\Services\HealthAiCoachService;
use App\Services\NutritionEstimateService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DailyFoodJournalController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:today,week,month,three_months,range,all'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
        ]);

        $period = $validated['period'] ?? 'month';
        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 10);

        $timezone = $request->user()->timezone ?: 'Africa/Kampala';
        $today = Carbon::now($timezone)->startOfDay();

        $query = DailyFoodJournal::query()
            ->where('user_id', $request->user()->id);

        match ($period) {
            'today' => $query->whereDate('journal_date', $today->toDateString()),
            'week' => $query->whereBetween('journal_date', [
                $today->copy()->subDays(6)->toDateString(),
                $today->toDateString(),
            ]),
            'month' => $query->whereBetween('journal_date', [
                $today->copy()->startOfMonth()->toDateString(),
                $today->copy()->endOfMonth()->toDateString(),
            ]),
            'three_months' => $query->whereBetween('journal_date', [
                $today->copy()->subMonths(3)->startOfDay()->toDateString(),
                $today->toDateString(),
            ]),
            'range' => ($from && $to)
                ? $query->whereBetween('journal_date', [$from, $to])
                : null,
            default => null,
        };

        $summaryQuery = clone $query;

        $history = $query
            ->orderByDesc('journal_date')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $filteredEntries = (clone $summaryQuery)->count();
        $daysRecorded = (clone $summaryQuery)
            ->select('journal_date')
            ->distinct()
            ->count('journal_date');

        return view('diet.daily-eating-history', compact(
            'history',
            'period',
            'from',
            'to',
            'perPage',
            'filteredEntries',
            'daysRecorded'
        ));
    }

    public function update(Request $request)
    {
        $data = $this->validateMeal($request);

        $payload = [
            'user_id' => $request->user()->id,
            'journal_date' => $data['journal_date'],
            'daily_food_notes' => $this->notes(
                $data['meal_type'],
                $data['food_items']
            ),
        ];

        if (Schema::hasColumn('daily_food_journals', 'meal_type')) {
            $payload['meal_type'] = $data['meal_type'];
        }

        if (Schema::hasColumn('daily_food_journals', 'food_items')) {
            $payload['food_items'] = $data['food_items'];
        }

        $entry = DailyFoodJournal::create($payload);

        try {
            $this->syncDietLog($request, $entry);
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
                $data['journal_date']
            );
        } catch (\Throwable $exception) {
            report($exception);
        }

        return redirect()
            ->route('diet-logs.index', ['diet_tab' => 'today'])
            ->with('success', ucfirst($data['meal_type']).' saved to today’s eating history.');
    }

    public function updateEntry(Request $request, DailyFoodJournal $entry)
    {
        abort_unless(
            (int) $entry->user_id === (int) $request->user()->id,
            403
        );

        $data = $this->validateMeal($request, $entry);

        $payload = [
            'journal_date' => $data['journal_date'],
            'daily_food_notes' => $this->notes(
                $data['meal_type'],
                $data['food_items']
            ),
        ];

        if (Schema::hasColumn('daily_food_journals', 'meal_type')) {
            $payload['meal_type'] = $data['meal_type'];
        }

        if (Schema::hasColumn('daily_food_journals', 'food_items')) {
            $payload['food_items'] = $data['food_items'];
        }

        $entry->forceFill($payload)->save();

        try {
            $this->syncDietLog($request, $entry);
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
                $data['journal_date']
            );
        } catch (\Throwable $exception) {
            report($exception);
        }

        return redirect()
            ->route('diet-logs.index', ['diet_tab' => 'history'])
            ->with('success', 'Eating history updated. Forgotten items were added.');
    }

    private function validateMeal(
        Request $request,
        ?DailyFoodJournal $existing = null
    ): array {
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

        return $data;
    }

    private function notes(string $mealType, array $items): string
    {
        return ucfirst($mealType).' - '.implode(', ', $items);
    }

    private function syncDietLog(
        Request $request,
        DailyFoodJournal $entry
    ): void {
        if (! Schema::hasTable('diet_logs')) {
            return;
        }

        $foodItems = [];

        if (Schema::hasColumn('daily_food_journals', 'food_items')) {
            $foodItems = (array) ($entry->food_items ?? []);
        }

        $food = implode(', ', $foodItems);

        if ($food === '') {
            $food = trim((string) $entry->daily_food_notes);
        }

        $estimate = null;

        try {
            $estimate = app(NutritionEstimateService::class)
                ->estimateCalories($food);
        } catch (\Throwable $exception) {
            report($exception);
        }

        $query = DietLog::query()
            ->where('user_id', $request->user()->id);

        if (Schema::hasColumn('diet_logs', 'daily_food_journal_id')) {
            $query->where('daily_food_journal_id', $entry->id);
        } else {
            $query->where('notes', 'like', '%Daily food journal #'.$entry->id.'%');
        }

        $log = $query->first() ?? new DietLog();

        $payload = [
            'user_id' => $request->user()->id,
            'meal_type' => (
                Schema::hasColumn('daily_food_journals', 'meal_type')
                && filled($entry->meal_type)
            )
                ? $entry->meal_type
                : $this->mealTypeFromNotes((string) $entry->daily_food_notes),
            'food_items' => $food,
            'calories' => $estimate['calories'] ?? null,
            'logged_at' => optional($entry->journal_date)->toDateString()
                ?? now()->toDateString(),
            'notes' => 'Daily food journal #'.$entry->id.'.'
                .(! empty($estimate['assumptions'])
                    ? ' AI calorie estimate: '.$estimate['assumptions']
                    : ''),
            'is_archived' => false,
        ];

        if (Schema::hasColumn('diet_logs', 'daily_food_journal_id')) {
            $payload['daily_food_journal_id'] = $entry->id;
        }

        $log->forceFill($payload)->save();
    }
    private function mealTypeFromNotes(string $notes): string
    {
        $text = strtolower(trim($notes));

        foreach ([
            'breakfast',
            'lunch',
            'snack',
            'dinner',
            'supper',
        ] as $meal) {
            if (str_starts_with($text, $meal.' -')
                || str_starts_with($text, $meal.' -')) {
                return $meal;
            }
        }

        return 'lunch';
    }

}
