@extends('layouts.app')

@section('title', 'Workspace Members')

@section('content')
<div class="mx-auto max-w-6xl space-y-5 px-3 py-4 sm:px-5" id="workspace-members-page">
<style>
#workspace-members-page,#workspace-members-page *{box-sizing:border-box}
#workspace-members-page .wm-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem}
#workspace-members-page .wm-name{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
#workspace-members-page input,#workspace-members-page select{min-width:0;max-width:100%}
@media(max-width:767px){
    #workspace-members-page .wm-grid{grid-template-columns:1fr}
    #workspace-members-page .wm-actions{display:grid!important;grid-template-columns:1fr;width:100%}
    #workspace-members-page .wm-actions>*{width:100%!important;min-width:0!important}
}
</style>

<div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-black uppercase tracking-widest text-emerald-600">Shared Workspace</p>
            <h1 class="mt-1 truncate text-2xl font-black text-slate-900">
                {{ $organization->name ?? $organization->organization_name ?? 'Workspace' }}
            </h1>
            <p class="mt-1 text-sm text-slate-500">Invite and manage Family/Team or Enterprise members.</p>
        </div>

        <a href="{{ route('workspace.index') }}" class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold sm:w-auto">
            Back to Workspace
        </a>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <div class="rounded-xl bg-slate-50 p-3">
            <p class="text-[11px] font-bold text-slate-400">SEATS USED</p>
            <p class="mt-1 text-xl font-black">{{ $seatsUsed }}</p>
        </div>
        <div class="rounded-xl bg-slate-50 p-3">
            <p class="text-[11px] font-bold text-slate-400">SEAT LIMIT</p>
            <p class="mt-1 text-xl font-black">{{ $seatLimit ?? 'Unlimited' }}</p>
        </div>
        <div class="col-span-2 rounded-xl bg-slate-50 p-3 sm:col-span-1">
            <p class="text-[11px] font-bold text-slate-400">AVAILABLE</p>
            <p class="mt-1 text-xl font-black">
                {{ $seatLimit === null ? '—' : max(0, $seatLimit - $seatsUsed) }}
            </p>
        </div>
    </div>
</div>

@if(session('success'))
<x-alert type="success" :message="session('success')" />
@endif

@if($errors->any())
<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
    {{ $errors->first() }}
</div>
@endif

<section class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
    <h2 class="font-black text-slate-900">Invite Member</h2>
    <p class="mt-1 text-xs text-slate-500">Existing users are added immediately. New users receive a 7-day email invitation.</p>

    <form method="POST" action="{{ route('workspace.members.invite') }}" class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_170px_auto]">
        @csrf
        <input name="email" type="email" required placeholder="member@example.com" class="rounded-xl border border-slate-200 px-3 py-2.5">
        <select name="role" class="rounded-xl border border-slate-200 px-3 py-2.5">
            <option value="member">Member</option>
            <option value="viewer">Viewer</option>
            <option value="admin">Administrator</option>
        </select>
        <button class="btn-primary rounded-xl px-4 py-2.5 font-bold text-white">
            <i class="fa-solid fa-user-plus mr-1"></i>Invite
        </button>
    </form>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
    <h2 class="font-black text-slate-900">Members</h2>

    <div class="wm-grid mt-4">
        @forelse($members as $member)
            @php
                $memberRole = strtolower((string)($member->role ?? 'member'));
                $memberStatus = isset($member->status)
                    ? strtolower((string)$member->status)
                    : ((isset($member->is_active) && !$member->is_active) ? 'suspended' : 'active');
                $isOwner = $memberRole === 'owner';
            @endphp

            <article class="min-w-0 rounded-xl border border-slate-200 p-4">
                <div class="min-w-0">
                    <p class="wm-name font-black text-slate-800">{{ $member->name ?? 'Member' }}</p>
                    <p class="wm-name text-xs text-slate-500">{{ $member->email ?? 'No email' }}</p>
                </div>

                <div class="mt-2 flex flex-wrap gap-2 text-[11px] font-bold">
                    <span class="rounded-full bg-slate-100 px-2 py-1">{{ ucfirst($memberRole) }}</span>
                    <span class="rounded-full px-2 py-1 {{ $memberStatus === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                        {{ ucfirst($memberStatus) }}
                    </span>
                </div>

                @unless($isOwner)
                    <div class="wm-actions mt-4 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('workspace.members.role', $member->membership_id) }}" class="flex min-w-0 flex-1 gap-2">
                            @csrf @method('PUT')
                            <select name="role" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-2 py-2 text-xs">
                                @foreach(['member'=>'Member','viewer'=>'Viewer','admin'=>'Administrator'] as $value=>$label)
                                    <option value="{{ $value }}" @selected($memberRole===$value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold">Save Role</button>
                        </form>

                        @if($memberStatus === 'suspended')
                            <form method="POST" action="{{ route('workspace.members.activate', $member->membership_id) }}">
                                @csrf
                                <button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white">Activate</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('workspace.members.suspend', $member->membership_id) }}">
                                @csrf
                                <button class="rounded-lg bg-amber-500 px-3 py-2 text-xs font-bold text-white">Suspend</button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('workspace.members.remove', $member->membership_id) }}"
                              onsubmit="return confirm('Remove this member from the workspace? Their personal diary will not be deleted.')">
                            @csrf @method('DELETE')
                            <button class="rounded-lg bg-rose-600 px-3 py-2 text-xs font-bold text-white">Remove</button>
                        </form>
                    </div>
                @endunless
            </article>
        @empty
            <p class="text-sm text-slate-500">No members found.</p>
        @endforelse
    </div>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
    <h2 class="font-black text-slate-900">Pending Invitations</h2>

    <div class="mt-4 space-y-3">
        @forelse($invitations as $invitation)
            <article class="flex min-w-0 flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-3">
                <div class="min-w-0">
                    <p class="wm-name font-bold text-slate-800">{{ $invitation->email }}</p>
                    <p class="text-xs text-slate-500">
                        {{ ucfirst($invitation->role) }} · expires {{ optional($invitation->expires_at)->diffForHumans() }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('workspace.invitations.resend', $invitation) }}">
                        @csrf
                        <button class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold">Resend</button>
                    </form>

                    <form method="POST" action="{{ route('workspace.invitations.cancel', $invitation) }}">
                        @csrf @method('DELETE')
                        <button class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700">Cancel</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="text-sm text-slate-500">No pending invitations.</p>
        @endforelse
    </div>
</section>
</div>
@endsection
