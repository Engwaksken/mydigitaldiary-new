@extends('layouts.app')

@section('title', $title . 's')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-{{ $accent }}-100 text-{{ $accent }}-600 flex items-center justify-center shadow-sm shrink-0">
                <i class="{{ $icon }} text-xl" aria-hidden="true"></i>
            </div>
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">{{ $title }}s</h1>
                @if (!empty($nudge))
                    <p class="pm-nudge"><i class="fa-solid fa-star" aria-hidden="true"></i><span>{{ $nudge }}</span></p>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if (view()->exists('crud.extras.' . $routeName . '-header'))
                @include('crud.extras.' . $routeName . '-header')
            @endif
            @if (auth()->user()->hasActiveAccess())
                <button type="button" onclick="openCrudCreateModal()"
                        class="inline-flex items-center justify-center gap-2 btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    <span>Add {{ $title }}</span>
                </button>
            @else
                <span class="inline-flex items-center gap-2 text-sm text-slate-400 px-4 py-2.5" title="Renew your subscription to add new records">
                    <i class="fa-solid fa-lock text-xs" aria-hidden="true"></i>
                    Renew to add
                </span>
            @endif
        </div>
    </div>

    @if (view()->exists('crud.extras.' . $routeName . '-top'))
        @include('crud.extras.' . $routeName . '-top')
    @endif

    @if ($routeName === 'diet-logs')
        <section data-diet-tab-panel="stats" hidden>
            @include('crud.extras.diet-logs-history')
    @elseif ($routeName === 'sleep-logs')
        <section data-sleep-tab-panel="stats" hidden>
    @endif

    @if (!empty($moduleGoalSummary))
        <div class="mb-5 rounded-xl border border-violet-100 bg-violet-50/60 px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-lg bg-white text-violet-600 flex items-center justify-center shadow-sm shrink-0">
                    <i class="fa-solid fa-bullseye" aria-hidden="true"></i>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="text-sm font-semibold text-slate-800">Goals for this area</p>
                        <span class="text-xs text-violet-700 bg-white rounded-full px-2 py-0.5">{{ $moduleGoalSummary['active'] }} active</span>
                        <span class="text-xs text-slate-500">{{ $moduleGoalSummary['average'] }}% avg. progress</span>
                    </div>
                    @if ($moduleGoalSummary['latest'])
                        <p class="text-xs text-slate-600 mt-1 truncate">Focus: {{ $moduleGoalSummary['latest']->title }} &middot; {{ $moduleGoalSummary['latest']->progress_percent }}%</p>
                    @else
                        <p class="text-xs text-slate-500 mt-1">No goal yet.</p>
                    @endif
                </div>
            </div>
            <a href="{{ route('personal-goals.index', ['module' => $moduleGoalSummary['module']]) }}"
               class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-white border border-violet-200 text-violet-700 text-sm font-semibold hover:bg-violet-100 transition-colors shrink-0">
                <i class="fa-solid fa-crosshairs text-xs" aria-hidden="true"></i>
                {{ $moduleGoalSummary['active'] ? 'View goals' : 'Add goal' }}
            </a>
        </div>
    @endif

    {{-- Compact summary strip: a few glanceable numbers, always visible
         above the Chart/Table/Calendar tabs. --}}
    @if (!empty($stats))
        @php
            $pmStatCount = count($stats);
            $pmStatCols = $pmStatCount <= 5 ? $pmStatCount : ($pmStatCount % 3 === 0 ? 3 : 4);
        @endphp
        <div class="pm-summary mb-6" style="--pm-summary-cols: {{ $pmStatCols }}" aria-label="{{ $title }} summary">
            @foreach ($stats as $stat)
                @php
                    $statColor = $stat['color'] ?? $accent;
                    $statIcon = $stat['icon'] ?? $icon;
                    $statRoute = $stat['route'] ?? ($stat['url'] ?? ($stat['link'] ?? null));
                @endphp
                @if ($statRoute)
                    <a href="{{ $statRoute }}" class="pm-summary-cell">
                @else
                    <div class="pm-summary-cell">
                @endif
                        <span class="pm-summary-icon bg-{{ $statColor }}-50 text-{{ $statColor }}-600">
                            <i class="{{ $statIcon }}" aria-hidden="true"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="pm-summary-label" title="{{ $stat['label'] }}">{{ $stat['label'] }}</span>
                            <span class="pm-summary-value" title="{{ $stat['value'] }}">{{ $stat['value'] }}</span>
                        </span>
                @if ($statRoute)
                    </a>
                @else
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    @php
        // Chart and Table are tabbed; Table is the default active
        // tab — visiting a module page is usually about the actual
        // records, with the chart as a secondary, occasional-use view.
        // Tabs are always shown (previously only when a chart existed)
        // since Calendar is always available, with or without a chart.
        // If there's no chart yet, only Table/Calendar show — no strip.
        $pmShowTabs = true;

        // Resolve bulk-delete support once in a normal Blade PHP block.
        // Keeping the assignment here avoids parser issues caused by the
        // inline @php(...) assignment that was added beside the table panel.
        $pmHasBulkDelete = \Illuminate\Support\Facades\Route::has($routeName . '.bulk-destroy')
            && auth()->user()->hasActiveAccess();
    @endphp

    @if ($pmShowTabs)
        <div role="tablist" aria-label="{{ $title }} sections" class="flex gap-1 border-b border-slate-200 mb-6">
            @if (!empty($chart))
                <button
                    type="button" role="tab" id="pm-crud-tab-chart" aria-controls="pm-crud-panel-chart"
                    aria-selected="false" tabindex="-1" data-tab="chart"
                    onclick="pmSelectCrudTab('chart')" onkeydown="pmCrudTabKeydown(event, 'chart')"
                    class="pm-crud-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300"
                >
                    <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                    <span>Chart</span>
                </button>
            @endif
            <button
                type="button" role="tab" id="pm-crud-tab-table" aria-controls="pm-crud-panel-table"
                aria-selected="true" tabindex="0" data-tab="table"
                onclick="pmSelectCrudTab('table')" onkeydown="pmCrudTabKeydown(event, 'table')"
                class="pm-crud-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-[var(--brand-1)] text-[var(--brand-1)]"
            >
                <i class="fa-solid fa-table-list" aria-hidden="true"></i>
                <span>{{ $title }}s</span>
            </button>
            <button
                type="button" role="tab" id="pm-crud-tab-calendar" aria-controls="pm-crud-panel-calendar"
                aria-selected="false" tabindex="-1" data-tab="calendar"
                onclick="pmSelectCrudTab('calendar')" onkeydown="pmCrudTabKeydown(event, 'calendar')"
                class="pm-crud-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300"
            >
                <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                <span>Calendar</span>
            </button>
        </div>
    @endif

    @php
        $countdownDateFields = [
            'projects' => 'deadline',
            'personal-goals' => 'target_date',
            'health-checkups' => 'next_due_date',
            'debts' => 'due_date',
            'education-plans' => 'target_completion_date',
        ];
        $countdownField = $countdownDateFields[$routeName] ?? null;
    @endphp

    @if (!empty($chart))
        @php
            // Built as a plain string here rather than nesting loop/conditional
            // directives directly inside the aria-label="..." attribute — that
            // inline-nested-directives-inside-an-attribute pattern is fragile
            // to parse and caused a real ParseError in testing.
            $chartAriaParts = [];
            foreach ($chart['labels'] as $i => $label) {
                $pointParts = [];
                foreach ($chart['datasets'] as $ds) {
                    $pointParts[] = trim(($ds['label'] ?? '') . ' ' . ($ds['data'][$i] ?? 0));
                }
                $chartAriaParts[] = $label . ' ' . implode(' ', $pointParts);
            }
            $chartAriaLabel = $chart['title'] . ': ' . implode(', ', $chartAriaParts) . '.';
        @endphp
        <div role="tabpanel" id="pm-crud-panel-chart" aria-labelledby="pm-crud-tab-chart" tabindex="0"
             class="pm-crud-panel" hidden>
            <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 p-5 mb-6">
                <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                    <i class="{{ $icon }} text-{{ $accent }}-500" aria-hidden="true"></i>
                    {{ $chart['title'] }}
                </h2>
                {{-- Fixed height + maintainAspectRatio:false (in the JS
                     below) gives precise control over the rendered size 
                     without both, Chart.js sizes itself from the
                     container's width using a fairly large default aspect
                     ratio, rendering much bigger than intended. --}}
                <div class="h-48">
                    <canvas id="crud-chart" role="img" aria-label="{{ $chartAriaLabel }}"></canvas>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
        <script>
            var pmCrudChartConfig = @json($chart);
            var pmCrudChartInstance = null;

            // Deferred until the Chart tab is actually shown (or immediately
            // if there are no tabs at all, i.e. this is the only section) 
            // Chart.js measures the canvas at construction time, and a
            // canvas inside a display:none ancestor measures as 0x0, which
            // renders blank even after the tab is later revealed.
            function pmInitCrudChartIfNeeded() {
                if (pmCrudChartInstance) { return; }

                var palette = ['#6366f1', '#10b981', '#f43f5e', '#f59e0b', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6', '#84cc16', '#f97316'];
                var chartType = pmCrudChartConfig.type;
                var datasets = pmCrudChartConfig.datasets.map(function (ds, i) {
                    if (chartType === 'doughnut') {
                        return Object.assign({}, ds, { backgroundColor: palette, borderWidth: 2, borderColor: '#ffffff' });
                    }
                    return Object.assign({}, ds, {
                        backgroundColor: chartType === 'line' ? palette[i] + '22' : palette[i],
                        borderColor: palette[i],
                        fill: chartType === 'line',
                        tension: 0.3,
                        borderRadius: chartType === 'bar' ? 4 : 0,
                    });
                });

                pmCrudChartInstance = new Chart(document.getElementById('crud-chart'), {
                    type: chartType,
                    data: { labels: pmCrudChartConfig.labels, datasets: datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                // Always shown now — a doughnut's colored
                                // slices are meaningless without a key
                                // mapping each color back to its category,
                                // and this used to be hidden for every
                                // single-dataset doughnut (i.e. nearly all
                                // of them, since a doughnut chart only ever
                                // has one dataset here).
                                display: true,
                                position: 'bottom',
                                labels: chartType === 'doughnut' ? {
                                    // Default doughnut legend only shows the
                                    // label (e.g. "Groceries") — this adds
                                    // the actual value too (e.g.
                                    // "Groceries: 120"), so the key doubles
                                    // as a real reference, not just a color
                                    // guide.
                                    generateLabels: function (chart) {
                                        var data = chart.data;
                                        if (!data.labels.length || !data.datasets.length) { return []; }
                                        var ds = data.datasets[0];
                                        return data.labels.map(function (label, i) {
                                            return {
                                                text: label + ': ' + ds.data[i],
                                                fillStyle: Array.isArray(ds.backgroundColor) ? ds.backgroundColor[i] : ds.backgroundColor,
                                                strokeStyle: ds.borderColor || '#ffffff',
                                                lineWidth: ds.borderWidth || 0,
                                                hidden: false,
                                                index: i,
                                            };
                                        });
                                    },
                                } : {},
                            },
                        },
                        scales: chartType === 'doughnut' ? {} : { y: { beginAtZero: true } },
                    },
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                // No tabs at all (chart is the only section) means the
                // panel is never hidden in the first place — safe to
                // initialize right away.
                var chartPanel = document.getElementById('pm-crud-panel-chart');
                if (chartPanel && !chartPanel.hasAttribute('hidden')) {
                    pmInitCrudChartIfNeeded();
                }
            });
        </script>
    @endif

    <div role="tabpanel" id="pm-crud-panel-table" aria-labelledby="pm-crud-tab-table" tabindex="0" class="pm-crud-panel">
    <form method="GET" action="{{ route($routeName . '.index') }}" class="flex flex-wrap items-end gap-3 mb-4">
        <div class="flex-1 min-w-[180px] max-w-xs">
            <label for="crud-search-{{ $routeName }}" class="sr-only">Search {{ strtolower($title) }}s</label>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm" aria-hidden="true"></i>
                <input type="search" id="crud-search-{{ $routeName }}" name="q" value="{{ $search }}"
                       placeholder="Search {{ strtolower($title) }}s..." class="pl-9 pm-input text-sm">
            </div>
        </div>

        <div>
            <label for="crud-period-{{ $routeName }}" class="sr-only">Filter by period</label>
            <select id="crud-period-{{ $routeName }}" name="period" onchange="pmToggleCrudDateRange(this)" class="pm-input text-sm">
                <option value="" @selected(!$period)>All time</option>
                <option value="daily" @selected($period === 'daily')>Today</option>
                <option value="weekly" @selected($period === 'weekly')>This week</option>
                <option value="monthly" @selected($period === 'monthly')>This month</option>
                <option value="range" @selected($period === 'range')>Custom range...</option>
            </select>
        </div>

        <div id="crud-date-range-{{ $routeName }}" class="flex items-end gap-2" style="{{ $period === 'range' ? '' : 'display: none;' }}">
            <div>
                <label for="crud-from-{{ $routeName }}" class="sr-only">From date</label>
                <input type="date" id="crud-from-{{ $routeName }}" name="from" value="{{ $from }}" class="pm-input text-sm">
            </div>
            <span class="text-slate-400 text-sm pb-2">to</span>
            <div>
                <label for="crud-to-{{ $routeName }}" class="sr-only">To date</label>
                <input type="date" id="crud-to-{{ $routeName }}" name="to" value="{{ $to }}" class="pm-input text-sm">
            </div>
        </div>

        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
            Filter
        </button>
        @if ($search || $period)
            <a href="{{ route($routeName . '.index') }}" class="text-sm text-slate-500 hover:text-slate-700 transition-colors pb-2.5">
                Clear
            </a>
        @endif
    </form>

    @if ($pmHasBulkDelete)
        <form id="pm-crud-bulk-delete-form" method="POST" action="{{ route($routeName . '.bulk-destroy') }}">
            @csrf
            @method('DELETE')
        </form>
        <div id="pm-crud-bulk-bar" class="hidden mb-3 items-center justify-between gap-3 rounded-xl border border-rose-100 bg-rose-50 px-4 py-3">
            <span class="text-sm font-medium text-rose-700"><span id="pm-crud-selected-count">0</span> selected</span>
            <button type="button" onclick="pmConfirmCrudBulkDelete()" class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700">
                <i class="fa-solid fa-trash-can"></i> Delete selected
            </button>
        </div>
    @endif

    @if ($routeName === 'savings-goals')
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @forelse ($items as $item)
                @php
                    $saved = (float) ($item->saved_amount ?? 0);
                    $target = max(0.0, (float) ($item->target_amount ?? 0));
                    $remaining = max(0.0, $target - $saved);
                    $progress = $target > 0 ? min(100, round(($saved / $target) * 100, 1)) : 0;

                    $statusLabel = $fields[3]['options'][$item->status] ?? ucfirst(str_replace('_', ' ', (string) $item->status));
                    $statusClasses = match ($item->status) {
                        'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                        'paused' => 'bg-amber-50 text-amber-700 border-amber-100',
                        default => 'bg-sky-50 text-sky-700 border-sky-100',
                    };

                    $rowValues = [];
                    foreach ($fields as $fieldConfig) {
                        $value = data_get($item, $fieldConfig['name']);
                        if (is_object($value) && method_exists($value, 'format')) {
                            if (in_array($fieldConfig['type'], ['datetime-local', 'datetime-native'], true)) {
                                $value = $value->format('Y-m-d\TH:i');
                            } elseif ($fieldConfig['type'] === 'time') {
                                $value = $value->format('H:i');
                            } else {
                                $value = $value->format('Y-m-d');
                            }
                        }
                        $rowValues[$fieldConfig['name']] = $value;
                    }
                @endphp

                <article class="pm-card-bg rounded-2xl border border-slate-100 border-l-4 border-l-emerald-400 shadow-sm p-5 hover:shadow-md transition-shadow min-w-0">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-bold text-slate-800 text-base truncate" title="{{ $item->name }}">
                                {{ $item->name }}
                            </h3>
                            <div class="mt-2">
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}">
                                    {{ $statusLabel }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button"
                                    onclick='openCrudViewModal({{ json_encode($rowValues) }}, {{ $item->id }}, true)'
                                    class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-slate-800 hover:bg-slate-50"
                                    title="View savings goal"
                                    aria-label="View {{ $item->name }}">
                                <i class="fa-solid fa-eye text-xs"></i>
                            </button>

                            @if (auth()->user()->hasActiveAccess())
                                <button type="button"
                                        onclick='openCrudEditModal({{ json_encode(route($routeName . '.update', $item->id)) }}, {{ json_encode($rowValues) }})'
                                        class="w-8 h-8 rounded-lg border border-emerald-100 bg-emerald-50 text-emerald-600 hover:bg-emerald-100"
                                        title="Edit savings goal"
                                        aria-label="Edit {{ $item->name }}">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                </button>

                                <button type="button"
                                        onclick='openCrudDeleteModal({{ json_encode(route($routeName . '.destroy', $item->id)) }}, {{ json_encode($item->name) }})'
                                        class="w-8 h-8 rounded-lg border border-rose-100 bg-rose-50 text-rose-500 hover:bg-rose-100"
                                        title="Delete savings goal"
                                        aria-label="Delete {{ $item->name }}">
                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mt-5">
                        <div class="rounded-xl bg-emerald-50/60 p-3">
                            <p class="text-[11px] uppercase tracking-wide font-semibold text-emerald-600">Saved</p>
                            <p class="font-bold text-slate-800 mt-1">{{ format_money($saved) }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-[11px] uppercase tracking-wide font-semibold text-slate-500">Target</p>
                            <p class="font-bold text-slate-800 mt-1">{{ format_money($target) }}</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="flex items-center justify-between gap-3 text-xs mb-1.5">
                            <span class="font-semibold text-slate-600">Progress</span>
                            <span class="font-bold text-emerald-700">{{ $progress }}%</span>
                        </div>
                        <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full bg-emerald-500"
                                 style="width: {{ $progress }}%"
                                 role="progressbar"
                                 aria-valuemin="0"
                                 aria-valuemax="100"
                                 aria-valuenow="{{ $progress }}"></div>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <p class="text-slate-400">Remaining</p>
                            <p class="font-semibold text-slate-700 mt-0.5">{{ format_money($remaining) }}</p>
                        </div>
                        <div>
                            <p class="text-slate-400">Target date</p>
                            <p class="font-semibold text-slate-700 mt-0.5">
                                {{ $item->target_date ? \Illuminate\Support\Carbon::parse($item->target_date)->format('d M Y') : 'No date' }}
                            </p>
                            @if ($item->target_date)
                                 <x-countdown :date="$item->target_date" :status="$item->status" />
                            @endif
                        </div>
                    </div>

                    @if (filled($item->notes))
                        <p class="mt-4 text-xs text-slate-500 line-clamp-2">
                            {{ \Illuminate\Support\Str::limit($item->notes, 120) }}
                        </p>
                    @endif
                </article>
            @empty
                <div class="md:col-span-2 xl:col-span-3 pm-card-bg rounded-xl border border-slate-100 p-10 text-center text-slate-400">
                    <i class="fa-solid fa-piggy-bank text-3xl mb-3 block opacity-30"></i>
                    <p>No savings goals found.</p>
                    @if ($search)
                        <p class="text-xs mt-1">Try a different search term.</p>
                    @endif
                </div>
            @endforelse
        </div>
    @else
    @php
        // Compact layout: title (+ folded details) | status | date | amount | actions.
        // Slots come from CrudController::tableLayout(); every field is
        // still shown in full in the View modal.
        $pmColumnsByName = collect($fields)->keyBy('name')->merge(collect($tableColumns)->keyBy('name'));
        $pmPrimaryField = $tableLayout['primary'] ? $pmColumnsByName->get($tableLayout['primary']) : null;
        $pmStatusField = $tableLayout['status'] ? $pmColumnsByName->get($tableLayout['status']) : null;
        $pmDateField = $tableLayout['date'] ? $pmColumnsByName->get($tableLayout['date']) : null;
        $pmAmountField = $tableLayout['amount'] ? $pmColumnsByName->get($tableLayout['amount']) : null;
        $pmNoteField = $tableLayout['note'] ? $pmColumnsByName->get($tableLayout['note']) : null;
        $pmMetaFields = collect($tableLayout['meta'])->map(fn ($name) => $pmColumnsByName->get($name))->filter()->values();
        // Short column/meta labels: drop hints like "(e.g. …)" and "Date & Time".
        $pmShortLabel = fn (string $label) => trim(preg_replace(['/\s*\(.*?\)/', '/\s*Date\s*&\s*Time\b/i'], '', $label)) ?: $label;
        $pmTableColspan =2 + ($pmHasBulkDelete ? 1 : 0) + ($pmStatusField ? 1 : 0) + ($pmDateField ? 1 : 0) + ($pmAmountField ? 1 : 0);

        $pmStatusTone = function ($value): string {
            $key = strtolower((string) $value);
            return match (true) {
                in_array($key, ['completed', 'complete', 'paid', 'done', 'active', 'received', 'achieved', 'great', 'good', 'resolved', 'closed'], true) => 'is-green',
                in_array($key, ['in_progress', 'pending', 'outstanding', 'paused', 'on_hold', 'medium', 'okay'], true) => 'is-amber',
                in_array($key, ['overdue', 'cancelled', 'canceled', 'failed', 'missed', 'high', 'low_mood', 'rejected'], true) => 'is-rose',
                in_array($key, ['scheduled', 'not_started', 'planned', 'open', 'new', 'upcoming'], true) => 'is-sky',
                default => 'is-slate',
            };
        };

        $pmRenderCell = function ($field, $item, $rowValues, bool $withCountdown = false) use ($routeName, $countdownField) {
            return trim(view('crud._cell', [
                'field' => $field,
                'item' => $item,
                'rowValues' => $rowValues,
                'routeName' => $routeName,
                'countdownField' => $countdownField,
                'withCountdown' => $withCountdown,
            ])->render());
        };
    @endphp
    <div class="pm-dt-wrap" role="region" aria-label="{{ $title }}s table">
        <table class="pm-dt">
            <caption class="sr-only">List of your {{ strtolower($title) }}s. Select a title to view all details.</caption>
            <thead>
                <tr>
                    @if ($pmHasBulkDelete)
                        <th scope="col" class="pm-dt-check">
                            <input type="checkbox" id="pm-crud-select-all" onchange="pmToggleAllCrudRows(this)" class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]" aria-label="Select all {{ strtolower($title) }} records">
                            <label for="pm-crud-select-all" class="pm-dt-check-label">Select all</label>
                        </th>
                    @endif
                    <th scope="col">{{ $pmPrimaryField ? $pmShortLabel($pmPrimaryField['label']) : $title }}</th>
                    @if ($pmStatusField)
                        <th scope="col">{{ $pmShortLabel($pmStatusField['label']) }}</th>
                    @endif
                    @if ($pmDateField)
                        <th scope="col">{{ $pmShortLabel($pmDateField['label']) }}</th>
                    @endif
                    @if ($pmAmountField)
                        <th scope="col" class="pm-dt-num">{{ $pmShortLabel($pmAmountField['label']) }}</th>
                    @endif
                    <th scope="col" class="pm-dt-actions"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    @php
                        $rowLabel = $item->{$tableLayout['primary'] ?? $fields[0]['name']} ?? null;
                        if (is_object($rowLabel) && method_exists($rowLabel, 'format')) {
                            $rowLabel = $rowLabel->format('d M Y');
                        } elseif ($pmPrimaryField && isset($pmPrimaryField['options']) && is_scalar($rowLabel) && array_key_exists($rowLabel, $pmPrimaryField['options'])) {
                            $rowLabel = $pmPrimaryField['options'][$rowLabel];
                        }
                        $rowLabel = is_string($rowLabel) || is_numeric($rowLabel) ? (string) $rowLabel : ('#' . $item->id);

                        // Formatted values for THIS row, used by the JS modal
                        // to populate the shared edit form when its Edit
                        // button is clicked. Dates/times are turned into the
                        // plain strings their <input> types expect.
                        $rowValues = [];
                        foreach ($fields as $f) {
                            $v = $item->{$f['name']} ?? null;
                            if (is_object($v) && method_exists($v, 'format')) {
                                if (in_array($f['type'], ['datetime-local', 'datetime-native'], true)) {
                                    $v = $v->format('Y-m-d\TH:i');
                                } elseif ($f['type'] === 'time') {
                                    $v = $v->format('H:i');
                                } else {
                                    $v = $v->format('Y-m-d');
                                }
                            } elseif ($f['type'] === 'time' && is_string($v) && $v !== '') {
                                $v = substr($v, 0, 5);
                            }
                            $rowValues[$f['name']] = $v;
                        }

                        $pmIsOwner = ($item->user_id ?? null) == auth()->id();
                        $pmPrimaryHtml = $pmPrimaryField ? $pmRenderCell($pmPrimaryField, $item, $rowValues) : '';
                        if ($pmPrimaryHtml === '') {
                            $pmPrimaryHtml = e($rowLabel !== '' ? $rowLabel : ('#' . $item->id));
                        }

                        // Muted second line: short secondary fields, then a note preview.
                        $pmSubParts = [];
                        if ($routeName === 'expenses') {
                            if ($item->relationLoaded('budget') && $item->budget) {
                                $pmSubParts[] = '<span class="pm-dt-chip"><i class="fa-solid fa-wallet text-[10px]" aria-hidden="true"></i>' . e(\Illuminate\Support\Str::limit((string) $item->budget->category, 28)) . '</span>';
                            }
                            $pmExpenseItemNames = $item->items->pluck('description')->filter(fn ($d) => filled($d))->map(fn ($d) => trim((string) $d))->values();
                            if ($pmExpenseItemNames->isNotEmpty()) {
                                $pmSubParts[] = '<span title="' . e($pmExpenseItemNames->implode(', ')) . '">' . e($pmExpenseItemNames->count() . ' ' . ($pmExpenseItemNames->count() === 1 ? 'item' : 'items') . ': ' . \Illuminate\Support\Str::limit($pmExpenseItemNames->implode(', '), 48)) . '</span>';
                            }
                        }
                        foreach ($pmMetaFields as $metaField) {
                            if ($metaField['type'] === 'checkbox') {
                                if ($item->{$metaField['name']}) {
                                    $pmSubParts[] = '<span><i class="fa-solid fa-check text-[10px] text-emerald-500" aria-hidden="true"></i> ' . e($metaField['label']) . '</span>';
                                }
                                continue;
                            }
                            $metaHtml = $pmRenderCell($metaField, $item, $rowValues);
                            if ($metaHtml === '') {
                                continue;
                            }
                            $needsLabel = (in_array($metaField['type'], ['number', 'date', 'datetime-local', 'datetime-native', 'time'], true) || str_ends_with($metaField['name'], '_time')) && empty($metaField['money']);
                            $pmSubParts[] = '<span>' . ($needsLabel ? '<span class="pm-dt-sub-label">' . e(\Illuminate\Support\Str::limit($pmShortLabel($metaField['label']), 22, '')) . '</span> ' : '') . $metaHtml . '</span>';
                        }
                        if ($pmNoteField) {
                            $noteHtml = $pmRenderCell($pmNoteField, $item, $rowValues);
                            if ($noteHtml !== '') {
                                $pmSubParts[] = '<span class="pm-dt-note">' . $noteHtml . '</span>';
                            }
                        }

                        $pmStatusValue = $pmStatusField ? $item->{$pmStatusField['name']} : null;
                        $pmStatusLabel = $pmStatusField && $pmStatusValue !== null && $pmStatusValue !== ''
                            ? ($pmStatusField['options'][$pmStatusValue] ?? ucfirst(str_replace('_', ' ', (string) $pmStatusValue)))
                            : null;
                    @endphp
                    <tr class="{{ $pmHasBulkDelete ? 'has-check' : '' }}">
                        @if ($pmHasBulkDelete)
                            <td class="pm-dt-check">
                                @if ($pmIsOwner)
                                    <input type="checkbox" name="ids[]" value="{{ $item->id }}" form="pm-crud-bulk-delete-form" onchange="pmUpdateCrudBulkBar()" class="pm-crud-row-checkbox rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]" aria-label="Select {{ $rowLabel }}">
                                @endif
                            </td>
                        @endif
                        <td class="pm-dt-main">
                            <button type="button"
                                    onclick='openCrudViewModal({{ json_encode($rowValues) }}, {{ $item->id }}, {{ $pmIsOwner ? "true" : "false" }})'
                                    class="pm-dt-title"
                                    title="View {{ $rowLabel }}">{!! $pmPrimaryHtml !!}</button>
                            @if (!empty($pmSubParts))
                                <span class="pm-dt-sub">{!! implode('', $pmSubParts) !!}</span>
                            @endif
                        </td>
                        @if ($pmStatusField)
                            <td class="pm-dt-aux">
                                @if ($pmStatusLabel)
                                    <span class="pm-dt-pill {{ $pmStatusTone($pmStatusValue) }}">{{ $pmStatusLabel }}</span>
                                @endif
                            </td>
                        @endif
                        @if ($pmDateField)
                            <td class="pm-dt-aux">{!! $pmRenderCell($pmDateField, $item, $rowValues, true) !!}</td>
                        @endif
                        @if ($pmAmountField)
                            <td class="pm-dt-num">{!! $pmRenderCell($pmAmountField, $item, $rowValues) !!}</td>
                        @endif
                        <td class="pm-dt-actions">
                            @if ($pmIsOwner && auth()->user()->hasActiveAccess())
                                <button type="button"
                                        onclick='openCrudEditModal({{ json_encode(route($routeName . '.update', $item->id)) }}, {{ json_encode($rowValues) }})'
                                        class="pm-dt-icon-btn pm-dt-desktop-only"
                                        title="Edit {{ $rowLabel }}">
                                    <i class="fa-solid fa-pen-to-square text-xs" aria-hidden="true"></i>
                                    <span class="sr-only">Edit {{ $rowLabel }}</span>
                                </button>
                            @endif
                            @if ($pmIsOwner || $routeName === 'meetings')
                                <details class="pm-dt-menu">
                                    <summary class="pm-dt-icon-btn" title="More actions">
                                        <i class="fa-solid fa-ellipsis-vertical text-sm" aria-hidden="true"></i>
                                        <span class="sr-only">Actions for {{ $rowLabel }}</span>
                                    </summary>
                                    <div class="pm-dt-menu-list">
                                        <button type="button"
                                                onclick='openCrudViewModal({{ json_encode($rowValues) }}, {{ $item->id }}, {{ $pmIsOwner ? "true" : "false" }})'
                                                class="pm-dt-menu-item">
                                            <i class="fa-solid fa-eye" aria-hidden="true"></i> View details
                                        </button>
                                        @if ($routeName === 'meetings' && $pmIsOwner)
                                            <a href="{{ route('meetings.notes', $item->id) }}#record-meeting"
                                               class="pm-dt-menu-item"
                                               title="Record meeting">
                                                <i class="fa-solid fa-microphone" aria-hidden="true"></i> Record meeting
                                            </a>
                                        @endif
                                        @if ($pmIsOwner)
                                            @if (auth()->user()->hasActiveAccess())
                                                <button type="button"
                                                        onclick='openCrudEditModal({{ json_encode(route($routeName . '.update', $item->id)) }}, {{ json_encode($rowValues) }})'
                                                        class="pm-dt-menu-item">
                                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit
                                                </button>
                                                <button type="button"
                                                        onclick='openCrudDeleteModal({{ json_encode(route($routeName . '.destroy', $item->id)) }}, {{ json_encode($rowLabel) }})'
                                                        class="pm-dt-menu-item is-danger">
                                                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Delete
                                                </button>
                                            @else
                                                <span class="pm-dt-menu-item text-slate-400" title="Renew your subscription to edit or delete">
                                                    <i class="fa-solid fa-lock" aria-hidden="true"></i> Renew to edit
                                                </span>
                                            @endif
                                        @else
                                            {{-- Shared with this user (an attendee), not theirs to edit or delete. --}}
                                            <form method="POST" action="{{ route('meetings.add-to-calendar', $item->id) }}">
                                                @csrf
                                                <button type="submit" class="pm-dt-menu-item" title="Add to your Meetings calendar">
                                                    <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i> Add to my calendar
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </details>
                            @else
                                <span class="text-xs text-slate-400 italic inline-flex items-center gap-1" title="Shared with you">
                                    <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
                                    <span class="sr-only md:not-sr-only">Shared</span>
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="pm-dt-empty">
                        <td colspan="{{ $pmTableColspan }}">
                            <i class="{{ $icon }} text-3xl mb-2 block opacity-30" aria-hidden="true"></i>
                            @if (request()->filled('q') || request()->filled('period') || request()->filled('module'))
                                No matching {{ strtolower($title) }}s. Try a different search or period.
                            @else
                                <span class="block text-slate-600 font-semibold">No {{ strtolower($title) }}s yet.</span>
                                <span class="block text-xs mt-1">Your first entry takes less than a minute.</span>
                                @if (auth()->user()->hasActiveAccess())
                                    <button type="button" onclick="openCrudCreateModal()"
                                            class="mt-3 inline-flex items-center gap-2 btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm">
                                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add your first {{ strtolower($title) }}
                                    </button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @endif

    <nav aria-label="Pagination" class="mt-4">
        {{ $items->links() }}
    </nav>
    @if ($pmHasBulkDelete)
        <script>
            function pmUpdateCrudBulkBar() {
                var boxes = Array.from(document.querySelectorAll('.pm-crud-row-checkbox'));
                var selected = boxes.filter(function (box) { return box.checked; });
                var bar = document.getElementById('pm-crud-bulk-bar');
                var count = document.getElementById('pm-crud-selected-count');
                var master = document.getElementById('pm-crud-select-all');
                if (bar) bar.classList.toggle('hidden', selected.length === 0);
                if (bar) bar.classList.toggle('flex', selected.length > 0);
                if (count) count.textContent = selected.length;
                if (master) {
                    master.checked = boxes.length > 0 && selected.length === boxes.length;
                    master.indeterminate = selected.length > 0 && selected.length < boxes.length;
                }
            }
            function pmToggleAllCrudRows(master) {
                document.querySelectorAll('.pm-crud-row-checkbox').forEach(function (box) { box.checked = master.checked; });
                pmUpdateCrudBulkBar();
            }
            function pmConfirmCrudBulkDelete() {
                var selected = document.querySelectorAll('.pm-crud-row-checkbox:checked');
                if (!selected.length) return;
                pmConfirmAction({
                    title: 'Delete selected {{ strtolower($title) }}s?',
                    message: 'You are about to permanently delete ' + selected.length + ' selected record' + (selected.length === 1 ? '' : 's') + '. This action cannot be undone.',
                    confirmText: 'Delete selected',
                    onConfirm: function () { document.getElementById('pm-crud-bulk-delete-form').requestSubmit(); }
                });
            }
        </script>
    @endif
    </div>

    <div role="tabpanel" id="pm-crud-panel-calendar" aria-labelledby="pm-crud-tab-calendar" tabindex="0" class="pm-crud-panel" hidden>
        <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 p-5">
            <div class="flex items-center justify-between mb-4">
                <a href="{{ route($routeName . '.index', array_merge(request()->query(), ['cal_month' => $calendar['prevMonth']])) }}"
                   class="w-9 h-9 rounded-lg border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-colors" aria-label="Previous month">
                    <i class="fa-solid fa-chevron-left text-xs" aria-hidden="true"></i>
                </a>
                <h2 class="font-semibold text-slate-800">{{ $calendar['month']->format('F Y') }}</h2>
                <a href="{{ route($routeName . '.index', array_merge(request()->query(), ['cal_month' => $calendar['nextMonth']])) }}"
                   class="w-9 h-9 rounded-lg border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-colors" aria-label="Next month">
                    <i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i>
                </a>
            </div>

            <p class="text-xs text-slate-400 mb-3">
                Click any day to add a new {{ strtolower($title) }} for that date{{ $dateFieldName ? '' : ' (date will need to be set manually  this module doesn\'t track a specific date field of its own beyond when it was added)' }}.
            </p>

            <div class="grid grid-cols-7 gap-1 text-center text-xs font-medium text-slate-400 uppercase mb-1">
                @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow)
                    <div>{{ $dow }}</div>
                @endforeach
            </div>

            <div class="grid grid-cols-7 gap-1">
                @php
                    $firstOfMonth = $calendar['month']->copy()->startOfMonth();
                    $leadingBlanks = $firstOfMonth->dayOfWeek;
                    $daysInMonth = $calendar['month']->daysInMonth;
                @endphp
                @for ($i = 0; $i < $leadingBlanks; $i++)
                    <div></div>
                @endfor
                @for ($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $dateKey = $firstOfMonth->copy()->day($day)->format('Y-m-d');
                        $dayItems = $calendar['byDay']->get($dateKey, collect());
                        $isToday = $dateKey === now()->format('Y-m-d');
                    @endphp
                    <div role="button" tabindex="0"
                         onclick="pmOpenCrudCalendarDay('{{ $dateKey }}')"
                         onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); pmOpenCrudCalendarDay('{{ $dateKey }}'); }"
                         aria-label="Add a new {{ strtolower($title) }} on {{ $dateKey }}"
                         class="text-left border rounded-lg p-1.5 min-h-[64px] cursor-pointer hover:bg-slate-50 transition-colors {{ $isToday ? 'border-[var(--brand-1)] bg-[var(--brand-1-tint-10)]' : 'border-slate-100' }}">
                        <span class="text-xs font-medium {{ $isToday ? 'text-[var(--brand-1)]' : 'text-slate-500' }}">{{ $day }}</span>
                        @foreach ($dayItems->take(2) as $dayItem)
                            {{-- Each existing item is its own clickable
                                 button, opening the SAME edit modal the
                                 table's Edit button uses (openCrudEditModal
                                 is already defined further down) 
                                 stopPropagation so clicking a pill doesn't
                                 ALSO trigger the day cell's "add new" click
                                 handler underneath it. --}}
                            <button type="button"
                                    onclick='event.stopPropagation(); openCrudEditModal({{ json_encode($dayItem["updateUrl"]) }}, {{ json_encode($dayItem["values"]) }})'
                                    class="block w-full text-left text-[10px] truncate bg-{{ $accent }}-50 text-{{ $accent }}-700 hover:bg-{{ $accent }}-100 rounded px-1 mt-0.5 transition-colors">
                                {{ $dayItem['label'] }}
                            </button>
                        @endforeach
                        @if ($dayItems->count() > 2)
                            <span class="block text-[10px] text-slate-400 mt-0.5">+{{ $dayItems->count() - 2 }} more</span>
                        @endif
                    </div>
                @endfor
            </div>
        </div>
    </div>

    @if (in_array($routeName, ['diet-logs', 'sleep-logs'], true))
        </section>
    @endif

    {{-- Shared create/edit modal. This drives Health, Expenses, Reminders, Projects,
         Meetings and the other generic CRUD modules, so one professional structure
         keeps the whole application consistent. --}}
    <dialog id="crud-modal" aria-labelledby="crud-modal-title" class="{{ in_array($routeName, ['expenses', 'meetings'], true) ? 'pm-dialog-lg' : 'pm-dialog' }} pm-modal-shell">
        <form method="POST" id="crud-modal-form" action="{{ old('_dialog_action', route($routeName . '.store')) }}" class="pm-modal-form">
            @csrf
            @if (old('_method') === 'PUT')
                @method('PUT')
            @endif
            <input type="hidden" name="_dialog_action" id="crud-modal-dialog-action" value="{{ old('_dialog_action', '') }}">

            <header class="pm-modal-header">
                <div class="pm-modal-heading">
                    <div class="pm-modal-icon">
                        <i class="{{ $icon }}" aria-hidden="true"></i>
                    </div>
                    <div class="min-w-0">
                        <h2 id="crud-modal-title" class="pm-modal-title">
                            {{ old('_method') === 'PUT' ? 'Edit' : 'New' }} {{ $title }}
                        </h2>
                        <p id="crud-modal-description" class="pm-modal-description">
                            {{ old('_method') === 'PUT' ? '' : '* Required' }}
                        </p>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('crud-modal').close()" class="pm-modal-close" aria-label="Close dialog">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </header>

            <div class="pm-modal-body">
                @include('crud._fields', ['fields' => $fields, 'item' => null])

                @if ($dateFieldName)
                    <div class="pm-modal-section flex items-start gap-3">
                        <input type="checkbox" id="crud-set-reminder" name="set_reminder" value="1"
                               class="mt-0.5 rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                        <div>
                            <label for="crud-set-reminder" class="!mb-0 text-sm font-semibold text-slate-700" title="Get notified at the relevant date and time.">
                                Also set a reminder
                            </label>
                        </div>
                    </div>
                @endif
            </div>

            <footer class="pm-modal-footer">
                <button type="button" onclick="document.getElementById('crud-modal').close()" class="pm-btn-cancel">Cancel</button>
                <button type="submit" class="pm-btn-save btn-primary text-white">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    <span id="crud-modal-save-label">Save {{ $title }}</span>
                </button>
            </footer>
        </form>
    </dialog>

    {{-- Shared read-only View modal. --}}
    <dialog id="crud-view-modal" aria-labelledby="crud-view-modal-title" class="pm-dialog-lg pm-modal-shell">
        <div class="pm-modal-content">
            <header class="pm-modal-header">
                <div class="pm-modal-heading">
                    <div class="pm-modal-icon"><i class="fa-solid fa-eye" aria-hidden="true"></i></div>
                    <div>
                        <h2 id="crud-view-modal-title" class="pm-modal-title">View {{ $title }}</h2>
                        <p class="pm-modal-description">Review the saved information below. Use Edit from the table if you need to make changes.</p>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('crud-view-modal').close()" class="pm-modal-close" aria-label="Close dialog">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </header>

            <div class="pm-modal-body">
                <dl id="crud-view-fields" class="pm-modal-read-grid"></dl>

                @if ($routeName === 'meetings')
                    <section id="crud-meeting-view-actions" class="hidden pm-modal-section">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-3">Meeting tools</p>
                        <div class="flex flex-wrap gap-2">
                            <a id="crud-meeting-record-link" href="#" class="inline-flex items-center gap-2 btn-primary text-white px-4 py-2.5 rounded-xl text-sm font-medium">
                                <i class="fa-solid fa-microphone" aria-hidden="true"></i> Record Meeting
                            </a>
                            <a id="crud-meeting-upload-link" href="#" class="inline-flex items-center gap-2 border border-slate-200 bg-white text-slate-700 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-50">
                                <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i> Upload Recording
                            </a>
                            <a id="crud-meeting-transcript-link" href="#" class="inline-flex items-center gap-2 border border-slate-200 bg-white text-slate-700 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-50">
                                <i class="fa-solid fa-file-lines" aria-hidden="true"></i> Transcript &amp; AI Summary
                            </a>
                        </div>
                    </section>
                @endif
            </div>

            <footer class="pm-modal-footer">
                <button type="button" onclick="document.getElementById('crud-view-modal').close()" class="pm-btn-cancel">Close</button>
            </footer>
        </div>
    </dialog>

    {{-- Shared delete-confirmation modal. --}}
    <dialog id="crud-delete-modal" aria-labelledby="crud-delete-modal-title" class="pm-dialog-sm pm-modal-shell">
        <div class="pm-modal-content">
            <header class="pm-modal-header">
                <div class="pm-modal-heading">
                    <div class="pm-modal-icon danger"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
                    <div>
                        <h2 id="crud-delete-modal-title" class="pm-modal-title">Delete {{ strtolower($title) }}?</h2>
                        <p class="pm-modal-description">Check the item carefully before removing it.</p>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('crud-delete-modal').close()" class="pm-modal-close" aria-label="Close dialog">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </header>
            <div class="pm-modal-body">
                <div class="pm-modal-section bg-rose-50/60 border-rose-100">
                    <p id="crud-delete-modal-desc" class="text-sm text-slate-700">This action cannot be undone.</p>
                </div>
            </div>
            <form method="POST" id="crud-delete-modal-form" class="!gap-0">
                @csrf
                @method('DELETE')
                <footer class="pm-modal-footer">
                    <button type="button" onclick="document.getElementById('crud-delete-modal').close()" class="pm-btn-cancel">Cancel</button>
                    <button type="submit" class="pm-btn-danger">
                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                        <span>Delete permanently</span>
                    </button>
                </footer>
            </form>
        </div>
    </dialog>

    <script>
        function pmToggleCrudDateRange(select) {
            var wrapper = document.getElementById('crud-date-range-{{ $routeName }}');
            if (wrapper) { wrapper.style.display = select.value === 'range' ? 'flex' : 'none'; }
        }

        function pmSelectCrudTab(key) {
            document.querySelectorAll('.pm-crud-tab').forEach(function (btn) {
                var isSelected = btn.dataset.tab === key;
                btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                btn.setAttribute('tabindex', isSelected ? '0' : '-1');
                btn.classList.toggle('border-[var(--brand-1)]', isSelected);
                btn.classList.toggle('text-[var(--brand-1)]', isSelected);
                btn.classList.toggle('border-transparent', !isSelected);
                btn.classList.toggle('text-slate-500', !isSelected);
                if (isSelected) { btn.focus(); }
            });
            document.querySelectorAll('.pm-crud-panel').forEach(function (panel) {
                panel.hidden = panel.id !== 'pm-crud-panel-' + key;
            });
            if (key === 'chart' && typeof pmInitCrudChartIfNeeded === 'function') {
                pmInitCrudChartIfNeeded();
            }
        }

        // Standard WAI-ARIA tabs keyboard pattern: Left/Right/Home/End move
        // focus AND activate the tab (not just focus it).
        function pmCrudTabKeydown(event, currentKey) {
            var tabs = Array.prototype.map.call(document.querySelectorAll('.pm-crud-tab'), function (t) { return t.dataset.tab; });
            var index = tabs.indexOf(currentKey);
            var nextIndex = null;

            if (event.key === 'ArrowRight') { nextIndex = (index + 1) % tabs.length; }
            else if (event.key === 'ArrowLeft') { nextIndex = (index - 1 + tabs.length) % tabs.length; }
            else if (event.key === 'Home') { nextIndex = 0; }
            else if (event.key === 'End') { nextIndex = tabs.length - 1; }
            else { return; }

            event.preventDefault();
            pmSelectCrudTab(tabs[nextIndex]);
        }

        function openCrudCreateModal() {
            var dialog = document.getElementById('crud-modal');
            var form = document.getElementById('crud-modal-form');
            form.reset();
            form.action = @json(route($routeName . '.store'));
            document.getElementById('crud-modal-dialog-action').value = form.action;
            var methodInput = form.querySelector('input[name="_method"]');
            if (methodInput) { methodInput.remove(); }
            document.getElementById('crud-modal-title').textContent = 'New {{ $title }}';
            document.getElementById('crud-modal-description').textContent = '* Required';
            var saveLabel = document.getElementById('crud-modal-save-label'); if (saveLabel) { saveLabel.textContent = 'Save {{ $title }}'; }
            dialog.classList.remove('pm-dialog-quick');
            dialog.classList.add('pm-dialog');
            dialog.showModal();
            if (typeof window.pmSync12HourTimeControls === 'function') {
                window.setTimeout(function () { window.pmSync12HourTimeControls(dialog); }, 0);
            }
        }

        // Calendar tab's "click a day to add" — opens the same create
        // modal as the "Add" button (same fields, same validation), then
        // pre-fills whichever field maps to this module's date column
        // (see CrudController's editableDateFieldName()) with the clicked
        // day. Modules with no editable date field of their own (e.g.
        // Feedback) just open the plain create modal — there's nothing to
        // pre-fill. Styled visibly smaller/lighter (.pm-dialog-quick) and
        // titled with the actual date, closer to the compact "quick add
        // event" popup Google Calendar/Teams show for this exact
        // interaction, rather than reusing the full-size form dialog as-is.
        function pmOpenCrudCalendarDay(dateKey) {
            openCrudCreateModal();

            var dialog = document.getElementById('crud-modal');
            dialog.classList.remove('pm-dialog');
            dialog.classList.add('pm-dialog-quick');

            var friendlyDate = new Date(dateKey + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });
            document.getElementById('crud-modal-title').textContent = 'New {{ $title }}  ' + friendlyDate;

            @if ($dateFieldName)
                var dateField = document.getElementById('field-{{ $dateFieldName }}');
                if (dateField) {
                    dateField.value = dateField.type === 'datetime-local' ? (dateKey + 'T09:00') : dateKey;
                }
            @endif
        }

        function openCrudViewModal(values, itemId, isOwner) {
            var fields = @json($fields);
            var container = document.getElementById('crud-view-fields');
            var dialog = document.getElementById('crud-view-modal');
            if (!container || !dialog) { return; }

            container.innerHTML = '';

            function displayValue(value) {
                if (Array.isArray(value)) {
                    return value.map(function (part) {
                        if (part === null || part === undefined) { return ''; }
                        if (typeof part !== 'object') { return String(part); }
                        return part.name || part.title || part.email || part.label || part.value || JSON.stringify(part);
                    }).filter(Boolean).join(', ');
                }
                if (value !== null && typeof value === 'object') {
                    return value.name || value.title || value.email || value.label || value.value || JSON.stringify(value, null, 2);
                }
                return value;
            }

            fields.forEach(function (field) {
                var value = displayValue(values[field.name]);

                if (value === null || value === undefined || value === '') { value = ''; }
                if (field.options && (typeof value === 'string' || typeof value === 'number') && Object.prototype.hasOwnProperty.call(field.options, value)) {
                    value = field.options[value];
                }
                if (field.type === 'checkbox') { value = value && value !== '0' ? 'Yes' : 'No'; }
                if (field.type === 'time' && value) {
                    var parts = String(value).match(/^(\d{1,2}):(\d{2})/);
                    if (parts) {
                        var hour24 = Number(parts[1]);
                        var hour12 = (hour24 % 12) || 12;
                        value = hour12 + ':' + parts[2] + ' ' + (hour24 >= 12 ? 'PM' : 'AM');
                    }
                }
                if ((field.type === 'datetime-local' || field.type === 'datetime-native') && value) {
                    var dtMatch = String(value).match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{1,2}):(\d{2})/);
                    if (dtMatch) {
                        var dtHour24 = Number(dtMatch[4]);
                        var dtHour12 = (dtHour24 % 12) || 12;
                        value = dtMatch[3] + '/' + dtMatch[2] + '/' + dtMatch[1] + ', ' + dtHour12 + ':' + dtMatch[5] + ' ' + (dtHour24 >= 12 ? 'PM' : 'AM');
                    }
                }

                var isEmpty = value === null || value === undefined || value === '';
                var displayText = isEmpty ? 'Not provided' : String(value);
                var isLongValue = field.type === 'textarea' || displayText.length > 80;

                var wrap = document.createElement('div');
                wrap.className = 'pm-view-field' + (isLongValue ? ' pm-view-field--wide' : '');
                var dt = document.createElement('dt');
                dt.className = 'pm-view-field-label';
                dt.textContent = field.label;
                var dd = document.createElement('dd');
                dd.className = 'pm-view-field-value' + (isEmpty ? ' is-empty' : '');
                wrap.appendChild(dt);
                wrap.appendChild(dd);

                var linkHost = null;
                if (!isEmpty && /^https?:\/\/\S+$/i.test(displayText.trim())) {
                    try { linkHost = new URL(displayText.trim()).host; } catch (e) { linkHost = null; }
                }

                if (linkHost) {
                    // A link shows as its host plus Copy, never the full URL, and is
                    // never clickable: joins go through the diary's protected route.
                    var linkText = document.createElement('span');
                    linkText.className = 'pm-view-field-link';
                    linkText.innerHTML = '<i class="fa-solid fa-link" aria-hidden="true"></i> ';
                    linkText.appendChild(document.createTextNode(linkHost + ' link'));
                    dd.appendChild(linkText);

                    var copy = document.createElement('button');
                    copy.type = 'button';
                    copy.className = 'pm-view-field-toggle';
                    copy.textContent = 'Copy link';
                    copy.addEventListener('click', function () {
                        var url = displayText.trim();
                        var done = function () { copy.textContent = 'Copied'; };
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(url).then(done, function () {});
                        }
                    });
                    wrap.appendChild(copy);
                } else {
                    dd.textContent = displayText;
                }

                container.appendChild(wrap);

                // Long content is clipped to a few lines with a "View" toggle
                // (removed again after opening if the text fits anyway).
                if (!isEmpty && isLongValue && !dd.firstElementChild) {
                    dd.classList.add('is-clamped');
                    var toggle = document.createElement('button');
                    toggle.type = 'button';
                    toggle.className = 'pm-view-field-toggle';
                    toggle.textContent = 'View';
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.addEventListener('click', function () {
                        var expanded = dd.classList.toggle('is-clamped') === false;
                        toggle.textContent = expanded ? 'Show less' : 'View';
                        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                    });
                    wrap.appendChild(toggle);
                }
            });

            @if ($routeName === 'meetings')
                var tools = document.getElementById('crud-meeting-view-actions');
                if (tools) {
                    if (isOwner) {
                        tools.classList.remove('hidden');
                        var base = @json(url('/meetings')) + '/' + itemId + '/notes';
                        var record = document.getElementById('crud-meeting-record-link');
                        var upload = document.getElementById('crud-meeting-upload-link');
                        var transcript = document.getElementById('crud-meeting-transcript-link');
                        if (record) { record.href = base + '#record-meeting'; }
                        if (upload) { upload.href = base + '#record-meeting'; }
                        if (transcript) { transcript.href = base + '#transcripts-summary'; }
                    } else {
                        tools.classList.add('hidden');
                    }
                }
            @endif

            dialog.showModal();

            // Now that it's visible, drop "View" toggles on text that fits.
            container.querySelectorAll('.pm-view-field-toggle').forEach(function (toggle) {
                var value = toggle.parentNode.querySelector('.pm-view-field-value');
                if (value && value.scrollHeight <= value.clientHeight + 2) {
                    value.classList.remove('is-clamped');
                    toggle.remove();
                }
            });
        }

        function openCrudEditModal(actionUrl, values) {
            var dialog = document.getElementById('crud-modal');
            dialog.classList.remove('pm-dialog-quick');
            dialog.classList.add('pm-dialog');
            var form = document.getElementById('crud-modal-form');
            form.reset();
            form.action = actionUrl;
            document.getElementById('crud-modal-dialog-action').value = actionUrl;

            var methodInput = form.querySelector('input[name="_method"]');
            if (!methodInput) {
                methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                form.appendChild(methodInput);
            }
            methodInput.value = 'PUT';

            Object.keys(values).forEach(function (name) {
                var el = form.elements[name];
                if (!el) { return; }
                // Checkbox fields render TWO inputs sharing the same name
                // (a hidden "0" fallback + the real checkbox), so
                // form.elements[name] is a RadioNodeList, not a single
                // element — .value doesn't check/uncheck it correctly.
                if (el instanceof RadioNodeList) {
                    var checkbox = form.querySelector('input[type="checkbox"][name="' + name + '"]');
                    if (checkbox) { checkbox.checked = !!values[name]; }
                    return;
                }
                if (el.type === 'checkbox') {
                    el.checked = !!values[name];
                    return;
                }
                el.value = values[name] === null ? '' : values[name];
            });

            document.getElementById('crud-modal-title').textContent = 'Edit {{ $title }}';
            document.getElementById('crud-modal-description').textContent = '';
            var saveLabel = document.getElementById('crud-modal-save-label'); if (saveLabel) { saveLabel.textContent = 'Save changes'; }
            dialog.showModal();

            window.setTimeout(function () {
                dialog.querySelectorAll('[data-pm-datetime12]').forEach(function (root) {
                    if (typeof root.pmSyncFromHidden === 'function') {
                        root.pmSyncFromHidden();
                    }
                });

                if (typeof window.pmSync12HourTimeControls === 'function') {
                    window.pmSync12HourTimeControls(dialog);
                }
            }, 0);
        }

        function openCrudDeleteModal(actionUrl, label) {
            var dialog = document.getElementById('crud-delete-modal');
            document.getElementById('crud-delete-modal-form').action = actionUrl;
            document.getElementById('crud-delete-modal-desc').textContent =
                'Delete "' + label + '"? This action cannot be undone.';
            dialog.showModal();
        }

       
        document.addEventListener('DOMContentLoaded', function () {
            @if ($errors->any() && old('_dialog_action'))
                pmSelectCrudTab('table');
                document.getElementById('crud-modal').showModal();
            @else
                // Deep link from the dashboard / quick-add sheet: ?new=1
                // opens the existing "Add" modal straight away.
                @if (auth()->user()->hasActiveAccess())
                    try {
                        var pmUrl = new URL(window.location.href);
                        if (pmUrl.searchParams.get('new') === '1') {
                            pmUrl.searchParams.delete('new');
                            window.history.replaceState(null, '', pmUrl.toString());
                            openCrudCreateModal();
                        }
                    } catch (e) { /* older browsers: user can still tap Add */ }
                @endif
            @endif
        });
    </script>

    {{-- Optional per-module extras (e.g. Meetings' "Schedule Multiple" modal).
         Silently does nothing for every other module view()->exists()
         just returns false when no matching file was created. --}}
    @if (view()->exists('crud.extras.' . $routeName . '-extra'))
        @include('crud.extras.' . $routeName . '-extra')
    @endif
@endsection
