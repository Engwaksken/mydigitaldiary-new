@extends('layouts.app')

@section('title', 'Add User')

@section('content')
<div id="admin-create-user" class="mx-auto w-full max-w-5xl min-w-0 space-y-4">
    <style>
        #admin-create-user,
        #admin-create-user * {
            box-sizing: border-box;
        }

        #admin-create-user input,
        #admin-create-user select,
        #admin-create-user textarea {
            min-width: 0 !important;
            max-width: 100% !important;
        }

        @media(max-width:767px) {
            #admin-create-user .admin-create-grid {
                grid-template-columns: minmax(0,1fr) !important;
            }

            #admin-create-user .admin-create-actions {
                display:grid !important;
                grid-template-columns:1fr !important;
                width:100% !important;
            }

            #admin-create-user .admin-create-actions > * {
                width:100% !important;
                min-width:0 !important;
                justify-content:center !important;
                text-align:center !important;
            }
        }
    </style>

    <div class="apple-surface rounded-2xl p-4 sm:p-5">
        <div class="flex min-w-0 flex-wrap items-start justify-between gap-3">
            <div class="flex min-w-0 items-start gap-3">
                <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-[var(--brand-1-tint-10)] text-[var(--brand-1)]">
                    <i class="fa-solid fa-user-plus text-xl"></i>
                </div>

                <div class="min-w-0">
                    <p class="text-xs font-black uppercase tracking-widest text-slate-400">
                        Administration
                    </p>

                    <h1 class="mt-1 text-2xl font-black text-slate-900">
                        Add New User
                    </h1>

                    <p class="mt-1 text-sm text-slate-500">
                        Create a normal user or staff account and assign access/subscription settings.
                    </p>
                </div>
            </div>

            <a
                href="{{ route('admin.users.index') }}"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 sm:w-auto"
            >
                <i class="fa-solid fa-arrow-left"></i>
                Back to Users
            </a>
        </div>
    </div>

    @if($errors->any())
        <x-alert type="error" :message="$errors->first()" :dismissible="false" :autoDismiss="false" />
    @endif

    <form
        method="POST"
        action="{{ route('admin.users.store') }}"
        class="apple-surface rounded-2xl p-4 sm:p-6 space-y-6"
    >
        @csrf

        <section>
            <h2 class="font-black text-slate-900">
                Account Information
            </h2>

            <div class="admin-create-grid mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="name" class="text-xs font-bold text-slate-700">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        class="pm-input mt-1 w-full"
                    >
                </div>

                <div>
                    <label for="email" class="text-xs font-bold text-slate-700">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        class="pm-input mt-1 w-full"
                    >
                </div>

                <div>
                    <label for="phone" class="text-xs font-bold text-slate-700">
                        Phone Number
                    </label>

                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        value="{{ old('phone') }}"
                        placeholder="+256..."
                        class="pm-input mt-1 w-full"
                    >
                </div>

                <div>
                    <label for="role" class="text-xs font-bold text-slate-700">
                        System Role
                    </label>

                    <select
                        id="role"
                        name="role"
                        required
                        class="pm-input mt-1 w-full"
                    >
                        @foreach(($availableRoles ?? \App\Http\Controllers\Admin\AdminUserController::availableRoles()) as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected(old('role', 'user') === $value)
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>

                    <p class="mt-1 text-[11px] leading-5 text-slate-500">
                        Support Officer is stored as the internal role <code>support</code>.
                        Finance Officer is stored as <code>finance</code>.
                    </p>
                </div>
            </div>
        </section>

        <section class="border-t border-slate-100 pt-5">
            <h2 class="font-black text-slate-900">
                Login Setup
            </h2>

            <div class="admin-create-grid mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="password" class="text-xs font-bold text-slate-700">
                        Temporary Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        class="pm-input mt-1 w-full"
                    >

                    <p class="mt-1 text-[11px] leading-5 text-slate-500">
                        Optional when password setup email is selected. Minimum 8 characters.
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <input type="hidden" name="send_password_setup" value="0">

                    <label class="flex items-start gap-3">
                        <input
                            type="checkbox"
                            name="send_password_setup"
                            value="1"
                            class="mt-1"
                            @checked(old('send_password_setup', '1') == '1')
                        >

                        <span class="min-w-0">
                            <span class="block font-bold text-slate-800">
                                Send password setup email
                            </span>

                            <span class="mt-1 block text-xs leading-5 text-slate-500">
                                Recommended for staff accounts. The user receives a secure password-reset/setup link.
                            </span>
                        </span>
                    </label>
                </div>
            </div>
        </section>

        <section class="border-t border-slate-100 pt-5">
            <h2 class="font-black text-slate-900">
                Subscription
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                Optional. Staff accounts such as Admin or Support Officer may not require a paid subscription if your access middleware exempts them.
            </p>

            <div class="admin-create-grid mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-xs font-bold text-slate-700">
                        Subscription Plan
                    </label>

                    <select
                        name="subscription_plan_id"
                        class="pm-input mt-1 w-full"
                    >
                        @php
                            $monthlyPlanId = collect($plans ?? [])
                                ->first(function ($plan) {
                                    return strtolower(trim((string) (
                                        $plan->code
                                        ?? $plan->slug
                                        ?? $plan->name
                                        ?? $plan->title
                                        ?? ''
                                    ))) === 'monthly';
                                })?->id;

                            $selectedPlanId = old(
                                'subscription_plan_id',
                                $monthlyPlanId
                            );
                        @endphp

                        @foreach($plans ?? [] as $plan)
                            <option
                                value="{{ $plan->id }}"
                                @selected((string) $selectedPlanId === (string) $plan->id)
                            >
                                {{ $plan->name ?? $plan->title ?? ('Plan #'.$plan->id) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-700">
                        Subscription Status
                    </label>

                    <select
                        name="subscription_status"
                        class="pm-input mt-1 w-full"
                    >
                        @foreach([
                            'trial' => 'Trial',
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                            'expired' => 'Expired',
                            'suspended' => 'Suspended',
                            'cancelled' => 'Cancelled',
                        ] as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected(old('subscription_status', 'trial') === $value)
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-700">
                        Subscription Starts
                    </label>

                    <input
                        type="date"
                        name="subscription_started_at"
                        value="{{ old('subscription_started_at') }}"
                        class="pm-input mt-1 w-full"
                    >
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-700">
                        Subscription Expires
                    </label>

                    <input
                        type="date"
                        name="subscription_expires_at"
                        value="{{ old('subscription_expires_at') }}"
                        class="pm-input mt-1 w-full"
                    >
                </div>

                <div class="sm:col-span-2">
                    <label class="text-xs font-bold text-slate-700">
                        Trial Ends
                    </label>

                    <input
                        type="date"
                        name="trial_ends_at"
                        value="{{ old('trial_ends_at') }}"
                        class="pm-input mt-1 w-full"
                    >
                </div>

                <div class="sm:col-span-2">
                    <label for="transcription_capacity_mb" class="text-xs font-bold text-slate-700">
                        Meeting transcription capacity override (MB, optional)
                    </label>
                    <input
                        id="transcription_capacity_mb"
                        type="number"
                        name="transcription_capacity_mb"
                        min="50"
                        max="10240"
                        step="1"
                        value="{{ old('transcription_capacity_mb') }}"
                        class="pm-input mt-1 w-full"
                        placeholder="Leave blank to use the assigned plan limit"
                    >
                    <p class="mt-1 text-xs text-slate-500">The plan's recording-size limit applies unless you set an override.</p>
                    @error('transcription_capacity_mb')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <div class="admin-create-actions flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-5">
            <a
                href="{{ route('admin.users.index') }}"
                class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold text-slate-700"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="btn-primary inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-bold text-white"
            >
                <i class="fa-solid fa-user-plus"></i>
                Create User
            </button>
        </div>
    </form>
</div>
@endsection
