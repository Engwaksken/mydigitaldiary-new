<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkUserRequest;
use App\Http\Requests\Admin\UpdateUserSubscriptionRequest;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    /**
     * Central role list for Admin user creation/editing.
     *
     * Internal values are deliberately stable:
     * - support = Support Officer
     * - finance = Finance Officer
     *
     * Add extra application roles here later without changing the forms.
     */
    public static function availableRoles(): array
    {
        return [
            'user' => 'User',
            'support' => 'Support Officer',
            'finance' => 'Finance Officer',
            'admin' => 'Admin',
            'super_admin' => 'Super Admin',
        ];
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $subscriptionStatus = trim(
            (string) $request->query('subscription_status', '')
        );

        $users = User::query()
            ->with('subscriptionPlan')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");

                    if (Schema::hasColumn('users', 'phone')) {
                        $sub->orWhere('phone', 'like', "%{$search}%");
                    }
                });
            })
            ->when(
                $subscriptionStatus !== ''
                    && Schema::hasColumn('users', 'subscription_status'),
                fn ($query) =>
                    // Legacy rows may still hold the old enum spellings.
                    $query->whereIn(
                        DB::raw("LOWER(TRIM(COALESCE(subscription_status, '')))"),
                        match (strtolower($subscriptionStatus)) {
                            'trial', 'trialing' => ['trial', 'trialing'],
                            'cancelled', 'canceled' => ['cancelled', 'canceled'],
                            default => [strtolower($subscriptionStatus)],
                        }
                    )
            )
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $plans = $this->subscriptionPlans();

        $statsQuery = User::query();

        $userStats = [
            'total' => (clone $statsQuery)->count(),
            'active' => 0,
            'trial' => 0,
            'expired_inactive' => 0,
            'suspended' => 0,
        ];

        if (Schema::hasColumn('users', 'subscription_status')) {
            $counts = User::query()
                ->selectRaw(
                    "LOWER(TRIM(COALESCE(subscription_status, ''))) AS state, COUNT(*) AS total"
                )
                ->groupBy('state')
                ->pluck('total', 'state');

            $userStats['active'] =
                (int) ($counts['active'] ?? 0);

            $userStats['trial'] =
                (int) ($counts['trial'] ?? 0)
                + (int) ($counts['trialing'] ?? 0);

            $userStats['expired_inactive'] =
                (int) ($counts['expired'] ?? 0)
                + (int) ($counts['inactive'] ?? 0)
                + (int) ($counts['cancelled'] ?? 0)
                + (int) ($counts['canceled'] ?? 0);
        }

        if (Schema::hasColumn('users', 'account_status')) {
            $userStats['suspended'] = User::query()
                ->where('account_status', 'suspended')
                ->count();
        } elseif (Schema::hasColumn('users', 'is_suspended')) {
            $userStats['suspended'] = User::query()
                ->where('is_suspended', true)
                ->count();
        } elseif (Schema::hasColumn('users', 'suspended')) {
            $userStats['suspended'] = User::query()
                ->where('suspended', true)
                ->count();
        }

        return view('admin.users.index', [
            'users' => $users,
            'plans' => $plans,
            'userStats' => $userStats,
            'availableRoles' => self::availableRoles(),
        ]);
    }

    public function create(): View
    {
        $plansQuery = SubscriptionPlan::query()
            ->where('is_enabled', true);

        if (Schema::hasColumn('subscription_plans', 'display_order')) {
            $plansQuery->orderBy('display_order');
        }

        if (Schema::hasColumn('subscription_plans', 'name')) {
            $plansQuery->orderBy('name');
        } elseif (Schema::hasColumn('subscription_plans', 'title')) {
            $plansQuery->orderBy('title');
        } else {
            $plansQuery->orderBy('id');
        }

        $plans = $plansQuery->get();

        return view('admin.users.create', [
            'plans' => $plans,
            'availableRoles' => self::availableRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $roles = array_keys(self::availableRoles());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in($roles)],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'max:255',
            ],
            'send_password_setup' => ['nullable', 'boolean'],
            'subscription_plan_id' => [
                'nullable',
                'integer',
                'exists:subscription_plans,id',
            ],
            'subscription_status' => [
                'nullable',
                Rule::in([
                    'active',
                    'trial',
                    'inactive',
                    'expired',
                    'suspended',
                    'cancelled',
                ]),
            ],
            'subscription_started_at' => [
                'nullable',
                'date',
            ],
            'subscription_expires_at' => [
                'nullable',
                'date',
            ],
            'trial_ends_at' => [
                'nullable',
                'date',
            ],
        ]);

        $sendSetup = $request->boolean('send_password_setup');

        if (
            blank($data['password'] ?? null)
            && ! $sendSetup
        ) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors([
                    'password' =>
                        'Enter a temporary password or select Send password setup email.',
                ]);
        }

        $user = DB::transaction(function () use ($data): User {
            $user = new User();

            $this->assignIfColumn(
                $user,
                'name',
                $data['name']
            );

            $this->assignIfColumn(
                $user,
                'email',
                strtolower(trim($data['email']))
            );

            if (
                Schema::hasColumn('users', 'phone')
                && ! blank($data['phone'] ?? null)
            ) {
                $user->phone = trim($data['phone']);
            }

            /*
             * A random internal password is used when the administrator
             * chooses the password-setup-email flow. The random value is
             * never displayed or sent.
             */
            $user->password = Hash::make(
                ! blank($data['password'] ?? null)
                    ? $data['password']
                    : bin2hex(random_bytes(32))
            );

            $this->setRole(
                $user,
                $data['role']
            );

            if (Schema::hasColumn('users', 'account_status')) {
                $user->account_status = 'active';
            }

            if (Schema::hasColumn('users', 'subscription_plan_id')) {
                $user->subscription_plan_id =
                    ! empty($data['subscription_plan_id'])
                        ? (int) $data['subscription_plan_id']
                        : $this->monthlyPlanId();
            }

            if (Schema::hasColumn('users', 'subscription_status')) {
                $user->subscription_status =
                    $data['subscription_status']
                    ?? (
                        ! empty($data['subscription_plan_id'])
                            ? 'active'
                            : 'trial'
                    );
            }

            foreach ([
                'subscription_started_at',
                'subscription_expires_at',
                'trial_ends_at',
            ] as $column) {
                if (
                    Schema::hasColumn('users', $column)
                    && array_key_exists($column, $data)
                ) {
                    $user->{$column} =
                        $data[$column] ?: null;
                }
            }

            if (
                Schema::hasColumn('users', 'email_verified_at')
                && blank($user->email_verified_at)
            ) {
                /*
                 * Admin-created accounts still need to authenticate normally.
                 * Marking the email as verified avoids blocking a legitimate
                 * staff account behind a second verification workflow.
                 */
                $user->email_verified_at = now();
            }

            $user->save();

            return $user;
        });

        if ((string) $user->subscription_status === 'active') {
            app(\App\Services\SubscriptionAdminNotificationService::class)->notify($user);
        }

        $setupEmailSent = false;

        if ($sendSetup) {
            try {
                $status = Password::sendResetLink([
                    'email' => $user->email,
                ]);

                $setupEmailSent =
                    $status === Password::RESET_LINK_SENT;
            } catch (\Throwable $e) {
                Log::warning(
                    'Admin-created user password setup email failed',
                    [
                        'user_id' => $user->id,
                        'message' => $e->getMessage(),
                    ]
                );
            }
        }

        $message = 'User created successfully.';

        if ($sendSetup) {
            $message .= $setupEmailSent
                ? ' A password setup email was sent.'
                : ' The account was created, but the password setup email could not be sent. Use Forgot Password or set a temporary password.';
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', $message);
    }

    public function show(User $user): View
    {
        $user->loadMissing('subscriptionPlan');

        return view('admin.users.show', [
            'user' => $user,
            'plans' => $this->subscriptionPlans(),
            'availableRoles' => self::availableRoles(),
        ]);
    }

    public function updateRole(
        Request $request,
        User $user
    ): RedirectResponse {
        abort_if(
            (int) $request->user()->id === (int) $user->id,
            422,
            'You cannot change your own role here.'
        );

        $data = $request->validate([
            'role' => [
                'required',
                Rule::in(
                    array_keys(self::availableRoles())
                ),
            ],
        ]);

        $this->setRole(
            $user,
            $data['role']
        );

        $user->save();

        return back()->with(
            'success',
            'User role updated to '
                .self::availableRoles()[$data['role']]
                .'.'
        );
    }

    public function suspend(
        Request $request,
        User $user
    ): RedirectResponse {
        abort_if(
            (int) $request->user()->id === (int) $user->id,
            422,
            'You cannot suspend your own account.'
        );

        $data = $request->validate([
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        if (Schema::hasColumn('users', 'account_status')) {
            $user->account_status = 'suspended';
        }

        if (Schema::hasColumn('users', 'is_suspended')) {
            $user->is_suspended = true;
        }

        if (Schema::hasColumn('users', 'suspended')) {
            $user->suspended = true;
        }

        if (Schema::hasColumn('users', 'suspended_at')) {
            $user->suspended_at = now();
        }

        if (Schema::hasColumn('users', 'suspended_reason')) {
            $user->suspended_reason =
                $data['reason'] ?? null;
        }

        $user->save();

        return back()->with(
            'success',
            'User suspended.'
        );
    }

    public function unsuspend(User $user): RedirectResponse
    {
        if (Schema::hasColumn('users', 'account_status')) {
            $user->account_status = 'active';
        }

        if (Schema::hasColumn('users', 'is_suspended')) {
            $user->is_suspended = false;
        }

        if (Schema::hasColumn('users', 'suspended')) {
            $user->suspended = false;
        }

        if (Schema::hasColumn('users', 'suspended_at')) {
            $user->suspended_at = null;
        }

        if (Schema::hasColumn('users', 'suspended_reason')) {
            $user->suspended_reason = null;
        }

        $user->save();

        return back()->with(
            'success',
            'User reactivated.'
        );
    }

    public function updateSubscription(
        UpdateUserSubscriptionRequest $request,
        User $user
    ): RedirectResponse {
        $data = $request->validated();
        try {
            $this->applySubscriptionUpdate($user, $data);

            return redirect()->route('admin.users.index')->with(
                'success', 'Subscription for '.$user->name.' updated successfully.'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        } catch (\Throwable $e) {
            Log::error('Admin subscription update failed', [
                'user_id' => $user->id,
                'admin_id' => auth()->id(),
                'subscription_status' => $data['subscription_status'] ?? null,
                'subscription_plan_id' => $data['subscription_plan_id'] ?? null,
                'message' => $e->getMessage(),
            ]);

            return back()->withInput()->withErrors([
                'subscription' => 'The subscription could not be updated. Please review the selected plan and dates and try again.',
            ]);
        }
    }

    /**
     * Shared single/bulk orchestration. Bulk supplies only nonblank edits.
     * This existing orchestration should move to an application service in
     * a follow-up once service ownership is available.
     */
    private function applySubscriptionUpdate(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data): void {
            // Lock before merging omitted fields, including within a bulk batch.
            $user = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $suppliedFields = array_keys($data);
            foreach (['subscription_status', 'subscription_plan_id', 'subscription_started_at', 'subscription_expires_at', 'trial_ends_at'] as $field) {
                if (! array_key_exists($field, $data)) {
                    $data[$field] = $user->{$field};
                }
            }

            $status = strtolower(trim((string) $data['subscription_status']));
            $status = match ($status) {
                'trialing' => 'trial',
                'canceled' => 'cancelled',
                default => $status,
            };
            $planId = filled($data['subscription_plan_id']) ? (int) $data['subscription_plan_id'] : null;
            $plan = $planId !== null ? SubscriptionPlan::query()->find($planId) : null;

            $dates = [];
            foreach (['subscription_started_at', 'subscription_expires_at', 'trial_ends_at'] as $field) {
                $dates[$field] = filled($data[$field]) ? Carbon::parse($data[$field]) : null;
                // Omitted timestamps stay stable on repeated partial saves.
                if ($dates[$field] && in_array($field, $suppliedFields, true)) {
                    $dates[$field] = $field === 'subscription_started_at'
                        ? $dates[$field]->startOfDay() : $dates[$field]->endOfDay();
                }
            }
            $startedAt = $dates['subscription_started_at'];
            $expiresAt = $dates['subscription_expires_at'];
            $trialEndsAt = $dates['trial_ends_at'];

            if ($status === 'active') {
                // Stable day boundary avoids a second save looking like a renewal.
                $startedAt ??= now()->startOfDay();
                if ($expiresAt === null && $plan !== null) {
                    $isLifetime = method_exists($plan, 'isLifetime')
                        ? (bool) $plan->isLifetime()
                        : (Schema::hasColumn('subscription_plans', 'is_lifetime')
                            ? (bool) $plan->getAttribute('is_lifetime') : false);
                    if (! $isLifetime) {
                        $expiresAt = $startedAt->copy()
                            ->addMonths(max(1, (int) ($plan->duration_months ?? 1)))
                            ->endOfDay();
                    }
                }
            }
            $trialEndsAt = $status === 'trial'
                ? ($trialEndsAt ?? now()->addDays(14)->endOfDay()) : null;

            // Check the effective dates after defaults and retained values,
            // inside the transaction so any earlier batch writes roll back.
            if ($startedAt !== null && $expiresAt !== null && $expiresAt->lt($startedAt)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'subscription_expires_at' => 'Subscription expiry date cannot be before the start date.',
                ]);
            }

            $updates = [
                'subscription_plan_id' => $planId,
                'subscription_started_at' => $startedAt,
                'subscription_expires_at' => $expiresAt,
                'trial_ends_at' => $trialEndsAt,
                'is_suspended' => $status === 'suspended',
                'suspended' => $status === 'suspended',
                'suspended_at' => $status === 'suspended' ? ($user->suspended_at ?: now()) : null,
            ];
            // Legacy aliases inform side effects without rewriting an omitted
            // status: blank bulk status still means leave the value unchanged.
            if (in_array('subscription_status', $suppliedFields, true)) {
                $updates['subscription_status'] = $status;
            }
            if ($status === 'suspended') {
                $updates['account_status'] = 'suspended';
            } else {
                if ((string) $user->account_status === 'suspended') {
                    $updates['account_status'] = 'active';
                }
                $updates['suspended_reason'] = null;
            }
            if (in_array($status, ['inactive', 'expired', 'suspended', 'cancelled'], true)) {
                $updates['auto_renew_subscription'] = false;
                $updates['auto_renew_disabled_at'] = $user->auto_renew_disabled_at ?: now();
            }
            $updates = array_filter(
                $updates, fn ($column) => Schema::hasColumn('users', $column), ARRAY_FILTER_USE_KEY
            );
            $user->forceFill($updates)->save();
            $subscriptionActivated = $status === 'active' && $user->wasChanged([
                'subscription_status', 'subscription_plan_id', 'subscription_started_at', 'subscription_expires_at',
            ]);

            // Both entry points use the existing deterministic activation key.
            if ($status === 'active' && $plan !== null
                && class_exists(\App\Services\SubscriptionRecordingQuotaGrantService::class)
                && Schema::hasTable('subscription_recording_extra_grants')) {
                $grantService = app(\App\Services\SubscriptionRecordingQuotaGrantService::class);
                $grantService->grantIncludedMinutes(
                    $user, $plan,
                    $grantService->key('admin', $user->id, $planId, $startedAt->toDateString()),
                    $expiresAt, null, 'admin_activation'
                );
            }
            if ($plan !== null && class_exists(\App\Models\Organization::class)) {
                $isIndividual = method_exists($plan, 'isIndividual') ? (bool) $plan->isIndividual() : true;
                if (! $isIndividual) {
                    $organization = \App\Models\Organization::query()
                        ->where('owner_user_id', $user->id)->lockForUpdate()->first();
                    if ($organization !== null) {
                        $organization->forceFill(['subscription_plan_id' => $plan->id])->save();
                    }
                }
            }
            if ($subscriptionActivated) {
                // Nested calls in bulk register callbacks on the outer
                // transaction. Rollback discards all activation notifications.
                DB::afterCommit(fn () => $this->notifySubscriptionActivation($user));
            }
        });
    }

    public function bulk(BulkUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $currentUserId = (int) $request->user()->id;

        $ids = collect($data['ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if (
            in_array(
                $data['action'],
                ['role', 'suspend', 'delete'],
                true
            )
            && $ids->contains($currentUserId)
        ) {
            return back()->withErrors([
                'bulk' =>
                    'Your own signed-in account cannot be role-changed, suspended or deleted through a bulk action.',
            ]);
        }

        if (
            $data['action'] === 'role'
            && blank($data['role'] ?? null)
        ) {
            return back()->withErrors([
                'role' =>
                    'Choose the role to assign to the selected users.',
            ]);
        }

        if (
            $data['action'] === 'subscription'
            && blank($data['subscription_status'] ?? null)
            && blank($data['subscription_plan_id'] ?? null)
            && blank($data['subscription_started_at'] ?? null)
            && blank($data['subscription_expires_at'] ?? null)
            && blank($data['trial_ends_at'] ?? null)
        ) {
            return back()->withErrors([
                'subscription' =>
                    'Choose at least one subscription value to update.',
            ]);
        }

        if ($data['action'] === 'delete') {
            abort_unless(
                strtoupper(
                    trim(
                        (string) ($data['confirmation'] ?? '')
                    )
                ) === 'DELETE',
                422,
                'Type DELETE to confirm bulk deletion.'
            );
        }

        $users = User::query()
            ->whereIn('id', $ids)
            ->get();

        try {
        DB::transaction(
            function () use (
                $users,
                $data,
                $currentUserId
            ): void {
                foreach ($users as $user) {
                    switch ($data['action']) {
                        case 'role':
                            if ((int) $user->id === $currentUserId) {
                                continue 2;
                            }

                            $this->setRole(
                                $user,
                                $data['role']
                            );

                            $user->save();
                            break;

                        case 'subscription':
                            // Blank bulk fields mean leave unchanged, whereas
                            // explicit blanks in the single form can clear.
                            $this->applySubscriptionUpdate($user, array_filter(
                                array_intersect_key($data, array_flip([
                                    'subscription_plan_id', 'subscription_status',
                                    'subscription_started_at', 'subscription_expires_at', 'trial_ends_at',
                                ])),
                                fn ($value) => $value !== null && $value !== ''
                            ));
                            break;

                        case 'suspend':
                            if ((int) $user->id === $currentUserId) {
                                continue 2;
                            }

                            if (
                                Schema::hasColumn(
                                    'users',
                                    'account_status'
                                )
                            ) {
                                $user->account_status =
                                    'suspended';
                            }

                            if (
                                Schema::hasColumn(
                                    'users',
                                    'is_suspended'
                                )
                            ) {
                                $user->is_suspended = true;
                            }

                            if (
                                Schema::hasColumn(
                                    'users',
                                    'suspended'
                                )
                            ) {
                                $user->suspended = true;
                            }

                            if (
                                Schema::hasColumn(
                                    'users',
                                    'suspended_at'
                                )
                            ) {
                                $user->suspended_at = now();
                            }

                            if (
                                Schema::hasColumn(
                                    'users',
                                    'suspended_reason'
                                )
                            ) {
                                $user->suspended_reason =
                                    $data['reason'] ?? null;
                            }

                            $user->save();
                            break;

                        case 'reactivate':
                            if (
                                Schema::hasColumn(
                                    'users',
                                    'account_status'
                                )
                            ) {
                                $user->account_status = 'active';
                            }

                            if (
                                Schema::hasColumn(
                                    'users',
                                    'is_suspended'
                                )
                            ) {
                                $user->is_suspended = false;
                            }

                            if (
                                Schema::hasColumn(
                                    'users',
                                    'suspended'
                                )
                            ) {
                                $user->suspended = false;
                            }

                            if (
                                Schema::hasColumn(
                                    'users',
                                    'suspended_at'
                                )
                            ) {
                                $user->suspended_at = null;
                            }

                            if (
                                Schema::hasColumn(
                                    'users',
                                    'suspended_reason'
                                )
                            ) {
                                $user->suspended_reason = null;
                            }

                            $user->save();
                            break;

                        case 'delete':
                            if ((int) $user->id === $currentUserId) {
                                continue 2;
                            }

                            $user->delete();
                            break;
                    }
                }
            }
        );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        } catch (\Throwable $e) {
            Log::error('Admin bulk user update failed', ['admin_id' => $currentUserId, 'action' => $data['action'], 'message' => $e->getMessage()]);
            return back()->withInput()->withErrors(['bulk' => 'The selected users could not be updated. No changes were saved. Please review the values and try again.']);
        }

        $count = $users->count();

        return back()->with(
            'success',
            $count.' selected user'
                .($count === 1 ? '' : 's')
                .' updated successfully.'
        );
    }

    public function destroy(
        Request $request,
        User $user
    ): RedirectResponse {
        abort_if(
            (int) $request->user()->id === (int) $user->id,
            422,
            'You cannot delete your own account.'
        );

        $confirmation = strtoupper(
            trim((string) $request->input(
                'confirmation',
                'DELETE'
            ))
        );

        abort_unless(
            $confirmation === 'DELETE',
            422,
            'Type DELETE to confirm.'
        );

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted.');
    }

    private function setRole(
        User $user,
        string $role
    ): void {
        /*
         * Older deployments may use `role`; newer user-management updates
         * may use `system_role`. Keep both synchronised when both exist.
         */
        if (Schema::hasColumn('users', 'system_role')) {
            $user->system_role = $role;
        }

        if (Schema::hasColumn('users', 'role')) {
            $user->role = $role;
        }
    }

    private function notifySubscriptionActivation(User $user): void
    {
        try {
            app(\App\Services\SubscriptionAdminNotificationService::class)->notify($user);
        } catch (\Throwable $e) {
            // Notification failure must not roll back a bulk edit or misreport a committed single edit.
            Log::warning('Admin subscription saved but activation notification failed', ['user_id' => $user->id, 'message' => $e->getMessage()]);
            session()->flash('warning', 'Subscription changes were saved, but an activation notification could not be delivered.');
        }
    }


    private function subscriptionPlans(): \Illuminate\Database\Eloquent\Collection
    {
        $query = SubscriptionPlan::query()->where('is_enabled', true);

        if (Schema::hasColumn('subscription_plans', 'display_order')) {
            $query->orderBy('display_order');
        }

        if (Schema::hasColumn('subscription_plans', 'name')) {
            $query->orderBy('name');
        } elseif (Schema::hasColumn('subscription_plans', 'title')) {
            $query->orderBy('title');
        } else {
            $query->orderBy('id');
        }

        return $query->get();
    }

    private function monthlyPlanId(): ?int
    {
        if (! Schema::hasTable('subscription_plans')) {
            return null;
        }

        foreach (['code', 'slug', 'name', 'title'] as $column) {
            if (! Schema::hasColumn('subscription_plans', $column)) {
                continue;
            }

            $id = SubscriptionPlan::query()
                ->whereRaw(
                    'LOWER(TRIM('.$column.')) = ?',
                    ['monthly']
                )
                ->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        if (
            Schema::hasColumn(
                'subscription_plans',
                'billing_interval'
            )
        ) {
            $id = SubscriptionPlan::query()
                ->whereRaw(
                    'LOWER(TRIM(billing_interval)) = ?',
                    ['monthly']
                )
                ->value('id');

            return $id ? (int) $id : null;
        }

        return null;
    }

    private function assignIfColumn(
        User $user,
        string $column,
        mixed $value
    ): void {
        if (Schema::hasColumn('users', $column)) {
            $user->{$column} = $value;
        }
    }
}
