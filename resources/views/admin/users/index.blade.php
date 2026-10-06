@extends('layouts.app')

@section('title', 'Users')

@section('content')
@php
    /*
    |--------------------------------------------------------------------------
    | Users statistics
    |--------------------------------------------------------------------------
    |
    | Use controller-provided $userStats when available.
    | Otherwise calculate directly from users so cards never display fake 0s
    | just because an older AdminUserController@index is still deployed.
    |
    */
    $plans = isset($plans) ? collect($plans) : collect();

    if (! isset($userStats) || ! is_array($userStats)) {
        $statsQuery = \App\Models\User::query();

        $userStats = [
            'total' => (clone $statsQuery)->count(),
            'active' => 0,
            'trial' => 0,
            'expired_inactive' => 0,
            'suspended' => 0,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'subscription_status')) {
            $subscriptionCounts = \App\Models\User::query()
                ->selectRaw(
                    "LOWER(TRIM(COALESCE(subscription_status, ''))) AS subscription_state, COUNT(*) AS total"
                )
                ->groupBy('subscription_state')
                ->pluck('total', 'subscription_state');

            $userStats['active'] =
                (int) ($subscriptionCounts['active'] ?? 0);

            $userStats['trial'] =
                (int) ($subscriptionCounts['trial'] ?? 0)
                + (int) ($subscriptionCounts['trialing'] ?? 0);

            $userStats['expired_inactive'] =
                (int) ($subscriptionCounts['expired'] ?? 0)
                + (int) ($subscriptionCounts['inactive'] ?? 0)
                + (int) ($subscriptionCounts['cancelled'] ?? 0)
                + (int) ($subscriptionCounts['canceled'] ?? 0);
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_suspended')) {
            $userStats['suspended'] = \App\Models\User::query()
                ->where('is_suspended', true)
                ->count();
        } elseif (\Illuminate\Support\Facades\Schema::hasColumn('users', 'suspended')) {
            $userStats['suspended'] = \App\Models\User::query()
                ->where('suspended', true)
                ->count();
        } elseif (\Illuminate\Support\Facades\Schema::hasColumn('users', 'subscription_status')) {
            $userStats['suspended'] = \App\Models\User::query()
                ->whereRaw(
                    "LOWER(TRIM(COALESCE(subscription_status, ''))) = 'suspended'"
                )
                ->count();
        }
    }

    $userStats = array_merge([
        'total' => 0,
        'active' => 0,
        'trial' => 0,
        'expired_inactive' => 0,
        'suspended' => 0,
    ], $userStats);
@endphp
<div class="space-y-4 max-w-7xl">
    @if(session('success'))
        <x-alert type="success" :message="session('success')" :dismissible="false" :autoDismiss="false" />
    @endif

    @if($errors->any())
        <x-alert type="error" :dismissible="false" :autoDismiss="false">
            <div class="font-black">The requested user update could not be saved.</div>
            <ul class="mt-1 list-disc space-y-1 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif
    <section class="apple-surface rounded-2xl p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-xs font-black uppercase tracking-[.12em] text-slate-400">
                    Administration
                </div>
                <h1 class="mt-1 text-2xl font-black">Users & Subscriptions</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Create users and staff accounts, manage roles, subscriptions and account access.
                </p>
            </div>

            <a
                href="{{ route('admin.users.create') }}"
                class="btn-primary inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-white sm:w-auto"
            >
                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                Add New User
            </a>

            <button
                type="button"
                id="admin-bulk-open"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 sm:w-auto"
                onclick="pmOpenBulkUserDialog()"
                disabled
            >
                <i class="fa-solid fa-users-gear" aria-hidden="true"></i>
                Bulk Manage
                <span
                    id="admin-bulk-count"
                    class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black"
                >0</span>
            </button>
        </div>

        <form method="GET" class="mt-4 grid gap-3 sm:grid-cols-[1fr_190px_auto]">
            <input name="search"
                   value="{{ request('search') }}"
                   class="pm-input w-full"
                   placeholder="Search name or email">

            <select name="subscription_status" class="pm-input w-full">
                <option value="">All subscriptions</option>
                @foreach(['active','trial','inactive','expired','suspended','cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('subscription_status') === $status)>
                        {{ ucfirst($status) }}
                    </option>
                @endforeach
            </select>

            <button class="apple-btn rounded-xl px-4 py-2 text-sm font-bold">
                Filter
            </button>
        </form>
    </section>


    {{-- =========================================================
         USER STATISTICS — same wide/accent card pattern as
         other My Digital Diary pages
    ========================================================== --}}
    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">

        <div class="relative overflow-hidden rounded-3xl border border-slate-100 bg-white px-5 py-4 shadow-sm">
            <span class="absolute inset-y-0 left-0 w-1 bg-lime-400"></span>
            <div class="flex min-h-[58px] items-center gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-lime-50 text-lime-600">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Total Users
                    </div>
                    <div class="mt-0.5 text-xl font-black text-slate-900">
                        {{ number_format((int) $userStats['total']) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-3xl border border-slate-100 bg-white px-5 py-4 shadow-sm">
            <span class="absolute inset-y-0 left-0 w-1 bg-emerald-400"></span>
            <div class="flex min-h-[58px] items-center gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Active
                    </div>
                    <div class="mt-0.5 text-xl font-black text-slate-900">
                        {{ number_format((int) $userStats['active']) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-3xl border border-slate-100 bg-white px-5 py-4 shadow-sm">
            <span class="absolute inset-y-0 left-0 w-1 bg-amber-400"></span>
            <div class="flex min-h-[58px] items-center gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Trial
                    </div>
                    <div class="mt-0.5 text-xl font-black text-slate-900">
                        {{ number_format((int) $userStats['trial']) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-3xl border border-slate-100 bg-white px-5 py-4 shadow-sm">
            <span class="absolute inset-y-0 left-0 w-1 bg-rose-400"></span>
            <div class="flex min-h-[58px] items-center gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                    <i class="fa-solid fa-user-clock"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Expired / Inactive
                    </div>
                    <div class="mt-0.5 text-xl font-black text-slate-900">
                        {{ number_format((int) $userStats['expired_inactive']) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-3xl border border-slate-100 bg-white px-5 py-4 shadow-sm">
            <span class="absolute inset-y-0 left-0 w-1 bg-violet-400"></span>
            <div class="flex min-h-[58px] items-center gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                    <i class="fa-solid fa-user-lock"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Suspended
                    </div>
                    <div class="mt-0.5 text-xl font-black text-slate-900">
                        {{ number_format((int) $userStats['suspended']) }}
                    </div>
                </div>
            </div>
        </div>

    </section>

    <section class="apple-surface rounded-2xl overflow-hidden" id="admin-user-list">
        <style>
            #admin-user-list,
            #admin-user-list * {
                box-sizing: border-box;
            }

            #admin-user-list .admin-user-table-wrap {
                width: 100%;
                max-width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            #admin-user-list table {
                min-width: 1120px;
                width: 100%;
            }

            #admin-user-list th,
            #admin-user-list td {
                white-space: nowrap !important;
                word-break: normal !important;
                overflow-wrap: normal !important;
                writing-mode: horizontal-tb !important;
            }

            #admin-user-list .admin-user-mobile-list {
                display: none;
            }

            #admin-user-list .admin-action-menu {
                min-width: 220px;
            }

            #admin-user-list .admin-action-menu a,
            #admin-user-list .admin-action-menu button {
                width: 100%;
                display: flex;
                align-items: center;
                gap: .55rem;
                padding: .6rem .75rem;
                border-radius: .65rem;
                text-align: left;
                font-size: .78rem;
                font-weight: 700;
                white-space: nowrap !important;
            }

            #admin-user-list .admin-action-menu a:hover,
            #admin-user-list .admin-action-menu button:hover {
                background: #f8fafc;
            }

            .admin-manage-dialog {
                width: min(94vw, 720px);
                max-width: 720px;
                max-height: calc(100dvh - 24px);
                padding: 0 !important;
                overflow: hidden;
                border: 0;
                border-radius: 1rem;
            }

            .admin-manage-dialog::backdrop {
                background: rgba(15, 23, 42, .55);
            }

            .admin-manage-dialog .admin-manage-scroll {
                max-height: calc(100dvh - 24px);
                overflow-y: auto;
                overflow-x: hidden;
            }

            .admin-user-tabs {
                display: flex !important;
                flex-wrap: nowrap !important;
                gap: .25rem !important;
                overflow-x: auto !important;
                overflow-y: hidden !important;
                white-space: nowrap !important;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
            }

            .admin-user-tab {
                flex: 0 0 auto !important;
                min-width: max-content !important;
                white-space: nowrap !important;
                word-break: normal !important;
                writing-mode: horizontal-tb !important;
            }

            .admin-user-panel[hidden] {
                display: none !important;
            }

            @media (max-width: 767px) {
                #admin-user-list .admin-user-desktop {
                    display: none;
                }

                #admin-user-list .admin-user-mobile-list {
                    display: grid;
                    grid-template-columns: 1fr;
                    gap: .75rem;
                    padding: .75rem;
                }

                .admin-manage-dialog {
                    width: calc(100vw - 16px);
                    max-width: calc(100vw - 16px);
                    max-height: calc(100dvh - 16px);
                }

                .admin-manage-dialog .admin-manage-scroll {
                    max-height: calc(100dvh - 16px);
                }

                .admin-manage-dialog .admin-user-form-grid {
                    grid-template-columns: 1fr !important;
                }

                .admin-manage-dialog input,
                .admin-manage-dialog select,
                .admin-manage-dialog textarea,
                .admin-manage-dialog button {
                    min-width: 0 !important;
                    max-width: 100% !important;
                }
            }
        </style>

        {{-- DESKTOP TABLE --}}
        <div class="admin-user-desktop admin-user-table-wrap">
            <table class="text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="w-10 px-4 py-3 text-left">
                            <input
                                id="admin-users-select-all"
                                type="checkbox"
                                aria-label="Select all users on this page"
                                onchange="pmToggleAllUsers(this.checked)"
                            >
                        </th>
                        <th class="px-4 py-3 text-left">User</th>
                        <th class="px-4 py-3 text-left">Role</th>
                        <th class="px-4 py-3 text-left">Subscription</th>
                        <th class="px-4 py-3 text-left">Current Status</th>
                        <th class="px-4 py-3 text-left">Subscription Expiry</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)
                        @php
                            $canonicalStatus = strtolower(
                                trim((string) ($user->subscription_status ?? 'trial'))
                            );

                            $canonicalStatus = match ($canonicalStatus) {
                                'trialing' => 'trial',
                                'canceled' => 'cancelled',
                                default => $canonicalStatus,
                            };

                            $statusClass = match ($canonicalStatus) {
                                'active' => 'bg-emerald-50 text-emerald-700',
                                'trial' => 'bg-amber-50 text-amber-700',
                                'suspended' => 'bg-rose-50 text-rose-700',
                                'expired', 'inactive', 'cancelled' => 'bg-slate-100 text-slate-600',
                                default => 'bg-slate-100 text-slate-600',
                            };

                            $accountStatus = strtolower(
                                trim((string) ($user->account_status ?? 'active'))
                            );

                            $displayRole = $user->system_role
                                ?? $user->role
                                ?? ($user->isAdmin() ? 'admin' : 'user');
                        @endphp

                        <tr>
                            <td class="px-4 py-4 align-top">
                                <input
                                    type="checkbox"
                                    class="admin-user-selector"
                                    value="{{ $user->id }}"
                                    data-user-name="{{ $user->name }}"
                                    aria-label="Select {{ $user->name }}"
                                    onchange="pmUpdateBulkSelection()"
                                >
                            </td>

                            <td class="px-4 py-4 align-top">
                                <div class="font-bold text-slate-900">{{ $user->name }}</div>
                                <div class="text-xs text-slate-500">{{ $user->email }}</div>
                                <div class="mt-1 text-[10px] text-slate-400">ID #{{ $user->id }}</div>
                            </td>

                            <td class="px-4 py-4 align-top">
                                <span class="inline-flex rounded-full bg-sky-50 px-2.5 py-1 text-[10px] font-black uppercase text-sky-700">
                                    {{ str_replace('_', ' ', $displayRole) }}
                                </span>
                            </td>

                            <td class="px-4 py-4 align-top">
                                <div class="font-semibold text-slate-700">
                                    {{ $user->subscriptionPlan?->name ?? 'No plan' }}
                                </div>
                            </td>

                            <td class="px-4 py-4 align-top">
                                <div class="flex flex-wrap gap-1.5">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase {{ $statusClass }}">
                                        {{ $canonicalStatus }}
                                    </span>

                                    @if($accountStatus === 'suspended' || method_exists($user, 'isSuspended') && $user->isSuspended())
                                        <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-[10px] font-black uppercase text-rose-700">
                                            Account suspended
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <td class="px-4 py-4 align-top text-xs text-slate-600">
                                @if($user->subscription_expires_at)
                                    {{ \Illuminate\Support\Carbon::parse($user->subscription_expires_at)->format('d M Y') }}
                                @else
                                    —
                                @endif
                            </td>

                            <td class="px-4 py-4 align-top text-right">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                                    onclick="document.getElementById('manage-user-modal-{{ $user->id }}').showModal()"
                                >
                                    <i class="fa-solid fa-user-gear text-[var(--brand-1)]" aria-hidden="true"></i>
                                    Manage User
                                </button>
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-400">
                                No users found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- MOBILE CARDS --}}
        <div class="admin-user-mobile-list">
            @forelse($users as $user)
                @php
                    $canonicalStatus = strtolower(
                        trim((string) ($user->subscription_status ?? 'trial'))
                    );

                    $canonicalStatus = match ($canonicalStatus) {
                        'trialing' => 'trial',
                        'canceled' => 'cancelled',
                        default => $canonicalStatus,
                    };

                    $accountStatus = strtolower(
                        trim((string) ($user->account_status ?? 'active'))
                    );

                    $displayRole = $user->system_role
                        ?? $user->role
                        ?? ($user->isAdmin() ? 'admin' : 'user');
                @endphp

                <article class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-600">
                            <input
                                type="checkbox"
                                class="admin-user-selector"
                                value="{{ $user->id }}"
                                data-user-name="{{ $user->name }}"
                                onchange="pmUpdateBulkSelection()"
                            >
                            Select
                        </label>

                        <span class="text-[10px] font-black uppercase tracking-wide text-slate-400">
                            ID #{{ $user->id }}
                        </span>
                    </div>

                    <div class="flex min-w-0 items-start gap-3">
                        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-slate-50 text-slate-600">
                            <i class="fa-solid fa-user"></i>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate font-black text-slate-900">{{ $user->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-xl bg-slate-50 p-2.5">
                            <span class="text-slate-400">Role</span>
                            <p class="mt-0.5 truncate font-bold text-slate-700">
                                {{ ucfirst(str_replace('_', ' ', $displayRole)) }}
                            </p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-2.5">
                            <span class="text-slate-400">Account</span>
                            <p class="mt-0.5 truncate font-bold text-slate-700">
                                {{ ucfirst($accountStatus) }}
                            </p>
                        </div>

                        <div class="col-span-2 rounded-xl bg-slate-50 p-2.5">
                            <span class="text-slate-400">Subscription</span>
                            <p class="mt-0.5 truncate font-bold text-slate-700">
                                {{ $user->subscriptionPlan?->name ?? 'No plan' }}
                                · {{ ucfirst($canonicalStatus) }}
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="btn-primary mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-white"
                        onclick="document.getElementById('manage-user-modal-{{ $user->id }}').showModal()"
                    >
                        <i class="fa-solid fa-user-gear" aria-hidden="true"></i>
                        Manage User
                    </button>

                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
                    No users found.
                </div>
            @endforelse
        </div>

        <div class="p-4">
            {{ $users->links() }}
        </div>
    </section>

{{-- One dialog per user, outside both responsive lists and the table markup. --}}
@foreach ($users as $user)
    @include('admin.users.partials.manage-user-modal', [
        'managedUser' => $user,
        'plans' => $plans,
        'canonicalStatus' => match ($user->subscription_status) {
            'trialing' => 'trial',
            'canceled' => 'cancelled',
            default => $user->subscription_status ?? 'trial',
        },
        'accountStatus' => $user->account_status ?? 'active',
        'displayRole' => $user->system_role ?? $user->role ?? ($user->isAdmin() ? 'admin' : 'user'),
        'availableRoles' => $availableRoles,
    ])
@endforeach

<dialog
    id="admin-bulk-user-modal"
    class="admin-manage-dialog shadow-2xl"
>
    <div class="admin-manage-scroll bg-white">
        <div class="sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-slate-100 bg-white px-4 py-4 sm:px-5">
            <div class="min-w-0">
                <h2 class="text-lg font-black text-slate-900">
                    Bulk Manage Users
                </h2>

                <p class="mt-1 text-xs text-slate-500">
                    <span id="admin-bulk-dialog-count">0</span>
                    selected user(s)
                </p>
            </div>

            <button
                type="button"
                class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-slate-500 hover:bg-slate-100"
                onclick="document.getElementById('admin-bulk-user-modal').close()"
                aria-label="Close"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form
            method="POST"
            action="{{ route('admin.users.bulk') }}"
            id="admin-bulk-user-form"
            class="space-y-5 p-4 sm:p-5"
            onsubmit="return pmValidateBulkUserForm(this);"
        >
            @csrf

            <div id="admin-bulk-hidden-ids"></div>

            <div>
                <label class="text-xs font-bold text-slate-700">
                    Bulk Action
                </label>

                <select
                    name="action"
                    id="admin-bulk-action"
                    class="pm-input mt-1 w-full"
                    required
                    onchange="pmShowBulkActionFields(this.value)"
                >
                    <option value="">Choose an action</option>
                    <option value="role">Change Role</option>
                    <option value="subscription">Update Subscription</option>
                    <option value="suspend">Suspend Users</option>
                    <option value="reactivate">Reactivate Users</option>
                    <option value="delete">Delete Users</option>
                </select>
            </div>

            <section
                id="admin-bulk-role-fields"
                class="admin-bulk-action-fields"
                hidden
            >
                <label class="text-xs font-bold text-slate-700">
                    New Role
                </label>

                <select name="role" class="pm-input mt-1 w-full">
                    @foreach($availableRoles as $value => $label)
                        <option value="{{ $value }}">
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </section>

            <section
                id="admin-bulk-subscription-fields"
                class="admin-bulk-action-fields"
                hidden
            >
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-xs font-bold text-slate-700">
                            Plan
                        </label>

                        <select
                            name="subscription_plan_id"
                            class="pm-input mt-1 w-full"
                        >
                            <option value="">
                                Leave unchanged
                            </option>

                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}">
                                    {{ $plan->name ?? $plan->title ?? ('Plan #'.$plan->id) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-700">
                            Status
                        </label>

                        <select
                            name="subscription_status"
                            class="pm-input mt-1 w-full"
                        >
                            <option value="">
                                Leave unchanged
                            </option>
                            <option value="active">Active</option>
                            <option value="trial">Trial</option>
                            <option value="inactive">Inactive</option>
                            <option value="expired">Expired</option>
                            <option value="suspended">Suspended</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-700">
                            Start Date
                        </label>

                        <input
                            type="date"
                            name="subscription_started_at"
                            class="pm-input mt-1 w-full"
                        >
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-700">
                            Expiry Date
                        </label>

                        <input
                            type="date"
                            name="subscription_expires_at"
                            class="pm-input mt-1 w-full"
                        >
                    </div>

                    <div class="sm:col-span-2">
                        <label class="text-xs font-bold text-slate-700">
                            Trial End Date
                        </label>

                        <input
                            type="date"
                            name="trial_ends_at"
                            class="pm-input mt-1 w-full"
                        >
                    </div>
                </div>
            </section>

            <section
                id="admin-bulk-suspend-fields"
                class="admin-bulk-action-fields"
                hidden
            >
                <label class="text-xs font-bold text-slate-700">
                    Suspension Reason
                </label>

                <textarea
                    name="reason"
                    rows="3"
                    class="pm-input mt-1 w-full"
                    placeholder="Optional reason for these accounts"
                ></textarea>
            </section>

            <section
                id="admin-bulk-reactivate-fields"
                class="admin-bulk-action-fields"
                hidden
            >
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                    The selected accounts will be reactivated.
                </div>
            </section>

            <section
                id="admin-bulk-delete-fields"
                class="admin-bulk-action-fields"
                hidden
            >
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                    <h3 class="font-black text-rose-700">
                        Delete Selected Users
                    </h3>

                    <p class="mt-1 text-xs leading-5 text-rose-600">
                        This action can remove multiple accounts.
                        Type <strong>DELETE</strong> to confirm.
                    </p>

                    <input
                        type="text"
                        name="confirmation"
                        autocomplete="off"
                        placeholder="Type DELETE"
                        class="pm-input mt-3 w-full border-rose-200"
                    >
                </div>
            </section>

            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700"
                    onclick="document.getElementById('admin-bulk-user-modal').close()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn-primary inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-white"
                >
                    <i class="fa-solid fa-check"></i>
                    Apply to Selected Users
                </button>
            </div>
        </form>
    </div>
</dialog>

<script>
    function pmSelectAdminUserTab(userId, tabName) {
        document
            .querySelectorAll(
                '[data-admin-user-tab][data-admin-user-id="' + userId + '"]'
            )
            .forEach(function (button) {
                var selected =
                    button.getAttribute('data-admin-user-tab') === tabName;

                button.classList.toggle(
                    'border-[var(--brand-1)]',
                    selected
                );

                button.classList.toggle(
                    'text-[var(--brand-1)]',
                    selected
                );

                button.classList.toggle(
                    'border-transparent',
                    !selected
                );

                button.classList.toggle(
                    'text-slate-500',
                    !selected
                );

                if (
                    selected
                    && typeof button.scrollIntoView === 'function'
                ) {
                    button.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest',
                        inline: 'center'
                    });
                }
            });

        document
            .querySelectorAll(
                '[data-admin-user-panel][data-admin-user-id="' + userId + '"]'
            )
            .forEach(function (panel) {
                panel.hidden =
                    panel.getAttribute('data-admin-user-panel') !== tabName;
            });
    }


    function pmSelectedUserCheckboxes() {
        return Array.from(
            document.querySelectorAll('.admin-user-selector:checked')
        );
    }

    function pmUpdateBulkSelection() {
        var count = pmUniqueSelectedUserIds().length;
        var button = document.getElementById('admin-bulk-open');
        var badge = document.getElementById('admin-bulk-count');
        var dialogCount = document.getElementById('admin-bulk-dialog-count');
        var selectAll = document.getElementById('admin-users-select-all');
        var allDesktop = Array.from(
            document.querySelectorAll(
                '.admin-user-desktop .admin-user-selector'
            )
        );

        if (badge) {
            badge.textContent = String(count);
        }

        if (dialogCount) {
            dialogCount.textContent = String(count);
        }

        if (button) {
            button.disabled = count === 0;
            button.classList.toggle('opacity-50', count === 0);
            button.classList.toggle('cursor-not-allowed', count === 0);
        }

        if (selectAll && allDesktop.length) {
            var selectedDesktop = allDesktop.filter(
                function (checkbox) {
                    return checkbox.checked;
                }
            ).length;

            selectAll.checked =
                selectedDesktop === allDesktop.length;

            selectAll.indeterminate =
                selectedDesktop > 0
                && selectedDesktop < allDesktop.length;
        }
    }

    function pmToggleAllUsers(checked) {
        document
            .querySelectorAll(
                '.admin-user-desktop .admin-user-selector'
            )
            .forEach(function (checkbox) {
                checkbox.checked = checked;
            });

        pmSyncMobileUserSelectors();
        pmUpdateBulkSelection();
    }

    function pmSyncMobileUserSelectors() {
        var selectedIds = new Set(
            Array.from(
                document.querySelectorAll(
                    '.admin-user-desktop .admin-user-selector:checked'
                )
            ).map(function (checkbox) {
                return checkbox.value;
            })
        );

        document
            .querySelectorAll(
                '.admin-user-mobile-list .admin-user-selector'
            )
            .forEach(function (checkbox) {
                checkbox.checked = selectedIds.has(checkbox.value);
            });
    }

    function pmUniqueSelectedUserIds() {
        return Array.from(
            new Set(
                pmSelectedUserCheckboxes().map(
                    function (checkbox) {
                        return checkbox.value;
                    }
                )
            )
        );
    }

    function pmOpenBulkUserDialog() {
        var ids = pmUniqueSelectedUserIds();

        if (!ids.length) {
            return;
        }

        var hidden = document.getElementById(
            'admin-bulk-hidden-ids'
        );

        hidden.innerHTML = '';

        ids.forEach(function (id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            hidden.appendChild(input);
        });

        var dialogCount = document.getElementById(
            'admin-bulk-dialog-count'
        );

        if (dialogCount) {
            dialogCount.textContent = String(ids.length);
        }

        document
            .getElementById('admin-bulk-user-modal')
            .showModal();
    }

    function pmShowBulkActionFields(action) {
        document
            .querySelectorAll('.admin-bulk-action-fields')
            .forEach(function (section) {
                section.hidden = true;
            });

        var section = document.getElementById(
            'admin-bulk-' + action + '-fields'
        );

        if (section) {
            section.hidden = false;
        }
    }

    function pmValidateBulkUserForm(form) {
        var ids = pmUniqueSelectedUserIds();
        var action = form.elements.namedItem('action').value;

        if (!ids.length) {
            alert('Select at least one user.');
            return false;
        }

        if (!action) {
            alert('Choose a bulk action.');
            return false;
        }

        if (
            action === 'delete'
            && form.confirmation.value.trim().toUpperCase()
                !== 'DELETE'
        ) {
            alert('Type DELETE to confirm bulk deletion.');
            return false;
        }

        return true;
    }

    document.addEventListener(
        'change',
        function (event) {
            if (
                event.target
                && event.target.classList.contains(
                    'admin-user-selector'
                )
            ) {
                var id = event.target.value;
                var checked = event.target.checked;

                document
                    .querySelectorAll(
                        '.admin-user-selector[value="' + id + '"]'
                    )
                    .forEach(function (checkbox) {
                        checkbox.checked = checked;
                    });

                pmUpdateBulkSelection();
            }
        }
    );

    document.addEventListener(
        'DOMContentLoaded',
        function () {
            pmUpdateBulkSelection();
            @if ($errors->any())
                var managedUserId = {{ \Illuminate\Support\Js::from(old('_managed_user_id')) }};
                if (managedUserId !== null && managedUserId !== '') {
                    var dialog = document.getElementById('manage-user-modal-' + managedUserId);
                    if (dialog && typeof dialog.showModal === 'function') {
                        if (!dialog.open) dialog.showModal();
                        pmSelectAdminUserTab(managedUserId, 'subscription');
                    }
                }
            @endif
        }
    );

</script>

</div>
@endsection
