@extends('layouts.app')
@section('title', $goal->title.' · Goal Progress')
@section('content')
@php
    $progress = (int)($snapshot['progress'] ?? $goal->progress_percent ?? 0);
    $components = $snapshot['components'] ?? [];
@endphp
<div class="max-w-6xl mx-auto space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('goal-intelligence') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-800"><i class="fa-solid fa-arrow-left mr-1"></i>Goals & Next Actions</a>
            <div class="mt-2 text-xs uppercase tracking-wide text-violet-600 font-bold">{{ str($goal->module)->replace('_',' ')->title() }} goal</div>
            <h1 class="text-2xl font-bold text-slate-900">{{ $goal->title }}</h1>
            @if($goal->description)<p class="text-sm text-slate-500 mt-1 max-w-3xl">{{ $goal->description }}</p>@endif
        </div>
        <a href="{{ route('personal-goals.index', ['module'=>$goal->module]) }}" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-700">Edit goal</a>
    </div>

    <section class="grid md:grid-cols-[1.3fr_.7fr] gap-4">
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4"><div><div class="text-xs uppercase tracking-wide text-slate-400">Automatic progress</div><div class="text-3xl font-bold text-slate-900 mt-1">{{ $progress }}%</div></div><div class="w-16 h-16 rounded-full bg-violet-50 text-violet-700 grid place-items-center"><i class="fa-solid fa-chart-line text-xl"></i></div></div>
            <div class="mt-4 h-3 bg-slate-100 rounded-full overflow-hidden"><div class="h-full rounded-full" style="width:{{ $progress }}%;background:var(--brand-1)"></div></div>
            <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-2">
                @foreach(['value'=>'Target value','milestones'=>'Milestones','tasks'=>'Linked tasks','plans'=>'Annual plans'] as $key=>$label)
                    @if(array_key_exists($key,$components))
                    <div class="rounded-xl bg-slate-50 border border-slate-100 p-3"><div class="text-[11px] text-slate-500">{{ $label }}</div><div class="font-bold text-slate-900 mt-1">{{ $components[$key] }}%</div></div>
                    @endif
                @endforeach
            </div>
            <p class="text-xs text-slate-500 mt-3">Progress updates automatically as linked plans, tasks and milestones move forward. If you use a numeric target, that progress is included too.</p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">What moved this goal this week?</div>
            <div class="text-2xl font-bold text-slate-900 mt-1">{{ $week['movement_count'] ?? 0 }} action{{ ($week['movement_count'] ?? 0) === 1 ? '' : 's' }}</div>
            <p class="text-sm text-slate-600 mt-2">{{ $week['message'] ?? '' }}</p>
            <div class="text-xs text-slate-400 mt-3">{{ \Illuminate\Support\Carbon::parse($week['week_start'])->format('d M') }} - {{ \Illuminate\Support\Carbon::parse($week['week_end'])->format('d M Y') }}</div>
        </div>
    </section>

    <section class="grid lg:grid-cols-[.8fr_1.2fr] gap-4">
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            @php($acc = $accountability ?? [])
            @php($state = $acc['status'] ?? 'on_track')
            @php($stateClass = match($state){'ahead'=>'bg-emerald-50 text-emerald-700','at_risk'=>'bg-amber-50 text-amber-700','overdue'=>'bg-rose-50 text-rose-700','completed'=>'bg-sky-50 text-sky-700',default=>'bg-violet-50 text-violet-700'})
            <div class="flex items-center justify-between gap-3">
                <div><div class="text-xs uppercase tracking-wide text-slate-400">Goal accountability</div><div class="text-lg font-bold text-slate-900 mt-1">{{ $acc['status_label'] ?? 'On Track' }}</div></div>
                <span class="px-3 py-1 rounded-full text-xs font-bold {{ $stateClass }}">{{ $acc['status_label'] ?? 'On Track' }}</span>
            </div>
            <p class="text-sm text-slate-600 mt-3">{{ $acc['message'] ?? '' }}</p>
            @if(($acc['expected_progress'] ?? null) !== null)
            <div class="grid grid-cols-2 gap-2 mt-4">
                <div class="rounded-xl bg-slate-50 p-3"><div class="text-[11px] text-slate-500">Actual progress</div><div class="font-bold text-slate-900">{{ $acc['progress'] ?? 0 }}%</div></div>
                <div class="rounded-xl bg-slate-50 p-3"><div class="text-[11px] text-slate-500">Expected by now</div><div class="font-bold text-slate-900">{{ $acc['expected_progress'] }}%</div></div>
            </div>
            @endif
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center justify-between"><div><div class="text-xs uppercase tracking-wide text-slate-400">Weekly check-in</div><div class="font-bold text-slate-900 mt-1">What will move this goal next?</div></div>@if(!empty($acc['checkin']))<span class="text-xs text-emerald-600 font-semibold">Saved this week</span>@endif</div>
            <form method="POST" action="{{ route('personal-goals.checkin.store',$goal) }}" class="mt-4 grid md:grid-cols-[.45fr_1fr] gap-3">@csrf
                <label class="block"><span class="text-xs font-semibold text-slate-600">Confidence (1-5)</span><select name="confidence" class="mt-1 w-full rounded-xl border-slate-200">@for($i=1;$i<=5;$i++)<option value="{{ $i }}" @selected(($acc['checkin']['confidence'] ?? 3)===$i)>{{ $i }}</option>@endfor</select></label>
                <label class="block"><span class="text-xs font-semibold text-slate-600">One action I will complete</span><input name="planned_action" required maxlength="255" value="{{ $acc['checkin']['planned_action'] ?? '' }}" class="mt-1 w-full rounded-xl border-slate-200" placeholder="e.g. Finish the next milestone on Thursday"></label>
                <label class="block md:col-span-2"><span class="text-xs font-semibold text-slate-600">Optional note</span><textarea name="note" rows="2" class="mt-1 w-full rounded-xl border-slate-200" placeholder="What may get in the way?">{{ $acc['checkin']['note'] ?? '' }}</textarea></label>
                <div class="md:col-span-2"><button class="px-4 py-2.5 rounded-xl text-white font-semibold text-sm" style="background:var(--brand-1)"><i class="fa-solid fa-check mr-1"></i>Save weekly check-in</button></div>
            </form>
        </div>
    </section>

    @if(!empty(($accountability ?? [])['overdue_milestones']))
    <section class="bg-amber-50/70 rounded-2xl border border-amber-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-amber-200"><h2 class="font-bold text-amber-900">Missed milestones</h2><p class="text-xs text-amber-700">Reschedule instead of leaving missed checkpoints behind.</p></div>
        <div class="divide-y divide-amber-200">
            @foreach($accountability['overdue_milestones'] as $missed)
            <div class="p-4 flex flex-col md:flex-row md:items-center gap-3">
                <div class="flex-1"><div class="font-semibold text-slate-900">{{ $missed['title'] }}</div><div class="text-xs text-amber-700 mt-1">{{ $missed['days_overdue'] }} day{{ $missed['days_overdue']==1?'':'s' }} overdue · was due {{ $missed['target_date'] }}</div></div>
                <form method="POST" action="{{ route('personal-goals.milestones.reschedule',[$goal,$missed['id']]) }}" class="flex items-center gap-2">@csrf @method('PATCH')<input type="date" name="target_date" required value="{{ $missed['suggested_date'] }}" class="rounded-xl border-amber-200 bg-white text-sm"><button class="px-3 py-2 rounded-xl bg-amber-600 text-white text-sm font-semibold">Reschedule</button></form>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    <section class="grid lg:grid-cols-[.85fr_1.15fr] gap-4">
        <div class="bg-gradient-to-br from-indigo-50 to-white rounded-2xl border border-indigo-100 p-5 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 grid place-items-center"><i class="fa-solid fa-lightbulb"></i></div>
                <div class="min-w-0 flex-1">
                    <div class="text-xs uppercase tracking-wide text-indigo-500 font-bold">Reflection & learning</div>
                    <h2 class="font-bold text-slate-900 mt-1">Turn progress into something you can repeat</h2>
                    <p class="text-sm text-slate-600 mt-1">Capture what worked, what was difficult and what you learned. These lessons are included in future AI Planner context when Goals access is enabled.</p>
                </div>
            </div>
            @if(!empty(($learning ?? [])['latest']))
                @php($latestLearning = $learning['latest'])
                <div class="mt-4 rounded-xl bg-white/80 border border-indigo-100 p-3">
                    <div class="text-[11px] uppercase tracking-wide text-slate-400">Latest lesson</div>
                    <div class="text-sm font-semibold text-slate-800 mt-1">{{ $latestLearning['lessons_learned'] }}</div>
                    @if(!empty($latestLearning['repeat_next_time']))<div class="text-xs text-emerald-700 mt-2"><strong>Repeat:</strong> {{ $latestLearning['repeat_next_time'] }}</div>@endif
                    @if(!empty($latestLearning['change_next_time']))<div class="text-xs text-amber-700 mt-1"><strong>Change:</strong> {{ $latestLearning['change_next_time'] }}</div>@endif
                </div>
            @else
                <div class="mt-4 text-xs text-indigo-700 bg-indigo-100/70 rounded-xl px-3 py-2">Complete a milestone or make meaningful progress, then record a short reflection here.</div>
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3"><div><div class="text-xs uppercase tracking-wide text-slate-400">Add reflection</div><div class="font-bold text-slate-900 mt-1">What did this goal teach you?</div></div><span class="text-xs text-slate-400">{{ $learning['count'] ?? 0 }} saved</span></div>
            <form method="POST" action="{{ route('personal-goals.reflections.store',$goal) }}" class="mt-4 grid md:grid-cols-2 gap-3">@csrf
                <label class="block md:col-span-2"><span class="text-xs font-semibold text-slate-600">Reflect on</span><select name="goal_milestone_id" class="mt-1 w-full rounded-xl border-slate-200"><option value="">Whole goal</option>@foreach($goal->milestones->where('status','completed') as $m)<option value="{{ $m->id }}">Milestone: {{ $m->title }}</option>@endforeach</select></label>
                <label class="block"><span class="text-xs font-semibold text-slate-600">What worked?</span><textarea name="what_worked" rows="3" class="mt-1 w-full rounded-xl border-slate-200" placeholder="Habits, decisions or support that helped"></textarea></label>
                <label class="block"><span class="text-xs font-semibold text-slate-600">What was difficult?</span><textarea name="challenges" rows="3" class="mt-1 w-full rounded-xl border-slate-200" placeholder="Obstacles, delays or distractions"></textarea></label>
                <label class="block md:col-span-2"><span class="text-xs font-semibold text-slate-600">What did you learn? <span class="text-rose-500">*</span></span><textarea name="lessons_learned" required rows="3" class="mt-1 w-full rounded-xl border-slate-200" placeholder="The lesson you want future planning to remember"></textarea></label>
                <label class="block"><span class="text-xs font-semibold text-slate-600">What should you repeat next time?</span><textarea name="repeat_next_time" rows="2" class="mt-1 w-full rounded-xl border-slate-200"></textarea></label>
                <label class="block"><span class="text-xs font-semibold text-slate-600">What should you change next time?</span><textarea name="change_next_time" rows="2" class="mt-1 w-full rounded-xl border-slate-200"></textarea></label>
                <label class="block"><span class="text-xs font-semibold text-slate-600">Confidence after reflection</span><select name="confidence_after" class="mt-1 w-full rounded-xl border-slate-200"><option value="">Not set</option>@for($i=1;$i<=5;$i++)<option value="{{ $i }}">{{ $i }} / 5</option>@endfor</select></label>
                <div class="md:self-end"><button class="w-full md:w-auto px-4 py-2.5 rounded-xl text-white font-semibold text-sm" style="background:var(--brand-1)"><i class="fa-solid fa-book-open mr-1"></i>Save reflection</button></div>
            </form>
        </div>
    </section>

    @if($goal->reflections->isNotEmpty())
    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100"><h2 class="font-bold text-slate-900">Lessons from this goal</h2><p class="text-xs text-slate-500">A personal learning history you can reuse on future goals.</p></div>
        <div class="divide-y divide-slate-100">
            @foreach($goal->reflections->take(6) as $reflection)
            <div class="p-4 flex gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 grid place-items-center flex-none"><i class="fa-solid fa-seedling text-sm"></i></div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2"><div class="font-semibold text-slate-900">{{ $reflection->milestone?->title ? 'Milestone · '.$reflection->milestone->title : 'Goal reflection' }}</div><span class="text-[11px] text-slate-400">{{ optional($reflection->reflected_on)->format('d M Y') }}</span></div>
                    <p class="text-sm text-slate-700 mt-1"><strong>Learned:</strong> {{ $reflection->lessons_learned }}</p>
                    @if($reflection->what_worked)<p class="text-xs text-emerald-700 mt-1"><strong>Worked:</strong> {{ $reflection->what_worked }}</p>@endif
                    @if($reflection->challenges)<p class="text-xs text-amber-700 mt-1"><strong>Difficult:</strong> {{ $reflection->challenges }}</p>@endif
                </div>
                <form method="POST" action="{{ route('personal-goals.reflections.destroy',[$goal,$reflection]) }}" class="flex-none">@csrf @method('DELETE')<button class="w-8 h-8 rounded-lg text-rose-500 hover:bg-rose-50" title="Delete reflection"><i class="fa-regular fa-trash-can"></i></button></form>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <div><h2 class="font-bold text-slate-900">Milestones</h2><p class="text-xs text-slate-500">Break the goal into meaningful checkpoints.</p></div>
            <span class="text-xs font-semibold text-violet-700 bg-violet-50 rounded-full px-3 py-1">{{ $snapshot['milestones_completed'] ?? 0 }}/{{ $snapshot['milestones_total'] ?? 0 }} complete</span>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($goal->milestones as $milestone)
            <div class="p-4 flex items-start gap-3">
                <form method="POST" action="{{ route('personal-goals.milestones.toggle', [$goal,$milestone]) }}">@csrf @method('PATCH')
                    <button class="mt-0.5 w-7 h-7 rounded-full border grid place-items-center {{ $milestone->status==='completed' ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-slate-300 text-slate-400' }}" title="Toggle milestone"><i class="fa-solid {{ $milestone->status==='completed' ? 'fa-check' : 'fa-circle' }} text-xs"></i></button>
                </form>
                <div class="min-w-0 flex-1">
                    <div class="font-semibold {{ $milestone->status==='completed' ? 'line-through text-slate-400' : 'text-slate-900' }}">{{ $milestone->title }}</div>
                    @if($milestone->description)<div class="text-sm text-slate-500 mt-1">{{ $milestone->description }}</div>@endif
                    <div class="flex flex-wrap gap-2 mt-2 text-xs text-slate-400">
                        @if($milestone->target_date)<span><i class="fa-regular fa-calendar mr-1"></i>{{ $milestone->target_date->format('d M Y') }}</span>@endif
                        @if($milestone->weight)<span>{{ $milestone->weight }}% weight</span>@endif
                    </div>
                </div>
                <form method="POST" action="{{ route('personal-goals.milestones.destroy', [$goal,$milestone]) }}">@csrf @method('DELETE')<button class="w-8 h-8 rounded-lg text-rose-500 hover:bg-rose-50" title="Delete milestone"><i class="fa-regular fa-trash-can"></i></button></form>
            </div>
            @empty
            <div class="p-6 text-center text-sm text-slate-500">No milestones yet. Add the first checkpoint below.</div>
            @endforelse
        </div>
        <form method="POST" action="{{ route('personal-goals.milestones.store', $goal) }}" class="p-4 bg-slate-50 border-t border-slate-100 grid md:grid-cols-[1.5fr_1fr_.7fr_auto] gap-2 items-end">@csrf
            <label class="block"><span class="text-xs font-semibold text-slate-600">Milestone</span><input name="title" required maxlength="255" class="mt-1 w-full rounded-xl border-slate-200" placeholder="e.g. Complete first module"></label>
            <label class="block"><span class="text-xs font-semibold text-slate-600">Target date</span><input type="date" name="target_date" class="mt-1 w-full rounded-xl border-slate-200"></label>
            <label class="block"><span class="text-xs font-semibold text-slate-600">Weight %</span><input type="number" name="weight" min="1" max="100" class="mt-1 w-full rounded-xl border-slate-200" placeholder="Optional"></label>
            <button class="px-4 py-2.5 rounded-xl text-white font-semibold text-sm" style="background:var(--brand-1)"><i class="fa-solid fa-plus mr-1"></i>Add</button>
        </form>
    </section>

    <section class="grid lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 font-bold text-slate-900">This week’s movement</div>
            <div class="divide-y divide-slate-100">
                @forelse($week['items'] ?? [] as $item)
                <div class="p-3 flex items-center gap-3"><div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 grid place-items-center"><i class="fa-solid fa-arrow-trend-up text-xs"></i></div><div class="min-w-0"><div class="text-[11px] text-slate-400">{{ $item['type'] }}</div><div class="text-sm font-semibold text-slate-800 truncate">{{ $item['title'] }}</div></div></div>
                @empty
                <div class="p-6 text-sm text-slate-500">Complete a linked task or milestone to start this week’s movement log.</div>
                @endforelse
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
            <h2 class="font-bold text-slate-900">Execution links</h2>
            <div class="grid grid-cols-3 gap-2 mt-3">
                <div class="rounded-xl bg-violet-50 p-3 text-center"><div class="text-xl font-bold text-violet-700">{{ $snapshot['plans_total'] ?? 0 }}</div><div class="text-[11px] text-violet-600">Plans</div></div>
                <div class="rounded-xl bg-sky-50 p-3 text-center"><div class="text-xl font-bold text-sky-700">{{ $snapshot['tasks_completed'] ?? 0 }}/{{ $snapshot['tasks_total'] ?? 0 }}</div><div class="text-[11px] text-sky-600">Tasks done</div></div>
                <div class="rounded-xl bg-emerald-50 p-3 text-center"><div class="text-xl font-bold text-emerald-700">{{ $snapshot['milestones_completed'] ?? 0 }}/{{ $snapshot['milestones_total'] ?? 0 }}</div><div class="text-[11px] text-emerald-600">Milestones</div></div>
            </div>
            <p class="text-xs text-slate-500 mt-3">Link more Annual Plans, Daily Planner tasks or Project Tasks to this goal to make progress more measurable.</p>
        </div>
    </section>
</div>
@endsection
