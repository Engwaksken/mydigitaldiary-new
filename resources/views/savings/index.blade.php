@extends('layouts.app')

@section('title', 'Savings')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 space-y-5">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                <i class="fa-solid fa-piggy-bank text-emerald-600 mr-2"></i>Savings
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Turn savings intentions into visible goals, contributions and progress.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('savings-goals.index') }}"
               class="px-4 py-2 rounded-lg bg-emerald-600 text-white font-semibold">
                <i class="fa-solid fa-bullseye mr-1"></i>Manage Goals
            </a>

            <a href="{{ route('savings-contributions.index') }}"
               class="px-4 py-2 rounded-lg border bg-white font-semibold">
                <i class="fa-solid fa-coins mr-1"></i>Contributions
            </a>
        </div>
    </div>

    {{-- Summary statistics --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white border rounded-xl p-4 border-l-4 border-l-emerald-500">
            <div class="text-xs text-slate-500">Total Saved</div>
            <div class="text-xl font-bold">{{ format_money($totalSaved) }}</div>
        </div>

        <div class="bg-white border rounded-xl p-4 border-l-4 border-l-blue-500">
            <div class="text-xs text-slate-500">Total Target</div>
            <div class="text-xl font-bold">{{ format_money($totalTarget) }}</div>
        </div>

        <div class="bg-white border rounded-xl p-4 border-l-4 border-l-amber-500">
            <div class="text-xs text-slate-500">Remaining</div>
            <div class="text-xl font-bold">{{ format_money($totalRemaining) }}</div>
        </div>

        <div class="bg-white border rounded-xl p-4 border-l-4 border-l-violet-500">
            <div class="text-xs text-slate-500">Goals</div>
            <div class="text-xl font-bold">{{ number_format((int) $totalGoals) }}</div>
        </div>
    </div>

    {{-- Search --}}
    <form method="GET"
          action="{{ route('savings.index') }}"
          class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-end gap-3">
            <label class="flex-1">
                <span class="block text-xs font-semibold text-slate-600 mb-1">
                    Search savings goals
                </span>

                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>

                    <input type="search"
                           name="search"
                           value="{{ $search }}"
                           placeholder="Search by goal name, status or notes..."
                           class="pm-input w-full pl-9">
                </div>
            </label>

            <div class="flex gap-2">
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 text-white font-semibold hover:bg-emerald-700">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Search
                </button>

                @if($search !== '')
                    <a href="{{ route('savings.index') }}"
                       class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-600 font-semibold hover:bg-slate-50">
                        <i class="fa-solid fa-rotate-left"></i>
                        Clear
                    </a>
                @endif
            </div>
        </div>
    </form>

    {{-- Results heading --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <h2 class="font-bold text-slate-900">Savings goals</h2>

            <p class="text-xs text-slate-500 mt-1">
                @if(method_exists($goals, 'total'))
                    Showing {{ $goals->firstItem() ?? 0 }}-{{ $goals->lastItem() ?? 0 }}
                    of {{ $goals->total() }} result(s)
                @else
                    {{ $goals->count() }} result(s)
                @endif
                · 9 goals per page
            </p>
        </div>

        @if($search !== '')
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                <i class="fa-solid fa-filter"></i>
                {{ $search }}
            </span>
        @endif
    </div>

    {{-- Three cards per row on desktop --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($goals as $goal)
            <article class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm min-w-0 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-bold text-slate-900 truncate"
                                title="{{ $goal->name }}">
                                {{ $goal->name }}
                            </h2>

                            <span class="text-xs px-2 py-1 rounded-full bg-slate-100 text-slate-600 whitespace-nowrap">
                                {{ ucwords(str_replace('_',' ',$goal->status)) }}
                            </span>
                        </div>
                    </div>

                    <div class="font-black text-emerald-700 shrink-0">
                        {{ $goal->progress_percent }}%
                    </div>
                </div>

                <p class="text-sm text-slate-500 mt-3">
                    Saved
                    <strong class="text-slate-700">{{ format_money($goal->saved_amount) }}</strong>
                    of
                    <strong class="text-slate-700">{{ format_money($goal->target_amount) }}</strong>
                </p>

                @if($goal->target_date)
                    <p class="text-xs text-slate-400 mt-1">
                        <i class="fa-regular fa-calendar mr-1"></i>
                        Target {{ $goal->target_date->format('d M Y') }}
                    </p>
                @endif

                <div class="mt-4 h-2.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full"
                         style="width:{{ min(100,$goal->progress_percent) }}%"
                         role="progressbar"
                         aria-valuenow="{{ min(100,$goal->progress_percent) }}"
                         aria-valuemin="0"
                         aria-valuemax="100"></div>
                </div>

                <div class="grid grid-cols-2 gap-3 mt-4">
                    <div class="rounded-xl bg-emerald-50/70 p-3">
                        <div class="text-[10px] uppercase tracking-wide text-emerald-600 font-semibold">
                            Saved
                        </div>
                        <div class="font-bold text-slate-800 mt-1">
                            {{ format_money($goal->saved_amount) }}
                        </div>
                    </div>

                    <div class="rounded-xl bg-amber-50/70 p-3">
                        <div class="text-[10px] uppercase tracking-wide text-amber-600 font-semibold">
                            Remaining
                        </div>
                        <div class="font-bold text-slate-800 mt-1">
                            {{ format_money($goal->remaining_amount) }}
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-100">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-slate-800">
                            Recent contributions
                        </h3>

                        <a href="{{ route('savings-contributions.index', ['savings_goal_id' => $goal->id]) }}"
                           class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">
                            View all
                        </a>
                    </div>

                    @if($goal->contributions->isEmpty())
                        <p class="text-xs text-slate-400 mt-2">
                            No contributions recorded yet.
                        </p>
                    @else
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach($goal->contributions->take(3) as $contribution)
                                <span class="px-2.5 py-1.5 rounded-lg bg-emerald-50 text-emerald-800 text-xs">
                                    {{ format_money($contribution->amount) }}
                                    ·
                                    {{ $contribution->contributed_at?->format('d M Y') }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </article>
        @empty
            <div class="md:col-span-2 xl:col-span-3 bg-white border rounded-2xl">
                <x-empty-state
                    icon="fa-solid fa-piggy-bank"
                    title="{{ $search !== '' ? 'No matching savings goals' : 'No savings goal yet' }}"
                    message="{{ $search !== '' ? 'Try another search term or clear the current search.' : 'Create a goal, set a target and start recording contributions.' }}"
                >
                    <x-slot name="action">

                @if($search !== '')
                    <a href="{{ route('savings.index') }}"
                       class="inline-flex px-4 py-2 rounded-lg border bg-white text-slate-700">
                        Clear Search
                    </a>
                @else
                    <a href="{{ route('savings-goals.index') }}"
                       class="inline-flex px-4 py-2 rounded-lg bg-emerald-600 text-white">
                        Create Savings Goal
                    </a>
                @endif
                    </x-slot>
                </x-empty-state>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($goals->hasPages())
        <div class="bg-white border border-slate-200 rounded-2xl px-4 py-3">
            {{ $goals->onEachSide(1)->links() }}
        </div>
    @endif
</div>
@endsection
