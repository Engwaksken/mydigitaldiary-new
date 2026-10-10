@extends('layouts.app')
@section('title','Notifications')
@section('content')
@php
    $center = $notificationCenter ?? ['items'=>[], 'unread_count'=>0, 'action_count'=>0, 'badge_count'=>0];
    $tones = [
        'emerald' => ['#ecfdf5','#047857'], 'sky' => ['#f0f9ff','#0369a1'],
        'violet' => ['#f5f3ff','#6d28d9'], 'amber' => ['#fffbeb','#b45309'],
        'rose' => ['#fff1f2','#be123c'], 'slate' => ['#f8fafc','#475569'],
    ];
    $deletableCount = collect($center['items'] ?? [])->where('source', 'database')->count();
@endphp
<div class="max-w-5xl mx-auto space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Notifications</h1>
            <p class="text-sm text-slate-500 mt-1">Your recent updates and anything that needs attention.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($deletableCount > 0)
                <form id="bulk-delete-form" method="POST" action="{{ route('notifications.bulk-destroy') }}" class="m-0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 rounded-lg border border-rose-200 bg-white text-sm font-semibold text-rose-700 hover:bg-rose-50" data-confirm-click="Delete selected notifications? This action cannot be undone." data-confirm-title="Delete selected notifications?" data-confirm-text="Delete">
                        <i class="fa-solid fa-trash mr-1"></i> Delete selected
                    </button>
                </form>
            @endif
            @if(($center['unread_count'] ?? 0) > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
                    <button class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50"><i class="fa-solid fa-check-double mr-1"></i> Mark all read</button>
                </form>
            @endif
        </div>
    </div>

    <div class="grid sm:grid-cols-3 gap-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">In bell</div><div class="text-xl font-bold mt-1">{{ count($center['items'] ?? []) }}</div></div>
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4"><div class="text-xs text-sky-700">Unread</div><div class="text-xl font-bold text-sky-800 mt-1">{{ $center['unread_count'] ?? 0 }}</div></div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4"><div class="text-xs text-amber-700">Needs attention</div><div class="text-xl font-bold text-amber-800 mt-1">{{ $center['action_count'] ?? 0 }}</div></div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden divide-y divide-slate-100">
        @forelse($center['items'] ?? [] as $item)
            @php $tone = $tones[$item['tone'] ?? 'slate'] ?? $tones['slate']; $isDb = ($item['source'] ?? '') === 'database'; @endphp
            <div class="p-4 flex gap-3 {{ !empty($item['unread']) ? 'bg-sky-50/40' : '' }}">
                @if($isDb)
                    <input type="checkbox" name="ids[]" value="{{ $item['database_id'] }}" form="bulk-delete-form" class="mt-1 rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-1)]" aria-label="Select {{ $item['title'] }}">
                @endif
                <div class="w-10 h-10 rounded-xl shrink-0 flex items-center justify-center" style="background:{{ $tone[0] }};color:{{ $tone[1] }}"><i class="fa-solid {{ $item['icon'] ?? 'fa-bell' }}"></i></div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2"><h2 class="font-semibold text-slate-900">{{ $item['title'] }}</h2>@if(!empty($item['action_required']))<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Action needed</span>@elseif(!empty($item['unread']))<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-sky-100 text-sky-700">New</span>@endif</div>
                    <p class="text-sm text-slate-600 mt-1">{{ $item['message'] }}</p>
                    <p class="text-xs text-slate-400 mt-2">{{ optional($item['created_at'] ?? null)->diffForHumans() }}</p>
                </div>
                <div class="shrink-0 flex items-center gap-1">
                    @if($isDb && !empty($item['unread']))
                        <form method="POST" action="{{ route('notifications.read', $item['database_id']) }}">@csrf
                            <input type="hidden" name="redirect" value="{{ $item['url'] }}">
                            <button class="text-sm font-semibold text-[var(--brand-1)]">Open</button>
                        </form>
                    @else
                        <a href="{{ $item['url'] }}" class="text-sm font-semibold text-[var(--brand-1)]">Open</a>
                    @endif
                    @if($isDb)
                        <form method="POST" action="{{ route('notifications.destroy', $item['database_id']) }}">@csrf @method('DELETE')
                            <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50" title="Delete" aria-label="Delete {{ $item['title'] }}" data-confirm-click="Delete this notification? This action cannot be undone." data-confirm-title="Delete notification?" data-confirm-text="Delete">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-10 text-center text-slate-500"><i class="fa-regular fa-bell-slash text-2xl mb-2"></i><p>You're all caught up.</p></div>
        @endforelse
    </div>
</div>
@endsection
