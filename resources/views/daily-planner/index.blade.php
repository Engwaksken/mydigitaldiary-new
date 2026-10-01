@extends('layouts.app')

@section('content')
@php
    $isToday = $date->isToday();
    $isPast = $date->lt(today());
    $isFuture = $date->gt(today());
    $tomorrowDate = $date->copy()->addDay()->toDateString();

    $formatTime = static function ($time) {
        if (!$time) return null;
        try {
            return \Carbon\Carbon::createFromFormat('H:i:s', $time)->format('g:i A');
        } catch (\Throwable $e) {
            try {
                return \Carbon\Carbon::createFromFormat('H:i', substr((string) $time, 0, 5))->format('g:i A');
            } catch (\Throwable $e2) {
                return substr((string) $time, 0, 5);
            }
        }
    };
@endphp

<style>
    .dp-btn-primary {
        background: var(--brand-1);
        color: #fff;
        transition: .2s ease;
    }
    .dp-btn-primary:hover { background: var(--brand-2); }
    .dp-primary-text { color: var(--brand-1); }
    .dp-primary-bg-soft { background: var(--brand-1-tint-10); }
    .dp-primary-border { border-color: var(--brand-1-tint-20); }
    .dp-progress-bar { background: var(--brand-1); }
    .dp-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 80;
        background: rgba(15, 23, 42, .55);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .dp-modal-backdrop.is-open { display: flex; }
    .dp-modal-panel {
        width: 100%;
        max-width: 640px;
        max-height: calc(100vh - 2rem);
        overflow-y: auto;
        background: #fff;
        border-radius: 1rem;
        box-shadow: 0 24px 70px rgba(15,23,42,.22);
    }
    .dp-stat-card {
        --dp-stat-accent: var(--brand-1);
        --dp-stat-soft: var(--brand-1-tint-10);
        background: #fff;
        border: 1px solid #e2e8f0;
        border-left: 4px solid var(--dp-stat-accent);
        border-radius: .85rem;
        padding: .8rem .9rem;
        display: flex;
        align-items: center;
        gap: .75rem;
        box-shadow: 0 3px 10px rgba(15,23,42,.04);
    }
    .dp-stat-icon {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: .65rem;
        background: var(--dp-stat-soft);
        color: var(--dp-stat-accent);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
    }
    .dp-time-badge {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .3rem .55rem;
        border-radius: .6rem;
        background: var(--brand-1-tint-10);
        color: var(--brand-1);
        font-weight: 700;
        white-space: nowrap;
    }
    .dp-timeline-row td { vertical-align: top; }

    .dp-tabs {
        display: flex;
        gap: .35rem;
        padding: .35rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: .85rem;
        width: fit-content;
        max-width: 100%;
        overflow-x: auto;
    }
    .dp-tab-button {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        padding: .65rem 1rem;
        border-radius: .65rem;
        color: #64748b;
        font-size: .875rem;
        font-weight: 700;
        white-space: nowrap;
        border: 0;
        background: transparent;
        cursor: pointer;
        transition: .2s ease;
    }
    .dp-tab-button:hover { color: var(--brand-1); }
    .dp-tab-button.is-active {
        background: #fff;
        color: var(--brand-1);
        box-shadow: 0 1px 4px rgba(15, 23, 42, .08);
    }
    /* Week view */
    .dp-week-grid {
        display: grid;
        gap: .75rem;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 11rem), 1fr));
    }
    @media (min-width: 1280px) {
        .dp-week-grid { grid-template-columns: repeat(7, minmax(0, 1fr)); }
    }
    .dp-week-day {
        border: 1px solid #e2e8f0;
        border-radius: .85rem;
        padding: .75rem;
        background: #f8fafc;
        min-height: 9rem;
    }
    .dp-week-day.is-today {
        border-color: var(--brand-1);
        background: #fff;
        box-shadow: 0 0 0 1px var(--brand-1);
    }
    .dp-week-task {
        display: flex;
        gap: .5rem;
        align-items: flex-start;
        padding: .45rem .5rem;
        border-radius: .6rem;
        background: #fff;
        border: 1px solid #eef2f7;
    }
    .dp-week-task-title {
        font-size: .85rem;
        font-weight: 600;
        color: #1e293b;
        overflow-wrap: anywhere;
    }
    .dp-week-task.is-done .dp-week-task-title {
        color: #94a3b8;
        text-decoration: line-through;
    }
    .dp-week-check {
        border: 0;
        background: transparent;
        padding: 0;
        color: var(--brand-1);
        font-size: 1rem;
        line-height: 1.25rem;
        cursor: pointer;
    }
    .dp-week-row {
        border: 1px solid #e2e8f0;
        border-radius: .85rem;
        padding: 1rem;
        background: #f8fafc;
    }
    .dp-chip {
        border: 1px solid #e2e8f0;
        background: #fff;
        border-radius: 999px;
        padding: .2rem .6rem;
        color: #475569;
        cursor: pointer;
    }
    .dp-chip:hover { border-color: var(--brand-1); color: var(--brand-1); }

    /* Tabs inside long modal forms (Add / Edit Task) */
    .dp-form-tabs {
        position: sticky;
        top: 0;
        z-index: 1;
        display: flex;
        gap: .25rem;
        margin: -1.25rem -1.25rem 0;
        padding: .5rem 1.25rem 0;
        background: #fff;
        border-bottom: 1px solid #e2e8f0;
        overflow-x: auto;
    }
    .dp-form-tab {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .6rem .85rem;
        margin-bottom: -1px;
        border: 0;
        border-bottom: 2px solid transparent;
        background: transparent;
        color: #64748b;
        font-size: .875rem;
        font-weight: 600;
        white-space: nowrap;
        cursor: pointer;
    }
    .dp-form-tab:hover { color: var(--brand-1); }
    .dp-form-tab.is-active {
        color: var(--brand-1);
        border-bottom-color: var(--brand-1);
    }
    .dp-form-panel { min-height: 16rem; }
    .dp-form-panel[hidden] { display: none !important; }
    .dp-tab-panel { display: none; }
    .dp-tab-panel.is-active { display: block; }
    .dp-filter-card {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem 1.25rem;
    }

    .dp-repeat-badge {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        margin-top: .45rem;
        padding: .3rem .55rem;
        border-radius: .6rem;
        background: #f5f3ff;
        color: #6d28d9;
        font-size: .72rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .dp-repeat-panel {
        border: 1px solid #ddd6fe;
        background: #faf5ff;
        border-radius: .9rem;
        padding: 1rem;
    }
    .dp-week-days {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: .45rem;
    }
    .dp-day-check {
        position: relative;
        min-width: 0;
    }
    .dp-day-check input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .dp-day-check span {
        display: flex;
        min-height: 42px;
        align-items: center;
        justify-content: center;
        border: 1px solid #d8b4fe;
        border-radius: .7rem;
        background: #fff;
        color: #6b21a8;
        font-size: .78rem;
        font-weight: 700;
        cursor: pointer;
    }
    .dp-day-check input:checked + span {
        border-color: #7e22ce;
        background: #7e22ce;
        color: #fff;
    }
    .dp-scope-box {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .6rem;
    }
    .dp-scope-option {
        display: flex;
        align-items: flex-start;
        gap: .55rem;
        padding: .8rem;
        border: 1px solid #e2e8f0;
        border-radius: .75rem;
        background: #fff;
    }
    @media (max-width: 640px) {
        .dp-week-days {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
        .dp-scope-box {
            grid-template-columns: 1fr;
        }
        .dp-modal-backdrop {
            padding: .5rem;
        }
        .dp-modal-panel {
            max-height: calc(100vh - 1rem);
            border-radius: .85rem;
        }
    }

</style>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-5">

    {{-- Header / date navigation --}}
    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                <i class="fa-solid fa-calendar-check mr-2 dp-primary-text"></i>Daily Planner
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Plan tasks by time, track progress, and review previous days whenever you need them.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('daily-planner.index', ['date' => $date->copy()->subDay()->toDateString()]) }}"
               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border bg-white text-sm hover:bg-slate-50">
                <i class="fa-solid fa-chevron-left"></i> Previous day
            </a>

            @unless($isToday)
                <a href="{{ route('daily-planner.index') }}"
                   class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border bg-white text-sm hover:bg-slate-50">
                    <i class="fa-solid fa-calendar-day"></i> Today
                </a>
            @endunless

            <form method="GET" class="m-0">
                <input type="date" name="date" value="{{ $date->toDateString() }}"
                       onchange="this.form.submit()"
                       class="pm-input rounded-lg text-sm">
            </form>

            <a href="{{ route('daily-planner.index', ['date' => $date->copy()->addDay()->toDateString()]) }}"
               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border bg-white text-sm hover:bg-slate-50">
                Next day <i class="fa-solid fa-chevron-right"></i>
            </a>
        </div>
    </div>

    {{-- Flash / validation --}}
    @if(session('success'))
        <div id="dpFlash" class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3">
            <i class="fa-solid fa-circle-check mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div id="dpError" class="rounded-xl border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3">
            <div class="font-semibold mb-1"><i class="fa-solid fa-triangle-exclamation mr-2"></i>Please fix the following:</div>
            <ul class="list-disc pl-5 text-sm space-y-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Day summary --}}
    <div class="bg-white border border-slate-200 rounded-2xl p-5">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500 mb-1">
                    <span>{{ $date->format('l, d M Y') }}</span>
                    @if($isPast)
                        <span class="px-2 py-1 rounded-full bg-amber-50 text-amber-700 text-xs font-semibold">Past plan</span>
                    @elseif($isToday)
                        <span class="px-2 py-1 rounded-full dp-primary-bg-soft dp-primary-text text-xs font-semibold">Today</span>
                    @else
                        <span class="px-2 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold">Upcoming</span>
                    @endif
                </div>
                <h2 class="text-xl font-bold text-slate-900">{{ $plan->title ?: 'My Daily Plan' }}</h2>
                @if($plan->notes)
                    <p class="text-sm text-slate-500 mt-1 max-w-3xl">{{ \Illuminate\Support\Str::limit($plan->notes, 160) }}</p>
                @endif
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="openDpModal('dayPlanModal')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border dp-primary-border dp-primary-text bg-white font-medium">
                    <i class="fa-solid fa-pen-to-square"></i> Save Day Plan
                </button>
                <button type="button" onclick="openPlanWeekModal()"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border dp-primary-border dp-primary-text bg-white font-medium">
                    <i class="fa-solid fa-calendar-week"></i> Plan Week
                </button>
                <button type="button" onclick="openAddTaskForDate(@js($date->toDateString()), @js($date->format('l, d M Y')), '')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg dp-btn-primary font-medium shadow-sm">
                    <i class="fa-solid fa-plus"></i> Add Task
                </button>
            </div>
        </div>

        <div class="mt-4 h-3 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full dp-progress-bar transition-all" style="width: {{ $progress }}%"></div>
        </div>
        <div class="mt-2 text-xs text-slate-500">{{ $progress }}% completed</div>
    </div>

    {{-- Statistics --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="dp-stat-card" style="--dp-stat-accent:#0f766e;--dp-stat-soft:#f0fdfa">
            <div class="dp-stat-icon"><i class="fa-solid fa-list-check"></i></div>
            <div class="min-w-0"><div class="text-[11px] uppercase tracking-wide text-slate-500 truncate">Total Tasks</div><div class="text-xl font-bold text-slate-900">{{ $total }}</div></div>
        </div>
        <div class="dp-stat-card" style="--dp-stat-accent:#059669;--dp-stat-soft:#ecfdf5">
            <div class="dp-stat-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div class="min-w-0"><div class="text-[11px] uppercase tracking-wide text-slate-500 truncate">Completed</div><div class="text-xl font-bold text-slate-900">{{ $done }}</div></div>
        </div>
        <div class="dp-stat-card" style="--dp-stat-accent:#d97706;--dp-stat-soft:#fffbeb">
            <div class="dp-stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
            <div class="min-w-0"><div class="text-[11px] uppercase tracking-wide text-slate-500 truncate">Pending</div><div class="text-xl font-bold text-slate-900">{{ $pending }}</div></div>
        </div>
        <div class="dp-stat-card" style="--dp-stat-accent:#2563eb;--dp-stat-soft:#eff6ff">
            <div class="dp-stat-icon"><i class="fa-solid fa-clock"></i></div>
            <div class="min-w-0"><div class="text-[11px] uppercase tracking-wide text-slate-500 truncate">Timed Tasks</div><div class="text-xl font-bold text-slate-900">{{ $scheduled }}</div></div>
        </div>
    </div>

    {{-- Planner tabs --}}
    <div class="space-y-4">
        <div class="dp-tabs" role="tablist" aria-label="Daily Planner sections">
            <button
                type="button"
                class="dp-tab-button {{ $activeTab === 'tasks' ? 'is-active' : '' }}"
                data-dp-tab="tasks"
                role="tab"
                aria-selected="{{ $activeTab === 'tasks' ? 'true' : 'false' }}"
            >
                <i class="fa-solid fa-list-check"></i>
                Tasks
                <span class="inline-flex items-center justify-center min-w-6 h-6 px-1.5 rounded-full dp-primary-bg-soft dp-primary-text text-xs">{{ $total }}</span>
            </button>
            <button
                type="button"
                class="dp-tab-button {{ $activeTab === 'week' ? 'is-active' : '' }}"
                data-dp-tab="week"
                role="tab"
                aria-selected="{{ $activeTab === 'week' ? 'true' : 'false' }}"
            >
                <i class="fa-solid fa-calendar-week"></i>
                This Week
                <span class="inline-flex items-center justify-center min-w-6 h-6 px-1.5 rounded-full dp-primary-bg-soft dp-primary-text text-xs">{{ $weekDays->sum(fn ($day) => $day['stats']['total']) }}</span>
            </button>
            <button
                type="button"
                class="dp-tab-button {{ $activeTab === 'history' ? 'is-active' : '' }}"
                data-dp-tab="history"
                role="tab"
                aria-selected="{{ $activeTab === 'history' ? 'true' : 'false' }}"
            >
                <i class="fa-solid fa-clock-rotate-left"></i>
                Past Tasks
                <span class="inline-flex items-center justify-center min-w-6 h-6 px-1.5 rounded-full bg-slate-100 text-slate-600 text-xs">{{ $pastPlans->total() }}</span>
            </button>
        </div>

        <section id="dp-tab-tasks" class="dp-tab-panel {{ $activeTab === 'tasks' ? 'is-active' : '' }}" role="tabpanel">
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="font-bold text-slate-900">
                    {{ $isToday ? "Today's Tasks" : 'Tasks for '.$date->format('d M Y') }}
                </h3>
                <p class="text-xs text-slate-500 mt-1">Timed tasks are shown first in the order they are scheduled.</p>
            </div>

            @if($total)
                <div class="flex flex-wrap items-center gap-2">
                    @if($pending)
                        <button type="button"
                                onclick="openBulkMoveModal('{{ $tomorrowDate }}', true)"
                                class="inline-flex items-center gap-2 text-sm dp-primary-text border dp-primary-border rounded-lg px-3 py-2 bg-white hover:bg-slate-50">
                            <i class="fa-solid fa-calendar-arrow-up"></i> Move selected
                        </button>
                        <button type="button"
                                onclick="moveAllPendingTomorrow()"
                                class="inline-flex items-center gap-2 text-sm text-amber-700 border border-amber-200 rounded-lg px-3 py-2 bg-amber-50 hover:bg-amber-100">
                            <i class="fa-solid fa-forward"></i> Move unfinished one-off tasks
                        </button>
                    @endif
                    <button type="submit" form="bulkDailyDelete"
                            class="inline-flex items-center gap-2 text-sm text-rose-700 border border-rose-200 rounded-lg px-3 py-2 bg-white hover:bg-rose-50"
                            data-confirm-click="Delete selected tasks? This action cannot be undone." data-confirm-title="Delete selected tasks?" data-confirm-text="Delete selected">
                        <i class="fa-solid fa-trash"></i> Delete selected
                    </button>
                </div>
            @endif
        </div>

        @if(!$total)
            <x-empty-state
                icon="fa-regular fa-calendar-check"
                title="No tasks saved for this day."
                message="Use Add Task to create a one-off task or a recurring task you only enter once."
            />
        @else
            <form id="bulkDailyDelete" method="POST" action="{{ route('daily-planner.items.bulk-destroy') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="occurrence_date" value="{{ $date->toDateString() }}">

                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[900px]">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-4 py-3 w-10"><input type="checkbox" id="dailySelectAll"></th>
                                <th class="px-3 py-3">Time</th>
                                <th class="px-3 py-3">Task</th>
                                <th class="px-3 py-3">Priority</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($plan->items as $item)
                                @php
                                    $start = $formatTime($item->start_time);
                                    $end = $formatTime($item->end_time);
                                @endphp
                                <tr class="dp-timeline-row border-t border-slate-100 {{ $item->is_completed ? 'bg-slate-50/70' : '' }}">
                                    <td class="px-4 py-4">
                                        <input class="daily-row" type="checkbox" name="ids[]" value="{{ $item->id }}" data-pending="{{ $item->is_completed ? '0' : '1' }}">
                                    </td>
                                    <td class="px-3 py-4 w-40">
                                        @if($start)
                                            <span class="dp-time-badge"><i class="fa-regular fa-clock"></i>{{ $start }}</span>
                                            @if($end)<div class="text-xs text-slate-400 mt-1 pl-1">to {{ $end }}</div>@endif
                                        @else
                                            <span class="text-xs text-slate-400 italic">Any time</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-4">
                                        <div class="font-semibold text-slate-800 {{ $item->is_completed ? 'line-through text-slate-400' : '' }}">
                                            {{ $item->title }}
                                        </div>
                                        @if($item->description)
                                            <div class="text-xs text-slate-500 mt-1 max-w-xl">{{ $item->description }}</div>
                                        @endif
                                        <div>
                                            <span class="dp-repeat-badge">
                                                <i class="fa-solid {{ $item->isRecurring() ? 'fa-repeat' : 'fa-clock' }}"></i>
                                                {{ $item->repeat_label ?? $item->repeatLabel() }}
                                            </span>
                                            @if($item->personalGoal)
                                                <span class="inline-flex items-center gap-1 mt-2 ml-1 px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-semibold">
                                                    <i class="fa-solid fa-bullseye"></i>
                                                    {{ \Illuminate\Support\Str::limit($item->personalGoal->title, 34) }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-4">
                                        @php
                                            $priorityClass = match($item->priority) {
                                                'high' => 'bg-rose-50 text-rose-700',
                                                'low' => 'bg-slate-100 text-slate-600',
                                                default => 'bg-amber-50 text-amber-700',
                                            };
                                        @endphp
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $priorityClass }}">{{ ucfirst($item->priority) }}</span>
                                    </td>
                                    <td class="px-3 py-4">
                                        @if($item->is_completed)
                                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700"><i class="fa-solid fa-circle-check"></i> Completed</span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500"><i class="fa-regular fa-circle"></i> Pending</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-right whitespace-nowrap">
                                        @php
                                            $editTaskPayload = [
                                                'id' => $item->id,
                                                'title' => $item->title,
                                                'plan_date' => $item->plan->plan_date->toDateString(),
                                                'personal_goal_id' => $item->personal_goal_id,
                                                'description' => $item->description,
                                                'priority' => $item->priority,
                                                'start_time' => $item->start_time ? substr((string) $item->start_time, 0, 5) : '',
                                                'end_time' => $item->end_time ? substr((string) $item->end_time, 0, 5) : '',
                                                'repeat_type' => $item->repeat_type ?: 'once',
                                                'repeat_days' => $item->repeat_days ?: [],
                                                'repeat_interval' => $item->repeat_interval ?: 1,
                                                'repeat_starts_on' => optional($item->repeat_starts_on)->toDateString(),
                                                'repeat_ends_on' => optional($item->repeat_ends_on)->toDateString(),
                                                'reminder_enabled' => (bool) ($item->reminder_enabled ?? false),
                                                'reminder_offset_minutes' => $item->reminder_offset_minutes,
                                                'reminder_custom_at' => optional($item->reminder_custom_at)->format('Y-m-d\TH:i'),
                                                'reminder_channels' => (array) ($item->reminder_channels ?? []),
                                                'occurrence_date' => $item->occurrence_date ?? $date->toDateString(),
                                                'is_recurring' => $item->isRecurring(),
                                                'action' => route('daily-planner.items.update', $item),
                                            ];
                                        @endphp
                                        @php
                                            $editTaskPayloadEncoded = base64_encode(
                                                json_encode(
                                                    $editTaskPayload,
                                                    JSON_UNESCAPED_UNICODE
                                                    | JSON_UNESCAPED_SLASHES
                                                    | JSON_INVALID_UTF8_SUBSTITUTE
                                                )
                                            );
                                        @endphp
                                        <button type="button"
                                                class="px-2.5 py-2 rounded-lg border border-slate-200 dp-primary-text bg-white"
                                                title="Edit task"
                                                data-task-encoded="{{ $editTaskPayloadEncoded }}">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>

                                        @if(!$item->is_completed && !$item->isRecurring())
                                            <button type="button"
                                                    onclick="openMoveTaskModal({{ $item->id }}, @js($item->title), @js(route('daily-planner.items.move', $item)), @js($date->copy()->addDay()->toDateString()))"
                                                    class="px-2.5 py-2 rounded-lg border border-amber-200 text-amber-700 bg-white"
                                                    title="Move task to another date">
                                                <i class="fa-solid fa-calendar-days"></i>
                                            </button>
                                        @endif

                                        <button type="submit" form="toggle-{{ $item->id }}"
                                                class="px-2.5 py-2 rounded-lg border border-slate-200 bg-white"
                                                title="{{ $item->is_completed ? 'Reopen task' : 'Mark complete' }}">
                                            <i class="fa-solid {{ $item->is_completed ? 'fa-rotate-left' : 'fa-check' }}"></i>
                                        </button>

                                        <button type="submit" form="delete-{{ $item->id }}"
                                                data-confirm-click="Delete this task? This action cannot be undone." data-confirm-title="Delete task?" data-confirm-text="Delete"
                                                class="px-2.5 py-2 rounded-lg border border-rose-200 text-rose-700 bg-white"
                                                title="Delete task">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>

            @foreach($plan->items as $item)
                <form id="toggle-{{ $item->id }}" method="POST" action="{{ route('daily-planner.items.toggle', $item) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="occurrence_date" value="{{ $item->occurrence_date ?? $date->toDateString() }}">
                </form>

                <form id="delete-{{ $item->id }}" method="POST" action="{{ route('daily-planner.items.destroy', $item) }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="occurrence_date" value="{{ $item->occurrence_date ?? $date->toDateString() }}">
                    <input
                        id="delete-scope-{{ $item->id }}"
                        type="hidden"
                        name="delete_scope"
                        value="{{ $item->isRecurring() ? 'occurrence' : 'series' }}"
                    >
                </form>
            @endforeach
        @endif
    </div>

        </section>

        <section id="dp-tab-week" class="dp-tab-panel {{ $activeTab === 'week' ? 'is-active' : '' }}" role="tabpanel">
            <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                    <div>
                        <h3 class="font-bold text-slate-900">
                            Week of {{ $weekStart->format('d M') }} – {{ $weekEnd->format('d M Y') }}
                        </h3>
                        <p class="text-sm text-slate-500">Every task due this week, including daily and repeating ones.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('daily-planner.index', ['date' => $weekStart->copy()->subWeek()->toDateString(), 'tab' => 'week']) }}"
                           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border bg-white text-sm hover:bg-slate-50">
                            <i class="fa-solid fa-chevron-left"></i> Previous week
                        </a>
                        @unless($weekStart->isSameDay(today()->startOfWeek(\Carbon\Carbon::MONDAY)))
                            <a href="{{ route('daily-planner.index', ['tab' => 'week']) }}"
                               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border bg-white text-sm hover:bg-slate-50">
                                This week
                            </a>
                        @endunless
                        <a href="{{ route('daily-planner.index', ['date' => $weekStart->copy()->addWeek()->toDateString(), 'tab' => 'week']) }}"
                           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border bg-white text-sm hover:bg-slate-50">
                            Next week <i class="fa-solid fa-chevron-right"></i>
                        </a>
                        <button type="button" onclick="openPlanWeekModal()"
                                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg dp-btn-primary text-sm font-medium">
                            <i class="fa-solid fa-calendar-plus"></i> Plan Week
                        </button>
                    </div>
                </div>

                <div class="dp-week-grid p-4">
                    @foreach($weekDays as $day)
                        @php $dayDate = $day['date']; @endphp
                        <div class="dp-week-day {{ $dayDate->isToday() ? 'is-today' : '' }}">
                            <div class="flex items-start justify-between gap-2">
                                <a href="{{ route('daily-planner.index', ['date' => $dayDate->toDateString()]) }}" class="block">
                                    <span class="block text-xs font-semibold uppercase tracking-wide {{ $dayDate->isToday() ? 'dp-primary-text' : 'text-slate-500' }}">
                                        {{ $dayDate->format('D') }}{{ $dayDate->isToday() ? ' · Today' : '' }}
                                    </span>
                                    <span class="block text-lg font-bold text-slate-900">{{ $dayDate->format('d M') }}</span>
                                </a>
                                <button type="button"
                                        onclick="openAddTaskForDate(@js($dayDate->toDateString()), @js($dayDate->format('l, d M Y')), 'week')"
                                        class="grid h-8 w-8 place-items-center rounded-lg border bg-white text-slate-600 hover:text-[var(--brand-1)]"
                                        aria-label="Add a task on {{ $dayDate->format('l, d M') }}">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>

                            @if($day['stats']['total'] > 0)
                                <div class="mt-2 text-xs text-slate-500">
                                    {{ $day['stats']['completed'] }}/{{ $day['stats']['total'] }} done
                                    <div class="mt-1 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                                        <div class="h-full dp-progress-bar" style="width: {{ $day['stats']['progress'] }}%"></div>
                                    </div>
                                </div>
                            @endif

                            <ul class="mt-3 space-y-2">
                                @forelse($day['items'] as $weekItem)
                                    <li class="dp-week-task {{ $weekItem->is_completed ? 'is-done' : '' }}">
                                        <form method="POST" action="{{ route('daily-planner.items.toggle', $weekItem) }}" class="m-0">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="occurrence_date" value="{{ $dayDate->toDateString() }}">
                                            <input type="hidden" name="return_tab" value="week">
                                            <button type="submit" class="dp-week-check"
                                                    aria-label="{{ $weekItem->is_completed ? 'Mark as not done' : 'Mark as done' }}: {{ $weekItem->title }}">
                                                <i class="fa-{{ $weekItem->is_completed ? 'solid fa-circle-check' : 'regular fa-circle' }}"></i>
                                            </button>
                                        </form>
                                        <div class="min-w-0">
                                            <div class="dp-week-task-title">{{ $weekItem->title }}</div>
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                                                @if($weekItem->start_time)
                                                    <span><i class="fa-regular fa-clock mr-0.5"></i>{{ $formatTime($weekItem->start_time) }}</span>
                                                @endif
                                                @if($weekItem->isRecurring())
                                                    <span class="text-violet-700"><i class="fa-solid fa-repeat mr-0.5"></i>{{ $weekItem->repeat_label }}</span>
                                                @endif
                                                <span class="capitalize">{{ $weekItem->priority }}</span>
                                            </div>
                                        </div>
                                    </li>
                                @empty
                                    <li class="text-xs text-slate-400">No tasks</li>
                                @endforelse
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="dp-tab-history\" class="dp-tab-panel {{ $activeTab === 'history' ? 'is-active' : '' }}" role="tabpanel">
            <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                    <div>
                        <h3 class="font-bold text-slate-900">
                            <i class="fa-solid fa-clock-rotate-left mr-2 dp-primary-text"></i>Past Tasks & Day Plans
                        </h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Search previous plans or tasks, filter by period, and reopen any saved day.
                        </p>
                    </div>
                    <div class="text-xs text-slate-500">
                        @if($pastPlans->total())
                            Showing {{ $pastPlans->firstItem() }}-{{ $pastPlans->lastItem() }} of {{ $pastPlans->total() }} saved plans
                        @else
                            0 saved plans found
                        @endif
                    </div>
                </div>

                <form method="GET" action="{{ route('daily-planner.index') }}" class="dp-filter-card">
                    <input type="hidden" name="tab" value="history">
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">

                    <div class="grid md:grid-cols-2 xl:grid-cols-5 gap-3 items-end">
                        <label class="block xl:col-span-2">
                            <span class="block text-xs font-semibold text-slate-600 mb-1">Search past tasks</span>
                            <div class="relative">
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                                <input
                                    type="search"
                                    name="history_search"
                                    value="{{ $historySearch }}"
                                    class="pm-input w-full pl-9"
                                    placeholder="Search plan title, notes or task..."
                                >
                            </div>
                        </label>

                        <label class="block">
                            <span class="block text-xs font-semibold text-slate-600 mb-1">Period</span>
                            <select name="history_period" id="historyPeriod" class="pm-input w-full" onchange="toggleHistoryCustomRange()">
                                <option value="all" @selected($historyPeriod === 'all')>All past dates</option>
                                <option value="last_7_days" @selected($historyPeriod === 'last_7_days')>Last 7 days</option>
                                <option value="last_30_days" @selected($historyPeriod === 'last_30_days')>Last 30 days</option>
                                <option value="last_90_days" @selected($historyPeriod === 'last_90_days')>Last 90 days</option>
                                <option value="this_month" @selected($historyPeriod === 'this_month')>This month</option>
                                <option value="last_month" @selected($historyPeriod === 'last_month')>Last month</option>
                                <option value="custom" @selected($historyPeriod === 'custom')>Custom range</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="block text-xs font-semibold text-slate-600 mb-1">Records per page</span>
                            <select name="history_per_page" class="pm-input w-full">
                                @foreach([10, 25, 50, 100] as $size)
                                    <option value="{{ $size }}" @selected($historyPerPage === $size)>{{ $size }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="flex gap-2">
                            <button type="submit" class="dp-btn-primary rounded-lg px-4 py-2.5 font-semibold inline-flex items-center gap-2">
                                <i class="fa-solid fa-filter"></i> Filter
                            </button>
                            <a
                                href="{{ route('daily-planner.index', ['date' => $date->toDateString(), 'tab' => 'history']) }}"
                                class="rounded-lg px-4 py-2.5 border bg-white text-slate-600 font-semibold inline-flex items-center gap-2"
                                title="Clear filters"
                            >
                                <i class="fa-solid fa-rotate-left"></i>
                                <span class="hidden sm:inline">Reset</span>
                            </a>
                        </div>
                    </div>

                    <div id="historyCustomRange" class="grid sm:grid-cols-2 gap-3 mt-3 {{ $historyPeriod === 'custom' ? '' : 'hidden' }}">
                        <label class="block">
                            <span class="block text-xs font-semibold text-slate-600 mb-1">From date</span>
                            <input type="date" name="history_from" value="{{ $historyFrom }}" class="pm-input w-full" max="{{ today()->subDay()->toDateString() }}">
                        </label>
                        <label class="block">
                            <span class="block text-xs font-semibold text-slate-600 mb-1">To date</span>
                            <input type="date" name="history_to" value="{{ $historyTo }}" class="pm-input w-full" max="{{ today()->subDay()->toDateString() }}">
                        </label>
                    </div>
                </form>

                @if($pastPlans->count())
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm min-w-[760px]">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-3 text-left">Date</th>
                                    <th class="px-3 py-3 text-left">Plan</th>
                                    <th class="px-3 py-3 text-center">Tasks</th>
                                    <th class="px-3 py-3 text-center">Completed</th>
                                    <th class="px-3 py-3 text-center">Pending</th>
                                    <th class="px-3 py-3 text-center">Progress</th>
                                    <th class="px-5 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pastPlans as $past)
                                    @php
                                        $pastTotal = (int) ($past->total ?? 0);
                                        $pastCompleted = (int) ($past->completed ?? 0);
                                        $pastPending = (int) ($past->pending ?? max(0, $pastTotal - $pastCompleted));
                                        $pastPercent = (int) ($past->progress ?? ($pastTotal > 0 ? round(($pastCompleted / $pastTotal) * 100) : 0));
                                    @endphp
                                    <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                                        <td class="px-5 py-3 font-medium text-slate-700 whitespace-nowrap">
                                            {{ $past->plan_date->format('D, d M Y') }}
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="font-medium text-slate-700">{{ $past->title ?: 'My Daily Plan' }}</div>
                                            @if($past->notes)
                                                <div class="text-xs text-slate-400 mt-1" title="{{ $past->notes }}">
                                                    {{ \Illuminate\Support\Str::limit($past->notes, 80) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-center font-semibold">{{ $pastTotal }}</td>
                                        <td class="px-3 py-3 text-center text-emerald-700 font-semibold">{{ $pastCompleted }}</td>
                                        <td class="px-3 py-3 text-center text-amber-600 font-semibold">{{ $pastPending }}</td>
                                        <td class="px-3 py-3 text-center">
                                            <span class="font-semibold dp-primary-text">{{ $pastPercent }}%</span>
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <a
                                                href="{{ route('daily-planner.index', ['date' => $past->plan_date->toDateString(), 'tab' => 'tasks']) }}"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border dp-primary-border dp-primary-text bg-white font-medium"
                                            >
                                                <i class="fa-regular fa-eye"></i> View Tasks
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($pastPlans->hasPages())
                        <div class="px-5 py-4 border-t border-slate-100">
                            {{ $pastPlans->links() }}
                        </div>
                    @endif
                @else
                    <x-empty-state
                        icon="fa-solid fa-magnifying-glass"
                        title="No past tasks found."
                        message="Try changing the search text or selected period."
                    />
                @endif
            </div>
        </section>
    </div>

</div>

{{-- Add Task Modal --}}
<div id="addTaskModal" class="dp-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="addTaskTitle">
    <div class="dp-modal-panel">
        <div class="flex items-center justify-between px-5 py-4 border-b">
            <div>
                <h3 id="addTaskTitle" class="font-bold text-lg">Add Task</h3>
                <p id="addTaskDateLabel" class="text-xs text-slate-500">{{ $date->format('l, d M Y') }}</p>
            </div>
            <button type="button" class="p-2 text-slate-500" onclick="closeDpModal('addTaskModal')"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form method="POST" action="{{ route('daily-planner.items.store') }}" class="p-5 space-y-4">
            @csrf
            <input id="addTaskPlanDate" type="hidden" name="plan_date" value="{{ $date->toDateString() }}">
            <input id="addTaskReturnTab" type="hidden" name="return_tab" value="">

            <div class="dp-form-tabs" role="tablist" aria-label="Task form sections">
                <button type="button" role="tab" id="addTask-tab-details" aria-controls="addTask-panel-details" aria-selected="true" tabindex="0" data-form-tab="details" class="dp-form-tab is-active">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i><span>Details</span>
                </button>
                <button type="button" role="tab" id="addTask-tab-schedule" aria-controls="addTask-panel-schedule" aria-selected="false" tabindex="-1" data-form-tab="schedule" class="dp-form-tab">
                    <i class="fa-solid fa-clock" aria-hidden="true"></i><span>Schedule & Repeat</span>
                </button>
                <button type="button" role="tab" id="addTask-tab-reminder" aria-controls="addTask-panel-reminder" aria-selected="false" tabindex="-1" data-form-tab="reminder" class="dp-form-tab">
                    <i class="fa-solid fa-bell" aria-hidden="true"></i><span>Reminder</span>
                </button>
            </div>

            <div class="dp-form-panel space-y-4" role="tabpanel" id="addTask-panel-details" aria-labelledby="addTask-tab-details" data-form-panel="details">
            <label class="block text-sm font-medium">Task <span class="text-rose-600">*</span>
                <input required name="title" value="{{ old('title') }}" class="pm-input mt-1 w-full" placeholder="What should I do?">
            </label>

            <label class="block text-sm font-medium">Description
                <textarea name="description" rows="3" class="pm-input mt-1 w-full" placeholder="Optional details">{{ old('description') }}</textarea>
            </label>

            <label class="block text-sm font-medium">Linked Goal <span class="text-slate-400 font-normal">(optional)</span>
                <select name="personal_goal_id" class="pm-input mt-1 w-full">
                    <option value="">No linked goal</option>
                    @foreach(($goalOptions ?? collect()) as $goalId => $goalTitle)<option value="{{ $goalId }}">{{ $goalTitle }}</option>@endforeach
                </select>
            </label>

            <div class="grid md:grid-cols-2 gap-3 mb-3"><div><label class="block text-sm font-medium text-slate-700 mb-1">Task Achievement</label><textarea name="achievements" rows="2" class="pm-input"></textarea></div><div><label class="block text-sm font-medium text-slate-700 mb-1">Task Challenge</label><textarea name="challenges" rows="2" class="pm-input"></textarea></div></div>
            </div>

            <div class="dp-form-panel space-y-4" role="tabpanel" id="addTask-panel-schedule" aria-labelledby="addTask-tab-schedule" data-form-panel="schedule" hidden>
            <div class="grid sm:grid-cols-3 gap-3">
                <label class="block text-sm font-medium">Priority
                    <select name="priority" class="pm-input mt-1 w-full">
                        <option value="high">High</option>
                        <option value="medium" selected>Medium</option>
                        <option value="low">Low</option>
                    </select>
                </label>
                <label class="block text-sm font-medium">Start Time
                    <input type="time" name="start_time" class="pm-input mt-1 w-full">
                </label>
                <label class="block text-sm font-medium">End Time
                    <input type="time" name="end_time" class="pm-input mt-1 w-full">
                </label>
            </div>

            <div class="dp-repeat-panel">
                <div class="flex items-start gap-3 mb-3">
                    <div class="grid h-9 w-9 place-items-center rounded-lg bg-white text-violet-700">
                        <i class="fa-solid fa-repeat"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-slate-800">Repeat task</h4>
                        <p class="text-xs text-slate-500">Create the task once and let My Digital Diary show it only on the scheduled days.</p>
                    </div>
                </div>

                <label class="block text-sm font-medium">
                    Repeat
                    <select id="addRepeatType" name="repeat_type" class="pm-input mt-1 w-full" onchange="toggleRepeatFields('add')">
                        <option value="once">Once</option>
                        <option value="daily">Every day</option>
                        <option value="specific_days">Specific days</option>
                        <option value="weekly">Every week</option>
                        <option value="monthly">Every month</option>
                    </select>
                </label>

                <div id="addRepeatFields" class="hidden mt-4 space-y-4">
                    <div id="addSpecificDays" class="hidden">
                        <div class="text-sm font-medium mb-2">Repeat on</div>
                        <div class="dp-week-days">
                            @foreach([
                                'monday' => 'Mon',
                                'tuesday' => 'Tue',
                                'wednesday' => 'Wed',
                                'thursday' => 'Thu',
                                'friday' => 'Fri',
                                'saturday' => 'Sat',
                                'sunday' => 'Sun',
                            ] as $dayValue => $dayLabel)
                                <label class="dp-day-check">
                                    <input type="checkbox" name="repeat_days[]" value="{{ $dayValue }}">
                                    <span>{{ $dayLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="block text-sm font-medium">
                            Starts
                            <input
                                id="addRepeatStarts"
                                type="date"
                                name="repeat_starts_on"
                                value="{{ $date->toDateString() }}"
                                class="pm-input mt-1 w-full"
                            >
                        </label>

                        <label class="block text-sm font-medium">
                            Ends <span class="text-slate-400 font-normal">(optional)</span>
                            <input
                                id="addRepeatEnds"
                                type="date"
                                name="repeat_ends_on"
                                min="{{ $date->toDateString() }}"
                                class="pm-input mt-1 w-full"
                            >
                        </label>
                    </div>

                    <input type="hidden" name="repeat_interval" value="1">

                    <div class="rounded-lg border border-violet-200 bg-white p-3 text-xs text-violet-800">
                        <i class="fa-solid fa-circle-info mr-1"></i>
                        Completing one recurring occurrence only completes that date. Future scheduled occurrences stay pending.
                    </div>
                </div>
            </div>

            </div>

            <div class="dp-form-panel space-y-4" role="tabpanel" id="addTask-panel-reminder" aria-labelledby="addTask-tab-reminder" data-form-panel="reminder" hidden>
            <div class="dp-repeat-panel">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex gap-3">
                        <i class="fa-solid fa-bell text-violet-600 mt-1"></i>
                        <div>
                            <h4 class="font-semibold text-slate-800">Task reminder</h4>
                            <p class="text-xs text-slate-500">Set it now and it will also appear under Reminders. Needs a start time or a custom date &amp; time.</p>
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm font-semibold">
                        <input type="checkbox" name="reminder_enabled" value="1" onchange="toggleTaskReminderFields('add', this.checked)">
                        Remind me
                    </label>
                </div>
                <div id="addTaskReminderFields" class="hidden mt-4 grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold">Remind me</label>
                        <select name="reminder_offset_minutes" class="pm-input mt-1 w-full" onchange="toggleCustomTaskReminder('add', this.value)">
                            <option value="0">At task time</option>
                            <option value="5">5 minutes before</option>
                            <option value="15" selected>15 minutes before</option>
                            <option value="30">30 minutes before</option>
                            <option value="60">1 hour before</option>
                            <option value="120">2 hours before</option>
                            <option value="1440">1 day before</option>
                            <option value="custom">Custom date &amp; time</option>
                        </select>
                    </div>
                    <div id="addTaskReminderCustom" class="hidden">
                        <label class="text-xs font-bold">Custom reminder date &amp; time</label>
                        <input type="datetime-local" name="reminder_custom_at" class="pm-input mt-1 w-full">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-bold">Notification channels</label>
                        <div class="mt-2 flex flex-wrap gap-3 text-sm">
                            <label><input type="checkbox" name="reminder_channels[]" value="in_app" checked> In-app</label>
                            <label><input type="checkbox" name="reminder_channels[]" value="push" checked> Push</label>
                            <label><input type="checkbox" name="reminder_channels[]" value="email"> Email</label>
                        </div>
                    </div>
                </div>
            </div>

            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeDpModal('addTaskModal')" class="px-4 py-2.5 rounded-lg border bg-white">Cancel</button>
                <button type="submit" class="px-4 py-2.5 rounded-lg dp-btn-primary font-medium"><i class="fa-solid fa-plus mr-1"></i>Add Task</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Task Modal --}}
<div id="editTaskModal" class="dp-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="editTaskTitle">
    <div class="dp-modal-panel">
        <div class="flex items-center justify-between px-5 py-4 border-b">
            <h3 id="editTaskTitle" class="font-bold text-lg">Edit Task</h3>
            <button type="button" class="p-2 text-slate-500" onclick="closeDpModal('editTaskModal')"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form id="editTaskForm" method="POST" action="" class="p-5 space-y-4">
            @csrf
            @method('PUT')

            <div class="dp-form-tabs" role="tablist" aria-label="Task form sections">
                <button type="button" role="tab" id="editTask-tab-details" aria-controls="editTask-panel-details" aria-selected="true" tabindex="0" data-form-tab="details" class="dp-form-tab is-active">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i><span>Details</span>
                </button>
                <button type="button" role="tab" id="editTask-tab-schedule" aria-controls="editTask-panel-schedule" aria-selected="false" tabindex="-1" data-form-tab="schedule" class="dp-form-tab">
                    <i class="fa-solid fa-clock" aria-hidden="true"></i><span>Schedule & Repeat</span>
                </button>
                <button type="button" role="tab" id="editTask-tab-reminder" aria-controls="editTask-panel-reminder" aria-selected="false" tabindex="-1" data-form-tab="reminder" class="dp-form-tab">
                    <i class="fa-solid fa-bell" aria-hidden="true"></i><span>Reminder</span>
                </button>
            </div>

            <div class="dp-form-panel space-y-4" role="tabpanel" id="editTask-panel-details" aria-labelledby="editTask-tab-details" data-form-panel="details">

            <label class="block text-sm font-medium">Task <span class="text-rose-600">*</span>
                <input id="editTaskName" required name="title" class="pm-input mt-1 w-full">
            </label>

            <label class="block text-sm font-medium">Description
                <textarea id="editTaskDescription" name="description" rows="3" class="pm-input mt-1 w-full"></textarea>
            </label>

            <label class="block text-sm font-medium">Task Date
                <input id="editTaskDate" type="date" name="plan_date" class="pm-input mt-1 w-full" required>
                <span class="block text-xs text-slate-500 mt-1">Change the date to move this pending task to another day.</span>
            </label>

            <label class="block text-sm font-medium">Linked Goal <span class="text-slate-400 font-normal">(optional)</span>
                <select id="editTaskGoal" name="personal_goal_id" class="pm-input mt-1 w-full">
                    <option value="">No linked goal</option>
                    @foreach(($goalOptions ?? collect()) as $goalId => $goalTitle)<option value="{{ $goalId }}">{{ $goalTitle }}</option>@endforeach
                </select>
            </label>

            </div>

            <div class="dp-form-panel space-y-4" role="tabpanel" id="editTask-panel-schedule" aria-labelledby="editTask-tab-schedule" data-form-panel="schedule" hidden>
            <div class="grid sm:grid-cols-3 gap-3">
                <label class="block text-sm font-medium">Priority
                    <select id="editTaskPriority" name="priority" class="pm-input mt-1 w-full">
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </label>
                <label class="block text-sm font-medium">Start Time
                    <input id="editTaskStart" type="time" name="start_time" class="pm-input mt-1 w-full">
                </label>
                <label class="block text-sm font-medium">End Time
                    <input id="editTaskEnd" type="time" name="end_time" class="pm-input mt-1 w-full">
                </label>
            </div>

            <input id="editOccurrenceDate" type="hidden" name="occurrence_date">

            <div class="dp-repeat-panel">
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="block text-sm font-medium">
                        Repeat
                        <select id="editRepeatType" name="repeat_type" class="pm-input mt-1 w-full" onchange="toggleRepeatFields('edit')">
                            <option value="once">Once</option>
                            <option value="daily">Every day</option>
                            <option value="specific_days">Specific days</option>
                            <option value="weekly">Every week</option>
                            <option value="monthly">Every month</option>
                        </select>
                    </label>

                    <label class="block text-sm font-medium">
                        Starts
                        <input id="editRepeatStarts" type="date" name="repeat_starts_on" class="pm-input mt-1 w-full">
                    </label>
                </div>

                <div id="editRepeatFields" class="hidden mt-4 space-y-4">
                    <div id="editSpecificDays" class="hidden">
                        <div class="text-sm font-medium mb-2">Repeat on</div>
                        <div class="dp-week-days">
                            @foreach([
                                'monday' => 'Mon',
                                'tuesday' => 'Tue',
                                'wednesday' => 'Wed',
                                'thursday' => 'Thu',
                                'friday' => 'Fri',
                                'saturday' => 'Sat',
                                'sunday' => 'Sun',
                            ] as $dayValue => $dayLabel)
                                <label class="dp-day-check">
                                    <input
                                        class="edit-repeat-day"
                                        type="checkbox"
                                        name="repeat_days[]"
                                        value="{{ $dayValue }}"
                                    >
                                    <span>{{ $dayLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <label class="block text-sm font-medium">
                        Ends <span class="text-slate-400 font-normal">(optional)</span>
                        <input id="editRepeatEnds" type="date" name="repeat_ends_on" class="pm-input mt-1 w-full">
                    </label>

                    <input type="hidden" name="repeat_interval" value="1">
                </div>

                <div id="editScopeBox" class="hidden mt-4">
                    <div class="text-sm font-semibold text-slate-700 mb-2">Apply changes to</div>
                    <div class="dp-scope-box">
                        <label class="dp-scope-option">
                            <input type="radio" name="edit_scope" value="occurrence">
                            <span>
                                <strong class="block text-sm text-slate-800">Only this occurrence</strong>
                                <span class="block text-xs text-slate-500 mt-1">Keep the rest of the recurring series unchanged.</span>
                            </span>
                        </label>

                        <label class="dp-scope-option">
                            <input type="radio" name="edit_scope" value="series" checked>
                            <span>
                                <strong class="block text-sm text-slate-800">Entire series</strong>
                                <span class="block text-xs text-slate-500 mt-1">Update the recurrence rule and all future scheduled appearances.</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            </div>

            <div class="dp-form-panel space-y-4" role="tabpanel" id="editTask-panel-reminder" aria-labelledby="editTask-tab-reminder" data-form-panel="reminder" hidden>
            <div class="dp-repeat-panel">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex gap-3">
                        <i class="fa-solid fa-bell text-violet-600 mt-1"></i>
                        <div>
                            <h4 class="font-semibold text-slate-800">Task reminder</h4>
                            <p class="text-xs text-slate-500">Set it now and it will also appear under Reminders. Needs a start time or a custom date &amp; time.</p>
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm font-semibold">
                        <input id="editReminderEnabled" type="checkbox" name="reminder_enabled" value="1" onchange="toggleTaskReminderFields('edit', this.checked)">
                        Remind me
                    </label>
                </div>
                <div id="editTaskReminderFields" class="hidden mt-4 grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold">Remind me</label>
                        <select id="editReminderOffset" name="reminder_offset_minutes" class="pm-input mt-1 w-full" onchange="toggleCustomTaskReminder('edit', this.value)">
                            <option value="0">At task time</option>
                            <option value="5">5 minutes before</option>
                            <option value="15">15 minutes before</option>
                            <option value="30">30 minutes before</option>
                            <option value="60">1 hour before</option>
                            <option value="120">2 hours before</option>
                            <option value="1440">1 day before</option>
                            <option value="custom">Custom date &amp; time</option>
                        </select>
                    </div>
                    <div id="editTaskReminderCustom" class="hidden">
                        <label class="text-xs font-bold">Custom reminder date &amp; time</label>
                        <input id="editReminderCustom" type="datetime-local" name="reminder_custom_at" class="pm-input mt-1 w-full">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-bold">Notification channels</label>
                        <div class="mt-2 flex flex-wrap gap-3 text-sm">
                            <label><input class="edit-reminder-channel" type="checkbox" name="reminder_channels[]" value="in_app" checked> In-app</label>
                            <label><input class="edit-reminder-channel" type="checkbox" name="reminder_channels[]" value="push" checked> Push</label>
                            <label><input class="edit-reminder-channel" type="checkbox" name="reminder_channels[]" value="email"> Email</label>
                        </div>
                    </div>
                </div>
            </div>

            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeDpModal('editTaskModal')" class="px-4 py-2.5 rounded-lg border bg-white">Cancel</button>
                <button type="submit" class="px-4 py-2.5 rounded-lg dp-btn-primary font-medium"><i class="fa-solid fa-floppy-disk mr-1"></i>Save Changes</button>
            </div>
        </form>
    </div>
</div>

{{-- Move One Task Modal --}}
<div id="moveTaskModal" class="dp-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="moveTaskTitle">
    <div class="dp-modal-panel max-w-lg">
        <div class="flex items-center justify-between px-5 py-4 border-b">
            <div>
                <h3 id="moveTaskTitle" class="font-bold text-lg">Move Pending Task</h3>
                <p id="moveTaskName" class="text-xs text-slate-500 mt-1"></p>
            </div>
            <button type="button" class="p-2 text-slate-500" onclick="closeDpModal('moveTaskModal')"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form id="moveTaskForm" method="POST" action="" class="p-5 space-y-4">
            @csrf
            @method('PATCH')
            <label class="block text-sm font-medium">Move to date
                <input id="moveTaskDate" type="date" name="target_date" min="{{ today()->toDateString() }}" class="pm-input mt-1 w-full" required>
            </label>
            <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                The existing task will be rescheduled, not duplicated. Its times, priority and linked goal stay unchanged.
            </div>
            <div class="flex flex-wrap justify-end gap-2">
                <button id="moveTaskTomorrowButton" type="button" class="px-4 py-2.5 rounded-lg border border-amber-200 bg-amber-50 text-amber-700 font-medium">Move to Tomorrow</button>
                <button type="button" onclick="closeDpModal('moveTaskModal')" class="px-4 py-2.5 rounded-lg border bg-white">Cancel</button>
                <button type="submit" class="px-4 py-2.5 rounded-lg dp-btn-primary font-medium"><i class="fa-solid fa-calendar-check mr-1"></i>Move Task</button>
            </div>
        </form>
    </div>
</div>

{{-- Bulk Move Pending Tasks Modal --}}
<div id="bulkMoveTaskModal" class="dp-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="bulkMoveTaskTitle">
    <div class="dp-modal-panel max-w-lg">
        <div class="flex items-center justify-between px-5 py-4 border-b">
            <div>
                <h3 id="bulkMoveTaskTitle" class="font-bold text-lg">Move Pending Tasks</h3>
                <p id="bulkMoveCount" class="text-xs text-slate-500 mt-1"></p>
            </div>
            <button type="button" class="p-2 text-slate-500" onclick="closeDpModal('bulkMoveTaskModal')"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form id="bulkMoveTaskForm" method="POST" action="{{ route('daily-planner.items.bulk-move') }}" class="p-5 space-y-4">
            @csrf
            @method('PATCH')
            <div id="bulkMoveIds"></div>
            <label class="block text-sm font-medium">Move selected pending tasks to
                <input id="bulkMoveDate" type="date" name="target_date" min="{{ today()->toDateString() }}" class="pm-input mt-1 w-full" required>
            </label>
            <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-sm text-slate-600">
                Completed tasks are left on their original date to preserve your history.
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeDpModal('bulkMoveTaskModal')" class="px-4 py-2.5 rounded-lg border bg-white">Cancel</button>
                <button id="bulkMoveSubmit" type="submit" class="px-4 py-2.5 rounded-lg dp-btn-primary font-medium"><i class="fa-solid fa-forward mr-1"></i>Move Tasks</button>
            </div>
        </form>
    </div>
</div>

<form id="moveAllPendingTomorrowForm" method="POST" action="{{ route('daily-planner.items.bulk-move') }}" class="hidden">
    @csrf
    @method('PATCH')
    <input type="hidden" name="target_date" value="{{ $tomorrowDate }}">
    @foreach($plan->items->where('is_completed', false) as $pendingItem)
        <input type="hidden" name="ids[]" value="{{ $pendingItem->id }}">
    @endforeach
</form>

{{-- Plan Week Modal --}}
<div id="planWeekModal" class="dp-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="planWeekTitle">
    <div class="dp-modal-panel max-w-3xl">
        <div class="flex items-center justify-between px-5 py-4 border-b">
            <div>
                <h3 id="planWeekTitle" class="font-bold text-lg">Plan the Week</h3>
                <p class="text-xs text-slate-500">{{ $weekStart->format('D d M') }} – {{ $weekEnd->format('D d M Y') }} · add tasks and tick the days they happen</p>
            </div>
            <button type="button" class="p-2 text-slate-500" onclick="closeDpModal('planWeekModal')" aria-label="Close"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form id="planWeekForm" method="POST" action="{{ route('daily-planner.week.store') }}" class="p-5 space-y-4" novalidate>
            @csrf
            <input type="hidden" name="week_start" value="{{ $weekStart->toDateString() }}">

            <div id="weekTaskRows" class="space-y-3"></div>

            <button type="button" onclick="addWeekTaskRow()" class="w-full rounded-xl border border-dashed border-slate-300 py-2.5 text-sm font-medium text-slate-600 hover:border-[var(--brand-1)] hover:text-[var(--brand-1)]">
                <i class="fa-solid fa-plus mr-1"></i> Add another task
            </button>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeDpModal('planWeekModal')" class="px-4 py-2.5 rounded-lg border bg-white">Cancel</button>
                <button type="submit" class="px-4 py-2.5 rounded-lg dp-btn-primary font-medium"><i class="fa-solid fa-calendar-check mr-1"></i>Save Week Plan</button>
            </div>
        </form>
    </div>
</div>

<template id="weekTaskTemplate">
    <div class="dp-week-row" data-week-row>
        <div class="flex items-center justify-between gap-2 mb-3">
            <span class="text-sm font-semibold text-slate-700" data-week-row-label>Task</span>
            <button type="button" class="text-slate-400 hover:text-rose-600 text-sm" data-week-row-remove aria-label="Remove this task">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </div>

        <div class="grid sm:grid-cols-4 gap-3">
            <label class="block text-sm font-medium sm:col-span-2">Task <span class="text-rose-600">*</span>
                <input name="tasks[__i__][title]" class="pm-input mt-1 w-full" placeholder="e.g. Morning run" data-week-title>
            </label>
            <label class="block text-sm font-medium">Start
                <input type="time" name="tasks[__i__][start_time]" class="pm-input mt-1 w-full">
            </label>
            <label class="block text-sm font-medium">End
                <input type="time" name="tasks[__i__][end_time]" class="pm-input mt-1 w-full">
            </label>
        </div>

        <div class="mt-3">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <span class="text-sm font-medium">Days <span class="text-rose-600">*</span></span>
                <div class="flex flex-wrap gap-1 text-xs">
                    <button type="button" class="dp-chip" data-week-preset="all">All week</button>
                    <button type="button" class="dp-chip" data-week-preset="weekdays">Mon–Fri</button>
                    <button type="button" class="dp-chip" data-week-preset="weekend">Weekend</button>
                    <button type="button" class="dp-chip" data-week-preset="none">Clear</button>
                </div>
            </div>
            <div class="dp-week-days">
                    <label class="dp-day-check"><input type="checkbox" name="tasks[__i__][days][]" value="monday"><span>Mon</span></label>
                    <label class="dp-day-check"><input type="checkbox" name="tasks[__i__][days][]" value="tuesday"><span>Tue</span></label>
                    <label class="dp-day-check"><input type="checkbox" name="tasks[__i__][days][]" value="wednesday"><span>Wed</span></label>
                    <label class="dp-day-check"><input type="checkbox" name="tasks[__i__][days][]" value="thursday"><span>Thu</span></label>
                    <label class="dp-day-check"><input type="checkbox" name="tasks[__i__][days][]" value="friday"><span>Fri</span></label>
                    <label class="dp-day-check"><input type="checkbox" name="tasks[__i__][days][]" value="saturday"><span>Sat</span></label>
                    <label class="dp-day-check"><input type="checkbox" name="tasks[__i__][days][]" value="sunday"><span>Sun</span></label>
            </div>
            <p class="hidden mt-1 text-xs text-rose-600" data-week-days-error>Tick at least one day.</p>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="tasks[__i__][repeat_weekly]" value="1">
                Repeat every week
            </label>
            <label class="inline-flex items-center gap-2 text-sm">Priority
                <select name="tasks[__i__][priority]" class="pm-input py-1.5">
                    <option value="high">High</option>
                    <option value="medium" selected>Medium</option>
                    <option value="low">Low</option>
                </select>
            </label>
        </div>

        <details class="mt-3 text-sm">
            <summary class="cursor-pointer text-slate-600">More options</summary>
            <div class="mt-3 grid sm:grid-cols-2 gap-3">
                <label class="block text-sm font-medium">Description
                    <textarea name="tasks[__i__][description]" rows="2" class="pm-input mt-1 w-full" placeholder="Optional details"></textarea>
                </label>
                <label class="block text-sm font-medium">Linked Goal
                    <select name="tasks[__i__][personal_goal_id]" class="pm-input mt-1 w-full">
                        <option value="">No linked goal</option>
                        @foreach(($goalOptions ?? collect()) as $goalId => $goalTitle)<option value="{{ $goalId }}">{{ $goalTitle }}</option>@endforeach
                    </select>
                </label>
            </div>
        </details>
    </div>
</template>

{{-- Save Day Plan Modal --}}
<div id="dayPlanModal" class="dp-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="dayPlanTitle">
    <div class="dp-modal-panel">
        <div class="flex items-center justify-between px-5 py-4 border-b">
            <div>
                <h3 id="dayPlanTitle" class="font-bold text-lg">Save Day Plan</h3>
                <p class="text-xs text-slate-500">{{ $date->format('l, d M Y') }}</p>
            </div>
            <button type="button" class="p-2 text-slate-500" onclick="closeDpModal('dayPlanModal')"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form method="POST" action="{{ route('daily-planner.update') }}" class="p-5 space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="plan_date" value="{{ $date->toDateString() }}">

            <label class="block text-sm font-medium">Plan Title
                <input type="text" name="title" value="{{ old('title', $plan->title ?: 'My Daily Plan') }}" class="pm-input mt-1 w-full" required>
            </label>

            <label class="block text-sm font-medium">Day Notes
                <textarea name="notes" rows="5" class="pm-input mt-1 w-full" placeholder="Focus, reminders, reflections or anything important for this day...">{{ old('notes', $plan->notes) }}</textarea>
            </label>

            <div class="grid md:grid-cols-2 gap-4">
                <label class="block text-sm font-medium">Achievements
                    <textarea name="achievements" rows="3" class="pm-input mt-1 w-full" placeholder="What did you achieve today?">{{ old('achievements', $plan->achievements) }}</textarea>
                </label>
                <label class="block text-sm font-medium">Challenges
                    <textarea name="challenges" rows="3" class="pm-input mt-1 w-full" placeholder="What challenges did you face?">{{ old('challenges', $plan->challenges) }}</textarea>
                </label>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeDpModal('dayPlanModal')" class="px-4 py-2.5 rounded-lg border bg-white">Cancel</button>
                <button type="submit" class="px-4 py-2.5 rounded-lg dp-btn-primary font-medium"><i class="fa-solid fa-floppy-disk mr-1"></i>Save Day Plan</button>
            </div>
        </form>
    </div>
</div>

<script>

    window.toggleTaskReminderFields = function(prefix, enabled) {
        const box = document.getElementById(prefix + 'TaskReminderFields');
        if (box) box.classList.toggle('hidden', !enabled);
    };
    window.toggleCustomTaskReminder = function(prefix, value) {
        const custom = document.getElementById(prefix + 'TaskReminderCustom');
        if (custom) custom.classList.toggle('hidden', value !== 'custom');
    };

    /*
     * Long modal forms are split into tabs (data-form-tab /
     * data-form-panel) so they need no tall scrolling. Tabs are scoped to
     * their own <form>, so Add and Edit don't affect each other.
     */
    window.selectDpFormTab = function(form, key, focus) {
        if (!form) return;
        form.querySelectorAll('[data-form-tab]').forEach(tab => {
            const active = tab.dataset.formTab === key;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
            if (active && focus) tab.focus();
        });
        form.querySelectorAll('[data-form-panel]').forEach(panel => {
            panel.hidden = panel.dataset.formPanel !== key;
        });
    };

    document.addEventListener('click', function (event) {
        const tab = event.target.closest('[data-form-tab]');
        if (tab) selectDpFormTab(tab.closest('form'), tab.dataset.formTab, false);
    });

    document.addEventListener('keydown', function (event) {
        const tab = event.target.closest ? event.target.closest('[data-form-tab]') : null;
        if (!tab || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        const tabs = [...tab.closest('[role="tablist"]').querySelectorAll('[data-form-tab]')];
        let index = tabs.indexOf(tab);
        if (event.key === 'ArrowRight') index = (index + 1) % tabs.length;
        if (event.key === 'ArrowLeft') index = (index - 1 + tabs.length) % tabs.length;
        if (event.key === 'Home') index = 0;
        if (event.key === 'End') index = tabs.length - 1;
        event.preventDefault();
        selectDpFormTab(tab.closest('form'), tabs[index].dataset.formTab, true);
    });

    /*
     * A required field on a hidden tab can't show the browser's
     * validation bubble, so jump to that field's tab first.
     */
    document.addEventListener('invalid', function (event) {
        const panel = event.target.closest ? event.target.closest('[data-form-panel]') : null;
        if (!panel || !panel.hidden) return;
        selectDpFormTab(panel.closest('form'), panel.dataset.formPanel, false);
        window.setTimeout(() => event.target.reportValidity && event.target.reportValidity(), 0);
    }, true);

    window.openDpModal = function(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.querySelectorAll('form').forEach(form => {
            const first = form.querySelector('[data-form-tab]');
            if (first) selectDpFormTab(form, first.dataset.formTab, false);
        });
        modal.classList.add('is-open');
    };

    window.closeDpModal = function(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('is-open');
    };

    /*
     * Add Task can be opened for any day (header button = the viewed
     * day; the week view's "+" = that day). returnTab 'week' sends the
     * user back to the week view after saving.
     */
    window.openAddTaskForDate = function(date, label, returnTab) {
        const planDate = document.getElementById('addTaskPlanDate');
        const dateLabel = document.getElementById('addTaskDateLabel');
        const returnInput = document.getElementById('addTaskReturnTab');
        const starts = document.getElementById('addRepeatStarts');
        const ends = document.getElementById('addRepeatEnds');

        if (planDate) planDate.value = date;
        if (dateLabel) dateLabel.textContent = label;
        if (returnInput) returnInput.value = returnTab || '';
        if (starts) starts.value = date;
        if (ends) ends.min = date;

        openDpModal('addTaskModal');
    };

    // ----- Plan the Week -----
    let weekRowIndex = 0;
    const WEEK_PRESETS = {
        all: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        weekdays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        weekend: ['saturday', 'sunday'],
        none: [],
    };

    function renumberWeekRows() {
        const rows = [...document.querySelectorAll('#weekTaskRows [data-week-row]')];
        rows.forEach((row, index) => {
            row.querySelector('[data-week-row-label]').textContent = 'Task ' + (index + 1);
            row.querySelector('[data-week-row-remove]').hidden = rows.length === 1;
        });
    }

    window.addWeekTaskRow = function(values) {
        const template = document.getElementById('weekTaskTemplate');
        const holder = document.getElementById('weekTaskRows');
        if (!template || !holder) return null;

        const index = weekRowIndex++;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__i__', index);
        const row = wrapper.firstElementChild;

        if (values) {
            Object.entries(values).forEach(([key, value]) => {
                if (key === 'days') {
                    row.querySelectorAll('input[name$="[days][]"]').forEach(box => {
                        box.checked = Array.isArray(value) && value.includes(box.value);
                    });
                    return;
                }
                const field = row.querySelector(`[name="tasks[${index}][${key}]"]`);
                if (!field) return;
                if (field.type === 'checkbox') field.checked = !!value && value !== '0';
                else field.value = value ?? '';
            });
        }

        holder.appendChild(row);
        renumberWeekRows();
        return row;
    };

    window.openPlanWeekModal = function() {
        if (!document.querySelector('#weekTaskRows [data-week-row]')) addWeekTaskRow();
        openDpModal('planWeekModal');
        window.setTimeout(() => document.querySelector('#weekTaskRows [data-week-title]')?.focus(), 50);
    };

    document.getElementById('weekTaskRows')?.addEventListener('click', function (event) {
        const row = event.target.closest('[data-week-row]');
        if (!row) return;

        const preset = event.target.closest('[data-week-preset]');
        if (preset) {
            const days = WEEK_PRESETS[preset.dataset.weekPreset] || [];
            row.querySelectorAll('input[name$="[days][]"]').forEach(box => {
                box.checked = days.includes(box.value);
            });
            row.querySelector('[data-week-days-error]').classList.add('hidden');
            return;
        }

        if (event.target.closest('[data-week-row-remove]')) {
            row.remove();
            renumberWeekRows();
        }
    });

    document.getElementById('weekTaskRows')?.addEventListener('change', function (event) {
        const row = event.target.closest('[data-week-row]');
        if (row && event.target.matches('input[name$="[days][]"]')) {
            row.querySelector('[data-week-days-error]').classList.add('hidden');
        }
    });

    // Every row needs a name and at least one day before submitting.
    document.getElementById('planWeekForm')?.addEventListener('submit', function (event) {
        let firstProblem = null;

        this.querySelectorAll('[data-week-row]').forEach(row => {
            const title = row.querySelector('[data-week-title]');
            const hasDay = !!row.querySelector('input[name$="[days][]"]:checked');
            const dayError = row.querySelector('[data-week-days-error]');

            title.classList.toggle('border-rose-500', title.value.trim() === '');
            dayError.classList.toggle('hidden', hasDay);

            if (!firstProblem && title.value.trim() === '') firstProblem = title;
            if (!firstProblem && !hasDay) firstProblem = row.querySelector('input[name$="[days][]"]');
        });

        if (firstProblem) {
            event.preventDefault();
            firstProblem.focus();
        }
    });

    // Restore the rows (and reopen) when the server rejected a week plan.
    (function () {
        const oldTasks = @json(old('tasks', []));
        const entries = Array.isArray(oldTasks) ? oldTasks : Object.values(oldTasks || {});
        if (entries.length === 0) return;
        entries.forEach(values => addWeekTaskRow(values));
        openDpModal('planWeekModal');
    })();

    window.toggleRepeatFields = function(prefix) {
        const type = document.getElementById(prefix + 'RepeatType');
        const fields = document.getElementById(prefix + 'RepeatFields');
        const specific = document.getElementById(prefix + 'SpecificDays');

        if (!type || !fields) return;

        const recurring = type.value !== 'once';
        fields.classList.toggle('hidden', !recurring);

        if (specific) {
            specific.classList.toggle(
                'hidden',
                type.value !== 'specific_days'
            );
        }

        const starts = document.getElementById(prefix + 'RepeatStarts');
        const ends = document.getElementById(prefix + 'RepeatEnds');

        if (starts) {
            starts.required = recurring;
        }

        if (ends && starts && starts.value) {
            ends.min = starts.value;
        }
    };

    document.addEventListener('change', function (event) {
        if (
            event.target
            && (
                event.target.id === 'addRepeatStarts'
                || event.target.id === 'editRepeatStarts'
            )
        ) {
            const prefix = event.target.id.startsWith('add')
                ? 'add'
                : 'edit';

            const ends = document.getElementById(prefix + 'RepeatEnds');

            if (ends) {
                ends.min = event.target.value || '';
            }
        }
    });

    toggleRepeatFields('add');

    window.openEditTaskFromButton = function(button) {
        if (!button) return;

        const encoded = button.dataset.taskEncoded || '';

        if (!encoded) {
            console.error('Daily Planner edit payload is missing.');
            return;
        }

        try {
            const binary = atob(encoded);
            const bytes = Uint8Array.from(
                binary,
                character => character.charCodeAt(0)
            );
            const task = JSON.parse(
                new TextDecoder('utf-8').decode(bytes)
            );

            window.openEditTask(task);
        } catch (error) {
            console.error('Could not open Daily Planner edit form.', error);
        }
    };

    window.openEditTask = function(task) {
        const modal = document.getElementById('editTaskModal');
        const form = document.getElementById('editTaskForm');

        if (!modal || !form) {
            console.error('Daily Planner edit modal/form was not found.');
            return;
        }

        /*
         * Open the modal first so an optional/missing field can never stop
         * the user from seeing the edit form.
         */
        openDpModal('editTaskModal');

        const setValue = function(id, value) {
            const element = document.getElementById(id);
            if (element) element.value = value ?? '';
        };

        form.action = task.action || '';

        setValue('editTaskName', task.title || '');
        setValue('editTaskDescription', task.description || '');
        setValue('editTaskDate', task.plan_date || '{{ $date->toDateString() }}');
        setValue(
            'editTaskGoal',
            task.personal_goal_id ? String(task.personal_goal_id) : ''
        );
        setValue('editTaskPriority', task.priority || 'medium');
        setValue('editTaskStart', task.start_time || '');
        setValue('editTaskEnd', task.end_time || '');

        setValue('editRepeatType', task.repeat_type || 'once');
        setValue(
            'editRepeatStarts',
            task.repeat_starts_on || task.plan_date || ''
        );
        setValue('editRepeatEnds', task.repeat_ends_on || '');

        /*
         * Reminder controls are optional in this Blade. The previous code
         * assumed these IDs always existed, which caused:
         * "Cannot set properties of null"
         * and stopped execution before the modal could open.
         */
        const reminderEnabled = !!task.reminder_enabled;
        const reminderEnabledField =
            document.getElementById('editReminderEnabled');

        if (reminderEnabledField) {
            reminderEnabledField.checked = reminderEnabled;
        }

        const offset = task.reminder_custom_at
            ? 'custom'
            : String(task.reminder_offset_minutes ?? 15);

        setValue('editReminderOffset', offset);
        setValue('editReminderCustom', task.reminder_custom_at || '');

        document
            .querySelectorAll('.edit-reminder-channel')
            .forEach(function (checkbox) {
                checkbox.checked = Array.isArray(task.reminder_channels)
                    ? task.reminder_channels.includes(checkbox.value)
                    : ['in_app', 'push'].includes(checkbox.value);
            });

        if (document.getElementById('editTaskReminderFields')) {
            toggleTaskReminderFields('edit', reminderEnabled);
        }

        if (document.getElementById('editTaskReminderCustom')) {
            toggleCustomTaskReminder('edit', offset);
        }

        setValue(
            'editOccurrenceDate',
            task.occurrence_date || task.plan_date || ''
        );

        document
            .querySelectorAll('.edit-repeat-day')
            .forEach(function (checkbox) {
                checkbox.checked = Array.isArray(task.repeat_days)
                    && task.repeat_days.includes(checkbox.value);
            });

        const scopeBox = document.getElementById('editScopeBox');
        if (scopeBox) {
            scopeBox.classList.toggle('hidden', !task.is_recurring);
        }

        toggleRepeatFields('edit');
    };

    window.openMoveTaskModal = function(id, title, action, tomorrow) {
        document.getElementById('moveTaskForm').action = action;
        document.getElementById('moveTaskName').textContent = title || 'Pending task';
        document.getElementById('moveTaskDate').value = tomorrow;
        document.getElementById('moveTaskTomorrowButton').onclick = function() {
            document.getElementById('moveTaskDate').value = tomorrow;
            document.getElementById('moveTaskForm').requestSubmit();
        };
        openDpModal('moveTaskModal');
    };

    window.openBulkMoveModal = function(defaultDate, selectedOnly) {
        const boxes = [...document.querySelectorAll('.daily-row')].filter(box => box.checked && box.dataset.pending === '1');
        const submit = document.getElementById('bulkMoveSubmit');
        if (boxes.length === 0) {
            document.getElementById('bulkMoveCount').textContent = 'Select at least one pending task first.';
            document.getElementById('bulkMoveIds').innerHTML = '';
            submit.disabled = true;
            submit.classList.add('opacity-50', 'cursor-not-allowed');
            openDpModal('bulkMoveTaskModal');
            return;
        }
        submit.disabled = false;
        submit.classList.remove('opacity-50', 'cursor-not-allowed');
        const holder = document.getElementById('bulkMoveIds');
        holder.innerHTML = '';
        boxes.forEach(box => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = box.value;
            holder.appendChild(input);
        });
        document.getElementById('bulkMoveDate').value = defaultDate;
        document.getElementById('bulkMoveCount').textContent = `${boxes.length} pending task${boxes.length === 1 ? '' : 's'} selected`;
        openDpModal('bulkMoveTaskModal');
    };

    window.moveAllPendingTomorrow = function() {
        const pending = [...document.querySelectorAll('.daily-row')].filter(box => box.dataset.pending === '1');
        if (pending.length === 0) return;
        pending.forEach(box => { box.checked = true; });
        openBulkMoveModal('{{ $tomorrowDate }}', false);
    };

    document.addEventListener('click', function(event) {
        const editButton = event.target.closest('[data-task-encoded]');

        if (!editButton) return;

        /*
         * The inline onclick remains for backwards compatibility, but this
         * delegated listener guarantees Edit still responds if a page-level
         * script or CSP prevents the inline handler from resolving.
         */
        if (
            typeof window.openEditTaskFromButton === 'function'
            && !editButton.dataset.editHandled
        ) {
            editButton.dataset.editHandled = '1';
            window.openEditTaskFromButton(editButton);

            window.setTimeout(function() {
                delete editButton.dataset.editHandled;
            }, 0);
        }
    });

    document.querySelectorAll('[data-dp-tab]').forEach(button => {
        button.addEventListener('click', function () {
            const target = this.dataset.dpTab;

            document.querySelectorAll('[data-dp-tab]').forEach(tabButton => {
                const active = tabButton.dataset.dpTab === target;
                tabButton.classList.toggle('is-active', active);
                tabButton.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            document.querySelectorAll('.dp-tab-panel').forEach(panel => {
                panel.classList.toggle('is-active', panel.id === `dp-tab-${target}`);
            });
        });
    });

    function toggleHistoryCustomRange() {
        const period = document.getElementById('historyPeriod');
        const customRange = document.getElementById('historyCustomRange');
        if (!period || !customRange) return;
        customRange.classList.toggle('hidden', period.value !== 'custom');
    }

    document.getElementById('dailySelectAll')?.addEventListener('change', function () {
        document.querySelectorAll('.daily-row').forEach(cb => cb.checked = this.checked);
    });

    document.querySelectorAll('.dp-modal-backdrop').forEach(modal => {
        modal.addEventListener('click', e => {
            if (e.target === modal) closeDpModal(modal.id);
        });
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.dp-modal-backdrop.is-open').forEach(modal => closeDpModal(modal.id));
        }
    });

    setTimeout(() => {
        document.getElementById('dpFlash')?.remove();
        document.getElementById('dpError')?.remove();
    }, 5000);
</script>
@endsection
