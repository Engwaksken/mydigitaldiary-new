<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Generic CRUD base controller.
 *
 * Every "personal monitoring" module (Plans, Income, Budgets, Expenses,
 * Diet, Sleep, Health, Projects, Reminders) shares the same list / create /
 * edit / delete behaviour, scoped to the logged-in user. Concrete
 * controllers only declare the model, the field definitions (used to build
 * the table + the form), the validation rules, and a Font Awesome icon +
 * accent color used to give each module a distinct visual identity while
 * staying visually consistent (see crud/index.blade.php and crud/form.blade.php).
 */
abstract class CrudController extends Controller
{
    /** Fully-qualified Eloquent model class, e.g. \App\Models\Plan::class */
    protected string $model;

    /** Route/view group name, e.g. "plans" -> plans.index, plans.store... */
    protected string $routeName;

    /** Human friendly singular title, e.g. "Plan" */
    protected string $title;

    /** Font Awesome icon class shown next to the page title, e.g. "fa-solid fa-list-check" */
    protected string $icon = 'fa-solid fa-table-list';

    /** Tailwind color name used for the icon badge/accents, e.g. "indigo", "emerald", "rose" */
    protected string $accent = 'indigo';

    /** Whether archived records should be omitted from this module's web views. */
    protected bool $excludeArchived = false;

    /**
     * Field definitions used to render both the table and the form.
     * Each entry: ['name' => '', 'label' => '', 'type' => 'text|textarea|number|date|time|select', 'options' => [], 'required' => bool]
     */
    protected array $fields = [];

    /** Optional index columns; forms and detail modals still use all fields. */
    protected array $tableColumns = [];

    /**
     * Fields as shown to users: "(optional)" never appears in a label
     * (forms show it in the placeholder instead), so it is stripped here
     * for the form, table headers and the details view alike.
     */
    protected function viewFields(array $fields): array
    {
        return array_map(function (array $field) {
            if (isset($field['label'])) {
                $field['label'] = trim(preg_replace('/\s*\(optional\)|,\s*optional(?=\))/i', '', $field['label']));
            }

            return $field;
        }, $fields);
    }

    /** Laravel validation rules for store/update */
    protected array $rules = [];

    /**
     * Which column represents "when this happened" for the period filter
     * below — defaults to created_at (always present, works for every
     * module with zero per-controller setup) but a subclass can override
     * with something more semantically relevant (e.g. ExpenseController
     * overriding to 'spent_at', so "this month" means the month it was
     * SPENT, not the month it was entered into the app).
     */
    protected string $dateField = 'created_at';

    public function index(Request $request)
    {
        $query = $this->model::where('user_id', $request->user()->id);

        if ($this->excludeArchived) {
            $query->where('is_archived', false);
        }

        return $this->renderIndex($request, $query);
    }

    /**
     * Applies search + period filtering to an arbitrary base query, then
     * renders crud.index with every variable it needs — search, period,
     * calendar, etc. Factored out specifically so controllers that
     * OVERRIDE index() with their own custom base query (MeetingController's
     * cross-user visibility join, ProjectTaskController/
     * SavingsContributionController's parent-scoped queries) can still get
     * search/period/calendar support by calling this instead of
     * duplicating all of it — and, going forward, so adding a new feature
     * here doesn't silently break those three the way this one did the
     * first time (they'd crashed with "undefined variable" once the base
     * index() started passing variables they didn't know to pass too).
     */
    protected function renderIndex(Request $request, $query, array $extra = [], ?callable $applyOrder = null, int $perPage = 15)
    {
        // Search — dynamically built from whichever of this module's
        // $fields are actual free-text columns (text/textarea), so this
        // works for every module without listing searchable columns by
        // hand per controller.
        $search = $request->query('q');
        if ($search) {
            $searchableColumns = collect($this->fields)
                ->filter(fn ($f) => in_array($f['type'], ['text', 'textarea'], true))
                ->pluck('name');

            if ($searchableColumns->isNotEmpty()) {
                $query->where(function ($q) use ($searchableColumns, $search) {
                    foreach ($searchableColumns as $column) {
                        $q->orWhere($column, 'like', '%' . $search . '%');
                    }
                });
            }
        }

        // Period filter — daily/weekly/monthly/range, all relative to
        // $dateField. 'range' needs both $from and $to; anything else
        // (missing dates, an unrecognized $period value) is ignored
        // rather than erroring, so a half-filled filter form just shows
        // everything instead of nothing.
        $period = $request->query('period');
        $from = $request->query('from');
        $to = $request->query('to');

        match ($period) {
            'daily' => $query->whereDate($this->dateField, now()->toDateString()),
            'weekly' => $query->whereBetween($this->dateField, [now()->startOfWeek(), now()->endOfWeek()]),
            'monthly' => $query->whereBetween($this->dateField, [now()->startOfMonth(), now()->endOfMonth()]),
            'range' => ($from && $to)
                ? $query->whereBetween($this->dateField, [$from . ' 00:00:00', $to . ' 23:59:59'])
                : null,
            default => null,
        };

        if ($applyOrder) {
            $applyOrder($query);
        } else {
            $query->orderByDesc('id');
        }

        $items = $query->paginate($perPage)->withQueryString();

        $goalModuleMap = [
            'incomes'=>'finance','budgets'=>'finance','expenses'=>'finance','debts'=>'finance','savings-contributions'=>'savings','savings-goals'=>'savings',
            'diet-logs'=>'diet','exercise-logs'=>'exercise','sleep-logs'=>'health','health-checkups'=>'health','wellbeing'=>'health',
            'projects'=>'projects','project-tasks'=>'productivity','education-plans'=>'education','spiritual-practices'=>'spiritual','notes'=>'productivity',
        ];
        $goalModule = $goalModuleMap[$this->routeName] ?? null;
        $moduleGoalSummary = null;
        if ($goalModule && \Illuminate\Support\Facades\Schema::hasTable('personal_goals')) {
            $goalQuery = \App\Models\PersonalGoal::where('user_id', $request->user()->id)
                ->where('module', $goalModule)->where('is_archived', false);
            $moduleGoalSummary = [
                'module' => $goalModule,
                'active' => (clone $goalQuery)->whereIn('status', ['not_started','in_progress'])->count(),
                'completed' => (clone $goalQuery)->where('status', 'completed')->count(),
                'average' => (int) round((clone $goalQuery)->avg('progress_percent') ?? 0),
                'latest' => (clone $goalQuery)->whereIn('status', ['not_started','in_progress'])->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")->orderBy('target_date')->first(),
            ];
        }

        return view('crud.index', array_merge([
            'items' => $items,
            'fields' => $this->viewFields($this->fields),
            'tableColumns' => $this->viewFields($this->tableColumns ?: $this->fields),
            'title' => $this->title,
            'routeName' => $this->routeName,
            'stats' => $this->stats($request),
            'icon' => $this->icon,
            'accent' => $this->accent,
            'chart' => $this->chart($request),
            'search' => $search,
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'calendar' => $this->calendarMonth($request),
            'dateFieldName' => $this->editableDateFieldName(),
            'moduleGoalSummary' => $moduleGoalSummary,
        ], $extra));
    }

    /**
     * Whichever of THIS module's own $fields actually maps to $dateField
     * — null if $dateField is 'created_at' or another column that isn't
     * one of the module's own editable fields. Used by the Calendar tab
     * to know whether clicking a day can pre-fill a date into the create
     * modal (it can, if this returns a name) or should just open the
     * modal plain (if not — e.g. Feedback, which has no date field of
     * its own to click into).
     */
    private function editableDateFieldName(): ?string
    {
        $match = collect($this->fields)->first(fn ($f) => $f['name'] === $this->dateField);

        return $match ? $match['name'] : null;
    }

    /**
     * Builds one calendar month's worth of this user's items, grouped by
     * day, for the Calendar tab — a lightweight, from-scratch month grid
     * (no FullCalendar.js or similar), consistent with how the rest of
     * this app avoids extra JS dependencies. Navigable via a `cal_month`
     * query param (e.g. ?cal_month=2026-09); defaults to the current
     * month.
     */
    private function calendarMonth(Request $request): array
    {
        $monthParam = $request->query('cal_month', now()->format('Y-m'));

        try {
            $start = \Illuminate\Support\Carbon::createFromFormat('Y-m', $monthParam)->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
        }

        $end = $start->copy()->endOfMonth();

        $labelField = collect($this->fields)->first(fn ($f) => in_array($f['type'], ['text', 'textarea'], true))['name'] ?? null;

        $items = $this->model::where('user_id', $request->user()->id)
            ->when($this->excludeArchived, fn ($query) => $query->where('is_archived', false))
            ->whereBetween($this->dateField, [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get();

        $byDay = $items->groupBy(function ($item) {
            $value = data_get($item, $this->dateField);
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : null;
        })->filter(fn ($group, $key) => $key !== null)
          ->map(function ($dayItems) use ($labelField) {
              // Deliberately NOT ->all() here — crud/index.blade.php's
              // calendar day cells call ->take(2) and ->count() on this,
              // both Collection methods. ->all() would convert it to a
              // plain array, which has neither, and would only actually
              // throw on a day that has items — an empty day falls back
              // to the collect() default a few lines below, silently
              // masking the bug until a real item showed up.
              return $dayItems->map(function ($item) use ($labelField) {
                  // Same field-value formatting the table's Edit button
                  // uses (dates/times turned into plain strings their
                  // <input> types expect) — this is what lets clicking a
                  // calendar pill open the exact same edit modal the
                  // table's Edit button does, via the same
                  // openCrudEditModal() JS function, no new code needed
                  // for that part.
                  $values = [];
                  foreach ($this->fields as $f) {
                      $v = data_get($item, $f['name']);
                      if (is_object($v) && method_exists($v, 'format')) {
                          $v = in_array($f['type'], ['datetime-local', 'datetime-native'], true) ? $v->format('Y-m-d\TH:i') : $v->format('Y-m-d');
                      }
                      $values[$f['name']] = $v;
                  }

                  return [
                      'id' => $item->id,
                      'label' => $labelField ? \Illuminate\Support\Str::limit((string) data_get($item, $labelField), 24) : ('#' . $item->id),
                      'updateUrl' => route($this->routeName . '.update', $item->id),
                      'values' => $values,
                  ];
              });
          });

        return [
            'month' => $start,
            'prevMonth' => $start->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $start->copy()->addMonth()->format('Y-m'),
            'byDay' => $byDay,
        ];
    }

    /**
     * Optional chart shown below the stats cards on the index page.
     * Return null (the default) to show no chart. When overridden, return:
     *   [
     *     'type' => 'line'|'bar'|'doughnut',
     *     'title' => string,
     *     'labels' => array,
     *     'datasets' => [['label' => ?string, 'data' => array], ...],
     *   ]
     * Rendered generically by crud/index.blade.php via Chart.js — no view
     * changes needed in the concrete controller.
     */
    protected function chart(Request $request): ?array
    {
        return null;
    }

    /**
     * Small summary cards shown above the table on the index page. Default
     * implementation is generic (total / this week / this month counts) —
     * override in a concrete controller for domain-specific numbers, e.g.
     * "Spent this month: $420" instead of just a row count. Each entry:
     * ['label' => string, 'value' => string].
     */
    protected function stats(Request $request): array
    {
        $userId = $request->user()->id;
        $base = $this->model::where('user_id', $userId);

        return [
            ['label' => 'Total', 'value' => (string) $base->count()],
            ['label' => 'Added this week', 'value' => (string) (clone $base)->where('created_at', '>=', now()->startOfWeek())->count()],
            ['label' => 'Added this month', 'value' => (string) (clone $base)->where('created_at', '>=', now()->startOfMonth())->count()],
        ];
    }

    public function create(Request $request)
    {
        return view('crud.form', [
            'item' => new $this->model,
            'fields' => $this->viewFields($this->fields),
            'title' => $this->title,
            'routeName' => $this->routeName,
            'icon' => $this->icon,
            'accent' => $this->accent,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules);
        $data['user_id'] = $request->user()->id;

        $item = $this->model::create($data);

        if ($request->boolean('set_reminder')) {
            $this->createLinkedReminder($request, $item);
        }

        $this->afterSave($request, $item, wasCreated: true);

        return redirect()
            ->route($this->routeName . '.index')
            ->with('success', "{$this->title} created.");
    }

    public function edit(Request $request, int $id)
    {
        $item = $this->model::where('user_id', $request->user()->id)->findOrFail($id);

        return view('crud.form', [
            'item' => $item,
            'fields' => $this->viewFields($this->fields),
            'title' => $this->title,
            'routeName' => $this->routeName,
            'icon' => $this->icon,
            'accent' => $this->accent,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $item = $this->model::where('user_id', $request->user()->id)->findOrFail($id);

        $data = $request->validate($this->rules);
        $item->update($data);

        if ($request->boolean('set_reminder')) {
            $this->createLinkedReminder($request, $item);
        }

        $this->afterSave($request, $item, wasCreated: false);

        return redirect()
            ->route($this->routeName . '.index')
            ->with('success', "{$this->title} updated.");
    }

    /**
     * No-op by default — a hook for subclasses that need to react to a
     * create/update without duplicating store()/update() wholesale.
     * MeetingController overrides this to send invitation emails to any
     * attendees entered on the form.
     */
    protected function afterSave(Request $request, $item, bool $wasCreated): void
    {
        //
    }

    public function destroy(Request $request, int $id)
    {
        $item = $this->model::where('user_id', $request->user()->id)->findOrFail($id);
        $item->delete();

        return back()->with('success', "{$this->title} deleted.");
    }

    /**
     * Deletes multiple records from the current module in one request.
     * Ownership is enforced in the query so a forged ID belonging to
     * another user is ignored rather than deleted.
     */
    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $deleted = $this->model::where('user_id', $request->user()->id)
            ->whereIn('id', $data['ids'])
            ->delete();

        return back()->with('success', $deleted === 1
            ? "1 {$this->title} deleted."
            : "{$deleted} {$this->title}s deleted.");
    }

    /**
     * Powers the "Also set a reminder for this" checkbox added to every
     * create/edit modal (see crud/index.blade.php's modal markup) — opt-in
     * on purpose, rather than always auto-creating one, since a reminder
     * for every single Expense/Diet Log entry would be far more noise
     * than help. Silently does nothing if this module's date field has no
     * value to remind about (e.g. an optional date left blank).
     */
    protected function createLinkedReminder(Request $request, $item): void
    {
        $dateValue = data_get($item, $this->dateField);
        if (! $dateValue) {
            return;
        }

        $labelField = collect($this->fields)->first(fn ($f) => in_array($f['type'], ['text', 'textarea'], true))['name'] ?? null;
        $label = $labelField ? data_get($item, $labelField) : ('#' . $item->id);

        $request->user()->reminders()->create([
            'title' => "{$this->title}: {$label}",
            'message' => "Reminder for your {$this->title} \"{$label}\".",
            'frequency' => 'once',
            'next_run_at' => \Illuminate\Support\Carbon::parse($dateValue),
            'channel' => 'mail',
            'is_active' => true,
            'alarm_enabled' => true,
        ]);
    }
}
