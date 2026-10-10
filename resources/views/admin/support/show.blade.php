@extends('layouts.app')
@section('title','Support Conversation')
@section('content')
<div class="mx-auto max-w-5xl space-y-4">
    <a href="{{ route('admin.support.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-slate-700"><i class="fa-solid fa-arrow-left"></i> Support Conversations</a>

    <div class="pm-card-bg rounded-2xl border border-slate-200 p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="truncate text-xl font-bold text-slate-800">{{ $conversation->user?->name ?? 'Deleted user' }}</h1>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $conversation->ended_at ? 'bg-slate-100 text-slate-600' : ($conversation->assigned_to_user_id ? 'bg-emerald-50 text-emerald-700' : 'bg-violet-50 text-violet-700') }}">
                        {{ $conversation->ended_at ? 'Ended' : ($conversation->assigned_to_user_id ? 'Human Support' : 'AI Support') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-500">{{ $conversation->user?->email ?? '—' }}</p>
                @if($conversation->support_rating)
                    <p class="mt-2 text-sm text-slate-600">Customer rating: <span class="whitespace-nowrap">@for ($i = 0; $i < 5; $i++)<i class="fa-solid fa-star {{ $i < $conversation->support_rating ? 'text-amber-500' : 'text-slate-300' }}"></i>@endfor</span>@if($conversation->support_rating_comment) — {{ $conversation->support_rating_comment }}@endif</p>
                @endif
            </div>

            <div class="flex w-full flex-col gap-2 sm:flex-row lg:w-auto">
                @if($conversation->assigned_to_user_id)
                    <div class="flex items-center rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-600">Assigned to <strong class="ml-1">{{ $conversation->assignee?->name ?? 'Unknown' }}</strong></div>
                    @if(in_array((string) auth()->user()->role, ['admin','super_admin'], true) || (int) $conversation->assigned_to_user_id === (int) auth()->id())
                                        <form method="POST" action="{{ route('admin.support.unassign',$conversation) }}">@csrf @method('PATCH')
                        <button class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl border border-rose-200 px-4 font-semibold text-rose-600 hover:bg-rose-50"><i class="fa-solid fa-user-xmark"></i> Disconnect</button>
                    </form>
                    @endif
                @elseif(!$conversation->ended_at && in_array((string) auth()->user()->role, ['admin','super_admin'], true))
                    <form method="POST" action="{{ route('admin.support.assign',$conversation) }}" class="flex w-full flex-col gap-2 sm:flex-row">@csrf @method('PATCH')
                        <select name="assigned_to_user_id" class="pm-input min-w-[220px]" required><option value="">Assign support person</option>@foreach($agents as $agent)<option value="{{ $agent->id }}">{{ $agent->name }}</option>@endforeach</select>
                        <button class="btn-primary inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl px-4 font-semibold text-white"><i class="fa-solid fa-user-check"></i> Assign</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div id="support-thread" class="pm-card-bg min-h-[360px] space-y-3 overflow-y-auto rounded-2xl border border-slate-200 p-4 shadow-sm sm:p-5">
        @forelse($messages as $message)
            @php $fromCustomer=$message->sender_type==='user'; $fromAi=$message->sender_type==='ai'; @endphp
            <div class="flex {{ $fromCustomer ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[88%] rounded-2xl px-4 py-3 sm:max-w-[76%] {{ $fromCustomer ? 'bg-slate-800 text-white' : ($fromAi ? 'bg-violet-50 text-slate-800 border border-violet-100' : 'bg-emerald-50 text-slate-800 border border-emerald-100') }}">
                    <div class="mb-1 text-[11px] {{ $fromCustomer ? 'text-slate-300' : 'text-slate-500' }}">{{ $fromCustomer ? ($conversation->user?->name ?? 'Customer') : ($fromAi ? 'AI Support' : ($message->user?->name ?? 'Support')) }} @if($message->created_at) · {{ $message->created_at->format('M j, H:i') }} @endif</div>
                    <div class="whitespace-pre-wrap break-words text-sm leading-6">{{ $message->message }}</div>
                </div>
            </div>
        @empty
            <div class="grid min-h-[300px] place-items-center text-sm text-slate-400">No messages in this conversation.</div>
        @endforelse
    </div>
    <div>{{ $messages->links() }}</div>

    @if(!$conversation->ended_at)
        <form method="POST" action="{{ route('admin.support.reply',$conversation) }}" class="pm-card-bg rounded-2xl border border-slate-200 p-4 shadow-sm">@csrf
            <label for="support-reply" class="mb-2 block text-sm font-semibold text-slate-700">Reply as support</label>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <textarea id="support-reply" name="message" required rows="3" maxlength="4000" class="pm-input min-h-[92px] flex-1" placeholder="Write your reply..."></textarea>
                <button class="btn-primary inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl px-5 font-semibold text-white"><i class="fa-solid fa-paper-plane"></i> Send Reply</button>
            </div>
            <p class="mt-2 text-xs text-slate-400">If the customer does not reply within 5 minutes after a support reply, the assignment is released automatically.</p>
        </form>
    @else
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">This support request was ended by the customer{{ $conversation->ended_at ? ' on '.$conversation->ended_at->format('M j, Y H:i') : '' }}.</div>
    @endif
</div>
@endsection
