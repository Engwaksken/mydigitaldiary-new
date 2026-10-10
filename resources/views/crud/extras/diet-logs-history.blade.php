@php
    $history = $dailyEatingHistory ?? null;
    $historyPeriod = $historyPeriod ?? request('history_period', 'month');
    $historyFrom = $historyFrom ?? request('history_from');
    $historyTo = $historyTo ?? request('history_to');
    $historyPerPage = (int) ($historyPerPage ?? request('history_per_page', 10));
    $historyFilteredEntries = (int) ($historyFilteredEntries ?? 0);
    $historyDaysRecorded = (int) ($historyDaysRecorded ?? ($history?->total() ?? 0));

    $mealLabels = [
        'breakfast' => 'Breakfast',
        'lunch' => 'Lunch',
        'snack' => 'Snacks',
        'dinner' => 'Dinner',
        'supper' => 'Supper',
    ];

    $mealIcons = [
        'breakfast' => 'fa-mug-hot',
        'lunch' => 'fa-bowl-food',
        'snack' => 'fa-apple-whole',
        'dinner' => 'fa-utensils',
        'supper' => 'fa-moon',
    ];
@endphp

<div class="pm-card-bg rounded-2xl border border-slate-100 border-l-4 border-l-orange-400 shadow-sm p-5">
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-slate-800">Daily eating history</h2>
            <p class="text-sm text-slate-500 mt-1">
                Each row represents one day. Breakfast, lunch, snacks, dinner and supper logged on that day are grouped together.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <div class="rounded-xl bg-orange-50 px-3 py-2 min-w-[120px]">
                <p class="text-[11px] uppercase tracking-wide font-semibold text-orange-600">Meal logs</p>
                <p class="text-lg font-bold text-slate-800">
                    {{ number_format($historyFilteredEntries) }}
                </p>
            </div>

            <div class="rounded-xl bg-emerald-50 px-3 py-2 min-w-[120px]">
                <p class="text-[11px] uppercase tracking-wide font-semibold text-emerald-600">Days recorded</p>
                <p class="text-lg font-bold text-slate-800">
                    {{ number_format($historyDaysRecorded) }}
                </p>
            </div>
        </div>
    </div>

    <form method="GET"
          action="{{ route('diet-logs.index') }}"
          class="mt-5 rounded-xl border border-slate-100 bg-slate-50/60 p-4">
        <input type="hidden" name="diet_tab" value="history">

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3 items-end">
            <div>
                <label for="diet-history-period"
                       class="block text-xs font-semibold text-slate-500 mb-1">
                    Period
                </label>
                <select id="diet-history-period"
                        name="history_period"
                        class="pm-input"
                        onchange="pmToggleDietHistoryRange()">
                    <option value="today" @selected($historyPeriod === 'today')>Today</option>
                    <option value="week" @selected($historyPeriod === 'week')>Last 7 days</option>
                    <option value="month" @selected($historyPeriod === 'month')>This month</option>
                    <option value="three_months" @selected($historyPeriod === 'three_months')>Last 3 months</option>
                    <option value="range" @selected($historyPeriod === 'range')>Custom range</option>
                    <option value="all" @selected($historyPeriod === 'all')>All history</option>
                </select>
            </div>

            <div data-diet-history-range>
                <label for="diet-history-from"
                       class="block text-xs font-semibold text-slate-500 mb-1">
                    From
                </label>
                <input id="diet-history-from"
                       type="date"
                       name="history_from"
                       value="{{ $historyFrom }}"
                       class="pm-input">
            </div>

            <div data-diet-history-range>
                <label for="diet-history-to"
                       class="block text-xs font-semibold text-slate-500 mb-1">
                    To
                </label>
                <input id="diet-history-to"
                       type="date"
                       name="history_to"
                       value="{{ $historyTo }}"
                       class="pm-input">
            </div>

            <div>
                <label for="diet-history-per-page"
                       class="block text-xs font-semibold text-slate-500 mb-1">
                    Days per page
                </label>
                <select id="diet-history-per-page"
                        name="history_per_page"
                        class="pm-input">
                    @foreach ([10, 25, 50] as $size)
                        <option value="{{ $size }}" @selected($historyPerPage === $size)>
                            {{ $size }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="xl:col-span-2 flex gap-2">
                <button type="submit"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg btn-primary px-4 py-2.5 text-sm font-semibold text-white">
                    <i class="fa-solid fa-filter"></i>
                    Apply filter
                </button>

                <a href="{{ route('diet-logs.index', ['diet_tab' => 'history']) }}"
                   class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                    Clear
                </a>
            </div>
        </div>
    </form>

    <div class="mt-5 space-y-3">
        @forelse ($history ?? [] as $day)
            @php
                $dayDate = \Carbon\Carbon::parse($day->history_date);
                $dayMeals = collect($day->meals ?? []);
            @endphp

            <div class="rounded-2xl border border-slate-100 bg-white overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-100 bg-slate-50/70 px-4 py-3">
                    <div>
                        <div class="font-bold text-slate-800">
                            {{ $dayDate->format('l, d M Y') }}
                        </div>
                        <div class="mt-0.5 text-xs text-slate-500">
                            {{ (int) $day->meal_count }}
                            {{ (int) $day->meal_count === 1 ? 'meal logged' : 'meals logged' }}
                        </div>
                    </div>

                    <div class="flex items-center gap-2 text-xs">
                        <span class="rounded-full bg-orange-50 px-2.5 py-1 font-semibold text-orange-700">
                            <i class="fa-solid fa-bolt mr-1"></i>
                            {{ number_format((int) $day->total_calories) }} kcal
                        </span>
                    </div>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach ($dayMeals as $meal)
                        @php
                            $mealType = strtolower((string) $meal->meal_type);
                            $mealLabel = $mealLabels[$mealType] ?? ucfirst($mealType ?: 'Meal');
                            $mealIcon = $mealIcons[$mealType] ?? 'fa-utensils';
                            $mealTime = optional($meal->logged_at)
                                ?->timezone(auth()->user()->timezone ?: config('app.timezone', 'Africa/Kampala'))
                                ?->format('g:i A');
                        @endphp

                        <div class="grid grid-cols-1 lg:grid-cols-[150px_1fr_auto] gap-3 px-4 py-3 hover:bg-slate-50/50">
                            <div>
                                <div class="inline-flex items-center gap-2 font-semibold text-slate-700">
                                    <i class="fa-solid {{ $mealIcon }} text-orange-500"></i>
                                    {{ $mealLabel }}
                                </div>

                                @if ($mealTime)
                                    <div class="mt-1 text-[11px] text-slate-400">
                                        {{ $mealTime }}
                                    </div>
                                @endif
                            </div>

                            <div class="min-w-0">
                                <div class="text-sm text-slate-700 whitespace-pre-line">
                                    {{ trim((string) $meal->food_items) ?: '—' }}
                                </div>

                                @if (filled($meal->notes))
                                    <div class="mt-1 text-xs text-slate-500">
                                        {{ \Illuminate\Support\Str::limit((string) $meal->notes, 180) }}
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center justify-between lg:justify-end gap-2">
                                @if (! is_null($meal->calories))
                                    <span class="text-xs font-semibold text-slate-500">
                                        {{ number_format((int) $meal->calories) }} kcal
                                    </span>
                                @endif

                                <button type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700"
                                        onclick="document.getElementById('diet-history-edit-{{ $meal->id }}').showModal()">
                                    <i class="fa-solid fa-pen"></i>
                                    Edit
                                </button>
                            </div>
                        </div>

                        <dialog id="diet-history-edit-{{ $meal->id }}"
                                class="pm-dialog pm-modal-shell">
                            <div class="pm-modal-content">
                                <header class="pm-modal-header">
                                    <div class="pm-modal-heading">
                                        <div class="pm-modal-icon">
                                            <i class="fa-solid fa-bowl-food"></i>
                                        </div>
                                        <div>
                                            <h2 class="pm-modal-title">Update meal log</h2>
                                            <p class="pm-modal-description">
                                                Update this meal while keeping it under
                                                {{ $dayDate->format('d M Y') }}.
                                            </p>
                                        </div>
                                    </div>

                                    <button type="button"
                                            class="pm-modal-close"
                                            onclick="document.getElementById('diet-history-edit-{{ $meal->id }}').close()">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </header>

                                <form method="POST"
                                      action="{{ route('diet-logs.update', $meal->id) }}">
                                    @csrf
                                    @method('PUT')

                                    <div class="pm-modal-body grid gap-4">
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-500 mb-1">
                                                Meal
                                            </label>
                                            <select name="meal_type"
                                                    class="pm-input"
                                                    required>
                                                @foreach ($mealLabels as $value => $label)
                                                    <option value="{{ $value }}"
                                                            @selected($mealType === $value)>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-slate-500 mb-1">
                                                Food items & portions
                                            </label>
                                            <textarea name="food_items"
                                                      class="pm-input"
                                                      rows="4"
                                                      maxlength="4000"
                                                      required>{{ $meal->food_items }}</textarea>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-slate-500 mb-1">
                                                Date
                                            </label>
                                            <input type="date"
                                                   name="logged_at"
                                                   class="pm-input"
                                                   value="{{ optional($meal->logged_at)->format('Y-m-d') }}"
                                                   required>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-slate-500 mb-1">
                                                Notes
                                            </label>
                                            <textarea name="notes"
                                                      class="pm-input"
                                                      rows="3"
                                                      maxlength="3000">{{ $meal->notes }}</textarea>
                                        </div>
                                    </div>

                                    <footer class="pm-modal-footer">
                                        <button type="button"
                                                class="pm-btn-cancel"
                                                onclick="document.getElementById('diet-history-edit-{{ $meal->id }}').close()">
                                            Cancel
                                        </button>

                                        <button type="submit"
                                                class="pm-btn-save btn-primary text-white">
                                            <i class="fa-solid fa-floppy-disk"></i>
                                            Save changes
                                        </button>
                                    </footer>
                                </form>
                            </div>
                        </dialog>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-slate-100 px-4 py-10 text-center text-slate-400">
                <i class="fa-solid fa-bowl-food text-3xl mb-2 block opacity-30"></i>
                No eating history was found for this period.

                @if ($historyPeriod !== 'all')
                    <div class="mt-3">
                        <a href="{{ route('diet-logs.index', ['diet_tab' => request('diet_tab', 'history'), 'history_period' => 'all']) }}"
                           class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            View all eating history
                        </a>
                    </div>
                @endif
            </div>
        @endforelse
    </div>

    @if ($history && $history->hasPages())
        <div class="mt-4">
            {{ $history->links() }}
        </div>
    @endif
</div>

<script>
function pmToggleDietHistoryRange() {
    const select = document.getElementById('diet-history-period');
    const show = select && select.value === 'range';

    document.querySelectorAll('[data-diet-history-range]').forEach(function (element) {
        element.classList.toggle('hidden', !show);
    });
}

document.addEventListener('DOMContentLoaded', pmToggleDietHistoryRange);
</script>
