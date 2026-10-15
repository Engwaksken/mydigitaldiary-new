{{-- resources/views/partials/sidebar.blade.php --}}
@php
    use Illuminate\Support\Facades\Route;

    $sidebarUser = auth()->user();

    /*
    |--------------------------------------------------------------------------
    | Organisation-aware sidebar permissions
    |--------------------------------------------------------------------------
    |
    | The owner is the paying workspace Super Admin.
    | A managed/invited member belongs to the owner's organisation and inherits
    | access from the owner's plan, but must not see Organisation management or
    | Billing/Subscription controls.
    |
    | OrganizationAccessService is authoritative when available. The fallback
    | keeps the UI safe if an older deployment has not yet loaded the service.
    */
    $sidebarAccess = null;
    $sidebarOrganization = null;
    $sidebarOwner = null;
    $sidebarEffectiveRole = null;
    $sidebarIsOwner = false;
    $sidebarIsManagedMember = false;

    if ($sidebarUser) {
        try {
            if (app()->bound(\App\Services\OrganizationAccessService::class)) {
                $sidebarAccess = app(\App\Services\OrganizationAccessService::class);

                // Repair old NULL convenience columns from organization_members.
                $sidebarAccess->syncUserHierarchy($sidebarUser);

                $sidebarOrganization = $sidebarAccess->organizationFor($sidebarUser);
                $sidebarOwner = $sidebarAccess->ownerFor($sidebarUser);
                $sidebarEffectiveRole = $sidebarAccess->effectiveRole($sidebarUser);
                $sidebarIsOwner = $sidebarAccess->isOwner($sidebarUser);
                $sidebarIsManagedMember = $sidebarAccess->isManagedMember($sidebarUser);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if ($sidebarEffectiveRole === null) {
            $rawRole = strtolower((string) ($sidebarUser->organization_role ?? ''));

            $sidebarIsOwner = $rawRole === 'owner';

            $sidebarIsManagedMember =
                ! $sidebarIsOwner
                && ! empty($sidebarUser->organization_id);

            $sidebarEffectiveRole = $rawRole ?: (
                $sidebarIsManagedMember ? 'member' : null
            );
        }
    }

    /*
     * Owner-only account administration.
     * Individual subscribers who are not managed organisation members still
     * keep normal Subscription/Billing access.
     */
    $sidebarCanManageOrganization =
        $sidebarUser
        && ! $sidebarIsManagedMember
        && (
            $sidebarIsOwner
            || ! empty($sidebarUser->organization_id)
            || Route::has('organization.show')
        );

    $sidebarCanViewBilling =
        $sidebarUser
        && ! $sidebarIsManagedMember;

    $sidebarRoleLabel = match ($sidebarEffectiveRole) {
        'owner' => 'Workspace Owner',
        'admin' => 'Administrator',
        'staff' => 'Staff',
        'viewer' => 'Viewer',
        'member' => 'Member',
        default => ucfirst((string) ($sidebarUser->role ?? 'User')),
    };

    $routeSafe = static function (?string $name, array $parameters = []) {
        if (! $name || ! Route::has($name)) {
            return '#';
        }

        return route($name, $parameters);
    };

    $isActive = static function (string|array $patterns): bool {
        foreach ((array) $patterns as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    };

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | `owner_only` hides the item for invited/managed members.
    | `billing` hides billing/subscription for invited/managed members.
    */
    $navGroups = [
        'overview' => [
            'label' => 'Overview',
            'icon' => 'fa-solid fa-house',
            'items' => [
                [
                    'route' => 'dashboard',
                    'pattern' => 'dashboard',
                    'icon' => 'fa-solid fa-house',
                    'label' => 'Dashboard',
                ],
                [
                    'route' => 'activity',
                    'pattern' => 'activity',
                    'icon' => 'fa-solid fa-clock-rotate-left',
                    'label' => 'Activity',
                ],
                [
                    'route' => 'monthly-review',
                    'pattern' => 'monthly-review',
                    'icon' => 'fa-solid fa-chart-line',
                    'label' => 'Monthly Review',
                ],
            ],
        ],

        'planning' => [
            'label' => 'Planning & Goals',
            'icon' => 'fa-solid fa-calendar-check',
            'items' => [
                [
                    'route' => 'daily-planner.index',
                    'pattern' => 'daily-planner.*',
                    'icon' => 'fa-solid fa-calendar-day',
                    'label' => 'Daily Planner',
                ],
                [
                    'route' => 'annual-plans.index',
                    'pattern' => 'annual-plans.*',
                    'icon' => 'fa-solid fa-bullseye',
                    'label' => 'Annual Plans',
                ],
                [
                    'route' => 'personal-goals.index',
                    'pattern' => 'personal-goals.*',
                    'icon' => 'fa-solid fa-crosshairs',
                    'label' => 'Goals',
                ],
                [
                    'route' => 'reminders.index',
                    'pattern' => 'reminders.*',
                    'icon' => 'fa-solid fa-bell',
                    'label' => 'Reminders',
                ],
                [
                    'route' => 'meetings.index',
                    'pattern' => ['meetings.*', 'meeting-recordings.*'],
                    'icon' => 'fa-solid fa-video',
                    'label' => 'Meetings',
                ],
            ],
        ],

        'finance' => [
            'label' => 'Finance',
            'icon' => 'fa-solid fa-wallet',
            'items' => [
                [
                    'route' => 'financial-planner.index',
                    'pattern' => 'financial-planner.*',
                    'icon' => 'fa-solid fa-chart-pie',
                    'label' => 'Financial Planner',
                ],
                [
                    'route' => 'incomes.index',
                    'pattern' => 'incomes.*',
                    'icon' => 'fa-solid fa-money-bill-trend-up',
                    'label' => 'Income',
                ],
                [
                    'route' => 'business-invoices.index',
                    'pattern' => 'business-invoices.*',
                    'icon' => 'fa-solid fa-file-invoice-dollar',
                    'label' => 'Business Invoices',
                ],
                [
                    'route' => 'expenses.index',
                    'pattern' => 'expenses.*',
                    'icon' => 'fa-solid fa-receipt',
                    'label' => 'Expenses',
                ],
                [
                    'route' => 'budgets.index',
                    'pattern' => 'budgets.*',
                    'icon' => 'fa-solid fa-scale-balanced',
                    'label' => 'Budgets',
                ],
                [
                    'route' => 'debts.index',
                    'pattern' => 'debts.*',
                    'icon' => 'fa-solid fa-hand-holding-dollar',
                    'label' => 'Debts',
                ],
                [
                    'route' => 'savings-goals.index',
                    'pattern' => 'savings-goals.*',
                    'icon' => 'fa-solid fa-piggy-bank',
                    'label' => 'Savings',
                ],
            ],
        ],

        'wellbeing' => [
            'label' => 'Life & Wellbeing',
            'icon' => 'fa-solid fa-heart-pulse',
            'items' => [
                [
                    'route' => 'wellbeing.index',
                    'pattern' => 'wellbeing.*',
                    'icon' => 'fa-solid fa-face-smile',
                    'label' => 'Wellbeing',
                ],
                [
                    'route' => 'diet-logs.index',
                    'pattern' => 'diet-logs.*',
                    'icon' => 'fa-solid fa-utensils',
                    'label' => 'Diet',
                ],
                [
                    'route' => 'exercise-logs.index',
                    'pattern' => 'exercise-logs.*',
                    'icon' => 'fa-solid fa-person-running',
                    'label' => 'Exercise',
                ],
                [
                    'route' => 'sleep-logs.index',
                    'pattern' => 'sleep-logs.*',
                    'icon' => 'fa-solid fa-bed',
                    'label' => 'Sleep',
                ],
                [
                    'route' => 'health-checkups.index',
                    'pattern' => 'health-checkups.*',
                    'icon' => 'fa-solid fa-stethoscope',
                    'label' => 'Health Checkups',
                ],
                [
                    'route' => 'education-plans.index',
                    'pattern' => 'education-plans.*',
                    'icon' => 'fa-solid fa-graduation-cap',
                    'label' => 'Education',
                ],
                [
                    'route' => 'network-contacts.index',
                    'pattern' => 'network-contacts.*',
                    'icon' => 'fa-solid fa-people-arrows',
                    'label' => 'Network',
                ],
                [
                    'route' => 'relationships.index',
                    'pattern' => 'relationships.*',
                    'icon' => 'fa-solid fa-heart',
                    'label' => 'Relationships',
                ],
                [
                    'route' => 'spiritual-practices.index',
                    'pattern' => 'spiritual-practices.*',
                    'icon' => 'fa-solid fa-hands-praying',
                    'label' => 'Spiritual Growth',
                ],
            ],
        ],

        'productivity' => [
            'label' => 'Productivity & AI',
            'icon' => 'fa-solid fa-brain',
            'items' => [
                [
                    'route' => 'projects.index',
                    'pattern' => 'projects.*',
                    'icon' => 'fa-solid fa-diagram-project',
                    'label' => 'Projects',
                ],
                [
                    'route' => 'project-tasks.index',
                    'pattern' => 'project-tasks.*',
                    'icon' => 'fa-solid fa-list-check',
                    'label' => 'Project Tasks',
                ],
                [
                    'route' => 'notes.index',
                    'pattern' => 'notes.*',
                    'icon' => 'fa-solid fa-note-sticky',
                    'label' => 'Notes',
                ],
                [
                    'route' => 'ai-plans.index',
                    'pattern' => 'ai-plans.*',
                    'icon' => 'fa-solid fa-robot',
                    'label' => 'AI Planner',
                ],
                [
                    'route' => 'business-card.edit',
                    'pattern' => 'business-card.*',
                    'icon' => 'fa-solid fa-id-card',
                    'label' => 'My Business Card',
                ],
                [
                    'route' => 'social-media-planner.index',
                    'pattern' => 'social-media-planner.*',
                    'icon' => 'fa-solid fa-share-nodes',
                    'label' => 'Social Media Planner',
                ],
            ],
        ],

        'workspace' => [
            'label' => 'Workspace',
            'icon' => 'fa-solid fa-people-group',
            'items' => [
                [
                    'route' => 'team-chat.index',
                    'pattern' => 'team-chat.*',
                    'icon' => 'fa-solid fa-comments',
                    'label' => 'Team Chat',
                ],
                [
                    'route' => 'organization.show',
                    'pattern' => 'organization.*',
                    'icon' => 'fa-solid fa-building-user',
                    'label' => 'Organisation',
                    'owner_only' => true,
                ],
            ],
        ],

        'tools' => [
            'label' => 'Tools & Account',
            'icon' => 'fa-solid fa-toolbox',
            'items' => [
                [
                    'route' => 'signature.show',
                    'pattern' => 'signature.*',
                    'icon' => 'fa-solid fa-signature',
                    'label' => 'Signatures',
                ],
                [
                    'route' => 'api-credentials.index',
                    'pattern' => 'api-credentials.*',
                    'icon' => 'fa-solid fa-key',
                    'label' => 'API Keys',
                ],
                [
                    'route' => 'notifications.index',
                    'pattern' => 'notifications.*',
                    'icon' => 'fa-solid fa-bell',
                    'label' => 'Notifications',
                ],
                [
                    'route' => 'feedback.index',
                    'pattern' => 'feedback.*',
                    'icon' => 'fa-solid fa-comment-dots',
                    'label' => 'Feedback',
                ],
                [
                    'route' => 'tips',
                    'pattern' => 'tips',
                    'icon' => 'fa-solid fa-lightbulb',
                    'label' => 'Usage Tips',
                ],
                [
                    'route' => 'profile.edit',
                    'pattern' => 'profile.*',
                    'icon' => 'fa-solid fa-user-gear',
                    'label' => 'Profile',
                ],

                {{-- Owner/individual-only billing --}}
                [
                    'route' => 'subscription.show',
                    'pattern' => 'subscription.*',
                    'icon' => 'fa-solid fa-credit-card',
                    'label' => 'Subscription & Billing',
                    'billing' => true,
                ],
            ],
        ],
    ];

    /*
     * Support staff keep a focused support sidebar.
     */
    if ((string) ($sidebarUser->role ?? '') === 'support') {
        $navGroups = [
            'support' => [
                'label' => 'Support',
                'icon' => 'fa-solid fa-headset',
                'items' => [
                    [
                        'route' => 'admin.support.index',
                        'pattern' => 'admin.support.*',
                        'icon' => 'fa-solid fa-headset',
                        'label' => 'Support Conversations',
                    ],
                    [
                        'route' => 'notifications.index',
                        'pattern' => 'notifications.*',
                        'icon' => 'fa-solid fa-bell',
                        'label' => 'Notifications',
                    ],
                    [
                        'route' => 'profile.edit',
                        'pattern' => 'profile.*',
                        'icon' => 'fa-solid fa-user',
                        'label' => 'Profile',
                    ],
                ],
            ],
        ];
    }
@endphp

<aside
    id="sidebar"
    class="fixed inset-y-0 left-0 z-50 flex w-[290px] -translate-x-full flex-col border-r border-slate-200 bg-white shadow-xl transition-transform duration-200 lg:translate-x-0 lg:shadow-none"
    aria-label="Main navigation"
>
    {{-- Brand --}}
    <div class="flex min-h-[72px] items-center justify-between gap-3 border-b border-slate-100 px-5">
        <a
            href="{{ $routeSafe('dashboard') }}"
            class="flex min-w-0 items-center gap-3 no-underline"
        >
            <div
                class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-lg font-black text-white"
                style="background: var(--brand-1, #00897B);"
            >
                M
            </div>

            <div class="min-w-0">
                <div class="truncate text-sm font-black text-slate-900">
                    {{ $siteSettings->site_name ?? 'My Digital Diary' }}
                </div>
                <div class="truncate text-[11px] text-slate-500">
                    Your private digital space
                </div>
            </div>
        </a>

        <button
            type="button"
            id="sidebar-close"
            class="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 text-slate-500 lg:hidden"
            aria-label="Close navigation"
        >
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    {{-- User / managed account context --}}
    @auth
        <div class="border-b border-slate-100 p-4">
            <a
                href="{{ $routeSafe('profile.edit') }}"
                class="flex items-center gap-3 rounded-2xl bg-slate-50 p-3 no-underline hover:bg-slate-100"
            >
                <div
                    class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-sm font-black text-white"
                    style="background: var(--brand-1, #00897B);"
                >
                    {{ strtoupper(substr((string) ($sidebarUser->name ?? 'U'), 0, 1)) }}
                </div>

                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-black text-slate-900">
                        {{ $sidebarUser->name }}
                    </div>
                    <div class="truncate text-[10px] font-bold uppercase tracking-wide text-slate-500">
                        {{ $sidebarRoleLabel }}
                    </div>

                    @if($sidebarIsManagedMember)
                        <div class="mt-1 truncate text-[10px] text-teal-700">
                            <i class="fa-solid fa-shield-halved mr-1"></i>
                            Managed by {{ $sidebarOwner?->name ?? 'workspace owner' }}
                        </div>
                    @endif
                </div>
            </a>
        </div>
    @endauth

    {{-- Navigation --}}
    <nav class="min-h-0 flex-1 overflow-y-auto px-3 py-4">
        <div class="space-y-4">
            @foreach($navGroups as $groupKey => $group)
                @php
                    $visibleItems = collect($group['items'])
                        ->filter(function ($item) use (
                            $sidebarCanManageOrganization,
                            $sidebarCanViewBilling
                        ) {
                            if (!empty($item['owner_only']) && !$sidebarCanManageOrganization) {
                                return false;
                            }

                            if (!empty($item['billing']) && !$sidebarCanViewBilling) {
                                return false;
                            }

                            return Route::has($item['route']);
                        })
                        ->values();
                @endphp

                @if($visibleItems->isNotEmpty())
                    <section>
                        <div class="mb-1 flex items-center gap-2 px-3 text-[10px] font-black uppercase tracking-[.12em] text-slate-400">
                            <i class="{{ $group['icon'] }} w-4 text-center"></i>
                            <span>{{ $group['label'] }}</span>
                        </div>

                        <ul class="space-y-1">
                            @foreach($visibleItems as $item)
                                @php
                                    $active = $isActive($item['pattern']);
                                @endphp

                                <li>
                                    <a
                                        href="{{ $routeSafe($item['route']) }}"
                                        @class([
                                            'flex min-h-[42px] items-center gap-3 rounded-xl px-3 py-2 text-sm font-bold no-underline transition',
                                            'bg-teal-50 text-teal-800' => $active,
                                            'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => !$active,
                                        ])
                                        @if($active) aria-current="page" @endif
                                    >
                                        <span
                                            @class([
                                                'grid h-8 w-8 shrink-0 place-items-center rounded-lg',
                                                'bg-teal-100 text-teal-700' => $active,
                                                'bg-slate-100 text-slate-500' => !$active,
                                            ])
                                        >
                                            <i class="{{ $item['icon'] }} text-xs"></i>
                                        </span>

                                        <span class="min-w-0 flex-1 truncate">
                                            {{ $item['label'] }}
                                        </span>

                                        @if($active)
                                            <span class="h-1.5 w-1.5 rounded-full bg-teal-600"></span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endforeach
        </div>
    </nav>

    {{-- Managed member notice --}}
    @if($sidebarIsManagedMember)
        <div class="mx-3 mb-3 rounded-2xl border border-teal-100 bg-teal-50 p-3">
            <div class="flex gap-2">
                <i class="fa-solid fa-circle-info mt-0.5 text-teal-700"></i>
                <div class="min-w-0">
                    <div class="text-xs font-black text-teal-900">
                        Workspace-managed account
                    </div>
                    <div class="mt-1 text-[11px] leading-4 text-teal-800">
                        Your plan and billing are managed by the workspace owner.
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Footer / logout --}}
    <div class="border-t border-slate-100 p-3">
        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button
                type="submit"
                class="flex min-h-[42px] w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-bold text-rose-700 hover:bg-rose-50"
            >
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-rose-50">
                    <i class="fa-solid fa-right-from-bracket text-xs"></i>
                </span>
                <span>Log Out</span>
            </button>
        </form>
    </div>
</aside>

{{-- Mobile overlay --}}
<button
    type="button"
    id="sidebar-overlay"
    class="fixed inset-0 z-40 hidden bg-slate-950/50 lg:hidden"
    aria-label="Close navigation"
></button>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const close = document.getElementById('sidebar-close');

    const openButtons = document.querySelectorAll(
        '[data-sidebar-open], #sidebar-open, #mobile-menu-button'
    );

    function openSidebar() {
        if (!sidebar || !overlay) return;
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeSidebar() {
        if (!sidebar || !overlay) return;

        if (window.innerWidth < 1024) {
            sidebar.classList.add('-translate-x-full');
        }

        overlay.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    openButtons.forEach((button) => {
        button.addEventListener('click', openSidebar);
    });

    close?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 1024) {
            sidebar?.classList.remove('-translate-x-full');
            overlay?.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        } else if (!overlay || overlay.classList.contains('hidden')) {
            sidebar?.classList.add('-translate-x-full');
        }
    });
});
</script>
