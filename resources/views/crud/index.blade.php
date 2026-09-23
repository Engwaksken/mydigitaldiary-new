@extends('layouts.app')

@section('title', $title . 's')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-{{ $accent }}-100 text-{{ $accent }}-600 flex items-center justify-center shadow-sm shrink-0">
                <i class="{{ $icon }} text-xl" aria-hidden="true"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">{{ $title }}s</h1>
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
                        <p class="text-xs text-slate-500 mt-1">Set a goal so your records and daily actions stay connected to what matters.</p>
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

    {{-- Stats cards are always visible — not tabbed — so they read like
         the at-a-glance summary they're meant to be, with the Chart/Table
         tabs underneath for the more detailed views. --}}
    @if (!empty($stats))
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            @foreach ($stats as $stat)
                @php
                    $statColor = $stat['color'] ?? $accent;
                    $statIcon = $stat['icon'] ?? $icon;
                    $statRoute = $stat['route'] ?? ($stat['url'] ?? ($stat['link'] ?? null));
                @endphp
                @if ($statRoute)
                    <a href="{{ $statRoute }}" class="pm-card-bg rounded-xl shadow-sm border border-slate-100 border-l-4 border-l-{{ $statColor }}-400 p-4 hover:shadow-md transition-shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[var(--brand-1)]">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-{{ $statColor }}-50 text-{{ $statColor }}-600 flex items-center justify-center shrink-0">
                            <i class="{{ $statIcon }} text-sm" aria-hidden="true"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-500 uppercase tracking-wide truncate">{{ $stat['label'] }}</p>
                            <p class="text-xl font-bold text-slate-800 truncate">{{ $stat['value'] }}</p>
                        </div>
                    </div>
                    </a>
                @else
                    <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 border-l-4 border-l-{{ $statColor }}-400 p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-{{ $statColor }}-50 text-{{ $statColor }}-600 flex items-center justify-center shrink-0">
                                <i class="{{ $statIcon }} text-sm" aria-hidden="true"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs text-slate-500 uppercase tracking-wide truncate">{{ $stat['label'] }}</p>
                                <p class="text-xl font-bold text-slate-800 truncate">{{ $stat['value'] }}</p>
                            </div>
                        </div>
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

    @if ($routeName === 'personal-goals')
        <div class="pm-table-swipe-hint md:hidden mb-2 text-[11px] font-medium text-slate-400">
            <i class="fa-solid fa-arrows-left-right mr-1"></i>
            Swipe the table sideways to view all goal columns.
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
    <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 overflow-x-auto pm-horizontal-table-wrap"
         role="region"
         aria-label="{{ $title }}s table"
         tabindex="0">
        <table class="min-w-full text-sm pm-horizontal-data-table {{ $routeName === 'personal-goals' ? 'pm-goals-horizontal-table' : '' }}">
            <caption class="sr-only">List of your {{ strtolower($title) }}s, with edit and delete actions for each.</caption>
            <thead class="bg-slate-50 text-left border-b border-slate-100">
                <tr>
                    @if ($pmHasBulkDelete)
                        <th scope="col" class="px-4 py-3 w-10">
                            <input type="checkbox" id="pm-crud-select-all" onchange="pmToggleAllCrudRows(this)" class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]" aria-label="Select all {{ strtolower($title) }} records">
                        </th>
                    @endif
                    @foreach ($tableColumns as $field)
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">{{ $field['label'] }}</th>
                    @endforeach
                    @if ($routeName === 'expenses')
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Items</th>
                    @endif
                    <th scope="col" class="px-4 py-3">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                    @php
                        $rowLabel = $item->{$fields[0]['name']} ?? null;
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
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors">
                        @if ($pmHasBulkDelete)
                            <td class="px-4 py-3 align-top">
                                @if (($item->user_id ?? null) == auth()->id())
                                    <input type="checkbox" name="ids[]" value="{{ $item->id }}" form="pm-crud-bulk-delete-form" onchange="pmUpdateCrudBulkBar()" class="pm-crud-row-checkbox rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]" aria-label="Select {{ $rowLabel }}">
                                @endif
                            </td>
                        @endif
                        @foreach ($tableColumns as $field)
                            <td class="px-4 py-3 align-top text-slate-700 {{ $routeName === 'personal-goals' && in_array($field['name'], ['description', 'notes'], true) ? 'pm-table-wrap-text' : '' }}">
                                @php
                                    $value = $item->{$field['name']};
                                    $meetingFieldName = strtolower((string) ($field['name'] ?? ''));
                                    $isMeetingLinkField = $routeName === 'meetings' && in_array($meetingFieldName, ['location', 'meeting_link', 'video_link', 'join_url', 'url'], true);
                                    $isMeetingAttendeesField = $routeName === 'meetings' && in_array($meetingFieldName, ['attendees', 'attendee', 'participants'], true);
                                    $isMeetingNotesField = $routeName === 'meetings' && in_array($meetingFieldName, ['notes', 'agenda', 'notes_agenda', 'description'], true);
                                @endphp
                                @if ($isMeetingLinkField)
                                    @php
                                        $meetingLinkValue = trim((string) ($value ?? ''));
                                        $meetingSafeExternalUrl = $item->safe_external_url;
                                    @endphp
                                    @if ($meetingLinkValue === '')
                                        <span class="text-slate-400"></span>
                                    @elseif ($meetingSafeExternalUrl && $item->canBeJoinedBy(auth()->user()))
                                        <a href="{{ $item->diary_join_url }}"
                                            class="inline-flex items-center gap-1 text-[var(--brand-1)] hover:underline font-medium"
                                           title="Join meeting">
                                            <i class="fa-solid fa-arrow-right-to-bracket text-[10px]" aria-hidden="true"></i>
                                            Join meeting
                                        </a>
                                    @else
                                        <span title="{{ $meetingLinkValue }}">{{ \Illuminate\Support\Str::limit($meetingLinkValue, 34) }}</span>
                                    @endif
                                @elseif ($isMeetingAttendeesField)
                                    @php
                                        $meetingAttendeeParts = collect();
                                        if (is_array($value) || $value instanceof \Illuminate\Support\Collection) {
                                            $meetingAttendeeParts = collect($value)->map(function ($entry) {
                                                if (is_scalar($entry)) return trim((string) $entry);
                                                if (is_array($entry)) return trim((string) ($entry['email'] ?? $entry['name'] ?? $entry['value'] ?? ''));
                                                if (is_object($entry)) return trim((string) ($entry->email ?? $entry->name ?? $entry->value ?? ''));
                                                return '';
                                            });
                                        } else {
                                            $rawAttendees = trim((string) ($value ?? ''));
                                            if ($rawAttendees !== '') {
                                                $meetingAttendeeParts = collect(preg_split('/[,;\n]+/', $rawAttendees));
                                            }
                                        }
                                        $meetingAttendeeParts = $meetingAttendeeParts->map(fn ($part) => trim((string) $part))->filter()->unique()->values();
                                        $meetingAttendeeCount = $meetingAttendeeParts->count();
                                        $meetingAttendeeTitle = $meetingAttendeeParts->implode(', ');
                                    @endphp
                                    @if ($meetingAttendeeCount > 0)
                                        <span class="inline-flex items-center gap-1.5 font-medium text-slate-700" title="{{ $meetingAttendeeTitle }}">
                                            <i class="fa-solid fa-users text-slate-400 text-xs" aria-hidden="true"></i>
                                            {{ $meetingAttendeeCount }}
                                        </span>
                                    @else
                                        <span class="text-slate-400"></span>
                                    @endif
                                @elseif ($isMeetingNotesField)
                                    @php $meetingNotesText = trim(strip_tags((string) ($value ?? ''))); @endphp
                                    @if ($meetingNotesText !== '')
                                        <span class="cursor-help" title="{{ $meetingNotesText }}">{{ \Illuminate\Support\Str::limit($meetingNotesText, 42) }}</span>
                                    @else
                                        <span class="text-slate-400"></span>
                                    @endif
                                @elseif ($field['name'] === 'notes')
                                    @php
                                        $notesPreviewFull = trim(strip_tags((string) ($value ?? '')));
                                    @endphp
                                    @if ($notesPreviewFull !== '')
                                        <span class="cursor-help whitespace-normal"
                                              title="{{ $notesPreviewFull }}">
                                            {{ \Illuminate\Support\Str::limit($notesPreviewFull, 48) }}
                                        </span>
                                    @else
                                        <span class="text-slate-400"></span>
                                    @endif
                                @elseif ($field['name'] === 'project_id' && isset($item->project))
                                    {{ $item->project->name }}
                                @elseif ($field['name'] === 'savings_goal_id' && isset($item->goal))
                                    {{ $item->goal->name }}
                                @elseif ($field['money'] ?? false)
                                    {{ $value !== null ? format_money($value) : '' }}
                                @elseif ($field['type'] === 'checkbox')
                                    @if ($value)
                                        <i class="fa-solid fa-circle-check text-emerald-500" aria-hidden="true"></i>
                                        <span class="sr-only">Yes</span>
                                    @else
                                        <i class="fa-solid fa-circle-xmark text-slate-300" aria-hidden="true"></i>
                                        <span class="sr-only">No</span>
                                    @endif
                                @elseif ($field['type'] === 'time' && $value)
                                    @php
                                        try {
                                            $displayTime = \Illuminate\Support\Carbon::parse((string) $value)->format('g:i A');
                                        } catch (\Throwable $e) {
                                            $displayTime = (string) $value;
                                        }
                                    @endphp
                                    {{ $displayTime }}
                                @elseif (is_object($value) && method_exists($value, 'format'))
                                    <span class="block">{{ in_array($field['type'], ['datetime-local', 'datetime-native'], true) ? $value->format('d M Y, g:i A') : $value->format('Y-m-d') }}</span>
                                    @if ($field['name'] === $countdownField && !($routeName === 'debts' && $item->status === 'paid'))
                                        <x-countdown :date="$value" :status="$item->status ?? null" />
                                    @endif
                                @elseif (is_array($value) || $value instanceof \Illuminate\Support\Collection)
                                    @php
                                        $arrayValue = $value instanceof \Illuminate\Support\Collection ? $value->all() : $value;
                                        $displayParts = collect($arrayValue)
                                            ->map(function ($entry) {
                                                if (is_null($entry)) {
                                                    return null;
                                                }
                                                if (is_scalar($entry)) {
                                                    return trim((string) $entry);
                                                }
                                                if (is_array($entry)) {
                                                    foreach (['name', 'title', 'email', 'label', 'value'] as $key) {
                                                        if (isset($entry[$key]) && is_scalar($entry[$key])) {
                                                            return trim((string) $entry[$key]);
                                                        }
                                                    }
                                                    return collect($entry)
                                                        ->filter(fn ($part) => is_scalar($part) && trim((string) $part) !== '')
                                                        ->map(fn ($part) => trim((string) $part))
                                                        ->implode(' - ');
                                                }
                                                if (is_object($entry)) {
                                                    foreach (['name', 'title', 'email', 'label', 'value'] as $key) {
                                                        if (isset($entry->{$key}) && is_scalar($entry->{$key})) {
                                                            return trim((string) $entry->{$key});
                                                        }
                                                    }
                                                    if (method_exists($entry, '__toString')) {
                                                        return trim((string) $entry);
                                                    }
                                                }
                                                return null;
                                            })
                                            ->filter(fn ($part) => filled($part))
                                            ->values()
                                            ->implode(', ');
                                    @endphp
                                    {{ $displayParts !== '' ? \Illuminate\Support\Str::limit($displayParts, 120) : '' }}
                                @elseif (isset($field['options']) && $value !== null && !is_array($value) && array_key_exists($value, $field['options']))
                                    {{ $field['options'][$value] }}
                                @else
                                    {{ $value !== null ? \Illuminate\Support\Str::limit((string) $value, 60) : '' }}
                                @endif
                            </td>
                        @endforeach
                        @if ($routeName === 'expenses')
                            @php
                                $expenseItemNames = $item->items
                                    ->pluck('description')
                                    ->filter(fn ($description) => filled($description))
                                    ->map(fn ($description) => trim((string) $description))
                                    ->values();
                                $expenseItemsText = $expenseItemNames->implode(', ');
                            @endphp
                            <td class="px-4 py-3 align-top text-slate-700 min-w-[220px] max-w-[420px]">
                                @if ($expenseItemsText !== '')
                                    <span class="whitespace-normal break-words" title="{{ $expenseItemsText }}">{{ $expenseItemsText }}</span>
                                @else
                                    <span class="text-slate-400"></span>
                                @endif
                            </td>
                        @endif
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button"
                                    onclick='openCrudViewModal({{ json_encode($rowValues) }}, {{ $item->id }}, {{ (($item->user_id ?? null) == auth()->id()) ? "true" : "false" }})'
                                    class="inline-flex items-center gap-1 text-slate-500 hover:text-slate-800 mr-3 transition-colors"
                                    title="View {{ $rowLabel }}">
                                <i class="fa-solid fa-eye text-xs" aria-hidden="true"></i>
                                <span class="sr-only">View {{ $rowLabel }}</span>
                            </button>
                            @if (($item->user_id ?? null) == auth()->id())
                                @if (auth()->user()->hasActiveAccess())
                                    <button type="button"
                                            onclick='openCrudEditModal({{ json_encode(route($routeName . '.update', $item->id)) }}, {{ json_encode($rowValues) }})'
                                            class="inline-flex items-center gap-1 text-{{ $accent }}-600 hover:text-{{ $accent }}-800 mr-3 transition-colors">
                                        <i class="fa-solid fa-pen-to-square text-xs" aria-hidden="true"></i>
                                        <span class="sr-only">Edit {{ $rowLabel }}</span>
                                    </button>
                                    <button type="button"
                                            onclick='openCrudDeleteModal({{ json_encode(route($routeName . '.destroy', $item->id)) }}, {{ json_encode($rowLabel) }})'
                                            class="inline-flex items-center gap-1 text-rose-500 hover:text-rose-700 transition-colors">
                                        <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
                                        <span class="sr-only">Delete {{ $rowLabel }}</span>
                                    </button>
                                @else
                                    <span class="text-xs text-slate-400" title="Renew your subscription to edit or delete">
                                        <i class="fa-solid fa-lock text-xs" aria-hidden="true"></i>
                                    </span>
                                @endif
                            @else
                                {{-- Visible because it's shared with this user (e.g. a Meeting they're
                                     an attendee on), but not theirs to edit or delete. --}}
                                @if ($routeName === 'meetings')
                                    <form method="POST" action="{{ route('meetings.add-to-calendar', $item->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1 text-amber-600 hover:text-amber-800 text-xs font-semibold" title="Add to your Meetings calendar">
                                            <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i> Add to my calendar
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400 italic inline-flex items-center gap-1">
                                        <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
                                        Shared with you
                                    </span>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($tableColumns) + 1 + ($routeName === 'expenses' ? 1 : 0) + ($pmHasBulkDelete ? 1 : 0) }}" class="px-4 py-10 text-center text-slate-400">
                            <i class="{{ $icon }} text-3xl mb-2 block opacity-30" aria-hidden="true"></i>
                            No {{ strtolower($title) }}s yet.
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
                            {{ old('_method') === 'PUT' ? 'Update the details below, then save your changes.' : 'Enter the details below. Required fields are marked with an asterisk.' }}
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
                            <label for="crud-set-reminder" class="!mb-0 text-sm font-semibold text-slate-700">
                                Also set a reminder
                            </label>
                            <p class="text-xs text-slate-500 mt-1">Get notified about this {{ strtolower($title) }} at the relevant date and time.</p>
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
            document.getElementById('crud-modal-description').textContent = 'Enter the details below. Required fields are marked with an asterisk.';
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
                dd.textContent = displayText;
                wrap.appendChild(dt);
                wrap.appendChild(dd);
                container.appendChild(wrap);
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
            document.getElementById('crud-modal-description').textContent = 'Update the details below, then save your changes.';
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
