{{--
    App layout sidebar — inline sidebar extracted from layouts/app.blade.php.

    NOTE: resources/views/partials/sidebar.blade.php is a different, more
    sophisticated implementation with organization-aware permissions. This
    partial preserves the original inline sidebar from the app layout.
    These two sidebar implementations should be reconciled in a future phase.
--}}
@php
    $isActive = fn (string $pattern) => request()->routeIs($pattern);
    $linkClass = function (string $pattern) use ($isActive) {
        $active = $isActive($pattern);
        return 'flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm transition-all border-l-[3px] '
            . ($active
                ? 'bg-[var(--brand-2-tint-25)] text-white font-medium border-[#FFBA00]'
                : 'text-slate-300 hover:bg-white/5 hover:text-white border-transparent');
    };

    $navGroups = [
        'finance' => [
            'label' => 'Finance',
            'icon' => 'fa-solid fa-sack-dollar',
            'items' => [
                ['route' => 'financial-planner.index', 'pattern' => 'financial-planner.*', 'icon' => 'fa-solid fa-chart-line', 'label' => 'Financial Planner'],
                ['route' => 'incomes.index', 'pattern' => 'incomes.*', 'icon' => 'fa-solid fa-money-bill-trend-up', 'label' => 'Income'],
                ['route' => 'budgets.index', 'pattern' => 'budgets.*', 'icon' => 'fa-solid fa-wallet', 'label' => 'Budgets'],
                ['route' => 'expenses.index', 'pattern' => 'expenses.*', 'icon' => 'fa-solid fa-receipt', 'label' => 'Expenses'],
                ['route' => 'debts.index', 'pattern' => 'debts.*', 'icon' => 'fa-solid fa-hand-holding-dollar', 'label' => 'Debts'],
                ['route' => 'savings.index', 'pattern' => 'savings.*', 'icon' => 'fa-solid fa-piggy-bank', 'label' => 'Savings'],
                ['route' => 'savings-contributions.index', 'pattern' => 'savings-contributions.*', 'icon' => 'fa-solid fa-coins', 'label' => 'Contributions'],
            ],
        ],
        'health' => [
            'label' => 'Health & Wellness',
            'icon' => 'fa-solid fa-heart-pulse',
            'items' => [
                ['route' => 'diet-logs.index', 'pattern' => 'diet-logs.*', 'icon' => 'fa-solid fa-utensils', 'label' => 'Diet'],
                ['route' => 'exercise-logs.index', 'pattern' => 'exercise-logs.*', 'icon' => 'fa-solid fa-person-running', 'label' => 'Exercise'],
                ['route' => 'sleep-logs.index', 'pattern' => 'sleep-logs.*', 'icon' => 'fa-solid fa-bed', 'label' => 'Sleep'],
                ['route' => 'health-checkups.index', 'pattern' => 'health-checkups.*', 'icon' => 'fa-solid fa-stethoscope', 'label' => 'Health'],
                ['route' => 'wellbeing.index', 'pattern' => 'wellbeing.*', 'icon' => 'fa-solid fa-droplet', 'label' => 'Daily Wellbeing'],
            ],
        ],
        'work' => [
            'label' => 'Work & Projects',
            'icon' => 'fa-solid fa-briefcase',
            'items' => [
                ['route' => 'projects.index', 'pattern' => 'projects.*', 'icon' => 'fa-solid fa-diagram-project', 'label' => 'Projects'],
                ['route' => 'project-tasks.index', 'pattern' => 'project-tasks.*', 'icon' => 'fa-solid fa-clipboard-check', 'label' => 'Tasks'],
                ['route' => 'meetings.index', 'pattern' => 'meetings.*', 'icon' => 'fa-solid fa-calendar-days', 'label' => 'Meetings'],
            ],
        ],
        'personal' => [
            'label' => 'Personal Life',
            'icon' => 'fa-solid fa-user-group',
            'items' => [
                ['route' => 'education-plans.index', 'pattern' => 'education-plans.*', 'icon' => 'fa-solid fa-graduation-cap', 'label' => 'Education'],
                ['route' => 'network-contacts.index', 'pattern' => 'network-contacts.*', 'icon' => 'fa-solid fa-people-arrows', 'label' => 'Network'],
                ['route' => 'relationships.index', 'pattern' => 'relationships.*', 'icon' => 'fa-solid fa-heart', 'label' => 'Relationships'],
                ['route' => 'spiritual-practices.index', 'pattern' => 'spiritual-practices.*', 'icon' => 'fa-solid fa-hands-praying', 'label' => 'Spiritual Growth'],
            ],
        ],
        'productivity' => [
            'label' => 'Productivity & AI',
            'icon' => 'fa-solid fa-brain',
            'items' => [
                ['route' => 'daily-planner.index', 'pattern' => 'daily-planner.*', 'icon' => 'fa-solid fa-calendar-check', 'label' => 'Daily Planner'],
                ['route' => 'annual-plans.index', 'pattern' => 'annual-plans.*', 'icon' => 'fa-solid fa-bullseye', 'label' => 'Annual Plans'],
                ['route' => 'personal-goals.index', 'pattern' => 'personal-goals.*', 'icon' => 'fa-solid fa-crosshairs', 'label' => 'Goals'],
                ['route' => 'reminders.index', 'pattern' => 'reminders.*', 'icon' => 'fa-solid fa-bell', 'label' => 'Reminders'],
                ['route' => 'business-card.edit', 'pattern' => 'business-card.*', 'icon' => 'fa-solid fa-id-card', 'label' => 'My Business Card'],
                ['route' => 'notes.index', 'pattern' => 'notes.*', 'icon' => 'fa-solid fa-note-sticky', 'label' => 'Notes'],
                ['route' => 'ai-plans.index', 'pattern' => 'ai-plans.*', 'icon' => 'fa-solid fa-robot', 'label' => 'AI Planner'],
            ],
        ],
        'tools' => [
            'label' => 'Tools & Account',
            'icon' => 'fa-solid fa-toolbox',
            'items' => [
                ['route' => 'api-credentials.index', 'pattern' => 'api-credentials.*', 'icon' => 'fa-solid fa-key', 'label' => 'API Keys'],
                ['route' => 'signature.show', 'pattern' => 'signature.*', 'icon' => 'fa-solid fa-signature', 'label' => 'Signatures'],
                ['route' => 'organization.show', 'pattern' => 'organization.*', 'icon' => 'fa-solid fa-building-user', 'label' => 'Organization'],
                ['route' => 'feedback.index', 'pattern' => 'feedback.*', 'icon' => 'fa-solid fa-comment-dots', 'label' => 'Feedback'],
                ['route' => 'tips', 'pattern' => 'tips', 'icon' => 'fa-solid fa-lightbulb', 'label' => 'Usage Tips'],
            ],
        ],
    ];

    if ((string) auth()->user()->role === 'support') {
        $navGroups = [
            'support' => [
                'label' => 'Support',
                'icon' => 'fa-solid fa-headset',
                'items' => [
                    ['route' => 'admin.support.index', 'pattern' => 'admin.support.*', 'icon' => 'fa-solid fa-comments', 'label' => 'Support Conversations'],
                ],
            ],
        ];
    } elseif (auth()->user()->isAdmin()) {
        $navGroups['admin'] = [
            'label' => 'Admin',
            'icon' => 'fa-solid fa-shield-halved',
            'items' => [
                ['route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'icon' => 'fa-solid fa-users', 'label' => 'Users'],
                ['route' => 'admin.login-activities.index', 'pattern' => 'admin.login-activities.*', 'icon' => 'fa-solid fa-shield-halved', 'label' => 'Login Activities'],
                ['route' => 'admin.statistics', 'pattern' => 'admin.statistics', 'icon' => 'fa-solid fa-chart-line', 'label' => 'Statistics'],
                ['route' => 'admin.settings.edit', 'pattern' => 'admin.settings.*', 'icon' => 'fa-solid fa-gear', 'label' => 'Settings'],
                ['route' => 'admin.payment-gateways.index', 'pattern' => 'admin.payment-gateways.*', 'icon' => 'fa-solid fa-credit-card', 'label' => 'Payment Gateways'],
                ['route' => 'admin.payments.index', 'pattern' => 'admin.payments.*', 'icon' => 'fa-solid fa-money-check-dollar', 'label' => 'Payments'],
                ['route' => 'admin.subscription-plans.index', 'pattern' => 'admin.subscription-plans.*', 'icon' => 'fa-solid fa-tags', 'label' => 'Subscription Plans'],
                ['route' => 'admin.feedback.index', 'pattern' => 'admin.feedback.*', 'icon' => 'fa-solid fa-comment-dots', 'label' => 'Admin Feedback'],
                ['route' => 'admin.support.index', 'pattern' => 'admin.support.*', 'icon' => 'fa-solid fa-headset', 'label' => 'Support Conversations'],
                ['route' => 'admin.announcements.index', 'pattern' => 'admin.announcements.*', 'icon' => 'fa-solid fa-bullhorn', 'label' => 'Announcements'],
                ['route' => 'admin.billing-logs.index', 'pattern' => 'admin.billing-logs.*', 'icon' => 'fa-solid fa-file-invoice-dollar', 'label' => 'Billing Activity'],
                ['route' => 'admin.enterprise-inquiries.index', 'pattern' => 'admin.enterprise-inquiries.*', 'icon' => 'fa-solid fa-handshake', 'label' => 'Enterprise Inquiries'],
                ['route' => 'admin.invoices.index', 'pattern' => 'admin.invoices.*', 'icon' => 'fa-solid fa-file-invoice', 'label' => 'Quotations & Invoices'],
            ],
        ];
    }

    $groupIsActive = fn (array $group) => collect($group['items'])->contains(fn ($item) => $isActive($item['pattern']));
@endphp

<button
    id="sidebar-toggle"
    type="button"
    class="md:hidden m-3 inline-flex items-center gap-2 px-3 py-2 rounded-lg btn-primary text-white text-sm shadow-sm"
    aria-expanded="false"
    aria-controls="sidebar"
>
    <i class="fa-solid fa-bars" aria-hidden="true"></i>
    <span>Menu</span>
</button>

<aside id="sidebar" class="hidden md:flex md:flex-col md:w-64 md:shrink-0 md:sticky md:top-0 md:h-screen md:self-start bg-gradient-to-b from-[var(--brand-1)] to-[var(--brand-1-dark)]">
    <div class="px-4 py-5 border-b border-white/10">
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 font-bold text-lg text-white {{ $isActive('profile.edit') ? 'opacity-80' : '' }}" @if($isActive('profile.edit')) aria-current="page" @endif>
            @if (auth()->user()->avatarUrl())
                <img src="{{ auth()->user()->avatarUrl() }}" alt="" aria-hidden="true"
                     class="w-8 h-8 rounded-full object-cover shrink-0"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <span class="w-8 h-8 rounded-full bg-gradient-to-br from-[var(--brand-1)] to-[var(--brand-2)] text-white items-center justify-center text-sm font-bold shrink-0 hidden" aria-hidden="true">
                    {{ auth()->user()->initial() }}
                </span>
            @else
                <span class="w-8 h-8 rounded-full bg-gradient-to-br from-[var(--brand-1)] to-[var(--brand-2)] text-white flex items-center justify-center text-sm font-bold shrink-0" aria-hidden="true">
                    {{ auth()->user()->initial() }}
                </span>
            @endif
            <span class="truncate">{{ auth()->user()->name }}</span>
        </a>
    </div>

    <nav aria-label="Main navigation" class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">
        <a href="{{ route('dashboard') }}" class="{{ $linkClass('dashboard') }}" @if($isActive('dashboard')) aria-current="page" @endif>
            <i class="fa-solid fa-gauge-high w-4 text-center" aria-hidden="true"></i> Dashboard
        </a>

        @foreach ($navGroups as $groupKey => $group)
            @php $groupActive = $groupIsActive($group); @endphp
            <div class="pt-1">
                <button type="button"
                        id="sidebar-group-btn-{{ $groupKey }}"
                        aria-expanded="{{ $groupActive ? 'true' : 'false' }}"
                        aria-controls="sidebar-group-{{ $groupKey }}"
                        data-group="{{ $groupKey }}"
                        onclick="pmToggleSidebarGroup('{{ $groupKey }}')"
                        class="w-full flex items-center gap-3 px-4 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider text-slate-400 hover:text-slate-200 hover:bg-white/5 transition-colors">
                    <i class="{{ $group['icon'] }} w-4 text-center" aria-hidden="true"></i>
                    <span class="flex-1 text-left">{{ $group['label'] }}</span>
                    <i class="fa-solid fa-chevron-down w-3 text-center transition-transform pm-sidebar-chevron" data-group="{{ $groupKey }}" aria-hidden="true"
                       style="{{ $groupActive ? '' : 'transform: rotate(-90deg);' }}"></i>
                </button>
                <div id="sidebar-group-{{ $groupKey }}" class="space-y-0.5 pl-2" @if (! $groupActive) hidden @endif>
                    @foreach ($group['items'] as $item)
                        <a href="{{ route($item['route']) }}" class="{{ $linkClass($item['pattern']) }}" @if($isActive($item['pattern'])) aria-current="page" @endif>
                            <i class="{{ $item['icon'] }} w-4 text-center" aria-hidden="true"></i> {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    <div class="px-2 py-3 border-t border-white/10 space-y-0.5">
        <button type="button" id="pm-sidebar-install" class="w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm text-slate-200 hover:bg-white/10 hover:text-white transition-all" aria-label="Install this app">
            <i class="fa-solid fa-download w-4 text-center" aria-hidden="true"></i> Install app
        </button>
        <a href="{{ route('subscription.show') }}" class="{{ $linkClass('subscription.show') }}" @if($isActive('subscription.show')) aria-current="page" @endif>
            <i class="fa-solid fa-credit-card w-4 text-center" aria-hidden="true"></i> Billing
        </a>
        <a href="{{ route('privacy.show') }}" class="{{ $linkClass('privacy.show') }}" @if($isActive('privacy.show')) aria-current="page" @endif>
            <i class="fa-solid fa-shield-halved w-4 text-center" aria-hidden="true"></i> Privacy &amp; Data
        </a>
        <a href="{{ route('help.show') }}" class="{{ $linkClass('help.show') }}" @if($isActive('help.show')) aria-current="page" @endif>
            <i class="fa-solid fa-circle-question w-4 text-center" aria-hidden="true"></i> Help &amp; FAQ
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm text-slate-300 hover:bg-white/5 hover:text-rose-300 transition-all">
                <i class="fa-solid fa-arrow-right-from-bracket w-4 text-center" aria-hidden="true"></i> Logout
            </button>
        </form>
    </div>
</aside>
