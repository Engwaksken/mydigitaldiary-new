@extends('layouts.app')
@section('title','Support Conversations')
@section('content')
<div class="space-y-5">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Support Conversations</h1>
        <p class="mt-1 text-sm text-slate-500">Review chats, assign or release support staff, and follow completed requests.</p>
    </div>

    <div class="pm-card-bg overflow-hidden rounded-2xl border border-slate-200 shadow-sm">
        <div class="overflow-x-auto pm-admin-table-scroll">
            <table class="min-w-full text-sm pm-admin-horizontal-table">
                <thead class="border-b border-slate-200 bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Customer</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Status</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Assigned support</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Messages</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Rating</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Last activity</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($conversations as $conversation)
                        <tr class="align-top hover:bg-slate-50/70">
                            <td class="px-4 py-4">
                                <div class="font-semibold text-slate-800">{{ $conversation->user?->name ?? 'Deleted user' }}</div>
                                <div class="mt-0.5 text-xs text-slate-500">{{ $conversation->user?->email ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-4">
                                @if($conversation->ended_at)
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Ended</span>
                                @elseif($conversation->assigned_to_user_id)
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Human</span>
                                @else
                                    <span class="inline-flex rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700">AI</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 min-w-[270px]">
                                @if($conversation->assigned_to_user_id)
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-slate-700">{{ $conversation->assignee?->name ?? 'Unknown' }}</span>
                                        @if(in_array((string) auth()->user()->role, ['admin','super_admin'], true) || (int) $conversation->assigned_to_user_id === (int) auth()->id())
                                        <form method="POST" action="{{ route('admin.support.unassign',$conversation) }}">
                                            @csrf @method('PATCH')
                                            <button class="rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Remove assignment</button>
                                        </form>
                                        @endif
                                    </div>
                                @elseif(!$conversation->ended_at && in_array((string) auth()->user()->role, ['admin','super_admin'], true))
                                    <form method="POST" action="{{ route('admin.support.assign',$conversation) }}" class="flex items-center gap-2">
                                        @csrf @method('PATCH')
                                        <select name="assigned_to_user_id" class="pm-input min-w-[160px] text-sm" required>
                                            <option value="">Assign person</option>
                                            @foreach($agents as $agent)<option value="{{ $agent->id }}">{{ $agent->name }}</option>@endforeach
                                        </select>
                                        <button class="btn-primary min-h-[40px] rounded-lg px-3 text-xs font-semibold text-white">Assign</button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400">No active assignment</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-slate-600">{{ $conversation->messages_count ?? 0 }}</td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                @if($conversation->support_rating)
                                    <span class="whitespace-nowrap">
                                        @for ($i = 0; $i < 5; $i++)
                                            <i class="fa-solid fa-star {{ $i < $conversation->support_rating ? 'text-amber-500' : 'text-slate-300' }}"></i>
                                        @endfor
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-slate-500">{{ optional($conversation->last_message_at)->diffForHumans() ?? '—' }}</td>
                            <td class="px-4 py-4 text-right">
                                <a href="{{ route('admin.support.show',$conversation) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"><i class="fa-solid fa-comments"></i> Open Chat</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-12 text-center text-slate-400">No support conversations yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $conversations->links() }}</div>
</div>
@endsection
