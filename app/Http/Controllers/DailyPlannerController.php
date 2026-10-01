<?php

namespace App\Http\Controllers;

use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Models\PersonalGoal;
use App\Models\Reminder;
use App\Services\DailyPlannerRecurrenceService;
use App\Services\DailyPlannerTaskReminderService;
use App\Services\DailyPlannerWellbeingSyncService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DailyPlannerController extends Controller
{
    public function __construct(
        private readonly DailyPlannerRecurrenceService $recurrence,
        private readonly DailyPlannerTaskReminderService $taskReminders
    ) {
    }

    public function index(Request $request): View
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->date)->startOfDay()
            : today();

        $plan = DailyPlan::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'plan_date' => $date->toDateString(),
            ],
            ['title' => 'My Daily Plan']
        );

        $items = $this->recurrence->itemsForDate(
            $request->user()->id,
            $date
        );

        $plan->setRelation(
            'items',
            new \Illuminate\Database\Eloquent\Collection(
                $items->all()
            )
        );

        $stats = $this->recurrence->statistics($items);

        $activeTab = in_array(
            $request->query('tab'),
            ['tasks', 'history'],
            true
        )
            ? $request->query('tab')
            : 'tasks';

        $historySearch = trim(
            (string) $request->query('history_q', '')
        );

        $historyPeriod = (string) $request->query(
            'history_period',
            'all'
        );

        $historyFrom = $request->query('history_from');
        $historyTo = $request->query('history_to');

        $historyPerPage = (int) $request->query(
            'history_per_page',
            10
        );

        if (! in_array(
            $historyPerPage,
            [10, 25, 50, 100],
            true
        )) {
            $historyPerPage = 10;
        }

        $pastQuery = DailyPlan::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('plan_date', '<', today());

        if ($historySearch !== '') {
            $pastQuery->where(function ($query) use ($historySearch) {
                $query
                    ->where(
                        'title',
                        'like',
                        "%{$historySearch}%"
                    )
                    ->orWhere(
                        'notes',
                        'like',
                        "%{$historySearch}%"
                    )
                    ->orWhereHas(
                        'items',
                        function ($items) use ($historySearch) {
                            $items
                                ->where(
                                    'title',
                                    'like',
                                    "%{$historySearch}%"
                                )
                                ->orWhere(
                                    'description',
                                    'like',
                                    "%{$historySearch}%"
                                );
                        }
                    );
            });
        }

        $this->applyHistoryPeriod(
            $pastQuery,
            $historyPeriod,
            $historyFrom,
            $historyTo
        );

        $pastPlans = $pastQuery
            ->orderByDesc('plan_date')
            ->paginate(
                $historyPerPage,
                ['*'],
                'history_page'
            )
            ->withQueryString();

        $pastPlans->getCollection()->transform(
            function (DailyPlan $past) use ($request) {
                $pastItems = $this->recurrence->itemsForDate(
                    $request->user()->id,
                    $past->plan_date
                );

                $pastStats =
                    $this->recurrence->statistics($pastItems);

                $past->setAttribute(
                    'total',
                    $pastStats['total']
                );

                $past->setAttribute(
                    'completed',
                    $pastStats['completed']
                );

                $past->setAttribute(
                    'pending',
                    $pastStats['pending']
                );

                $past->setAttribute(
                    'progress',
                    $pastStats['progress']
                );

                return $past;
            }
        );

        $goalOptions = PersonalGoal::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('title')
            ->pluck('title', 'id');

        return view('daily-planner.index', [
            'date' => $date,
            'plan' => $plan,
            'total' => $stats['total'],
            'done' => $stats['completed'],
            'pending' => $stats['pending'],
            'scheduled' => $stats['timed'],
            'progress' => $stats['progress'],
            'goalOptions' => $goalOptions,
            'activeTab' => $activeTab,
            'pastPlans' => $pastPlans,
            'historySearch' => $historySearch,
            'historyPeriod' => $historyPeriod,
            'historyFrom' => $historyFrom,
            'historyTo' => $historyTo,
            'historyPerPage' => $historyPerPage,
        ]);
    }

    public function updatePlan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'achievements' => ['nullable', 'string'],
            'challenges' => ['nullable', 'string'],
        ]);

        $date = Carbon::parse(
            $data['plan_date']
        )->toDateString();

        $plan = DailyPlan::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'plan_date' => $date,
            ],
            ['title' => 'My Daily Plan']
        );

        $plan->update([
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'achievements' => $data['achievements'] ?? null,
            'challenges' => $data['challenges'] ?? null,
        ]);

        return $this->backToDate(
            $date,
            'Day plan saved.'
        );
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $data = $this->validateItem($request, true);

        $planDate = Carbon::parse(
            $data['plan_date']
        )->toDateString();

        $plan = DailyPlan::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'plan_date' => $planDate,
            ],
            ['title' => 'My Daily Plan']
        );

        $recurrence = $this->recurrence->normalizeRecurrence(
            $data,
            $planDate
        );

        unset(
            $data['plan_date'],
            $data['repeat_type'],
            $data['repeat_days'],
            $data['repeat_interval'],
            $data['repeat_starts_on'],
            $data['repeat_ends_on'],
            $data['edit_scope'],
            $data['occurrence_date']
        );

        $data = array_merge($data, $recurrence);

        $data['sort_order'] =
            ($plan->items()->max('sort_order') ?? 0) + 1;

        $item = $plan->items()->create($data);

        $this->taskReminders->sync($item, $request->user(), Carbon::parse($planDate));

        return $this->backToDate(
            $planDate,
            $recurrence['repeat_type'] === 'once'
                ? 'Task added.'
                : 'Recurring task added. You only need to create this task once.'
        );
    }

    public function updateItem(
        Request $request,
        DailyPlanItem $item
    ): RedirectResponse {
        $this->owned($request, $item);

        $data = $this->validateItem(
            $request,
            false
        );

        $scope = $data['edit_scope'] ?? 'series';

        $viewDate = Carbon::parse(
            $data['occurrence_date']
                ?? $data['plan_date']
                ?? $item->plan->plan_date
        )->toDateString();

        if ($item->isRecurring() && $scope === 'occurrence') {
            $this->recurrence->updateOccurrenceOnly(
                $item,
                $request->user()->id,
                Carbon::parse($viewDate),
                $data
            );

            return $this->backToDate(
                $viewDate,
                'Only this occurrence was updated.'
            );
        }

        $oldDate = $item->plan->plan_date->toDateString();

        $targetDate = filled($data['plan_date'] ?? null)
            ? Carbon::parse(
                $data['plan_date']
            )->toDateString()
            : $oldDate;

        $recurrence =
            $this->recurrence->normalizeRecurrence(
                $data,
                $targetDate
            );

        unset(
            $data['plan_date'],
            $data['repeat_type'],
            $data['repeat_days'],
            $data['repeat_interval'],
            $data['repeat_starts_on'],
            $data['repeat_ends_on'],
            $data['edit_scope'],
            $data['occurrence_date']
        );

        $data = array_merge($data, $recurrence);

        if (
            ! $item->isRecurring()
            && $targetDate !== $oldDate
        ) {
            if ($item->is_completed) {
                return $this->backToDate(
                    $oldDate
                )->withErrors([
                    'task' =>
                        'Completed tasks cannot be moved. Reopen the task first.',
                ]);
            }

            $targetPlan = DailyPlan::firstOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'plan_date' => $targetDate,
                ],
                ['title' => 'My Daily Plan']
            );

            $data['daily_plan_id'] = $targetPlan->id;

            $data['sort_order'] =
                ($targetPlan->items()->max('sort_order') ?? 0)
                + 1;
        }

        $item->update($data);

        $this->taskReminders->sync($item->fresh(), $request->user(), Carbon::parse($viewDate ?? $occurrenceDate ?? $targetDate ?? $oldDate));

        if (
            ! $item->isRecurring()
            && $targetDate !== $oldDate
        ) {
            $this->rescheduleReminderDate(
                $request,
                $item,
                $targetDate
            );
        }

        return $this->backToDate(
            $viewDate,
            $item->isRecurring()
                ? 'Recurring task series updated.'
                : 'Task updated.'
        );
    }

    public function toggle(
        Request $request,
        DailyPlanItem $item
    ): RedirectResponse {
        $this->owned($request, $item);

        $date = Carbon::parse(
            $request->input(
                'occurrence_date',
                $item->plan->plan_date
            )
        );

        $completion = $this->recurrence->toggleOccurrence(
            $item,
            $request->user()->id,
            $date
        );

        $freshItem = $item->fresh();
        if ((bool) ($completion->is_completed ?? $freshItem->is_completed)) {
            $this->taskReminders->cancel($freshItem, $request->user());
        } else {
            $this->taskReminders->sync($freshItem, $request->user(), $date);
        }

        $syncMessage = app(DailyPlannerWellbeingSyncService::class)->syncCompletion(
            $request->user(),
            $freshItem,
            $date,
            (bool) ($completion->is_completed ?? $freshItem->is_completed)
        );

        return $this->backToDate(
            $date->toDateString(),
            $syncMessage ?: 'Task status updated.'
        );
    }

    public function moveItem(
        Request $request,
        DailyPlanItem $item
    ): RedirectResponse {
        $this->owned($request, $item);

        if ($item->isRecurring()) {
            return $this->backToDate(
                $request->input(
                    'occurrence_date',
                    today()->toDateString()
                )
            )->withErrors([
                'task' =>
                    'Recurring tasks follow their repeat schedule. Edit the recurring series instead of moving it.',
            ]);
        }

        if ($item->is_completed) {
            return $this->backToDate(
                $item->plan->plan_date->toDateString()
            )->withErrors([
                'task' =>
                    'Completed tasks cannot be moved. Reopen the task first.',
            ]);
        }

        $data = $request->validate([
            'target_date' => ['required', 'date'],
        ]);

        $targetDate = Carbon::parse(
            $data['target_date']
        )->toDateString();

        $this->movePendingItemToDate(
            $request,
            $item,
            $targetDate
        );

        return $this->backToDate(
            $targetDate,
            'Task moved successfully.'
        );
    }

    public function bulkMove(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'target_date' => ['required', 'date'],
        ]);

        $targetDate = Carbon::parse(
            $data['target_date']
        )->toDateString();

        $items = DailyPlanItem::query()
            ->with('plan')
            ->whereIn('id', $data['ids'])
            ->whereHas(
                'plan',
                fn ($query) =>
                    $query->where(
                        'user_id',
                        $request->user()->id
                    )
            )
            ->get();

        $moved = 0;
        $skipped = 0;

        foreach ($items as $item) {
            if ($item->isRecurring() || $item->is_completed) {
                $skipped++;
                continue;
            }

            $this->movePendingItemToDate(
                $request,
                $item,
                $targetDate
            );

            $moved++;
        }

        return $this->backToDate(
            $targetDate,
            $moved.' task'.($moved === 1 ? '' : 's')
            .' moved.'
            .($skipped
                ? " {$skipped} recurring/completed task(s) skipped."
                : '')
        );
    }

    public function destroyItem(
        Request $request,
        DailyPlanItem $item
    ): RedirectResponse {
        $this->owned($request, $item);

        $date = Carbon::parse(
            $request->input(
                'occurrence_date',
                $item->plan->plan_date
            )
        );

        $scope = $request->input(
            'delete_scope',
            $item->isRecurring() ? 'occurrence' : 'series'
        );

        if ($item->isRecurring() && $scope === 'occurrence') {
            $this->recurrence->skipOccurrence(
                $item,
                $request->user()->id,
                $date
            );

            return $this->backToDate(
                $date->toDateString(),
                'Only this occurrence was removed.'
            );
        }

        if ($item->isRecurring() && $scope === 'future') {
            $item->update([
                'repeat_ends_on' =>
                    $date->copy()
                        ->subDay()
                        ->toDateString(),
            ]);

            return $this->backToDate(
                $date->toDateString(),
                'This and future occurrences were removed.'
            );
        }

        $this->taskReminders->cancel($item, $request->user());
        $item->delete();

        return $this->backToDate(
            $date->toDateString(),
            'Task deleted.'
        );
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'occurrence_date' => ['nullable', 'date'],
        ]);

        $date = Carbon::parse(
            $data['occurrence_date'] ?? today()
        );

        $items = DailyPlanItem::query()
            ->with('plan')
            ->whereIn('id', $data['ids'])
            ->whereHas(
                'plan',
                fn ($query) =>
                    $query->where(
                        'user_id',
                        $request->user()->id
                    )
            )
            ->get();

        foreach ($items as $item) {
            if ($item->isRecurring()) {
                $this->recurrence->skipOccurrence(
                    $item,
                    $request->user()->id,
                    $date
                );
            } else {
                $item->delete();
            }
        }

        return $this->backToDate(
            $date->toDateString(),
            $items->count().' task(s) updated.'
        );
    }

    private function validateItem(
        Request $request,
        bool $creating
    ): array {
        /*
         * The reminder <select> offers "custom" alongside the minute
         * offsets; a custom reminder is stored as reminder_custom_at with
         * no offset, and a preset offset clears any old custom time.
         */
        $customReminder = $request->input('reminder_offset_minutes') === 'custom';

        if ($customReminder) {
            $request->merge(['reminder_offset_minutes' => null]);
        }

        $data = $request->validate([
            'plan_date' => [
                $creating ? 'required' : 'nullable',
                'date',
            ],
            'title' => ['required', 'string', 'max:255'],
            'personal_goal_id' => [
                'nullable',
                'integer',
                'exists:personal_goals,id',
            ],
            'description' => ['nullable', 'string'],
            'achievements' => ['nullable', 'string'],
            'challenges' => ['nullable', 'string'],
            'priority' => [
                'required',
                Rule::in(['low', 'medium', 'high']),
            ],
            'start_time' => [
                'nullable',
                'date_format:H:i',
            ],
            'end_time' => [
                'nullable',
                'date_format:H:i',
                'after:start_time',
            ],
            'repeat_type' => [
                'nullable',
                Rule::in(
                    DailyPlannerRecurrenceService::REPEAT_TYPES
                ),
            ],
            'repeat_days' => [
                'nullable',
                'array',
                Rule::requiredIf(
                    fn () =>
                        $request->input('repeat_type')
                        === 'specific_days'
                ),
            ],
            'repeat_days.*' => [
                Rule::in(
                    DailyPlannerRecurrenceService::WEEK_DAYS
                ),
            ],
            'repeat_interval' => [
                'nullable',
                'integer',
                'min:1',
                'max:52',
            ],
            'repeat_starts_on' => ['nullable', 'date'],
            'repeat_ends_on' => [
                'nullable',
                'date',
                'after_or_equal:repeat_starts_on',
            ],
            'occurrence_date' => ['nullable', 'date'],
            'edit_scope' => [
                'nullable',
                Rule::in(['occurrence', 'series']),
            ],
            'reminder_enabled' => ['nullable', 'boolean'],
            'reminder_offset_minutes' => ['nullable', 'integer', Rule::in(DailyPlannerTaskReminderService::OFFSETS)],
            'reminder_custom_at' => [
                Rule::requiredIf(fn () => $customReminder && $request->boolean('reminder_enabled')),
                'nullable',
                'date',
            ],
            'reminder_channels' => ['nullable', 'array'],
            'reminder_channels.*' => [Rule::in(['in_app', 'push', 'email'])],
        ]);

        // An unticked checkbox is not submitted, so read it explicitly.
        $data['reminder_enabled'] = $request->boolean('reminder_enabled');

        if (! $customReminder) {
            $data['reminder_custom_at'] = null;
        }

        return $data;
    }

    private function owned(
        Request $request,
        DailyPlanItem $item
    ): void {
        $item->loadMissing('plan');

        abort_unless(
            $item->plan
            && (int) $item->plan->user_id
                === (int) $request->user()->id,
            403
        );
    }

    private function movePendingItemToDate(
        Request $request,
        DailyPlanItem $item,
        string $targetDate
    ): void {
        $targetPlan = DailyPlan::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'plan_date' => $targetDate,
            ],
            ['title' => 'My Daily Plan']
        );

        $item->update([
            'daily_plan_id' => $targetPlan->id,
            'sort_order' =>
                ($targetPlan->items()->max('sort_order') ?? 0) + 1,
        ]);

        $this->rescheduleReminderDate(
            $request,
            $item,
            $targetDate
        );
    }

    private function rescheduleReminderDate(
        Request $request,
        DailyPlanItem $item,
        string $targetDate
    ): void {
        if (
            ! Schema::hasColumns(
                'reminders',
                ['source_type', 'source_id']
            )
        ) {
            return;
        }

        Reminder::query()
            ->where('user_id', $request->user()->id)
            ->where('source_type', 'daily_plan_item')
            ->where('source_id', $item->id)
            ->where('is_active', true)
            ->get()
            ->each(
                function (Reminder $reminder) use ($targetDate) {
                    if (! $reminder->next_run_at) {
                        return;
                    }

                    $reminder->next_run_at = Carbon::parse(
                        $targetDate.' '
                        .$reminder->next_run_at->format('H:i:s')
                    );

                    $reminder->save();
                }
            );
    }

    private function applyHistoryPeriod(
        $query,
        string $period,
        ?string $from,
        ?string $to
    ): void {
        $today = today();

        match ($period) {
            '7_days' => $query->whereDate(
                'plan_date',
                '>=',
                $today->copy()->subDays(7)
            ),
            '30_days' => $query->whereDate(
                'plan_date',
                '>=',
                $today->copy()->subDays(30)
            ),
            '90_days' => $query->whereDate(
                'plan_date',
                '>=',
                $today->copy()->subDays(90)
            ),
            'this_month' => $query->whereBetween(
                'plan_date',
                [
                    $today->copy()->startOfMonth(),
                    $today->copy()->endOfMonth(),
                ]
            ),
            'last_month' => $query->whereBetween(
                'plan_date',
                [
                    $today->copy()
                        ->subMonthNoOverflow()
                        ->startOfMonth(),
                    $today->copy()
                        ->subMonthNoOverflow()
                        ->endOfMonth(),
                ]
            ),
            'custom' => $this->applyCustomRange(
                $query,
                $from,
                $to
            ),
            default => null,
        };
    }

    private function applyCustomRange(
        $query,
        ?string $from,
        ?string $to
    ): void {
        if ($from) {
            $query->whereDate(
                'plan_date',
                '>=',
                Carbon::parse($from)
            );
        }

        if ($to) {
            $query->whereDate(
                'plan_date',
                '<=',
                Carbon::parse($to)
            );
        }
    }

    private function backToDate(
        string $date,
        ?string $success = null
    ): RedirectResponse {
        $response = redirect()->route(
            'daily-planner.index',
            ['date' => $date]
        );

        return $success
            ? $response->with('success', $success)
            : $response;
    }
}
