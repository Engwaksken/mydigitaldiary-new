@extends('layouts.app')
@section('title', 'Annual Plans')


@push('styles')
<style>
    #annualPlanModal {
        width: min(760px, calc(100vw - 32px));
        max-width: 760px;
        max-height: calc(100dvh - 32px);
        padding: 0 !important;
        border: 0;
        border-radius: 20px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 24px 70px rgba(15, 23, 42, .22);
    }
    #annualPlanModal::backdrop { background: rgba(15, 23, 42, .52); backdrop-filter: blur(2px); }
    #annualPlanForm { padding: 0 !important; gap: 0 !important; }
    #annualPlanModal .annual-plan-modal-header {
        display: flex; align-items: center; justify-content: space-between; gap: 16px;
        padding: 20px 24px 18px; border-bottom: 1px solid #e2e8f0;
    }
    #annualPlanModal .annual-plan-modal-body {
        padding: 22px 24px 26px; overflow-y: auto; max-height: calc(100dvh - 190px);
    }
    #annualPlanModal .annual-plan-fields { display: grid; gap: 20px; }
    #annualPlanModal .annual-plan-modal-footer {
        display: flex; justify-content: flex-end; align-items: center; gap: 10px;
        padding: 16px 24px 20px; border-top: 1px solid #e2e8f0; background: #fff;
    }
    #annualPlanModal .pm-input { width: 100%; min-width: 0; }
    @media (max-width: 640px) {
        #annualPlanModal { width: calc(100vw - 20px); max-height: calc(100dvh - 20px); border-radius: 16px; }
        #annualPlanModal .annual-plan-modal-header { padding: 18px 16px 15px; }
        #annualPlanModal .annual-plan-modal-body { padding: 18px 16px 22px; max-height: calc(100dvh - 172px); }
        #annualPlanModal .annual-plan-modal-footer { padding: 14px 16px 16px; }
        #annualPlanModal .annual-plan-fields { gap: 17px; }
        #annualPlanModal .annual-plan-modal-footer > * { min-height: 44px; }
    }
</style>
@endpush


@section('content')
    <div class="mb-4 rounded-xl border border-violet-100 bg-violet-50/60 px-4 py-3 flex items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-slate-800">Goals aligned to this area</p>
            <p class="text-xs text-slate-500 mt-0.5">Connect your plans and daily actions to a clear outcome.</p>
        </div>
        <a href="{{ route('personal-goals.index', ['module' => 'personal']) }}" class="shrink-0 inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white border border-violet-200 text-violet-700 text-sm font-semibold hover:bg-violet-100">
            <i class="fa-solid fa-bullseye"></i> Goals
        </a>
    </div>

<div class="flex flex-col gap-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-calendar-check text-[var(--brand-1)]"></i> Annual Plans</h1>
            <p class="text-sm text-slate-500 mt-1">Manage yearly goals and month-by-month plans, progress, targets and reminders.</p>
        </div>
        <button type="button" onclick="openAnnualPlanModal()" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium"><i class="fa-solid fa-plus mr-1"></i> Add Plan</button>
    </div>

    @if(session('success'))<x-alert type="success" :message="session('success')" :dismissible="false" :autoDismiss="false" />@endif
    @if($errors->any())<x-alert type="error" :dismissible="false" :autoDismiss="false"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif

    <x-summary-strip label="Annual plans summary" :stats="[
        ['label' => 'Total plans', 'value' => number_format((int) $total), 'icon' => 'fa-solid fa-list-check', 'color' => 'teal', 'href' => route('annual-plans.index', ['year' => $year])],
        ['label' => 'Completed', 'value' => number_format((int) $completed), 'icon' => 'fa-solid fa-circle-check', 'color' => 'emerald', 'href' => route('annual-plans.index', ['year' => $year, 'status' => 'completed'])],
        ['label' => 'Monthly plans', 'value' => number_format((int) $monthly), 'icon' => 'fa-solid fa-calendar-days', 'color' => 'blue', 'href' => route('annual-plans.index', ['year' => $year, 'period' => 'monthly'])],
        ['label' => 'With reminder', 'value' => number_format((int) $withReminder), 'icon' => 'fa-solid fa-bell', 'color' => 'amber'],
        ['label' => 'Progress', 'value' => $overallProgress . '%', 'icon' => 'fa-solid fa-chart-line', 'color' => 'violet'],
    ]" />

    <form method="GET" class="pm-card-bg rounded-xl border border-slate-100 p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-7 gap-3 items-end">
        <div class="lg:col-span-2"><label class="text-xs font-medium text-slate-600">Search</label><input type="search" name="q" value="{{ $search }}" class="pm-input mt-1" placeholder="Search plans..."></div>
        <div><label class="text-xs font-medium text-slate-600">Year</label><select name="year" class="pm-input mt-1">@foreach($availableYears as $y)<option value="{{ $y }}" @selected((int)$y===$year)>{{ $y }}</option>@endforeach</select></div>
        <div><label class="text-xs font-medium text-slate-600">Status</label><select name="status" class="pm-input mt-1"><option value="">All statuses</option><option value="in_progress" @selected($status==='in_progress')>In progress</option><option value="pending" @selected($status==='pending')>Pending</option><option value="completed" @selected($status==='completed')>Completed</option></select></div>
        <div><label class="text-xs font-medium text-slate-600">Period</label><select name="period" class="pm-input mt-1"><option value="">All periods</option><option value="annually" @selected($period==='annually')>Annual</option><option value="monthly" @selected($period==='monthly')>Monthly</option></select></div>
        <div><label class="text-xs font-medium text-slate-600">Month</label><select name="month" class="pm-input mt-1"><option value="0">All months</option>@foreach(range(1,12) as $m)<option value="{{ $m }}" @selected($month===$m)>{{ \Carbon\Carbon::create()->month($m)->format('M') }}</option>@endforeach</select></div>
        <div><label class="text-xs font-medium text-slate-600">Rows</label><select name="per_page" class="pm-input mt-1">@foreach([10,25,50,100] as $n)<option value="{{ $n }}" @selected($perPage===$n)>{{ $n }}</option>@endforeach</select></div>
        <div class="lg:col-span-7 flex gap-2 justify-end"><a href="{{ route('annual-plans.index') }}" class="px-4 py-2.5 rounded-lg border text-sm"><i class="fa-solid fa-rotate-left mr-1"></i> Reset</a><button class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm"><i class="fa-solid fa-filter mr-1"></i> Filter</button></div>
    </form>

    <form id="bulkAnnualPlansForm" method="POST" action="{{ route('annual-plans.bulk-destroy') }}" data-confirm="Delete the selected plans? This action cannot be undone." data-confirm-title="Delete selected plans?" data-confirm-text="Delete selected">@csrf @method('DELETE')</form>
    <div class="pm-card-bg rounded-xl border border-slate-100 overflow-hidden">
        <div class="px-4 py-3 border-b flex items-center justify-between gap-3"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" id="selectAllAnnualPlans"> Select all on page</label><button type="submit" form="bulkAnnualPlansForm" class="text-sm text-rose-600"><i class="fa-solid fa-trash mr-1"></i> Delete selected</button></div>
        @forelse($plans as $plan)
            <div class="p-4 border-b last:border-b-0 flex flex-col lg:flex-row gap-4 lg:items-center">
                <input type="checkbox" form="bulkAnnualPlansForm" name="ids[]" value="{{ $plan->id }}" class="annual-plan-checkbox">
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold text-slate-800 {{ $plan->status==='completed' ? 'line-through opacity-70' : '' }}">{{ $plan->title }}</h3>
                        <span class="text-xs px-2 py-1 rounded-full bg-slate-100"><i class="fa-solid {{ $plan->period==='monthly' ? 'fa-calendar-day' : 'fa-calendar' }} mr-1"></i>{{ $plan->period==='monthly' ? (\Carbon\Carbon::create()->month($plan->plan_month ?: 1)->format('F').' '.$plan->plan_year) : ('Annual '.$plan->plan_year) }}</span>
                        <span class="text-xs px-2 py-1 rounded-full {{ $plan->status==='completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ ucfirst(str_replace('_',' ',$plan->status)) }}</span>
                    </div>
                    @if($plan->description)<p class="text-sm text-slate-500 mt-1">{{ $plan->description }}</p>@endif
                    <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-xs text-slate-500">
                        @if($plan->target_date)
                            <span>
                                <i class="fa-solid fa-flag-checkered mr-1"></i>Target {{ $plan->target_date->format('d M Y') }}
                                <x-countdown :date="$plan->target_date" :status="$plan->status" />
                            </span>
                        @endif
                        @if($plan->reminder_at)<span><i class="fa-solid fa-bell mr-1"></i>{{ $plan->reminder_at->format('d M Y, g:i A') }}</span>@endif
                    </div>
                    <div class="flex items-center gap-3 mt-3"><div class="h-2 bg-slate-100 rounded-full overflow-hidden flex-1"><div class="h-full bg-[var(--brand-1)]" style="width:{{ $plan->progress_percent }}%"></div></div><span class="text-sm font-semibold">{{ $plan->progress_percent }}%</span></div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('annual-plans.toggle',$plan) }}">@csrf @method('PATCH')<input type="hidden" name="completed" value="{{ $plan->status==='completed' ? 0 : 1 }}"><button class="px-3 py-2 rounded-lg border text-sm"><i class="fa-solid {{ $plan->status==='completed' ? 'fa-arrow-rotate-left' : 'fa-check' }} mr-1"></i>{{ $plan->status==='completed' ? 'Reopen' : 'Complete' }}</button></form>
                    @php
                        $planModalPayload = [
                            'id' => $plan->id,
                            'title' => $plan->title,
                            'description' => $plan->description,
                            'plan_year' => $plan->plan_year,
                            'period' => $plan->period,
                            'plan_month' => $plan->plan_month,
                            'target_date' => optional($plan->target_date)->format('Y-m-d'),
                            'reminder_at' => optional($plan->reminder_at)->format('Y-m-d\TH:i'),
                            'progress_percent' => $plan->progress_percent,
                        ];
                    @endphp
                    <button
                        type="button"
                        data-plan='@json($planModalPayload)'
                        onclick="openAnnualPlanModal(JSON.parse(this.dataset.plan))"
                        class="px-3 py-2 rounded-lg border text-sm"
                    ><i class="fa-solid fa-pen-to-square mr-1"></i>Edit</button>
                    <form method="POST" action="{{ route('annual-plans.destroy',$plan) }}" data-confirm="Delete this plan? This action cannot be undone." data-confirm-title="Delete plan?" data-confirm-text="Delete">@csrf @method('DELETE')<button class="px-3 py-2 rounded-lg border text-rose-600 text-sm"><i class="fa-solid fa-trash"></i></button></form>
                </div>
            </div>
        @empty
            <x-empty-state icon="fa-solid fa-calendar-xmark" title="No plans match the selected filters." />
        @endforelse
    </div>

    <div class="flex flex-col sm:flex-row justify-between items-center gap-3 text-sm text-slate-500">
        <span>Showing {{ $plans->firstItem() ?? 0 }} to {{ $plans->lastItem() ?? 0 }} of {{ $plans->total() }}</span>
        @if($plans->hasPages())<div>{{ $plans->links() }}</div>@endif
    </div>
</div>

<dialog id="annualPlanModal" aria-labelledby="annualPlanModalTitle">
    <form id="annualPlanForm" method="POST" class="flex flex-col">@csrf<input type="hidden" name="_method" id="annualPlanMethod" value="POST">
        <div class="annual-plan-modal-header">
            <div class="min-w-0">
                <h2 id="annualPlanModalTitle" class="text-lg font-bold text-slate-900"><i class="fa-solid fa-calendar-plus text-[var(--brand-1)] mr-2"></i>Add Plan</h2>
                <p class="mt-1 text-xs text-slate-500">Create a clear plan, target date and reminder.</p>
            </div>
            <button type="button" onclick="annualPlanModal.close()" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50" aria-label="Close Add Plan"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="annual-plan-modal-body">
            <div class="annual-plan-fields">
                @include('annual-plans.partials.form-fields')
            </div>
        </div>
        <div class="annual-plan-modal-footer">
            <button type="button" onclick="annualPlanModal.close()" class="px-4 py-2.5 border border-slate-300 rounded-xl bg-white text-slate-700 font-medium">Cancel</button>
            <button class="btn-primary text-white px-4 py-2.5 rounded-xl font-semibold"><i class="fa-solid fa-floppy-disk mr-1"></i>Save Plan</button>
        </div>
    </form>
</dialog>

<script>
function syncMonthField(root=document){const period=root.querySelector('.annual-period-select');const box=root.querySelector('.annual-month-field');if(!period||!box)return;box.classList.toggle('hidden',period.value!=='monthly');}
function openAnnualPlanModal(plan=null){const d=document.getElementById('annualPlanModal'),f=document.getElementById('annualPlanForm');f.reset();f.action=plan?`{{ url('/annual-plans') }}/${plan.id}`:`{{ route('annual-plans.store') }}`;document.getElementById('annualPlanMethod').value=plan?'PUT':'POST';document.getElementById('annualPlanModalTitle').innerHTML=plan?'<i class="fa-solid fa-pen-to-square text-[var(--brand-1)] mr-1"></i>Edit Plan':'<i class="fa-solid fa-calendar-plus text-[var(--brand-1)] mr-1"></i>Add Plan';if(plan){for(const [k,v] of Object.entries(plan)){const el=f.elements[k];if(el&&v!==null&&v!==undefined)el.value=v;}}syncMonthField(f);d.showModal();}
document.addEventListener('change',e=>{if(e.target.classList.contains('annual-period-select'))syncMonthField(e.target.form||document);});
document.getElementById('selectAllAnnualPlans')?.addEventListener('change',e=>document.querySelectorAll('.annual-plan-checkbox').forEach(c=>c.checked=e.target.checked));
</script>
@endsection
