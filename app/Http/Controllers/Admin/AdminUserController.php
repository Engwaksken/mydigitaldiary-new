<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendAppreciationToUsersJob;
use Illuminate\Http\JsonResponse;
use App\Mail\WeeklyAppreciationMail;
use App\Models\AnnouncementDelivery;
use App\Models\AppreciationCampaign;
use App\Models\AppreciationEmailLog;
use App\Services\AdminUserFilter;
use App\Services\AdminUserSelection;
use App\Services\AppreciationMessageService;
use App\Services\AppreciationSender;
use App\Services\SendTime;
use App\Http\Requests\Admin\BulkUserRequest;
use App\Http\Requests\Admin\UpdateUserSubscriptionRequest;
use App\Http\Requests\Admin\UpdateTranscriptionCapacityRequest;
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
        $filter = self::userFilter($request->query());

        $users = self::filteredUsersQuery($filter->toArray())
            ->with('subscriptionPlan')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $appreciationStatus = $this->appreciationStatusFor($users->getCollection()->pluck('id')->all());

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
            'filter' => $filter,
            'appreciationStatus' => $appreciationStatus,
        ]);
    }

    /**
     * The Users page filters as a query: the list and the "all users
     * matching these filters" bulk actions both use this, so they agree.
     *
     * @param  array<string, mixed>  $filters  search, subscription_status, verification, appreciation
     */
    public static function filteredUsersQuery(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        return self::userFilter($filters)->query();
    }

    /** @param  array<string, mixed>  $filters */
    private static function userFilter(array $filters): AdminUserFilter
    {
        return AdminUserFilter::fromArray($filters);
    }

    /**
     * For the Users table "Appreciation" column: whether each user has this
     * week's email, otherwise when they last had one.
     *
     * @param  list<int>  $userIds
     * @return array<int, array{this_week:bool, last_sent_at:?Carbon}>
     */
    private function appreciationStatusFor(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $weekKey = AppreciationMessageService::weekKey();
        $thisWeek = AppreciationEmailLog::query()->whereIn('user_id', $userIds)->where('week_key', $weekKey)
            ->pluck('user_id')->map(fn ($id) => (int) $id)->flip();
        $last = AppreciationEmailLog::query()->whereIn('user_id', $userIds)->whereNotNull('sent_at')
            ->groupBy('user_id')->selectRaw('user_id, MAX(sent_at) AS last_sent_at')
            ->pluck('last_sent_at', 'user_id');

        $status = [];
        foreach ($userIds as $id) {
            $value = $last[$id] ?? null;
            $status[(int) $id] = [
                'this_week' => $thisWeek->has((int) $id),
                'last_sent_at' => $value ? Carbon::parse($value, config('app.timezone')) : null,
            ];
        }

        return $status;
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
            'transcription_capacity_mb' => ['nullable', 'integer', 'min:50', 'max:10240'],
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

            if (
                Schema::hasColumn('users', 'transcription_capacity_mb')
                && array_key_exists('transcription_capacity_mb', $data)
            ) {
                $user->transcription_capacity_mb = $data['transcription_capacity_mb'] ?: null;
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
            'recentDeliveries' => AnnouncementDelivery::query()->with('announcement')
                ->where('user_id', $user->id)->latest('id')->limit(10)->get(),
            'recentAppreciation' => AppreciationEmailLog::query()->with('sender')
                ->where('user_id', $user->id)->latest('id')->limit(10)->get(),
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

    /**
     * Preview page for the "Send Appreciation Email" bulk action, for a few
     * picked users (?ids=1,2,3) or thousands matching the Users filters
     * (?selection=<token>). The "Will receive it" list is paginated (50 at a
     * time, searchable) and skipped people are summarised by reason. Drafts
     * are only written for the people the admin opens.
     */
    public function appreciationPreview(Request $request): View|RedirectResponse
    {
        $weekKey = AppreciationMessageService::weekKey();
        $selectionToken = (string) $request->query('selection', '');
        $selection = $selectionToken !== '' ? AdminUserSelection::get($selectionToken) : null;

        if ($selectionToken !== '' && $selection === null) {
            return redirect()->route('admin.users.index')->withErrors([
                'bulk' => 'That selection has expired. Select the users again.',
            ]);
        }

        $ids = $selection['ids'] ?? $this->idsFromQuery($request->query('ids'));
        [$eligibleIds, $skippedGroups] = $this->classifyForAppreciation($ids, $weekKey);

        $search = trim((string) $request->query('q', ''));
        $eligible = $this->eligiblePage($eligibleIds, $search);

        $skippedTotal = array_sum(array_column($skippedGroups, 'count'));

        return view('admin.users.appreciation-preview', [
            'weekKey' => $weekKey,
            'eligible' => $eligible,
            'eligibleCount' => count($eligibleIds),
            'eligibleIds' => $selection === null ? $eligibleIds : [],
            'skippedGroups' => $skippedGroups,
            'skippedTotal' => $skippedTotal,
            'selectionToken' => $selection !== null ? $selectionToken : null,
            'selectionDescription' => $selection['description'] ?? null,
            'search' => $search,
            'ids' => $selection === null ? implode(',', $ids) : null,
            'weeklySchedule' => \App\Services\WeeklyAppreciationSchedule::describe(),
        ]);
    }

    /**
     * Splits the selected users into those who will get this week's email
     * and those skipped, grouped by reason (largest group first, up to 100
     * names each), in chunks so thousands of users stay cheap.
     *
     * @param  list<int>  $ids
     * @return array{0: list<int>, 1: list<array{reason:string, count:int, users:list<array{name:string, email:?string}>}>}
     */
    private function classifyForAppreciation(array $ids, string $weekKey): array
    {
        $eligible = [];
        $groups = [];

        foreach (array_chunk(array_values(array_unique($ids)), 500) as $chunk) {
            $sentThisWeek = AppreciationEmailLog::query()->whereIn('user_id', $chunk)->where('week_key', $weekKey)
                ->pluck('user_id')->map(fn ($id) => (int) $id)->flip();

            User::query()->whereIn('id', $chunk)->orderBy('name')->get()
                ->each(function (User $user) use ($weekKey, $sentThisWeek, &$eligible, &$groups): void {
                    $reason = AppreciationSender::ineligibleReason($user, $weekKey, $sentThisWeek->has((int) $user->id));

                    if ($reason === null) {
                        $eligible[] = (int) $user->id;

                        return;
                    }

                    $groups[$reason] ??= ['reason' => $reason, 'count' => 0, 'users' => []];
                    $groups[$reason]['count']++;
                    if (count($groups[$reason]['users']) < 100) {
                        $groups[$reason]['users'][] = ['name' => (string) $user->name, 'email' => $user->email];
                    }
                });
        }

        $groups = array_values($groups);
        usort($groups, fn (array $a, array $b) => [$b['count'], $a['reason']] <=> [$a['count'], $b['reason']]);
        foreach ($groups as &$group) {
            usort($group['users'], fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));
        }

        return [$eligible, $groups];
    }

    /** @param  list<int>  $eligibleIds */
    private function eligiblePage(array $eligibleIds, string $search): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = User::query()
            ->when($eligibleIds === [], fn ($q) => $q->whereRaw('1 = 0'))
            ->when($eligibleIds !== [], fn ($q) => $q->whereIn('id', $eligibleIds))
            ->when($search !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->orderBy('id');

        return $query->paginate(50, ['id', 'name', 'email'])->withQueryString();
    }

    /**
     * One person's appreciation email exactly as it will be sent, as JSON for
     * the preview page. ?regenerate=1 writes a new message.
     */
    public function appreciationPreviewEmail(Request $request, User $user, AppreciationSender $sender): JsonResponse
    {
        $weekKey = AppreciationMessageService::weekKey();
        $reason = AppreciationSender::ineligibleReason($user, $weekKey);
        abort_if($reason !== null, 422, $reason ?? '');

        $copy = $sender->draft($user, $weekKey, fresh: $request->boolean('regenerate'));
        AppreciationSender::rememberPreviewed((int) $request->user()->id, (int) $user->id, $weekKey);

        return response()->json([
            'subject' => $copy['subject'],
            'source' => $copy['source'],
            'html' => (new WeeklyAppreciationMail($user, $copy['subject'], $copy['paragraphs']))->render(),
        ]);
    }

    /**
     * Sends now (a campaign with status sending, queued straight away) or
     * schedules a campaign for later. Either way the previewed drafts are
     * copied onto the campaign so exactly that text is sent.
     */
    public function appreciationSend(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'selection' => ['nullable', 'string', 'max:64'],
            'ids' => ['required_without:selection', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:users,id'],
        ] + SendTime::rules());

        if (filled($data['selection'] ?? null)) {
            $selection = AdminUserSelection::get($data['selection']);
            if ($selection === null) {
                return redirect()->route('admin.users.index')->withErrors([
                    'bulk' => 'That selection has expired. Select the users again.',
                ]);
            }
            $ids = $selection['ids'];
        } else {
            $ids = array_map('intval', $data['ids']);
        }

        $ids = array_values(array_unique($ids));
        $scheduledFor = SendTime::fromRequest($request);

        return $scheduledFor
            ? $this->scheduleAppreciation($request, $ids, $scheduledFor)
            : $this->queueAppreciation($request, $ids);
    }

    /** @return list<int> */
    private function idsFromQuery(mixed $value): array
    {
        return collect(explode(',', (string) $value))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Queues this week's appreciation email for the selected users who are
     * eligible (verified, opted in, not admin/suspended, not yet sent this
     * week) and reports how many were left out.
     *
     * @param  list<int>  $ids
     */
    private function queueAppreciation(Request $request, array $ids): RedirectResponse
    {
        $weekKey = AppreciationMessageService::weekKey();
        $eligible = AppreciationSender::eligibleIds($ids, $weekKey);
        $skipped = count($ids) - count($eligible);

        if ($eligible === []) {
            return redirect()->route('admin.users.index')->withErrors([
                'bulk' => 'None of the selected users can receive the appreciation email this week. '
                    .'It only goes to verified, non-admin users who have not opted out and have not had one this week.',
            ]);
        }

        $adminId = (int) $request->user()->id;
        $campaign = AppreciationCampaign::create([
            'created_by' => $adminId,
            'user_ids' => $eligible,
            'status' => AppreciationCampaign::STATUS_SENDING,
            'week_key' => $weekKey,
            'drafts' => AppreciationSender::previewedDrafts($adminId, $eligible, $weekKey) ?: null,
        ]);

        SendAppreciationToUsersJob::dispatch($eligible, $weekKey, $campaign->id);

        $message = 'Appreciation email is being sent to '.count($eligible).' '.str('user')->plural(count($eligible)).'.';
        if ($skipped > 0) {
            $message .= " {$skipped} skipped (unverified, admin, suspended, opted out or already sent this week).";
        }

        return redirect()->route('admin.users.index')->with('success', $message);
    }

    /**
     * Stores a scheduled campaign for every selected user: eligibility and
     * the once-per-week rule are checked when it sends, for that week.
     *
     * @param  list<int>  $ids
     */
    private function scheduleAppreciation(Request $request, array $ids, Carbon $scheduledFor): RedirectResponse
    {
        $weekKey = AppreciationMessageService::weekKey();
        $adminId = (int) $request->user()->id;
        $ids = User::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($ids === []) {
            return redirect()->route('admin.users.index')->withErrors(['bulk' => 'Select at least one user.']);
        }

        AppreciationCampaign::create([
            'created_by' => $adminId,
            'user_ids' => $ids,
            'scheduled_for' => $scheduledFor,
            'status' => AppreciationCampaign::STATUS_SCHEDULED,
            'drafts' => AppreciationSender::previewedDrafts($adminId, $ids, $weekKey) ?: null,
        ]);

        return redirect()->route('admin.appreciation.index')->with('success',
            'Appreciation email scheduled for '.SendTime::format($scheduledFor).' ('.SendTime::timezone().') for '
            .number_format(count($ids)).' '.str('user')->plural(count($ids)).'. Who is eligible is checked when it sends.');
    }

    /**
     * "Select all users matching these filters": resolves the matching IDs
     * now, with the same query as the list, and keeps them server-side
     * behind a short-lived token rather than in the URL. Only the sending
     * actions get here (BulkUserRequest rejects the others).
     */
    private function bulkForFilteredUsers(array $data): RedirectResponse
    {
        $filter = self::userFilter((array) ($data['filter'] ?? []));
        $ids = $filter->query()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($ids === []) {
            return back()->withErrors(['bulk' => 'No users match these filters.']);
        }

        $token = AdminUserSelection::store($ids, $filter->description());

        return $data['action'] === 'appreciation'
            ? redirect()->route('admin.users.appreciation.preview', ['selection' => $token])
            : redirect()->route('admin.announcements.create', ['selection' => $token]);
    }

    public function bulk(BulkUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $currentUserId = (int) $request->user()->id;

        if ($request->isFiltered()) {
            return $this->bulkForFilteredUsers($data);
        }

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

        if ($data['action'] === 'appreciation') {
            // Review each person's message before anything is sent.
            return redirect()->route('admin.users.appreciation.preview', ['ids' => $ids->implode(',')]);
        }

        if ($data['action'] === 'announcement') {
            // Compose on the announcement form, so the tabs, preview and
            // confirmation all apply; only these users will receive it.
            return redirect()->route('admin.announcements.create', ['recipients' => $ids->implode(',')]);
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

    public function updateTranscriptionCapacity(
        UpdateTranscriptionCapacityRequest $request,
        User $user
    ): \Illuminate\Http\JsonResponse|RedirectResponse {
        $user->transcription_capacity_mb = $request->validated('transcription_capacity_mb');
        $user->save();

        if (! $request->expectsJson()) {
            return back()->with('success', 'Transcription capacity updated.')
                ->with('open_manage_user', $user->id);
        }

        return response()->json([
            'transcription_capacity_mb' => $user->transcription_capacity_mb,
            'effective_transcription_capacity_mb' => $user->effectiveTranscriptionCapacityMb(),
        ]);
    }

    public function updateSubscriptionPrice(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'currency_code' => ['required_with:price', 'string', 'size:3'],
            'effective_from' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($data, $user, $request): void {
            $now = now();
            $user->subscriptionPriceOverrides()->whereNull('effective_until')
                ->where('effective_from', '<=', $now)->update(['effective_until' => $now]);
            if (array_key_exists('price', $data) && $data['price'] !== null) {
                $user->subscriptionPriceOverrides()->create([
                    'price' => $data['price'],
                    'currency_code' => strtoupper($data['currency_code']),
                    'effective_from' => $data['effective_from'] ?? $now,
                    'changed_by_user_id' => $request->user()->id,
                ]);
            }
        });

        return back()->with('success', 'Personal subscription price updated.');
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
