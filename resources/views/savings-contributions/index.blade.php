@extends('layouts.app')

@section('title', 'Savings Contributions')

@section('content')
@php
    $money = fn ($value) => function_exists('format_money')
        ? format_money((float) $value)
        : 'UGX '.number_format((float) $value, 0);

    $focus = $goalSummary['latest'] ?? null;
@endphp

<style>
    .sc-card{
        background:#fff;
        border:1px solid #e2e8f0;
        border-radius:1rem;
        box-shadow:0 6px 20px rgba(15,23,42,.045);
    }
    .sc-stat{
        background:#fff;
        border:1px solid #e2e8f0;
        border-left:4px solid var(--sc-accent,#84cc16);
        border-radius:1rem;
        padding:1rem;
        display:flex;
        align-items:center;
        gap:.8rem;
        box-shadow:0 5px 16px rgba(15,23,42,.04);
    }
    .sc-stat-icon{
        width:2.5rem;
        height:2.5rem;
        border-radius:.8rem;
        display:grid;
        place-items:center;
        color:var(--sc-accent,#84cc16);
        background:var(--sc-soft,#f7fee7);
        flex:0 0 auto;
    }
    .sc-modal{
        position:fixed;
        inset:0;
        z-index:100;
        display:none;
        align-items:center;
        justify-content:center;
        padding:1rem;
        background:rgba(15,23,42,.55);
        backdrop-filter:blur(4px);
    }
    .sc-modal.open{display:flex}
    .sc-modal-panel{
        width:min(620px,100%);
        max-height:92vh;
        overflow:auto;
        background:#fff;
        border-radius:1.25rem;
        box-shadow:0 24px 70px rgba(15,23,42,.25);
    }
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

    <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 flex items-center gap-3">
                <span class="w-12 h-12 rounded-xl bg-lime-100 text-lime-700 grid place-items-center">
                    <i class="fa-solid fa-coins"></i>
                </span>
                Contributions
            </h1>
            <p class="text-sm text-slate-500 mt-2">
                Record and review money added towards your savings goals.
            </p>
        </div>

        <button type="button"
                onclick="openContributionModal()"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold shadow-sm">
            <i class="fa-solid fa-plus"></i>
            Add Contribution
        </button>
    </header>

    @if(session('success'))
        <x-alert type="success" :message="session('success')" :dismissible="false" :autoDismiss="false" />
    @endif

    @if($errors->any())
        <x-alert type="error" :dismissible="false" :autoDismiss="false">
            <div class="font-bold mb-1">Please correct the following:</div>
            <ul class="list-disc pl-5 text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <div id="contribution-status-message" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"></div>

    {{-- Goals for this area --}}
    <section class="sc-card px-4 py-3 bg-violet-50/50 border-violet-100">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-white text-violet-600 grid place-items-center shadow-sm shrink-0">
                    <i class="fa-solid fa-bullseye"></i>
                </div>

                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="font-bold text-slate-900">Goals for this area</h2>

                        <span class="text-xs font-semibold text-violet-700 bg-white border border-violet-100 rounded-full px-2 py-1">
                            {{ (int) $goalSummary['active'] }} active
                        </span>

                        <span class="text-xs text-slate-500">
                            {{ (int) $goalSummary['average'] }}% avg. progress
                        </span>
                    </div>

                    @if($focus)
                        <p class="text-sm text-slate-600 mt-1">
                            <span class="font-semibold">Focus:</span>
                            {{ $focus->clean_title ?: 'Untitled goal' }}
                            <span class="text-slate-400 mx-1">—</span>
                            {{ (int) ($focus->progress_percent ?? 0) }}%
                        </p>
                    @else
                        <p class="text-sm text-slate-500 mt-1">
                            No linked goal yet. Add a goal to connect your savings contributions to a bigger target.
                        </p>
                    @endif
                </div>
            </div>

            <a href="{{ route('personal-goals.index', ['module' => $goalSummary['module']]) }}"
               class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-white border border-violet-200 text-violet-700 font-semibold hover:bg-violet-100 shrink-0">
                <i class="fa-solid fa-crosshairs"></i>
                {{ $goalSummary['active'] ? 'View Goals' : 'Add Goal' }}
            </a>
        </div>
    </section>

    {{-- Statistics --}}
    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="sc-stat" style="--sc-accent:#65a30d;--sc-soft:#f7fee7">
            <div class="sc-stat-icon"><i class="fa-solid fa-coins"></i></div>
            <div class="min-w-0">
                <div class="text-xs uppercase tracking-wide text-slate-500">This Month</div>
                <div class="text-xl font-black text-slate-900 truncate">{{ $money($stats['this_month']) }}</div>
            </div>
        </div>

        <div class="sc-stat" style="--sc-accent:#059669;--sc-soft:#ecfdf5">
            <div class="sc-stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
            <div class="min-w-0">
                <div class="text-xs uppercase tracking-wide text-slate-500">All-Time Total</div>
                <div class="text-xl font-black text-slate-900 truncate">{{ $money($stats['all_time']) }}</div>
            </div>
        </div>

        <div class="sc-stat" style="--sc-accent:#2563eb;--sc-soft:#eff6ff">
            <div class="sc-stat-icon"><i class="fa-solid fa-list-ol"></i></div>
            <div>
                <div class="text-xs uppercase tracking-wide text-slate-500">Contributions</div>
                <div class="text-xl font-black text-slate-900">{{ number_format($stats['count']) }}</div>
            </div>
        </div>

        <div class="sc-stat" style="--sc-accent:#7c3aed;--sc-soft:#f5f3ff">
            <div class="sc-stat-icon"><i class="fa-solid fa-bullseye"></i></div>
            <div>
                <div class="text-xs uppercase tracking-wide text-slate-500">Goals Contributed</div>
                <div class="text-xl font-black text-slate-900">{{ number_format($stats['goals_contributed']) }}</div>
            </div>
        </div>
    </section>

    {{-- Filters --}}
    <form method="GET" action="{{ route('savings-contributions.index') }}"
          class="sc-card p-4">
        <div class="grid md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_240px_190px_auto] gap-3 items-end">
            <label>
                <span class="block text-xs font-bold text-slate-600 mb-1">Search</span>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="search"
                           name="search"
                           value="{{ $search }}"
                           placeholder="Search goal or notes..."
                           class="pm-input w-full pl-9">
                </div>
            </label>

            <label>
                <span class="block text-xs font-bold text-slate-600 mb-1">Savings Goal</span>
                <select name="savings_goal_id" class="pm-input w-full">
                    <option value="">All goals</option>
                    @foreach($goals as $goal)
                        <option value="{{ $goal->id }}" @selected((int)$goalId === (int)$goal->id)>
                            {{ $goal->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="block text-xs font-bold text-slate-600 mb-1">Month</span>
                <input type="month" name="month" value="{{ $month }}" class="pm-input w-full">
            </label>

            <div class="flex gap-2">
                <button type="submit"
                        class="px-4 py-2.5 rounded-xl bg-teal-600 text-white font-bold">
                    Filter
                </button>
                <a href="{{ route('savings-contributions.index') }}"
                   class="px-4 py-2.5 rounded-xl border bg-white text-slate-600 font-semibold">
                    Clear
                </a>
            </div>
        </div>
    </form>

    {{-- Contributions table --}}
    <section>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
            <div>
                <h2 class="font-black text-slate-900">Contribution History</h2>
                <p class="text-xs text-slate-500">
                    {{ $contributions->total() }} record(s) · 12 per page
                </p>
            </div>

            @if($contributions->count())
                <button type="submit"
                        form="bulkContributionForm"
                        data-confirm-click="Delete selected contributions?"
                        data-confirm-title="Delete selected contributions?"
                        data-confirm-text="Delete selected"
                        class="px-3 py-2 rounded-xl border border-rose-200 bg-white text-rose-700 text-sm font-bold">
                    <i class="fa-solid fa-trash mr-1"></i>
                    Delete Selected
                </button>
            @endif
        </div>

        @if(!$contributions->count())
            <div class="sc-card">
                <x-empty-state
                    icon="fa-solid fa-coins"
                    title="No contributions found"
                    message="Add your first contribution or adjust the filters."
                />
            </div>
        @else
            <form id="bulkContributionForm"
                  method="POST"
                  action="{{ route('savings-contributions.bulk-destroy') }}">
                @csrf
                @method('DELETE')

                <div class="pm-dt-wrap">
                    <table class="pm-dt">
                        <caption class="sr-only">Savings contribution history</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="pm-dt-check">
                                    <input type="checkbox" id="selectAllContributions" aria-label="Select all contributions">
                                    <span class="pm-dt-check-label" aria-hidden="true">Select all</span>
                                </th>
                                <th scope="col">Savings goal</th>
                                <th scope="col">Date</th>
                                <th scope="col" class="pm-dt-num">Amount</th>
                                <th scope="col" class="pm-dt-actions"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($contributions as $contribution)
                                @php
                                    $contributionGoalName = $contribution->goal?->name ?? 'Deleted goal';
                                    $contributionNotes = trim((string) ($contribution->notes ?? ''));
                                @endphp
                                <tr class="has-check">
                                    <td class="pm-dt-check">
                                        <input class="contribution-check"
                                               type="checkbox"
                                               name="ids[]"
                                               value="{{ $contribution->id }}"
                                               aria-label="Select contribution to {{ $contributionGoalName }}">
                                    </td>

                                    <td class="pm-dt-main">
                                        <span class="pm-dt-title" title="{{ $contributionGoalName }}">{{ $contributionGoalName }}</span>
                                        <span class="pm-dt-sub">
                                            @if($contributionNotes !== '')
                                                <span class="pm-dt-note" title="{{ $contributionNotes }}">{{ \Illuminate\Support\Str::limit($contributionNotes, 80) }}</span>
                                            @else
                                                <span>Contribution</span>
                                            @endif
                                        </span>
                                    </td>

                                    <td class="pm-dt-aux">
                                        {{ $contribution->contributed_at?->format('d M Y') ?? '—' }}
                                    </td>

                                    <td class="pm-dt-num text-emerald-700">
                                        {{ $money($contribution->amount) }}
                                    </td>

                                    <td class="pm-dt-actions">
                                        @php
                                            $editContributionPayload = [
                                                'id' => (int) $contribution->id,
                                                'savings_goal_id' => (int) $contribution->savings_goal_id,
                                                'amount' => (float) $contribution->amount,
                                                'contributed_at' => $contribution->contributed_at
                                                    ? $contribution->contributed_at->format('Y-m-d')
                                                    : '',
                                                'notes' => (string) ($contribution->notes ?? ''),
                                            ];

                                            $editContributionJson = json_encode(
                                                $editContributionPayload,
                                                JSON_HEX_TAG
                                                | JSON_HEX_AMP
                                                | JSON_HEX_APOS
                                                | JSON_HEX_QUOT
                                                | JSON_UNESCAPED_UNICODE
                                            ) ?: '{}';
                                        @endphp

                                        <div class="inline-flex items-center gap-1">
                                            <button type="button"
                                                    class="pm-dt-icon-btn text-blue-700"
                                                    data-contribution="{{ e($editContributionJson) }}"
                                                    onclick="editContributionFromButton(this)"
                                                    title="Edit"
                                                    aria-label="Edit contribution to {{ $contributionGoalName }}">
                                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                            </button>

                                            <button type="submit"
                                                    form="deleteContribution{{ $contribution->id }}"
                                                    data-confirm-click="Delete this contribution?"
                                                    data-confirm-title="Delete contribution?"
                                                    data-confirm-text="Delete"
                                                    class="pm-dt-icon-btn text-rose-700"
                                                    title="Delete"
                                                    aria-label="Delete contribution to {{ $contributionGoalName }}">
                                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>

            @foreach($contributions as $contribution)
                <form id="deleteContribution{{ $contribution->id }}"
                      method="POST"
                      action="{{ route('savings-contributions.destroy', $contribution) }}">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach

            @if($contributions->hasPages())
                <div class="mt-5 sc-card px-4 py-3">
                    {{ $contributions->appends([
                        'search' => $search,
                        'savings_goal_id' => $goalId ?: null,
                        'month' => $month ?: null,
                    ])->links() }}
                </div>
            @endif
        @endif
    </section>
</div>

{{-- Add/Edit modal --}}
<div id="contributionModal" class="sc-modal" onclick="closeContributionModalOnBackdrop(event)">
    <div class="sc-modal-panel">
        <form id="contributionForm" method="POST" action="{{ route('savings-contributions.store') }}">
            @csrf
            <input id="contributionMethod" type="hidden" name="_method" value="POST">

            <div class="p-5 border-b flex items-center justify-between">
                <div>
                    <h2 id="contributionModalTitle" class="text-xl font-black text-slate-900">
                        Add Contribution
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Record money added to one of your savings goals.
                    </p>
                </div>

                <button type="button"
                        onclick="closeContributionModal()"
                        class="w-10 h-10 rounded-xl hover:bg-slate-100">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-5 grid sm:grid-cols-2 gap-4">
                <label class="sm:col-span-2">
                    <span class="block text-xs font-bold text-slate-600 mb-1">Savings Goal *</span>
                    <select id="contributionGoal"
                            name="savings_goal_id"
                            required
                            class="pm-input w-full">
                        <option value="">Choose goal</option>
                        @foreach($goals as $goal)
                            <option value="{{ $goal->id }}">
                                {{ $goal->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span class="block text-xs font-bold text-slate-600 mb-1">Amount *</span>
                    <input id="contributionAmount"
                           type="number"
                           name="amount"
                           min="0.01"
                           step="0.01"
                           required
                           class="pm-input w-full"
                           placeholder="0.00">
                </label>

                <label>
                    <span class="block text-xs font-bold text-slate-600 mb-1">Date *</span>
                    <input id="contributionDate"
                           type="date"
                           name="contributed_at"
                           required
                           value="{{ now()->toDateString() }}"
                           class="pm-input w-full">
                </label>

                <label class="sm:col-span-2">
                    <span class="block text-xs font-bold text-slate-600 mb-1">Notes</span>
                    <textarea id="contributionNotes"
                              name="notes"
                              rows="4"
                              class="pm-input w-full"
                              placeholder="Optional notes about this contribution..."></textarea>
                </label>
            </div>

            <div class="p-5 border-t flex justify-end gap-2">
                <button type="button"
                        onclick="closeContributionModal()"
                        class="px-4 py-2.5 rounded-xl border bg-white font-semibold">
                    Cancel
                </button>

                <button type="submit"
                        class="px-4 py-2.5 rounded-xl bg-teal-600 text-white font-bold">
                    <i class="fa-solid fa-floppy-disk mr-1"></i>
                    Save Contribution
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const contributionModal = document.getElementById('contributionModal');
const contributionForm = document.getElementById('contributionForm');
const contributionMethod = document.getElementById('contributionMethod');
const contributionModalTitle = document.getElementById('contributionModalTitle');

function openContributionModal() {
    contributionModalTitle.textContent = 'Add Contribution';
    contributionForm.action = @json(route('savings-contributions.store'));
    contributionMethod.value = 'POST';

    document.getElementById('contributionGoal').value = @json($goalId ?: '');
    document.getElementById('contributionAmount').value = '';
    document.getElementById('contributionDate').value = @json(now()->toDateString());
    document.getElementById('contributionNotes').value = '';

    contributionModal.classList.add('open');
}

function editContributionFromButton(button) {
    try {
        const message = document.getElementById('contribution-status-message');
        if (message) {
            message.classList.add('hidden');
            message.textContent = '';
        }
        const data = JSON.parse(button.dataset.contribution || '{}');
        editContribution(data);
    } catch (error) {
        console.error('Could not read contribution data.', error);
        const message = document.getElementById('contribution-status-message');
        if (message) {
            message.textContent = 'Could not open this contribution for editing.';
            message.classList.remove('hidden');
            message.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

function editContribution(data) {
    contributionModalTitle.textContent = 'Edit Contribution';

    const base = @json(url('/savings-contributions'));
    contributionForm.action = base + '/' + data.id;
    contributionMethod.value = 'PUT';

    document.getElementById('contributionGoal').value = data.savings_goal_id || '';
    document.getElementById('contributionAmount').value = data.amount || '';
    document.getElementById('contributionDate').value = data.contributed_at || '';
    document.getElementById('contributionNotes').value = data.notes || '';

    contributionModal.classList.add('open');
}

function closeContributionModal() {
    contributionModal.classList.remove('open');
}

function closeContributionModalOnBackdrop(event) {
    if (event.target === contributionModal) {
        closeContributionModal();
    }
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && contributionModal.classList.contains('open')) {
        closeContributionModal();
    }
});

document.getElementById('selectAllContributions')?.addEventListener('change', function() {
    document.querySelectorAll('.contribution-check').forEach(function(box) {
        box.checked = this.checked;
    }, this);
});

@if($errors->any())
    openContributionModal();
@endif
</script>
@endsection
