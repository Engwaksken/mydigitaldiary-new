@extends('layouts.app')

@section('title', 'Spiritual Growth')

@section('content')
@php
    $has = fn (string $column) => in_array($column, $columns ?? [], true);

    $displayTitle = function ($item) {
        return data_get($item, 'practice_title')
            ?: data_get($item, 'title')
            ?: ucfirst(str_replace('_', ' ', data_get($item, 'practice_type', 'Reflection')));
    };

    $displayDate = function ($item) {
        $value = data_get($item, 'practiced_at')
            ?: data_get($item, 'created_at');

        if (!$value) return '—';

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d M Y');
        } catch (\Throwable) {
            return '—';
        }
    };
@endphp

<div class="max-w-7xl mx-auto px-3 sm:px-5 py-5">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-5">
        <div>
            <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-seedling text-emerald-600"></i>
                Spiritual Growth
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Record prayer, meditation, reflection, gratitude, worship and other practices in a way that respects every faith and personal path.
            </p>
        </div>

        @if($tableExists)
            <button
                type="button"
                onclick="openSpiritualForm()"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 text-sm font-black"
            >
                <i class="fa-solid fa-plus"></i>
                Add Practice
            </button>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <div class="font-bold mb-1">Please correct the following:</div>
            <ul class="list-disc pl-5 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @unless($tableExists)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6">
            <h2 class="font-black text-amber-900">
                Spiritual Growth database table is missing
            </h2>
            <p class="text-sm text-amber-800 mt-2">
                Run <code class="font-mono">php artisan migrate --force</code>, then reload this page.
            </p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-emerald-500">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Total Practices</div>
                        <div class="mt-1 text-2xl font-black text-slate-900">{{ number_format($stats['total'] ?? 0) }}</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                        <i class="fa-solid fa-seedling"></i>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-sky-500">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">This Week</div>
                        <div class="mt-1 text-2xl font-black text-slate-900">{{ number_format($stats['this_week'] ?? 0) }}</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center">
                        <i class="fa-solid fa-calendar-week"></i>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-violet-500">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">This Month</div>
                        <div class="mt-1 text-2xl font-black text-slate-900">{{ number_format($stats['this_month'] ?? 0) }}</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-violet-50 text-violet-700 flex items-center justify-center">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-amber-500">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Recurring</div>
                        <div class="mt-1 text-2xl font-black text-slate-900">{{ number_format($stats['recurring'] ?? 0) }}</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center">
                        <i class="fa-solid fa-repeat"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100">
                <form method="GET" action="{{ route('spiritual-practices.index') }}" class="grid grid-cols-1 md:grid-cols-[1fr_180px_auto] gap-2">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input
                            type="search"
                            name="q"
                            value="{{ request('q') }}"
                            placeholder="Search practice, faith/path, reflection..."
                            class="w-full rounded-lg border-slate-300 pl-9"
                        >
                    </div>

                    <select name="period" class="rounded-lg border-slate-300">
                        <option value="">All dates</option>
                        <option value="today" @selected(request('period') === 'today')>Today</option>
                        <option value="week" @selected(request('period') === 'week')>This week</option>
                        <option value="month" @selected(request('period') === 'month')>This month</option>
                    </select>

                    <button class="rounded-lg bg-slate-900 text-white px-4 py-2.5 font-bold text-sm">
                        Filter
                    </button>
                </form>
            </div>

            @if($items->isEmpty())
                <div class="p-10 text-center">
                    <i class="fa-solid fa-seedling text-4xl text-slate-300"></i>
                    <h3 class="font-black text-slate-800 mt-3">No spiritual growth entries found</h3>
                    <p class="text-sm text-slate-500 mt-1">
                        Add a practice to begin building your reflection history.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-[920px] w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">Practice</th>
                                <th class="px-4 py-3 text-left">Faith / Path</th>
                                <th class="px-4 py-3 text-left">Reflection</th>
                                <th class="px-4 py-3 text-left">Repeat</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            @foreach($items as $item)
                                @php
                                    $repeat = data_get($item, 'recurrence_frequency');
                                @endphp

                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold text-slate-700">
                                        {{ $displayDate($item) }}
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="font-black text-slate-900">{{ $displayTitle($item) }}</div>
                                        <div class="text-xs text-slate-500 mt-0.5">
                                            {{ ucfirst(str_replace('_', ' ', (string) data_get($item, 'practice_type', 'reflection'))) }}
                                        </div>
                                    </td>

                                    <td class="px-4 py-3 text-slate-600">
                                        {{ data_get($item, 'faith_path') ?: 'Prefer not to specify' }}
                                    </td>

                                    <td class="px-4 py-3 text-slate-600 max-w-sm">
                                        {{ \Illuminate\Support\Str::limit((string) data_get($item, 'reflection'), 100) ?: '—' }}
                                    </td>

                                    <td class="px-4 py-3">
                                        @if($repeat)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 text-violet-700 px-2.5 py-1 text-xs font-bold">
                                                <i class="fa-solid fa-repeat"></i>
                                                {{ ucfirst($repeat) }}
                                            </span>
                                        @else
                                            <span class="text-slate-400 text-xs">Does not repeat</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                type="button"
                                                data-id="{{ $item->id }}"
                                                data-faith-path="{{ e((string) data_get($item, 'faith_path')) }}"
                                                data-custom-faith="{{ e((string) data_get($item, 'custom_faith_path')) }}"
                                                data-practice-type="{{ e((string) data_get($item, 'practice_type')) }}"
                                                data-practice-title="{{ e((string) (data_get($item, 'practice_title') ?: data_get($item, 'title'))) }}"
                                                data-theme-topic="{{ e((string) data_get($item, 'theme_topic')) }}"
                                                data-practiced-at="{{ e((string) data_get($item, 'practiced_at')) }}"
                                                data-duration="{{ e((string) data_get($item, 'duration_minutes')) }}"
                                                data-inspirational="{{ e((string) (data_get($item, 'inspirational_text') ?: data_get($item, 'scriptures'))) }}"
                                                data-source-tradition="{{ e((string) data_get($item, 'source_tradition')) }}"
                                                data-reflection="{{ e((string) data_get($item, 'reflection')) }}"
                                                data-gratitude="{{ e((string) data_get($item, 'gratitude')) }}"
                                                data-intention="{{ e((string) data_get($item, 'intention')) }}"
                                                data-community-place="{{ e((string) data_get($item, 'community_place')) }}"
                                                data-recurrence="{{ e((string) data_get($item, 'recurrence_frequency')) }}"
                                                data-recurrence-end="{{ e((string) data_get($item, 'recurrence_ends_at')) }}"
                                                data-notes="{{ e((string) data_get($item, 'notes')) }}"
                                                onclick="openSpiritualFormFromButton(this)"
                                                class="px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 text-xs font-semibold"
                                            >
                                                <i class="fa-solid fa-pen mr-1"></i>Edit
                                            </button>

                                            <form method="POST" action="{{ route('spiritual-practices.destroy', $item->id) }}" onsubmit="return confirm('Delete this spiritual growth entry?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="px-3 py-2 rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50 text-xs font-semibold">
                                                    <i class="fa-solid fa-trash mr-1"></i>Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if(method_exists($items, 'links'))
                    <div class="p-4 border-t border-slate-100">
                        {{ $items->links() }}
                    </div>
                @endif
            @endif
        </div>
    @endunless
</div>

<dialog id="spiritual-form-modal" class="w-[96vw] max-w-3xl rounded-2xl p-0 backdrop:bg-slate-950/50">
    <div class="bg-white rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <div>
                <h2 id="spiritual-form-title" class="text-lg font-black">Add Spiritual Growth</h2>
            </div>

            <button
                type="button"
                onclick="document.getElementById('spiritual-form-modal').close()"
                class="w-9 h-9 rounded-full hover:bg-slate-100"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="spiritual-form" method="POST" class="p-5">
            <div class="mb-4 rounded-xl border border-fuchsia-200 bg-fuchsia-50 p-3">
                <div class="flex flex-col sm:flex-row gap-2">
                    <div class="flex-1">
                        <label for="spiritual-ai-topic" class="text-xs font-bold text-fuchsia-900">Topic / title</label>
                        <input id="spiritual-ai-topic" type="text" class="w-full rounded-lg border-slate-300 mt-1"
                               placeholder="e.g. Gratitude, patience, morning reflection">
                    </div>
                    <button type="button" id="spiritual-ai-generate" onclick="generateSpiritualAiDraft()"
                            class="self-end inline-flex items-center gap-2 rounded-xl bg-fuchsia-600 px-4 py-2.5 text-sm font-bold text-white">
                        <i class="fa-solid fa-wand-magic-sparkles"></i><span>AI Generate</span>
                    </button>
                </div>
                <p id="spiritual-ai-status" class="mt-2 text-xs text-fuchsia-700">AI fills empty fields only. You can edit everything before saving.</p>
                <div id="spiritual-bible-refs" class="mt-2 hidden rounded-lg border border-slate-200 bg-white p-2.5 text-xs text-slate-700"></div>
            </div>
            @csrf
            <input id="spiritual-method" type="hidden" name="_method" value="POST">

            <div class="pm-form-tabs" role="tablist" aria-label="Practice sections" data-pm-form-tabs>
                <button type="button" role="tab" id="spiritual-tab-0" aria-controls="spiritual-panel-0" aria-selected="true" tabindex="0" data-pm-form-tab="0" class="pm-form-tab is-active">Practice</button>
                <button type="button" role="tab" id="spiritual-tab-1" aria-controls="spiritual-panel-1" aria-selected="false" tabindex="-1" data-pm-form-tab="1" class="pm-form-tab">Reflection</button>
                <button type="button" role="tab" id="spiritual-tab-2" aria-controls="spiritual-panel-2" aria-selected="false" tabindex="-1" data-pm-form-tab="2" class="pm-form-tab">Place, Repeat &amp; Notes</button>
            </div>

            <div class="pm-form-panel grid grid-cols-1 md:grid-cols-2 gap-4" role="tabpanel" id="spiritual-panel-0" aria-labelledby="spiritual-tab-0" data-pm-form-panel="0">
                @if($has('faith_path'))
                    <div>
                        <label class="block text-sm font-bold mb-1">Faith / Spiritual Path</label>
                        <select id="spiritual-faith-path" name="faith_path" class="w-full rounded-lg border-slate-300">
                            @foreach($faithPaths as $path)
                                <option value="{{ $path }}">{{ $path }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if($has('custom_faith_path'))
                    <div id="custom-faith-wrapper" class="hidden">
                        <label class="block text-sm font-bold mb-1">Custom Faith / Path</label>
                        <input id="spiritual-custom-faith" name="custom_faith_path" placeholder="Your faith or path" class="w-full rounded-lg border-slate-300">
                    </div>
                @endif

                @if($has('practice_type'))
                    <div>
                        <label class="block text-sm font-bold mb-1">Practice Type</label>
                        <select id="spiritual-practice-type" name="practice_type" class="w-full rounded-lg border-slate-300">
                            @foreach($practiceTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if($has('practice_title') || $has('title'))
                    <div>
                        <label class="block text-sm font-bold mb-1">Practice Title</label>
                        <input id="spiritual-practice-title" name="{{ $has('practice_title') ? 'practice_title' : 'title' }}" placeholder="e.g. Morning prayer" class="w-full rounded-lg border-slate-300">
                    </div>
                @endif

                @if($has('theme_topic'))
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold mb-1">Theme / Topic</label>
                        <input id="spiritual-theme-topic" name="theme_topic" placeholder="e.g. Patience, gratitude (optional)" class="w-full rounded-lg border-slate-300">
                    </div>
                @endif

                @if($has('practiced_at'))
                    <div>
                        <label class="block text-sm font-bold mb-1">Practice Date</label>
                        <input id="spiritual-practiced-at" name="practiced_at" type="date" value="{{ now()->format('Y-m-d') }}" class="w-full rounded-lg border-slate-300">
                    </div>
                @endif

                @if($has('duration_minutes'))
                    <div>
                        <label class="block text-sm font-bold mb-1">Duration (minutes)</label>
                        <input id="spiritual-duration" name="duration_minutes" type="number" min="0" placeholder="e.g. 20 (optional)" class="w-full rounded-lg border-slate-300">
                    </div>
                @endif

            </div>

            <div class="pm-form-panel grid grid-cols-1 md:grid-cols-2 gap-4" role="tabpanel" id="spiritual-panel-1" aria-labelledby="spiritual-tab-1" data-pm-form-panel="1" hidden>
                @if($has('inspirational_text') || $has('scriptures'))
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold mb-1">Sacred / Inspirational Text</label>
                        <textarea id="spiritual-inspirational" name="{{ $has('inspirational_text') ? 'inspirational_text' : 'scriptures' }}" rows="2" placeholder="A verse, quote or passage (optional)" class="w-full rounded-lg border-slate-300"></textarea>
                    </div>
                @endif

                @if($has('source_tradition'))
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold mb-1">Source / Tradition</label>
                        <input id="spiritual-source-tradition" name="source_tradition" placeholder="e.g. Psalm 23 (optional)" class="w-full rounded-lg border-slate-300">
                    </div>
                @endif

                @if($has('reflection'))
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold mb-1">Reflection</label>
                        <textarea id="spiritual-reflection" name="reflection" rows="4" placeholder="What stood out to you? (optional)" class="w-full rounded-lg border-slate-300"></textarea>
                    </div>
                @endif

                @if($has('gratitude'))
                    <div>
                        <label class="block text-sm font-bold mb-1">Gratitude</label>
                        <textarea id="spiritual-gratitude" name="gratitude" rows="3" placeholder="I am grateful for… (optional)" class="w-full rounded-lg border-slate-300"></textarea>
                    </div>
                @endif

                @if($has('intention'))
                    <div>
                        <label class="block text-sm font-bold mb-1">Intention</label>
                        <textarea id="spiritual-intention" name="intention" rows="3" placeholder="Today I intend to… (optional)" class="w-full rounded-lg border-slate-300"></textarea>
                    </div>
                @endif

            </div>

            <div class="pm-form-panel grid grid-cols-1 md:grid-cols-2 gap-4" role="tabpanel" id="spiritual-panel-2" aria-labelledby="spiritual-tab-2" data-pm-form-panel="2" hidden>
                @if($has('community_place'))
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold mb-1">Community / Place</label>
                        <input id="spiritual-community-place" name="community_place" placeholder="e.g. Home, church, mosque (optional)" class="w-full rounded-lg border-slate-300">
                    </div>
                @endif

                @if($has('recurrence_frequency'))
                    <div>
                        <label class="block text-sm font-bold mb-1">Repeat</label>
                        <select id="spiritual-recurrence" name="recurrence_frequency" class="w-full rounded-lg border-slate-300">
                            <option value="">Does not repeat</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                @endif

                @if($has('recurrence_ends_at'))
                    <div id="recurrence-end-wrapper">
                        <label class="block text-sm font-bold mb-1">Repeat Until</label>
                        <input id="spiritual-recurrence-end" name="recurrence_ends_at" type="date" class="w-full rounded-lg border-slate-300">
                    </div>
                @endif

                @if($has('notes'))
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold mb-1">Notes</label>
                        <textarea id="spiritual-notes" name="notes" rows="3" placeholder="Notes (optional)" class="w-full rounded-lg border-slate-300"></textarea>
                    </div>
                @endif
            </div>

            <div class="mt-5 flex flex-col-reverse sm:flex-row justify-end gap-2">
                <button
                    type="button"
                    onclick="document.getElementById('spiritual-form-modal').close()"
                    class="px-4 py-2.5 rounded-lg border border-slate-200 font-semibold"
                >
                    Cancel
                </button>

                <button
                    class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-black"
                >
                    <i class="fa-solid fa-floppy-disk mr-1"></i>
                    Save Practice
                </button>
            </div>
        </form>
    </div>
</dialog>

@include('partials.form-tabs')

<script>
(function () {
    'use strict';

    const modal = document.getElementById('spiritual-form-modal');
    const form = document.getElementById('spiritual-form');

    function setValue(id, value) {
        const element = document.getElementById(id);
        if (element) element.value = value ?? '';
    }

    function refreshConditionalFields() {
        const faith = document.getElementById('spiritual-faith-path');
        const customWrapper = document.getElementById('custom-faith-wrapper');

        if (faith && customWrapper) {
            customWrapper.classList.toggle(
                'hidden',
                faith.value !== 'Custom'
            );
        }

        const recurrence = document.getElementById('spiritual-recurrence');
        const endWrapper = document.getElementById('recurrence-end-wrapper');

        if (recurrence && endWrapper) {
            endWrapper.classList.toggle(
                'opacity-50',
                recurrence.value === ''
            );
        }
    }

    window.openSpiritualForm = function (item = null) {
        document.getElementById('spiritual-form-title').textContent =
            item ? 'Edit Spiritual Growth' : 'Add Spiritual Growth';

        form.action = item
            ? @json(url('/spiritual-practices')) + '/' + item.id
            : @json(route('spiritual-practices.store'));

        document.getElementById('spiritual-method').value =
            item ? 'PUT' : 'POST';

        setValue('spiritual-faith-path', item?.faith_path || 'Prefer not to specify');
        setValue('spiritual-custom-faith', item?.custom_faith_path || '');
        setValue('spiritual-practice-type', item?.practice_type || 'reflection');
        setValue('spiritual-practice-title', item?.practice_title || '');
        setValue('spiritual-theme-topic', item?.theme_topic || '');
        setValue('spiritual-practiced-at', item?.practiced_at || @json(now()->format('Y-m-d')));
        setValue('spiritual-duration', item?.duration_minutes || '');
        setValue('spiritual-inspirational', item?.inspirational_text || '');
        setValue('spiritual-source-tradition', item?.source_tradition || '');
        setValue('spiritual-reflection', item?.reflection || '');
        setValue('spiritual-gratitude', item?.gratitude || '');
        setValue('spiritual-intention', item?.intention || '');
        setValue('spiritual-community-place', item?.community_place || '');
        setValue('spiritual-recurrence', item?.recurrence_frequency || '');
        setValue('spiritual-recurrence-end', item?.recurrence_ends_at || '');
        setValue('spiritual-notes', item?.notes || '');

        refreshConditionalFields();
        modal.showModal();
    };

    window.openSpiritualFormFromButton = function (button) {
        const d = button.dataset;

        window.openSpiritualForm({
            id: Number(d.id || 0),
            faith_path: d.faithPath || '',
            custom_faith_path: d.customFaith || '',
            practice_type: d.practiceType || 'reflection',
            practice_title: d.practiceTitle || '',
            theme_topic: d.themeTopic || '',
            practiced_at: (d.practicedAt || '').slice(0, 10),
            duration_minutes: d.duration || '',
            inspirational_text: d.inspirational || '',
            source_tradition: d.sourceTradition || '',
            reflection: d.reflection || '',
            gratitude: d.gratitude || '',
            intention: d.intention || '',
            community_place: d.communityPlace || '',
            recurrence_frequency: d.recurrence || '',
            recurrence_ends_at: (d.recurrenceEnd || '').slice(0, 10),
            notes: d.notes || '',
        });
    };

    document.getElementById('spiritual-faith-path')
        ?.addEventListener('change', refreshConditionalFields);

    document.getElementById('spiritual-recurrence')
        ?.addEventListener('change', refreshConditionalFields);

    refreshConditionalFields();
})();
</script>

<script>
async function generateSpiritualAiDraft() {
    const form = document.getElementById('spiritual-form');
    const topic = document.getElementById('spiritual-ai-topic');
    const button = document.getElementById('spiritual-ai-generate');
    const status = document.getElementById('spiritual-ai-status');
    if (!form || !topic || !button || !status) return;
    if (!topic.value.trim()) { status.textContent = 'Enter a topic or title first.'; topic.focus(); return; }

    const context = {};
    ['faith_path','practice_type'].forEach(name => {
        const field = form.elements.namedItem(name);
        if (field && String(field.value || '').trim()) context[name] = field.value;
    });

    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Generating...</span>';
    status.textContent = 'Preparing your editable spiritual practice draft...';

    try {
        const response = await fetch(@json(route('ai.form-assist')), {
            method:'POST', credentials:'same-origin',
            headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''},
            body:JSON.stringify({module:'spiritual-practices',topic:topic.value.trim(),context})
        });
        const json = await response.json();
        if (!response.ok || !json.ok) throw new Error(json.message || 'Could not generate a draft.');
        const refsBox = document.getElementById('spiritual-bible-refs');
        const refs = Array.isArray(json.data?.bible_references) ? json.data.bible_references : [];
        if (refsBox) {
            if (refs.length) {
                refsBox.classList.remove('hidden');
                refsBox.innerHTML = '<p class="font-bold text-slate-900">Bible references (' + refs.length + ')</p>' +
                    refs.map(ref => '<p class="mt-1">' + String(ref.reference || '') + ' — ' + String(ref.text || '') + '</p>').join('');
            } else {
                refsBox.classList.add('hidden');
                refsBox.innerHTML = '';
            }
        }
        let filled = 0;
        Object.entries(json.data || {}).forEach(([name,value]) => {
            if (name === 'bible_references' || name === 'bible_references_text') return;
            const field = form.elements.namedItem(name);
            if (!field || String(field.value || '').trim() !== '') return;
            field.value = value;
            field.dispatchEvent(new Event('change',{bubbles:true}));
            filled++;
        });
        if (typeof json.data?.bible_references_text === 'string' && json.data.bible_references_text.trim() !== '') {
            const sacred = form.elements.namedItem('inspirational_text') || form.elements.namedItem('scriptures');
            if (sacred) {
                const current = String(sacred.value || '').trim();
                if (current === '') {
                    sacred.value = json.data.bible_references_text;
                    sacred.dispatchEvent(new Event('change',{bubbles:true}));
                    filled++;
                } else if (!current.includes(json.data.bible_references_text.split('\n')[0])) {
                    sacred.value = current + '\n\n' + json.data.bible_references_text;
                    sacred.dispatchEvent(new Event('change',{bubbles:true}));
                }
            }
        }
        status.textContent = `Draft ready. ${filled} empty field${filled === 1 ? '' : 's'} filled${refs.length ? `, including ${refs.length} Bible references` : ''}. Review before saving.`;
        button.innerHTML = '<i class="fa-solid fa-rotate"></i><span>Regenerate</span>';
    } catch (error) {
        status.textContent = error.message || 'AI could not prepare a draft. Your current form was kept.';
        button.innerHTML = original;
    } finally { button.disabled = false; }
}
</script>

@endsection
