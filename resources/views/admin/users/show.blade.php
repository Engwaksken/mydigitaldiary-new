@extends('layouts.app')

@section('title', 'Manage ' . $user->name)

@section('content')
    @php
        $isSelf = $user->id === auth()->id();
        $subscriptionHasErrors = $errors->hasAny(['subscription', 'subscription_status', 'subscription_plan_id', 'subscription_started_at', 'subscription_expires_at', 'trial_ends_at']);
    @endphp

    <a href="{{ route('admin.users.index') }}" class="text-sm text-[var(--brand-1)] hover:underline">&larr; All users</a>

    <h1 class="text-2xl font-bold mt-2 mb-6 flex items-center gap-3">
        <span class="w-10 h-10 rounded-xl bg-[var(--brand-1-tint-10)] text-[var(--brand-1)] flex items-center justify-center shadow-sm shrink-0 text-base">
            <i class="fa-solid fa-user-gear" aria-hidden="true"></i>
        </span>
        <span class="text-slate-800 tracking-tight">{{ $user->name }}</span>
    </h1>

    <div class="max-w-xl">
        @if ($errors->any())
            <x-alert type="error" :dismissible="false" :autoDismiss="false">
                <p class="font-semibold">The user update could not be saved.</p>
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif
        {{-- Account info is always visible, never tabbed — same reasoning
             as stat cards elsewhere: a quick-reference summary shouldn't
             be hidden behind a click. --}}
        <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 mb-6">
            <h2 class="font-semibold mb-3">Account</h2>
            <dl class="text-sm space-y-1">
                <div class="flex justify-between"><dt class="text-slate-500">Email</dt><dd>{{ $user->email }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Role</dt><dd>{{ $user->isAdmin() ? 'Admin' : 'User' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Joined</dt><dd>{{ $user->created_at->format('Y-m-d') }}</dd></div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Data consent</dt>
                    <dd>{{ $user->hasGivenDataConsent() ? 'Given ' . $user->data_consent_at->format('Y-m-d') : 'Not recorded' }}</dd>
                </div>
                <div class="flex justify-between"><dt class="text-slate-500">Account status</dt><dd>{{ $user->isSuspended() ? 'Suspended' : 'Active' }}</dd></div>
                <div class="pt-3 mt-3 border-t border-slate-100">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <dt class="text-slate-500">Usage progress</dt>
                        <dd class="font-bold text-slate-700">{{ $usageProgress ?? 0 }}%</dd>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full rounded-full bg-[var(--brand-1)] transition-all duration-500" style="width: {{ (int) ($usageProgress ?? 0) }}%"></div>
                    </div>
                </div>
            </dl>
        </div>

        @if ($isSelf)
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-800">
                This is your own account. Suspension, role, and deletion changes to your own account
                aren't available here — ask another admin, or use your own
                <a href="{{ route('privacy.show') }}" class="underline font-medium">Privacy &amp; Data</a> page.
            </div>
        @else
            <div role="tablist" aria-label="Manage {{ $user->name }}" class="flex gap-1 border-b border-slate-200 mb-6 overflow-x-auto">
                <button type="button" role="tab" id="pm-user-tab-login" aria-controls="pm-user-panel-login"
                        aria-selected="{{ $subscriptionHasErrors ? 'false' : 'true' }}" tabindex="{{ $subscriptionHasErrors ? '-1' : '0' }}" data-tab="login"
                        onclick="pmSelectUserTab('login')" onkeydown="pmUserTabKeydown(event, 'login')"
                        class="pm-user-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap {{ $subscriptionHasErrors ? 'border-transparent text-slate-500' : 'border-[var(--brand-1)] text-[var(--brand-1)]' }}">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                    <span>Login Access</span>
                </button>
                <button type="button" role="tab" id="pm-user-tab-subscription" aria-controls="pm-user-panel-subscription"
                        aria-selected="{{ $subscriptionHasErrors ? 'true' : 'false' }}" tabindex="{{ $subscriptionHasErrors ? '0' : '-1' }}" data-tab="subscription"
                        onclick="pmSelectUserTab('subscription')" onkeydown="pmUserTabKeydown(event, 'subscription')"
                        class="pm-user-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap {{ $subscriptionHasErrors ? 'border-[var(--brand-1)] text-[var(--brand-1)]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                    <i class="fa-solid fa-crown" aria-hidden="true"></i>
                    <span>Subscription</span>
                </button>
                <button type="button" role="tab" id="pm-user-tab-role" aria-controls="pm-user-panel-role"
                        aria-selected="false" tabindex="-1" data-tab="role"
                        onclick="pmSelectUserTab('role')" onkeydown="pmUserTabKeydown(event, 'role')"
                        class="pm-user-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
                    <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                    <span>Role</span>
                </button>
                <button type="button" role="tab" id="pm-user-tab-data" aria-controls="pm-user-panel-data"
                        aria-selected="false" tabindex="-1" data-tab="data"
                        onclick="pmSelectUserTab('data')" onkeydown="pmUserTabKeydown(event, 'data')"
                        class="pm-user-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                    <span>Data Recovery</span>
                </button>
                <button type="button" role="tab" id="pm-user-tab-danger" aria-controls="pm-user-panel-danger"
                        aria-selected="false" tabindex="-1" data-tab="danger"
                        onclick="pmSelectUserTab('danger')" onkeydown="pmUserTabKeydown(event, 'danger')"
                        class="pm-user-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-transparent text-rose-500 hover:text-rose-700 hover:border-rose-300">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <span>Danger Zone</span>
                </button>
            </div>

            <div role="tabpanel" id="pm-user-panel-login" aria-labelledby="pm-user-tab-login" tabindex="0" class="pm-user-panel pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6" @if($subscriptionHasErrors) hidden @endif>
                @if ($user->isSuspended())
                    <p class="text-sm text-slate-600 mb-3">This account is currently suspended and cannot log in.</p>
                    <form method="POST" action="{{ route('admin.users.unsuspend', $user->id) }}">
                        @csrf
                        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                            Reactivate account
                        </button>
                    </form>
                @else
                    <p class="text-sm text-slate-600 mb-3">This account can currently log in normally.</p>
                    <form method="POST" action="{{ route('admin.users.suspend', $user->id) }}"
                          data-confirm="Suspend {{ $user->name }}? They will be unable to log in until reactivated." data-confirm-title="Suspend user?" data-confirm-text="Suspend">
                        @csrf
                        <button type="submit" class="bg-rose-600 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:shadow-md hover:bg-rose-700 transition-all">
                            Suspend account
                        </button>
                    </form>
                @endif
            </div>

            <div role="tabpanel" id="pm-user-panel-subscription" aria-labelledby="pm-user-tab-subscription" tabindex="0" class="pm-user-panel pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6" @unless($subscriptionHasErrors) hidden @endunless>
                <form method="POST" action="{{ route('admin.users.subscription.update', $user) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="_managed_user_id" value="{{ $user->id }}">
                    <div>
                        <label for="subscription_status" class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select id="subscription_status" name="subscription_status"
                                class="pm-input" required>
                            @foreach (['trial' => 'Trial', 'active' => 'Active', 'inactive' => 'Inactive', 'cancelled' => 'Cancelled', 'expired' => 'Expired', 'suspended' => 'Suspended'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('subscription_status', match ($user->subscription_status) { 'trialing' => 'trial', 'canceled' => 'cancelled', default => $user->subscription_status }) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="subscription_plan_id" class="block text-sm font-medium text-slate-700 mb-1">Plan</label>
                        <select id="subscription_plan_id" name="subscription_plan_id" class="pm-input">
                            <option value="">No plan / unassigned</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}" @selected((string) old('subscription_plan_id', $user->subscription_plan_id) === (string) $plan->id)>{{ $plan->name ?? $plan->title ?? ('Plan #'.$plan->id) }}</option>
                            @endforeach
                            @if ($user->subscriptionPlan && ! $plans->contains('id', $user->subscription_plan_id))
                                <option value="{{ $user->subscription_plan_id }}" @selected((string) old('subscription_plan_id', $user->subscription_plan_id) === (string) $user->subscription_plan_id)>
                                    {{ $user->subscriptionPlan->name ?? $user->subscriptionPlan->title ?? ('Plan #'.$user->subscription_plan_id) }} (current plan)
                                </option>
                            @endif
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Choose an enabled plan, keep the current assigned plan, or select No plan to remove the assignment.</p>
                    </div>
                    <div>
                        <label for="subscription_started_at" class="block text-sm font-medium text-slate-700 mb-1">Subscription starts</label>
                        <input type="date" id="subscription_started_at" name="subscription_started_at" value="{{ old('subscription_started_at', $user->subscription_started_at ? \Illuminate\Support\Carbon::parse($user->subscription_started_at)->format('Y-m-d') : '') }}" class="pm-input">
                    </div>
                    <div>
                        <label for="subscription_expires_at" class="block text-sm font-medium text-slate-700 mb-1">Subscription expires</label>
                        <input type="date" id="subscription_expires_at" name="subscription_expires_at" value="{{ old('subscription_expires_at', optional($user->subscription_expires_at)->format('Y-m-d')) }}" class="pm-input">
                    </div>
                    <div>
                        <label for="trial_ends_at" class="block text-sm font-medium text-slate-700 mb-1">Trial ends</label>
                        <input type="date" id="trial_ends_at" name="trial_ends_at" value="{{ old('trial_ends_at', optional($user->trial_ends_at)->format('Y-m-d')) }}" class="pm-input">
                        <p class="text-xs text-slate-500 mt-1">Selecting Active closes the free trial.</p>
                    </div>
                    <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                        Update subscription
                    </button>
                </form>
            </div>

            <div role="tabpanel" id="pm-user-panel-role" aria-labelledby="pm-user-tab-role" tabindex="0" class="pm-user-panel pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6" hidden>
                <form method="POST" action="{{ route('admin.users.role', $user->id) }}" class="flex items-end gap-3">
                    @csrf
                    <div>
                        <label for="role" class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                        <select id="role" name="role"
                                class="pm-input">
                            <option value="user" @selected(!$user->isAdmin())>User</option>
                            <option value="admin" @selected($user->isAdmin())>Admin</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                        Update role
                    </button>
                </form>
            </div>

            <div role="tabpanel" id="pm-user-panel-data" aria-labelledby="pm-user-tab-data" tabindex="0" class="pm-user-panel pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6" hidden>
                <div class="flex items-start gap-3 mb-5">
                    <span class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 grid place-items-center shrink-0">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </span>
                    <div>
                        <h2 class="font-bold text-slate-800">Restore user data</h2>
                        <p class="text-sm text-slate-500 mt-1">Restore all records still available in this user's 30-day recycle bin. The record contents are not displayed to the administrator.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-5">
                    <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                        <p class="text-xs text-slate-500 uppercase tracking-wide">Usage progress</p>
                        <p class="text-2xl font-extrabold text-slate-800 mt-1">{{ $usageProgress ?? 0 }}%</p>
                    </div>
                    <div class="rounded-2xl bg-amber-50 border border-amber-100 p-4">
                        <p class="text-xs text-amber-700 uppercase tracking-wide">Recoverable items</p>
                        <p class="text-2xl font-extrabold text-amber-900 mt-1">{{ $recoverableCount ?? 0 }}</p>
                    </div>
                </div>

                @if (($recoverableCount ?? 0) > 0)
                    <form method="POST" action="{{ route('admin.users.restore-data', $user) }}"
                          data-confirm="Restore all recoverable data for {{ $user->name }}?" data-confirm-title="Restore user data?" data-confirm-text="Restore" data-confirm-danger="false">
                        @csrf
                        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-semibold inline-flex items-center gap-2">
                            <i class="fa-solid fa-rotate-left"></i>
                            Restore {{ $recoverableCount }} item(s)
                        </button>
                    </form>
                    <p class="text-xs text-slate-400 mt-3">After recovery, a confirmation email will be sent to <strong>{{ $user->email }}</strong>.</p>
                @else
                    <div class="rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 p-4 text-sm">
                        <i class="fa-solid fa-circle-check mr-1"></i> There is currently no deleted data available to restore.
                    </div>
                @endif
            </div>

            <div role="tabpanel" id="pm-user-panel-danger" aria-labelledby="pm-user-tab-danger" tabindex="0" class="pm-user-panel bg-white shadow-sm border border-rose-200 rounded-xl p-6" hidden>
                <h2 class="font-semibold mb-3 text-rose-700">Delete account</h2>
                <p class="text-sm text-slate-600 mb-4">
                    Permanently deletes {{ $user->name }}'s account and every record they created across
                    every module. This cannot be undone.
                </p>
                <form method="POST" action="{{ route('admin.users.destroy', $user->id) }}"
                      data-confirm="Permanently delete {{ $user->name }} and all their data? This cannot be undone." data-confirm-title="Delete user permanently?" data-confirm-text="Delete permanently">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bg-rose-600 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:shadow-md hover:bg-rose-700 transition-all">
                        Permanently delete this account
                    </button>
                </form>
            </div>

            <script>
                function pmSelectUserTab(key) {
                    document.querySelectorAll('.pm-user-tab').forEach(function (btn) {
                        var isSelected = btn.dataset.tab === key;
                        btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                        btn.setAttribute('tabindex', isSelected ? '0' : '-1');
                        var isDanger = btn.dataset.tab === 'danger';
                        btn.classList.toggle('border-[var(--brand-1)]', isSelected && !isDanger);
                        btn.classList.toggle('text-[var(--brand-1)]', isSelected && !isDanger);
                        btn.classList.toggle('border-rose-500', isSelected && isDanger);
                        btn.classList.toggle('text-rose-700', isSelected && isDanger);
                        btn.classList.toggle('border-transparent', !isSelected);
                        if (!isDanger) { btn.classList.toggle('text-slate-500', !isSelected); }
                        if (isSelected) { btn.focus(); }
                    });
                    document.querySelectorAll('.pm-user-panel').forEach(function (panel) {
                        panel.hidden = panel.id !== 'pm-user-panel-' + key;
                    });
                }

                function pmUserTabKeydown(event, currentKey) {
                    var tabs = Array.prototype.map.call(document.querySelectorAll('.pm-user-tab'), function (t) { return t.dataset.tab; });
                    var index = tabs.indexOf(currentKey);
                    var nextIndex = null;

                    if (event.key === 'ArrowRight') { nextIndex = (index + 1) % tabs.length; }
                    else if (event.key === 'ArrowLeft') { nextIndex = (index - 1 + tabs.length) % tabs.length; }
                    else if (event.key === 'Home') { nextIndex = 0; }
                    else if (event.key === 'End') { nextIndex = tabs.length - 1; }
                    else { return; }

                    event.preventDefault();
                    pmSelectUserTab(tabs[nextIndex]);
                }
            </script>
        @endif
    </div>
@endsection
