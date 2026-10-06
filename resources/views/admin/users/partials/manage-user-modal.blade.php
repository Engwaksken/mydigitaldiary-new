@php
    $isSelf = (int) auth()->id() === (int) $managedUser->id;

    $isSuspended =
        $accountStatus === 'suspended'
        || (method_exists($managedUser, 'isSuspended') && $managedUser->isSuspended());

    $roleValue = strtolower((string) $displayRole);
@endphp

<dialog
    id="manage-user-modal-{{ $managedUser->id }}"
    class="admin-manage-dialog shadow-2xl"
>
    <div class="admin-manage-scroll bg-white">
        <div class="sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-slate-100 bg-white px-4 py-4 sm:px-5">
            <div class="min-w-0">
                <h2 class="truncate text-lg font-black text-slate-900">
                    Manage User
                </h2>

                <p class="truncate text-xs text-slate-500">
                    {{ $managedUser->name }} · {{ $managedUser->email }}
                </p>
            </div>

            <button
                type="button"
                class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-slate-500 hover:bg-slate-100"
                onclick="document.getElementById('manage-user-modal-{{ $managedUser->id }}').close()"
                aria-label="Close"
            >
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <div
            class="admin-user-tabs border-b border-slate-200 px-4 pt-3 sm:px-5"
            role="tablist"
            aria-label="Manage {{ $managedUser->name }}"
        >
            <button
                type="button"
                class="admin-user-tab border-b-2 border-[var(--brand-1)] px-3 py-2.5 text-xs font-bold text-[var(--brand-1)]"
                data-admin-user-tab="subscription"
                data-admin-user-id="{{ $managedUser->id }}"
                onclick="pmSelectAdminUserTab({{ $managedUser->id }}, 'subscription')"
            >
                <i class="fa-solid fa-credit-card mr-1"></i>
                Subscription
            </button>

            <button
                type="button"
                class="admin-user-tab border-b-2 border-transparent px-3 py-2.5 text-xs font-bold text-slate-500"
                data-admin-user-tab="role"
                data-admin-user-id="{{ $managedUser->id }}"
                onclick="pmSelectAdminUserTab({{ $managedUser->id }}, 'role')"
            >
                <i class="fa-solid fa-user-shield mr-1"></i>
                Role
            </button>

            <button
                type="button"
                class="admin-user-tab border-b-2 border-transparent px-3 py-2.5 text-xs font-bold text-slate-500"
                data-admin-user-tab="access"
                data-admin-user-id="{{ $managedUser->id }}"
                onclick="pmSelectAdminUserTab({{ $managedUser->id }}, 'access')"
            >
                <i class="fa-solid fa-user-lock mr-1"></i>
                Access
            </button>

            <button
                type="button"
                class="admin-user-tab border-b-2 border-transparent px-3 py-2.5 text-xs font-bold text-slate-500"
                data-admin-user-tab="more"
                data-admin-user-id="{{ $managedUser->id }}"
                onclick="pmSelectAdminUserTab({{ $managedUser->id }}, 'more')"
            >
                <i class="fa-solid fa-ellipsis mr-1"></i>
                More
            </button>
        </div>

        <div class="p-4 sm:p-5">
            {{-- SUBSCRIPTION --}}
            <section
                class="admin-user-panel"
                data-admin-user-panel="subscription"
                data-admin-user-id="{{ $managedUser->id }}"
            >
                <div class="mb-4">
                    <h3 class="font-black text-slate-900">Subscription</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Change plan, status, dates and trial information.
                    </p>
                </div>

                <form
                    method="POST"
                    action="{{ route('admin.users.subscription.update', $managedUser) }}"
                    class="space-y-4"
                >
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="_managed_user_id" value="{{ $managedUser->id }}">

                    <div class="admin-user-form-grid grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-bold text-slate-700">
                                Subscription Status
                            </label>

                            <select
                                name="subscription_status"
                                class="pm-input mt-1 w-full"
                                required
                            >
                                @foreach(['active','trial','inactive','expired','suspended','cancelled'] as $status)
                                    <option
                                        value="{{ $status }}"
                                        @selected(((string) old('_managed_user_id') === (string) $managedUser->id ? old('subscription_status', $canonicalStatus) : $canonicalStatus) === $status)
                                    >
                                        {{ ucfirst($status) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-slate-700">
                                Plan
                            </label>

                            <select
                                name="subscription_plan_id"
                                class="pm-input mt-1 w-full"
                            >
                                <option value="">
                                    {{ $plans->isEmpty() ? 'No subscription plans configured' : 'No plan / unassigned' }}
                                </option>

                                @foreach($plans as $plan)
                                    <option
                                        value="{{ $plan->id }}"
                                        @selected((string) ((string) old('_managed_user_id') === (string) $managedUser->id ? old('subscription_plan_id', $managedUser->subscription_plan_id) : $managedUser->subscription_plan_id) === (string) $plan->id)
                                    >
                                        {{ $plan->name ?? $plan->title ?? ('Plan #'.$plan->id) }}
                                    </option>
                                @endforeach
                                @if ($managedUser->subscriptionPlan && ! $plans->contains('id', $managedUser->subscription_plan_id))
                                    <option value="{{ $managedUser->subscription_plan_id }}" @selected((string) ((string) old('_managed_user_id') === (string) $managedUser->id ? old('subscription_plan_id', $managedUser->subscription_plan_id) : $managedUser->subscription_plan_id) === (string) $managedUser->subscription_plan_id)>
                                        {{ $managedUser->subscriptionPlan->name }} (current plan)
                                    </option>
                                @endif
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-slate-700">
                                Subscription Starts
                            </label>

                            <input
                                type="date"
                                name="subscription_started_at"
                                value="{{ (string) old('_managed_user_id') === (string) $managedUser->id ? old('subscription_started_at', $managedUser->subscription_started_at ? \Illuminate\Support\Carbon::parse($managedUser->subscription_started_at)->format('Y-m-d') : '') : ($managedUser->subscription_started_at ? \Illuminate\Support\Carbon::parse($managedUser->subscription_started_at)->format('Y-m-d') : '') }}"
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
                                value="{{ (string) old('_managed_user_id') === (string) $managedUser->id ? old('subscription_expires_at', optional($managedUser->subscription_expires_at)->format('Y-m-d')) : optional($managedUser->subscription_expires_at)->format('Y-m-d') }}"
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
                                value="{{ (string) old('_managed_user_id') === (string) $managedUser->id ? old('trial_ends_at', optional($managedUser->trial_ends_at)->format('Y-m-d')) : optional($managedUser->trial_ends_at)->format('Y-m-d') }}"
                                class="pm-input mt-1 w-full"
                            >

                            <p class="mt-1 text-[11px] text-slate-500">
                                Selecting Active should close the free trial.
                            </p>
                        </div>
                    </div>

                    <button class="btn-primary inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-white sm:w-auto">
                        <i class="fa-solid fa-floppy-disk"></i>
                        Save Subscription
                    </button>
                </form>
            </section>

            {{-- ROLE --}}
            <section
                class="admin-user-panel"
                data-admin-user-panel="role"
                data-admin-user-id="{{ $managedUser->id }}"
                hidden
            >
                <div class="mb-4">
                    <h3 class="font-black text-slate-900">System Role</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Control the user's system-wide administration permissions.
                    </p>
                </div>

                <form
                    method="POST"
                    action="{{ route('admin.users.role', $managedUser) }}"
                    class="space-y-3"
                >
                    @csrf

                    <div>
                        <label class="text-xs font-bold text-slate-700">
                            Role
                        </label>

                        <select
                            name="role"
                            class="pm-input mt-1 w-full"
                            {{ $isSelf ? 'disabled' : '' }}
                        >
                            @foreach($availableRoles as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected($roleValue === $value)
                                >
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if($isSelf)
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                            You cannot change your own role from this screen.
                        </div>
                    @else
                        <button class="btn-primary inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-white sm:w-auto">
                            <i class="fa-solid fa-user-shield"></i>
                            Update Role
                        </button>
                    @endif
                </form>
            </section>

            {{-- ACCESS --}}
            <section
                class="admin-user-panel"
                data-admin-user-panel="access"
                data-admin-user-id="{{ $managedUser->id }}"
                hidden
            >
                <div class="mb-4">
                    <h3 class="font-black text-slate-900">Account Access</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Suspend or reactivate access without deleting the user's data.
                    </p>
                </div>

                @if($isSelf)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        You cannot suspend your own currently logged-in account.
                    </div>
                @elseif($isSuspended)
                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
                        This account is currently suspended.
                    </div>

                    <form
                        method="POST"
                        action="{{ route('admin.users.unsuspend', $managedUser) }}"
                        class="mt-3"
                    >
                        @csrf

                        <button class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white sm:w-auto">
                            <i class="fa-solid fa-user-check"></i>
                            Reactivate User
                        </button>
                    </form>
                @else
                    <form
                        method="POST"
                        action="{{ route('admin.users.suspend', $managedUser) }}"
                        class="space-y-3"
                    >
                        @csrf

                        <div>
                            <label class="text-xs font-bold text-slate-700">
                                Suspension reason
                            </label>

                            <textarea
                                name="reason"
                                rows="3"
                                class="pm-input mt-1 w-full"
                                placeholder="Optional reason shown in the administrative record"
                            ></textarea>
                        </div>

                        <button class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-bold text-white sm:w-auto">
                            <i class="fa-solid fa-user-lock"></i>
                            Suspend User
                        </button>
                    </form>
                @endif
            </section>

            {{-- MORE --}}
            <section
                class="admin-user-panel"
                data-admin-user-panel="more"
                data-admin-user-id="{{ $managedUser->id }}"
                hidden
            >
                <div class="space-y-3">
                    @if(\Illuminate\Support\Facades\Route::has('admin.users.show'))
                        <a
                            href="{{ route('admin.users.show', $managedUser) }}"
                            class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm font-bold text-slate-700 hover:bg-slate-50"
                        >
                            <i class="fa-solid fa-eye text-sky-600"></i>
                            View Full User Details
                        </a>
                    @endif

                    @if(!$isSelf)
                        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                            <h3 class="font-black text-rose-700">
                                Delete User
                            </h3>

                            <p class="mt-1 text-xs leading-5 text-rose-600">
                                Permanently removes the account. Type DELETE to confirm.
                            </p>

                            <form
                                method="POST"
                                action="{{ route('admin.users.destroy', $managedUser) }}"
                                class="mt-3 space-y-2"
                                onsubmit="return this.confirmation.value === 'DELETE';"
                            >
                                @csrf
                                @method('DELETE')

                                <input
                                    name="confirmation"
                                    autocomplete="off"
                                    placeholder="Type DELETE"
                                    class="pm-input w-full border-rose-200"
                                    required
                                >

                                <button class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white sm:w-auto">
                                    <i class="fa-solid fa-trash-can"></i>
                                    Delete User
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                            Your own account cannot be deleted from this administrative list.
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</dialog>
