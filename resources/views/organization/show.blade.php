@extends('layouts.app')

@section('title', 'Manage Members')

@section('content')
@php
    $roles = [
        'member' => 'Member',
        'viewer' => 'Viewer',
        'staff' => 'Staff',
        'admin' => 'Administrator',
    ];
@endphp

<div class="space-y-4">
    <div class="apple-surface rounded-2xl p-4 sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-xs font-black uppercase tracking-[.12em] text-slate-400">
                    Team Workspace
                </div>
                <h1 class="mt-1 text-xl font-black text-slate-900">
                    Manage Members
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Invite people, assign roles and manage access for your subscribed workspace.
                </p>
            </div>

            @if($organization)
                <button
                    type="button"
                    onclick="document.getElementById('invite-member-dialog').showModal()"
                    class="btn-primary rounded-xl px-4 py-2.5 text-sm font-bold text-white"
                    @disabled($remainingSeats <= 0)
                >
                    <i class="fa-solid fa-user-plus mr-1"></i>
                    Add Member
                </button>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc space-y-1 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!$organization)
        <div class="apple-surface rounded-2xl p-6 text-center">
            <i class="fa-solid fa-users-slash text-3xl text-slate-300"></i>
            <h2 class="mt-3 text-lg font-black text-slate-900">
                Member management is not available
            </h2>
            <p class="mx-auto mt-2 max-w-xl text-sm text-slate-500">
                @if($eligibleForTeam)
                    Your team workspace could not be prepared. Run the latest organization migration and reload this page.
                @else
                    Family, Small Team, Organization and Enterprise subscriptions include team member management.
                @endif
            </p>
            <a href="{{ route('subscription.show') }}" class="mt-4 inline-flex apple-btn rounded-xl px-4 py-2.5 text-sm font-bold">
                View Subscription
            </a>
        </div>
    @else
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="apple-surface rounded-2xl p-4">
                <div class="text-xs font-bold text-slate-500">Plan</div>
                <div class="mt-1 text-lg font-black text-slate-900">
                    {{ $organization->plan?->name ?? 'Team Plan' }}
                </div>
            </div>
            <div class="apple-surface rounded-2xl p-4">
                <div class="text-xs font-bold text-slate-500">Seats Used</div>
                <div class="mt-1 text-lg font-black text-slate-900">
                    {{ $seatsUsed }} / {{ $seatLimit }}
                </div>
            </div>
            <div class="apple-surface rounded-2xl p-4">
                <div class="text-xs font-bold text-slate-500">Seats Available</div>
                <div class="mt-1 text-lg font-black text-teal-700">
                    {{ $remainingSeats }}
                </div>
            </div>
        </div>

        <section class="apple-surface rounded-2xl pm-org-members">
            <style>
                .pm-org-members .pm-dt thead th { border-radius: 0; }
                .pm-org-members .pm-org-role { width: auto; min-width: 0; padding-top: .35rem; padding-bottom: .35rem; font-size: .8125rem; }
            </style>
            <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                <h2 class="font-black text-slate-900">
                    {{ $organization->name }}
                </h2>
                <p class="mt-1 text-xs text-slate-500">
                    Your personal diary entries remain private unless explicitly shared.
                </p>
            </div>

            <table class="pm-dt">
                <caption class="sr-only">Organization members, with their role, status and actions.</caption>
                <thead>
                    <tr>
                        <th scope="col">Member</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="pm-dt-actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($members as $member)
                        @php
                            $status = strtolower((string) $member->status);
                            $memberEmail = $member->user?->email ?? $member->invited_email;
                            $memberName = $member->user?->name ?? 'Pending invitation';
                        @endphp
                        <tr>
                            <td class="pm-dt-main">
                                <span class="pm-dt-title">{{ $memberName }}</span>
                                @if (filled($memberEmail))
                                    <span class="pm-dt-sub" title="{{ $memberEmail }}"><span>{{ $memberEmail }}</span></span>
                                @endif
                            </td>
                            <td class="pm-dt-aux">
                                <form
                                    method="POST"
                                    action="{{ route('organization.members.role', $member) }}"
                                    class="flex items-center gap-2"
                                >
                                    @csrf
                                    @method('PUT')
                                    <label for="pm-org-role-{{ $member->id }}" class="sr-only">Role for {{ $memberName }}</label>
                                    <select id="pm-org-role-{{ $member->id }}" name="role" class="pm-input pm-org-role" onchange="this.form.submit()">
                                        @foreach($roles as $value => $label)
                                            <option value="{{ $value }}" @selected($member->role === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                            <td class="pm-dt-aux">
                                <span class="pm-dt-pill {{ $status === 'active' ? 'is-green' : ($status === 'invited' ? 'is-amber' : 'is-slate') }}">
                                    {{ ucfirst($status) }}
                                </span>
                            </td>
                            <td class="pm-dt-actions">
                                <details class="pm-dt-menu">
                                    <summary class="pm-dt-icon-btn" aria-label="Actions for {{ $memberName }}" title="Actions">
                                        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                    </summary>
                                    <div class="pm-dt-menu-list">
                                        <button
                                            type="button"
                                            class="pm-dt-menu-item"
                                            data-edit-org-member
                                            data-id="{{ $member->id }}"
                                            data-email="{{ e((string) ($member->user?->email ?? $member->invited_email ?? '')) }}"
                                            data-role="{{ $member->role }}"
                                            data-status="{{ $member->status }}"
                                        >
                                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                            Edit
                                        </button>

                                        @if($status === 'inactive')
                                            <form method="POST" action="{{ route('organization.members.activate', $member) }}">
                                                @csrf
                                                <button class="pm-dt-menu-item">
                                                    <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                                                    Reactivate
                                                </button>
                                            </form>
                                        @elseif($status === 'active')
                                            <form method="POST" action="{{ route('organization.members.deactivate', $member) }}">
                                                @csrf
                                                <button class="pm-dt-menu-item">
                                                    <i class="fa-solid fa-user-slash" aria-hidden="true"></i>
                                                    Suspend
                                                </button>
                                            </form>
                                        @endif

                                        <form
                                            method="POST"
                                            action="{{ route('organization.members.remove', $member) }}"
                                            onsubmit="return confirm('Remove this member from the organization?')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button class="pm-dt-menu-item is-danger">
                                                <i class="fa-solid fa-user-minus" aria-hidden="true"></i>
                                                Remove
                                            </button>
                                        </form>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr class="pm-dt-empty">
                            <td colspan="4">
                                No members yet. Use Add Member to invite your first member.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if(method_exists($members, 'links'))
                <div class="border-t border-slate-100 px-4 py-3">
                    {{ $members->links() }}
                </div>
            @endif
        </section>
    @endif
</div>

@if($organization)
<dialog id="edit-member-dialog" class="rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/55">
    <form
        method="POST"
        id="edit-member-form"
        action=""
        class="w-[min(92vw,480px)] overflow-hidden rounded-2xl bg-white"
    >
        @csrf
        @method('PUT')

        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-black text-slate-900">
                Edit Member
            </h2>
            <p class="mt-1 text-xs text-slate-500">
                The owner can change workspace role. Email can only be changed while the invitation is still pending.
            </p>
        </div>

        <div class="space-y-4 px-5 py-4">
            <div>
                <label class="text-xs font-bold text-slate-700">
                    Email address
                </label>
                <input
                    type="email"
                    name="email"
                    id="edit-member-email"
                    class="pm-input mt-1 w-full"
                >
                <p id="edit-member-email-help" class="mt-1 text-[11px] text-slate-500"></p>
            </div>

            <div>
                <label class="text-xs font-bold text-slate-700">
                    Workspace role
                </label>
                <select
                    name="role"
                    id="edit-member-role"
                    required
                    class="pm-input mt-1 w-full"
                >
                    @foreach($roles as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <button
                type="button"
                onclick="this.closest('dialog').close()"
                class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold"
            >
                Cancel
            </button>
            <button
                type="submit"
                class="btn-primary rounded-xl px-5 py-2.5 text-sm font-bold text-white"
            >
                Save Changes
            </button>
        </div>
    </form>
</dialog>
@endif

@if($organization)
<dialog
    id="invite-member-dialog"
    class="member-dialog"
>
    <form
        method="POST"
        action="{{ route('organization.invite') }}"
        class="member-dialog-form"
    >
        @csrf

        <div class="member-dialog-header">
            <div>
                <h2 class="text-lg font-black text-slate-900">
                    Add Member
                </h2>
                <p class="mt-1 text-xs text-slate-500">
                    Existing users keep their current password. For a new email,
                    enter the member name and set a temporary password.
                </p>
            </div>

            <button
                type="button"
                onclick="this.closest('dialog').close()"
                class="member-dialog-close"
                aria-label="Close"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="member-dialog-body">
            <div class="space-y-4">
                <div>
                    <label class="text-xs font-bold text-slate-700">
                        Member name
                    </label>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        class="pm-input mt-1 w-full"
                        placeholder="Required only for a new account"
                    >
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-700">
                        Email address
                    </label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        class="pm-input mt-1 w-full"
                        placeholder="member@example.com"
                    >
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-700">
                        Workspace role
                    </label>
                    <select
                        name="role"
                        required
                        class="pm-input mt-1 w-full"
                    >
                        @foreach($roles as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected(old('role', 'member') === $value)
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-xs font-bold text-slate-700">
                            Temporary password
                        </label>
                        <input
                            type="password"
                            name="temporary_password"
                            autocomplete="new-password"
                            class="pm-input mt-1 w-full"
                            placeholder="For new users only"
                        >
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-700">
                            Confirm password
                        </label>
                        <input
                            type="password"
                            name="temporary_password_confirmation"
                            autocomplete="new-password"
                            class="pm-input mt-1 w-full"
                            placeholder="Repeat password"
                        >
                    </div>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-xs leading-5 text-amber-900">
                    <strong>Existing account:</strong>
                    leave the temporary password blank; the user's current
                    password is preserved.
                    <br>
                    <strong>New account:</strong>
                    use at least 8 characters with letters and numbers.
                    Share it separately with the member.
                </div>

                <div class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    {{ $remainingSeats }} member seat(s) currently available.
                </div>
            </div>
        </div>

        <div class="member-dialog-footer">
            <button
                type="button"
                onclick="this.closest('dialog').close()"
                class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold"
            >
                Cancel
            </button>

            <button
                type="submit"
                class="btn-primary rounded-xl px-5 py-2.5 text-sm font-bold text-white"
            >
                <i class="fa-solid fa-user-plus mr-1"></i>
                Add Member
            </button>
        </div>
    </form>
</dialog>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const dialog = document.getElementById('edit-member-dialog');
    const form = document.getElementById('edit-member-form');
    const email = document.getElementById('edit-member-email');
    const role = document.getElementById('edit-member-role');
    const help = document.getElementById('edit-member-email-help');

    document.querySelectorAll('[data-edit-org-member]').forEach((button) => {
        button.addEventListener('click', function () {
            if (!dialog || !form || !email || !role) return;

            const id = button.dataset.id;
            const status = (button.dataset.status || '').toLowerCase();

            form.action = @json(route('organization.members.edit', ['member' => '__MEMBER__']))
                .replace('__MEMBER__', encodeURIComponent(id));

            email.value = button.dataset.email || '';
            role.value = button.dataset.role || 'member';

            const pending = status === 'invited';
            email.readOnly = !pending;
            email.classList.toggle('bg-slate-100', !pending);

            if (help) {
                help.textContent = pending
                    ? 'Pending invitation: the owner may correct this email before the invitation is accepted.'
                    : 'Active member: account email is personal profile data and cannot be changed here.';
            }

            dialog.showModal();
        });
    });
});
</script>


<style>
.member-dialog {
    width: min(94vw, 520px);
    max-width: 520px;
    max-height: 90dvh;
    margin: auto;
    padding: 0;
    border: 0;
    border-radius: 22px;
    background: transparent;
    overflow: hidden;
}

.member-dialog::backdrop {
    background: rgba(15, 23, 42, .58);
    backdrop-filter: blur(3px);
}

.member-dialog-form {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto;
    max-height: 90dvh;
    overflow: hidden;
    border-radius: 22px;
    background: #fff;
    box-shadow: 0 24px 80px rgba(15, 23, 42, .28);
}

.member-dialog-header,
.member-dialog-footer {
    background: #fff;
    z-index: 2;
}

.member-dialog-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 20px;
    border-bottom: 1px solid #e2e8f0;
}

.member-dialog-body {
    min-height: 0;
    overflow-y: auto;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
    padding: 18px 20px;
}

.member-dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 14px 20px;
    border-top: 1px solid #e2e8f0;
    box-shadow: 0 -8px 24px rgba(15, 23, 42, .04);
}

.member-dialog-close {
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    flex: 0 0 auto;
    border: 1px solid #e2e8f0;
    border-radius: 11px;
    background: #fff;
    color: #64748b;
}

@media (max-width: 640px) {
    .member-dialog {
        width: calc(100vw - 16px);
        max-width: calc(100vw - 16px);
        max-height: calc(100dvh - 16px);
    }

    .member-dialog-form {
        max-height: calc(100dvh - 16px);
    }

    .member-dialog-header {
        padding: 16px;
    }

    .member-dialog-body {
        padding: 16px;
    }

    .member-dialog-footer {
        padding: 12px 16px;
    }

    .member-dialog-footer > button {
        flex: 1 1 0;
    }
}

/* When the mobile browser opens its keyboard, keep enough bottom space
   inside the scrolling region so the last field can be brought above it. */
@media (max-height: 650px) {
    .member-dialog-body {
        padding-bottom: 28px;
    }
}
</style>

@endsection
