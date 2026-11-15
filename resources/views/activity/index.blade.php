@extends('layouts.app')

@section('title', 'Activity Log')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shadow-sm shrink-0">
                <i class="fa-solid fa-clock-rotate-left text-xl" aria-hidden="true"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Activity Log</h1>
        </div>
        <a href="{{ route('user-guide') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50">
            <i class="fa-solid fa-book-open text-[var(--brand-1)]"></i>
            User Guide
        </a>
    </div>

    <div class="border-b border-slate-200 mb-6 overflow-x-auto">
        <nav class="flex gap-6 min-w-max" aria-label="Activity sections">
            <a href="{{ route('activity') }}" class="inline-flex items-center gap-2 py-3 text-sm font-semibold text-[var(--brand-1)] border-b-2" style="border-color: var(--brand-1);">
                <i class="fa-solid fa-clock-rotate-left"></i> Activity Log
            </a>
        </nav>
    </div>

    <p class="text-sm text-slate-500 mb-6">
        A pulse of what you've been doing lately across expenses, income, plans, tasks, meetings,
        sleep, diet, and reminders - not an exhaustive record of every change in every module.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        @foreach ($stats as $stat)
            <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 border-l-4 border-l-{{ $stat['color'] }}-400 p-4">
                <div class="w-9 h-9 rounded-lg bg-{{ $stat['color'] }}-50 text-{{ $stat['color'] }}-600 flex items-center justify-center mb-2">
                    <i class="{{ $stat['icon'] }} text-sm" aria-hidden="true"></i>
                </div>
                <p class="text-xs text-slate-500 uppercase tracking-wide truncate">{{ $stat['label'] }}</p>
                <p class="text-xl font-bold text-slate-800 truncate">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('activity') }}" class="flex flex-wrap items-end gap-3 mb-4">
        <div class="flex-1 min-w-[180px] max-w-xs">
            <label for="q" class="sr-only">Search activity</label>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm" aria-hidden="true"></i>
                <input type="search" id="q" name="q" value="{{ $search }}" placeholder="Search activity..."
                       class="pm-input pl-9 text-sm">
            </div>
        </div>

        <div>
            <label for="activity-type" class="sr-only">Filter by activity type</label>
            <select id="activity-type" name="type" class="pm-input text-sm">
                <option value="" @selected(!$type)>All activity types</option>
                <option value="expense" @selected($type === 'expense')>Expenses</option>
                <option value="income" @selected($type === 'income')>Income</option>
                <option value="planner" @selected($type === 'planner')>Planner</option>
                <option value="task" @selected($type === 'task')>Tasks</option>
                <option value="meeting" @selected($type === 'meeting')>Meetings</option>
                <option value="reminder" @selected($type === 'reminder')>Reminders</option>
                <option value="signature" @selected($type === 'signature')>Signed Documents</option>
                <option value="business_card" @selected($type === 'business_card')>Business Card</option>
                <option value="sleep" @selected($type === 'sleep')>Sleep</option>
                <option value="diet" @selected($type === 'diet')>Diet</option>
            </select>
        </div>

        <div>
            <label for="activity-period" class="sr-only">Filter by period</label>
            <select id="activity-period" name="period" onchange="pmToggleActivityDateRange(this)" class="pm-input text-sm">
                <option value="" @selected(!$period)>All time</option>
                <option value="daily" @selected($period === 'daily')>Today</option>
                <option value="weekly" @selected($period === 'weekly')>This week</option>
                <option value="monthly" @selected($period === 'monthly')>This month</option>
                <option value="range" @selected($period === 'range')>Custom range...</option>
            </select>
        </div>

        <div id="activity-date-range" class="flex items-end gap-2" style="{{ $period === 'range' ? '' : 'display: none;' }}">
            <input type="date" name="from" value="{{ $from }}" class="pm-input text-sm">
            <span class="text-slate-400 text-sm pb-2">to</span>
            <input type="date" name="to" value="{{ $to }}" class="pm-input text-sm">
        </div>

        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
            Filter
        </button>
        @if ($search || $type || $period)
            <a href="{{ route('activity') }}" class="text-sm text-slate-500 hover:text-slate-700 transition-colors pb-2.5">Clear</a>
        @endif
    </form>

    <script>
        function pmToggleActivityDateRange(select) {
            var wrapper = document.getElementById('activity-date-range');
            if (wrapper) { wrapper.style.display = select.value === 'range' ? 'flex' : 'none'; }
        }
    </script>

    <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
        @if ($activity->isEmpty())
            @if ($search)
                <p class="text-sm text-slate-400">No activity matches "{{ $search }}". <a href="{{ route('activity') }}" class="text-[var(--brand-1)] hover:underline">Clear search</a>.</p>
            @else
                <p class="text-sm text-slate-400">Nothing logged yet - activity across your modules will show up here.</p>
            @endif
        @else
            <ol class="relative border-l border-slate-100 ml-3 space-y-5">
                @foreach ($activity as $item)
                    <li class="ml-4">
                        <span class="absolute -left-[9px] w-4 h-4 rounded-full bg-{{ $item['color'] }}-100 border-2 border-white flex items-center justify-center">
                            <i class="{{ $item['icon'] }} text-{{ $item['color'] }}-600" style="font-size: 7px;" aria-hidden="true"></i>
                        </span>
                        <p class="text-sm text-slate-700">{{ $item['text'] }}</p>
                        <time class="text-xs text-slate-400" datetime="{{ $item['time']->toIso8601String() }}">
                            {{ $item['time']->format('Y-m-d H:i') }} &middot; {{ $item['time']->diffForHumans() }}
                        </time>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    <nav aria-label="Pagination" class="mt-4">
        {{ $activity->links() }}
    </nav>

    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 text-sm text-[var(--brand-1)] hover:underline mt-4">
        <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>
        Back to Dashboard
    </a>
@endsection
