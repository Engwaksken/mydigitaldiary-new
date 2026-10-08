@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php

    $dashboardSystemLogoUrl = null;
    if (isset($siteSettings)) {
        foreach (['logoUrl', 'siteLogoUrl', 'faviconUrl'] as $logoMethod) {
            if (method_exists($siteSettings, $logoMethod)) {
                try {
                    $candidate = $siteSettings->{$logoMethod}();
                    if (is_string($candidate) && trim($candidate) !== '') {
                        $dashboardSystemLogoUrl = $candidate;
                        break;
                    }
                } catch (\Throwable $e) {
                    // Continue to the next configured logo source.
                }
            }
        }
    }

    $firstName = trim(explode(' ', auth()->user()->name ?? 'User')[0] ?? 'User');
    $money = static fn ($value) => 'UGX '.number_format((float) $value, 0);

    // PersonalProgressService returns one nested payload. The dashboard cards
    // read these two child arrays directly, so bind them here instead of
    // silently falling back to zero when the variables are not defined.
    $financialHealth = is_array($personalProgress ?? null)
        ? ($personalProgress['financial_health'] ?? [])
        : [];
    $weeklyReview = is_array($personalProgress ?? null)
        ? ($personalProgress['weekly_review'] ?? [])
        : [];

    $toneMap = [
        'emerald' => ['bg' => '#ecfdf5', 'fg' => '#047857', 'border' => '#a7f3d0'],
        'rose' => ['bg' => '#fff1f2', 'fg' => '#be123c', 'border' => '#fecdd3'],
        'amber' => ['bg' => '#fffbeb', 'fg' => '#b45309', 'border' => '#fde68a'],
        'sky' => ['bg' => '#f0f9ff', 'fg' => '#0369a1', 'border' => '#bae6fd'],
        'indigo' => ['bg' => '#eef2ff', 'fg' => '#4338ca', 'border' => '#c7d2fe'],
        'fuchsia' => ['bg' => '#fdf4ff', 'fg' => '#a21caf', 'border' => '#f5d0fe'],
        'blue' => ['bg' => '#eff6ff', 'fg' => '#1d4ed8', 'border' => '#bfdbfe'],
        'violet' => ['bg' => '#f5f3ff', 'fg' => '#6d28d9', 'border' => '#ddd6fe'],
        'slate' => ['bg' => '#f8fafc', 'fg' => '#475569', 'border' => '#e2e8f0'],
        'teal' => ['bg' => '#f0fdfa', 'fg' => '#0f766e', 'border' => '#99f6e4'],
    ];
    $insightTone = $toneMap[$dailyInsight['tone'] ?? 'emerald'] ?? $toneMap['emerald'];

    $financeCards = [
        ['title' => 'Income', 'monthly' => $monthlyIncome ?? 0, 'overall' => $totalIncome ?? 0, 'route' => route('incomes.index'), 'icon' => 'fa-arrow-trend-up', 'theme' => 'income'],
        ['title' => 'Expenses', 'monthly' => $monthlyExpenses ?? 0, 'overall' => $totalExpenses ?? 0, 'route' => route('expenses.index'), 'icon' => 'fa-receipt', 'theme' => 'expenses'],
        ['title' => 'Budgets', 'monthly' => $monthlyBudget ?? 0, 'overall' => $totalBudget ?? 0, 'route' => route('budgets.index'), 'icon' => 'fa-chart-pie', 'theme' => 'budgets'],
        ['title' => 'Savings', 'monthly' => $monthlySavings ?? 0, 'overall' => $totalSavingsContributions ?? ($totalSaved ?? 0), 'route' => route('savings-contributions.index'), 'icon' => 'fa-piggy-bank', 'theme' => 'savings'],
        ['title' => 'Debts', 'monthly' => $monthlyDebt ?? 0, 'overall' => $totalDebtOutstanding ?? 0, 'route' => route('debts.index'), 'icon' => 'fa-hand-holding-dollar', 'theme' => 'debts'],
        ['title' => 'Goals', 'monthly' => $monthlySavings ?? 0, 'overall' => $totalSavingsTarget ?? 0, 'route' => route('savings-goals.index'), 'icon' => 'fa-bullseye', 'theme' => 'goals'],
    ];

    $todayFocus = collect($todaysPlanItems ?? []);

    if (
        $todayFocus->isEmpty()
        && class_exists(\App\Services\PeriodReviewMetricsService::class)
    ) {
        try {
            $todayFocus = app(
                \App\Services\PeriodReviewMetricsService::class
            )->todayFocus(auth()->user(), 6);
        } catch (\Throwable $e) {
            report($e);
            $todayFocus = collect();
        }
    }

    $todayFocus = $todayFocus->take(6);
    $nextActions = collect($goalIntelligence['next_actions'] ?? [])->take(3);
    $defaultDashboardTab = $todayFocus->isNotEmpty() ? 'focus' : ($nextActions->isNotEmpty() ? 'actions' : 'tools');

    /*
     * Retention / daily-rhythm data.
     *
     * Prefer controller-provided $engagement, but self-load from the service
     * when the controller has not yet been updated. That makes this dashboard
     * a true drop-in replacement instead of rendering no visible change.
     */
    $engagement = is_array($engagement ?? null) ? $engagement : [];

    if (empty($engagement) && class_exists(\App\Services\DailyEngagementService::class)) {
        try {
            $engagement = app(\App\Services\DailyEngagementService::class)
                ->dashboard(auth()->user());
        } catch (\Throwable $e) {
            report($e);
            $engagement = [];
        }
    }

    $growthStreak = (int) data_get($engagement, 'streak.current', 0);
    $bestGrowthStreak = (int) data_get($engagement, 'streak.best', 0);
    $startDayCompleted = (bool) data_get($engagement, 'start_day.completed', false);
    $closeDayCompleted = (bool) data_get($engagement, 'close_day.completed', false);
    $engagementProgress = is_array(data_get($engagement, 'progress'))
        ? data_get($engagement, 'progress')
        : [];
    $engagementCelebration = data_get($engagement, 'celebration');
    $tomorrowFocus = collect(data_get($engagement, 'tomorrow.focus', []))->take(3);

    $growth = is_array($growth ?? null) ? $growth : [];
    if (empty($growth) && class_exists(\App\Services\GrowthStrategyService::class)) {
        try { $growth = app(\App\Services\GrowthStrategyService::class)->dashboard(auth()->user()); } catch (\Throwable $e) { report($e); $growth = []; }
    }
@endphp

@if (auth()->user()->offboarded_at && ! auth()->user()->personal_email_verified_at)
    <x-alert type="warning" :dismissible="false" class="mb-4">
        <p class="font-semibold mb-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Personal email required</p>
        <p class="mb-2">Add and verify a personal email to continue using your account after leaving your organisation.</p>
        <form method="POST" action="{{ route('personal-email.send-code') }}" class="flex flex-wrap gap-2">
            @csrf
            <input type="email" name="personal_email" required placeholder="you@example.com" class="pm-input text-sm flex-1 min-w-[14rem]">
            <button class="btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium">Send code</button>
        </form>
    </x-alert>
@endif

@if ($expiryNotification ?? false)
    <dialog id="pm-expiry-notice-modal" class="rounded-2xl p-6 pm-dialog-sm shadow-2xl backdrop:bg-slate-900/50">
        <h2 class="text-lg font-bold text-slate-800 mb-2">Subscription notice</h2>
        <p class="text-sm text-slate-600 mb-5">
            @if (($expiryNotification->data['days_remaining'] ?? 0) > 0)
                Your subscription expires in {{ $expiryNotification->data['days_remaining'] }} day(s).
            @else
                Your subscription has expired. Renew to continue adding and editing information.
            @endif
        </p>
        <div class="flex justify-end gap-3">
            <button type="button" onclick="document.getElementById('pm-expiry-notice-modal').close()" class="text-sm text-slate-500">Later</button>
            <a href="{{ route('subscription.show') }}" class="btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium">View subscription</a>
        </div>
    </dialog>
    <script>document.getElementById('pm-expiry-notice-modal')?.showModal();</script>
@endif

@php
    /*
     * "Today" hub data. Everything here is derived from values the
     * controller (or the self-loading block above) already provides, so
     * this view never fails just because an optional service is missing.
     */
    $tdTimezone = auth()->user()->timezone ?: 'Africa/Kampala';
    $tdNow = \Illuminate\Support\Carbon::now($tdTimezone);
    $tdToday = $tdNow->toDateString();
    $tdYesterday = $tdNow->copy()->subDay()->toDateString();
    $tdHour = (int) $tdNow->format('G');
    $tdGreeting = $tdHour < 12 ? 'Good morning' : ($tdHour < 17 ? 'Good afternoon' : 'Good evening');
    $tdGreetingIcon = $tdHour < 6 ? 'fa-moon' : ($tdHour < 17 ? 'fa-sun' : 'fa-moon');
    $tdEvening = $tdHour >= 17;

    // Streak: the stored "current" only resets on the next action, so a
    // streak whose last active day is older than yesterday is shown as 0.
    $tdActiveDays = collect($recentActiveDays ?? [])->map(fn ($d) => substr((string) $d, 0, 10))->all();
    $tdLastActive = substr((string) data_get($engagement, 'streak.last_day', ''), 0, 10);
    $tdActiveToday = $tdLastActive === $tdToday || in_array($tdToday, $tdActiveDays, true);
    $tdStreakAlive = $tdActiveToday || $tdLastActive === $tdYesterday;
    $tdStreak = $tdStreakAlive ? $growthStreak : 0;
    $tdWeekDots = collect(range(6, 0))->map(function ($daysAgo) use ($tdNow, $tdActiveDays, $tdToday, $tdActiveToday) {
        $day = $tdNow->copy()->subDays($daysAgo);
        $date = $day->toDateString();

        return [
            'label' => substr($day->format('D'), 0, 1),
            'title' => $day->format('l j M'),
            'active' => in_array($date, $tdActiveDays, true) || ($date === $tdToday && $tdActiveToday),
            'today' => $date === $tdToday,
        ];
    });

    // Today's tasks: prefer recurrence-aware stats from the controller.
    $tdTaskTotal = (int) ($todayTaskStats['total'] ?? data_get($engagementProgress, 'tasks_total', 0));
    $tdTaskDone = (int) ($todayTaskStats['completed'] ?? data_get($engagementProgress, 'tasks_completed', 0));
    $tdTaskPercent = $tdTaskTotal > 0 ? (int) round(($tdTaskDone / $tdTaskTotal) * 100) : 0;
    $tdTasks = $todayFocus->take(5);

    // Coming up later today (meetings, reminders, project tasks due).
    $tdAgenda = collect();
    foreach (collect($upcomingMeetings ?? []) as $meeting) {
        $tdAgenda->push(['icon' => 'fa-video', 'tone' => 'violet', 'title' => $meeting->title, 'time' => $meeting->start_at?->copy()->timezone($tdTimezone), 'url' => Route::has('meetings.show') ? route('meetings.show', $meeting) : route('meetings.index')]);
    }
    foreach (collect($upcomingReminders ?? []) as $reminder) {
        $tdAgenda->push(['icon' => 'fa-bell', 'tone' => 'amber', 'title' => $reminder->title, 'time' => $reminder->next_run_at?->copy()->timezone($tdTimezone), 'url' => route('reminders.index')]);
    }
    foreach (collect($upcomingTasks ?? []) as $projectTask) {
        $tdAgenda->push(['icon' => 'fa-clipboard-check', 'tone' => 'sky', 'title' => $projectTask->title, 'time' => null, 'url' => route('project-tasks.index')]);
    }
    $tdAgenda = $tdAgenda->sortBy(fn ($row) => $row['time'] ? $row['time']->format('H:i') : '99:99')->values();

    // Progress rings — only where data already exists; otherwise a one-tap setup link.
    $tdSavingsPercent = ($totalSavingsTarget ?? 0) > 0 ? (int) min(100, round((($totalSaved ?? 0) / $totalSavingsTarget) * 100)) : null;
    $tdRings = [
        [
            'label' => 'This week',
            'value' => (int) ($weeklyReview['completion_percent'] ?? 0),
            'text' => (int) ($weeklyReview['completed_tasks'] ?? 0).'/'.(int) ($weeklyReview['total_tasks'] ?? 0).' tasks',
            'color' => '#7c3aed',
            'url' => route('activity'),
            'empty' => false,
            'hint' => 'Share of this week’s planned tasks you have completed.',
        ],
        [
            'label' => 'Annual plans',
            'value' => (int) ($annualPlanProgress ?? 0),
            'text' => ($annualPlanTotal ?? 0) > 0 ? (int) ($annualPlanCompleted ?? 0).'/'.(int) $annualPlanTotal.' done' : 'Add a plan',
            'color' => '#2563eb',
            'url' => route('annual-plans.index'),
            'empty' => ($annualPlanTotal ?? 0) === 0,
            'hint' => 'Average progress across this year’s plans.',
        ],
        [
            'label' => 'Savings',
            'value' => (int) ($tdSavingsPercent ?? 0),
            'text' => $tdSavingsPercent !== null ? $money($totalSaved ?? 0) : 'Set a target',
            'color' => '#0d9488',
            'url' => route('savings-goals.index'),
            'empty' => $tdSavingsPercent === null,
            'hint' => 'Saved so far against all savings goal targets.',
        ],
        [
            'label' => 'Money health',
            'value' => (int) ($financialHealth['score'] ?? 0),
            'text' => $financialHealth['label'] ?? 'Getting started',
            'color' => '#d97706',
            'url' => route('financial-planner.index'),
            'empty' => false,
            'hint' => 'Score out of 100 from this month’s income, spending and saving.',
        ],
    ];

    // Get-started checklist (server-known steps; "install" is resolved in the browser).
    $tdActivationSteps = collect(data_get($growth, 'activation.steps', []))->keyBy(fn ($s) => is_array($s) ? ($s['key'] ?? '') : ($s->key ?? ''));
    $tdStepDone = fn (string $key) => (bool) data_get($tdActivationSteps->get($key), 'complete', false);
    $tdGetStarted = [
        ['key' => 'plan', 'label' => 'Add your first task', 'icon' => 'fa-calendar-check', 'done' => $tdStepDone('plan') || $tdTaskTotal > 0, 'url' => route('daily-planner.index', ['new' => 1])],
        ['key' => 'goal', 'label' => 'Set a goal', 'icon' => 'fa-bullseye', 'done' => $tdStepDone('goal'), 'url' => route('personal-goals.index', ['new' => 1])],
        ['key' => 'money', 'label' => 'Record money', 'icon' => 'fa-wallet', 'done' => $tdStepDone('money'), 'url' => route('expenses.index', ['new' => 1])],
        ($dailyReminders['push_available'] ?? false)
            // One tap: asks for notification permission and registers this
            // device for the morning / evening reminders (see js/push.js).
            ? ['key' => 'daily-reminders', 'label' => 'Turn on daily reminders', 'icon' => 'fa-bell', 'done' => (bool) ($dailyReminders['on'] ?? false), 'url' => null]
            : ['key' => 'reminder', 'label' => 'Turn on a reminder', 'icon' => 'fa-bell', 'done' => (bool) ($hasAnyReminder ?? false), 'url' => route('reminders.index', ['new' => 1])],
    ];

    // "On this day" memories (controller-provided; hidden when empty).
    $tdMemories = collect(data_get($onThisDay ?? [], 'items', []))->take(3);
    $tdMemoryComparison = data_get($onThisDay ?? [], 'comparison');
    $tdMemoryMore = data_get($onThisDay ?? [], 'more_url');
    $tdGetStartedDone = collect($tdGetStarted)->where('done', true)->count();

    // App launcher — every module stays one tap away, without shouting.
    $tdLauncher = collect([
        ['Planner', 'fa-calendar-day', '#047857', 'daily-planner.index'],
        ['Goals', 'fa-bullseye', '#be123c', 'personal-goals.index'],
        ['Reminders', 'fa-bell', '#b45309', 'reminders.index'],
        ['Notes', 'fa-note-sticky', '#ca8a04', 'notes.index'],
        ['Meetings', 'fa-video', '#6d28d9', 'meetings.index'],
        ['Expenses', 'fa-receipt', '#e11d48', 'expenses.index'],
        ['Savings', 'fa-piggy-bank', '#0d9488', 'savings.index'],
        ['Health', 'fa-heart-pulse', '#dc2626', 'wellbeing.index'],
        ['Annual Plans', 'fa-list-check', '#1d4ed8', 'annual-plans.index'],
        ['Projects', 'fa-diagram-project', '#0369a1', 'projects.index'],
        ['Income', 'fa-arrow-trend-up', '#047857', 'incomes.index'],
        ['Budgets', 'fa-chart-pie', '#1d4ed8', 'budgets.index'],
        ['Debts', 'fa-hand-holding-dollar', '#c2410c', 'debts.index'],
        ['Money Planner', 'fa-wallet', '#047857', 'financial-planner.index'],
        ['Checkups', 'fa-stethoscope', '#be123c', 'health-checkups.index'],
        ['Spiritual', 'fa-seedling', '#a21caf', 'spiritual-practices.index'],
        ['AI Planner', 'fa-wand-magic-sparkles', '#6d28d9', 'ai-plans.index'],
        ['Business Card', 'fa-address-card', '#0f766e', 'business-card.edit'],
        ['Sign', 'fa-signature', '#4338ca', 'signature.show'],
        ['Education', 'fa-graduation-cap', '#4338ca', 'education-plans.index'],
        ['Network', 'fa-address-book', '#0369a1', 'network-contacts.index'],
        ['Relationships', 'fa-heart', '#db2777', 'relationships.index'],
        ['Social Planner', 'fa-bullhorn', '#0369a1', 'social-media-planner.index'],
        ['Activity', 'fa-clock-rotate-left', '#475569', 'activity'],
    ])->filter(fn ($tool) => Route::has($tool[3]))->values();

    $tdPriorityColor = fn ($priority) => match (strtolower((string) $priority)) {
        'urgent', 'high' => '#e11d48',
        'medium' => '#d97706',
        default => '#94a3b8',
    };
@endphp

<style>
    /* ------------------------------------------------------------------
       Today hub — calm, glanceable, one clear next step per card.
       ------------------------------------------------------------------ */
    .td{--td-brand:var(--brand-1,#0f766e);--td-ink:#0f172a;--td-muted:#64748b;--td-line:#e7ecf3;--td-soft:#f6f8fb;width:min(1120px,100%);margin:0 auto;padding:2px 0 24px;color:var(--td-ink)}
    .td *{box-sizing:border-box}
    html:not(.pm-a11y-underline-links) .td a{text-decoration:none!important}
    .td-card{background:#fff;border:1px solid var(--td-line);border-radius:20px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 24px rgba(15,23,42,.04)}
    .td-section{margin-bottom:16px}
    .td-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:0 2px 10px}
    .td-h2{display:flex;align-items:center;gap:8px;font-size:15px;font-weight:800;color:var(--td-ink)}
    .td-h2 i{color:var(--td-brand);font-size:14px}
    .td-link{font-size:12px;font-weight:700;color:var(--td-brand)!important;white-space:nowrap}
    .td-link:hover{opacity:.8}
    .td-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:40px;padding:0 16px;border-radius:12px;font-size:13px;font-weight:700;border:1px solid transparent;cursor:pointer;transition:transform .15s ease,box-shadow .15s ease,background .15s ease;white-space:nowrap}
    .td-btn:active{transform:scale(.97)}
    .td-btn-primary{background:var(--td-brand);color:#fff!important;box-shadow:0 6px 16px color-mix(in srgb,var(--td-brand) 28%,transparent)}
    .td-btn-primary:hover{box-shadow:0 8px 20px color-mix(in srgb,var(--td-brand) 36%,transparent)}
    .td-btn-ghost{background:#fff;border-color:var(--td-line);color:#334155!important}
    .td-btn-ghost:hover{background:var(--td-soft)}
    .td-btn-done{background:#ecfdf5;border-color:#a7f3d0;color:#047857!important}
    .td-icon-btn{width:40px;height:40px;border-radius:12px;border:1px solid var(--td-line);background:#fff;color:#475569;display:inline-flex;align-items:center;justify-content:center;transition:background .15s ease}
    .td-icon-btn:hover{background:var(--td-soft)}

    /* Hero */
    .td-hero{position:relative;overflow:hidden;padding:22px 22px 18px;border-radius:24px;border:1px solid color-mix(in srgb,var(--td-brand) 18%,#e2e8f0);background:
        radial-gradient(120% 140% at 100% 0%,color-mix(in srgb,var(--td-brand) 16%,transparent) 0%,transparent 55%),
        radial-gradient(90% 120% at 0% 100%,#fff7ed 0%,transparent 60%),
        linear-gradient(180deg,#ffffff 0%,#fbfdfc 100%);box-shadow:0 10px 30px rgba(15,23,42,.06)}
    .td-hero-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
    .td-date{font-size:12px;font-weight:700;color:var(--td-muted);display:flex;align-items:center;gap:6px}
    .td-date i{color:#f59e0b}
    .td-hello{font-size:26px;line-height:1.15;font-weight:800;letter-spacing:-.02em;margin-top:4px;font-family:'Outfit','Poppins',sans-serif}
    .td-hero-actions{display:flex;align-items:center;gap:8px}
    .td-hero-body{display:grid;grid-template-columns:auto 1fr;gap:20px;align-items:center;margin-top:18px}
    .td-ring{--p:0;--c:var(--td-brand);--size:104px;--track:#e9eef5;position:relative;width:var(--size);height:var(--size);border-radius:50%;background:conic-gradient(var(--c) calc(var(--p) * 1%),var(--track) 0);display:grid;place-items:center;flex:0 0 auto;transition:--p .6s ease}
    .td-ring::before{content:"";position:absolute;inset:9px;border-radius:50%;background:#fff}
    .td-ring>*{position:relative;text-align:center}
    .td-ring-num{font-size:22px;font-weight:800;line-height:1;font-family:'Outfit','Poppins',sans-serif}
    .td-ring-sub{font-size:10px;font-weight:700;color:var(--td-muted);margin-top:3px}
    .td-streak{display:flex;flex-wrap:wrap;align-items:center;gap:12px}
    .td-streak-pill{display:inline-flex;align-items:center;gap:7px;padding:8px 12px;border-radius:999px;background:#fff7ed;border:1px solid #fed7aa;color:#c2410c;font-size:13px;font-weight:800}
    .td-streak-pill.cold{background:#f8fafc;border-color:#e2e8f0;color:#475569}
    .td-flame{display:inline-block;transform-origin:50% 90%}
    .td-streak-pill:not(.cold) .td-flame{animation:tdFlicker 2.4s ease-in-out infinite}
    .td-dots{display:flex;gap:6px}
    .td-dot{width:26px;display:flex;flex-direction:column;align-items:center;gap:3px;font-size:9px;font-weight:700;color:#94a3b8}
    .td-dot span{width:12px;height:12px;border-radius:50%;background:#e9eef5;border:2px solid transparent;transition:background .2s ease}
    .td-dot.on span{background:#f97316}
    .td-dot.today span{border-color:color-mix(in srgb,var(--td-brand) 60%,#fff)}
    .td-dot.today{color:var(--td-ink)}
    .td-nudge{font-size:13px;color:#475569;margin-top:10px;font-weight:600}
    .td-routine{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
    .td-celebrate{margin-top:14px;padding:10px 12px;border-radius:14px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:12px;font-weight:700}

    /* Notification bell (existing data, restyled) */
    .md-notif{position:relative}.md-notif>summary{list-style:none;cursor:pointer;position:relative}.md-notif>summary::-webkit-details-marker{display:none}
    .md-notif-badge{position:absolute;right:-5px;top:-5px;min-width:18px;height:18px;padding:0 4px;border-radius:999px;background:#e11d48;color:#fff;font-size:9px;font-weight:900;display:flex;align-items:center;justify-content:center;border:2px solid #fff}
    .md-notif-panel{position:absolute;z-index:80;right:0;top:46px;width:min(370px,calc(100vw - 28px));background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 18px 45px rgba(15,23,42,.18);overflow:hidden}
    .md-notif-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border-bottom:1px solid #eef2f7}
    .md-notif-title{font-size:13px;font-weight:800;color:#172033}.md-notif-sub{font-size:11px;color:#64748b}
    .md-notif-list{max-height:380px;overflow-y:auto}
    .md-notif-item{display:flex;gap:10px;padding:11px 13px;border-bottom:1px solid #f1f5f9;color:#334155!important;background:#fff}.md-notif-item:hover{background:#f8fafc}.md-notif-item.unread{background:#f0f9ff}
    .md-notif-icon{width:32px;height:32px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex:0 0 auto}
    .md-notif-copy{min-width:0;flex:1}.md-notif-item-title{font-size:12px;font-weight:700;color:#172033;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .md-notif-msg{font-size:11px;line-height:1.35;color:#64748b;margin-top:2px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .md-notif-time{font-size:10px;color:#94a3b8;margin-top:3px;display:block}
    .md-notif-dot{width:7px;height:7px;border-radius:50%;background:#0ea5e9;margin-top:5px;flex:0 0 auto}
    .md-notif-action{font-size:9px;font-weight:800;color:#b45309;background:#fffbeb;border:1px solid #fde68a;border-radius:999px;padding:1px 6px}
    .md-notif-foot{padding:10px 13px;background:#f8fafc;display:flex;align-items:center;justify-content:space-between;gap:8px}
    .md-notif-foot a,.md-notif-foot button{font-size:11px;font-weight:700;color:var(--td-brand)!important}
    .md-notif-empty{padding:28px 16px;text-align:center;color:#94a3b8;font-size:12px}

    /* Get started */
    .td-start{padding:16px 18px;border-radius:20px;background:linear-gradient(135deg,color-mix(in srgb,var(--td-brand) 9%,#fff),#fff 70%);border:1px solid color-mix(in srgb,var(--td-brand) 22%,#e2e8f0)}
    .td-start-bar{height:6px;border-radius:999px;background:#e9eef5;overflow:hidden;margin:10px 0 12px}
    .td-start-bar>span{display:block;height:100%;background:var(--td-brand);border-radius:inherit;transition:width .4s ease}
    .td-start-list{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px}
    .td-step{display:flex;align-items:center;gap:9px;padding:10px 11px;border-radius:14px;background:#fff;border:1px solid var(--td-line);color:#334155!important;font-size:12px;font-weight:700;min-width:0;transition:transform .15s ease,border-color .15s ease;cursor:pointer;text-align:left;width:100%}
    .td-step:hover{transform:translateY(-1px);border-color:color-mix(in srgb,var(--td-brand) 40%,#e2e8f0)}
    .td-step i.td-step-ic{width:28px;height:28px;border-radius:9px;display:grid;place-items:center;background:var(--td-soft);color:var(--td-brand);flex:0 0 auto;font-size:12px}
    .td-step span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .td-step.done{background:#f0fdf4;border-color:#bbf7d0;color:#15803d!important}
    .td-step.done i.td-step-ic{background:#dcfce7;color:#15803d}
    .td-step.done span{text-decoration:line-through;text-decoration-color:#86efac}
    .td-step[aria-busy="true"]{opacity:.7;cursor:progress}
    .td-start-msg{margin:10px 2px 0;font-size:12px;font-weight:600;color:#475569}
    .td-start-msg a,.td-start-msg button{color:var(--td-brand)!important;font-weight:700;background:none;border:0;padding:0;cursor:pointer}

    /* Memories card */
    .td-memory{padding:14px 16px;border-radius:18px;border:1px solid #fde7c7;background:linear-gradient(160deg,#fffaf2 0%,#fff 70%)}
    .td-memory-list{list-style:none;margin:8px 0 0;padding:0;display:grid;gap:4px}
    .td-memory-item{display:flex;align-items:flex-start;gap:10px;padding:7px 4px;border-radius:12px;color:#334155!important}
    a.td-memory-item:hover{background:#fff4e2}
    .td-memory-ic{width:30px;height:30px;border-radius:10px;display:grid;place-items:center;flex:0 0 auto;font-size:12px}
    .td-memory-when{display:block;font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#b45309}
    .td-memory-text{display:block;font-size:13px;font-weight:600;line-height:1.35;color:#1e293b;overflow-wrap:anywhere}
    .td-memory-note{font-size:12px;color:#64748b;margin-top:8px}

    /* Two-column today grid */
    .td-grid{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:16px;align-items:start}
    .td-pad{padding:16px 18px}

    /* Tasks */
    .td-tasks{list-style:none;margin:0;padding:0;display:grid;gap:6px}
    .td-task{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:14px;border:1px solid var(--td-line);background:#fff;transition:background .2s ease,opacity .3s ease,border-color .2s ease}
    .td-task:hover{background:var(--td-soft)}
    .td-check{position:relative;width:26px;height:26px;border-radius:50%;border:2px solid #cbd5e1;background:#fff;display:grid;place-items:center;flex:0 0 auto;cursor:pointer;color:transparent;font-size:12px;transition:background .2s ease,border-color .2s ease,color .2s ease,transform .15s ease}
    .td-check:hover{border-color:var(--td-brand)}
    .td-check:focus-visible{outline:3px solid color-mix(in srgb,var(--td-brand) 35%,transparent);outline-offset:2px}
    .td-check[disabled]{cursor:progress;opacity:.7}
    .td-task.is-done .td-check{background:var(--td-brand);border-color:var(--td-brand);color:#fff;animation:tdPop .35s ease}
    .td-task.is-done .td-check::after{content:"";position:absolute;inset:-6px;border-radius:50%;border:2px solid var(--td-brand);opacity:0;animation:tdRipple .5s ease}
    .td-task.is-done{background:#f8fafc}
    .td-task.is-done .td-task-title{color:#94a3b8;text-decoration:line-through}
    .td-task-copy{min-width:0;flex:1}
    .td-task-title{font-size:14px;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;transition:color .2s ease}
    .td-task-meta{font-size:11px;color:var(--td-muted);margin-top:1px;display:flex;align-items:center;gap:6px}
    .td-prio{width:7px;height:7px;border-radius:50%;flex:0 0 auto}
    .td-alldone{display:none;align-items:center;gap:10px;padding:12px;border-radius:14px;background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;font-size:13px;font-weight:700;margin-top:8px}
    .td-alldone.show{display:flex;animation:tdFadeUp .35s ease}
    .td-empty{display:flex;flex-direction:column;align-items:center;text-align:center;gap:10px;padding:22px 12px;border:1px dashed #d5dde8;border-radius:16px;background:var(--td-soft)}
    .td-empty i{font-size:22px;color:#94a3b8}
    .td-empty p{font-size:13px;color:#475569;font-weight:600;margin:0}

    /* Agenda */
    .td-agenda{list-style:none;margin:0;padding:0;display:grid;gap:4px}
    .td-agenda a{display:flex;align-items:center;gap:10px;padding:8px 6px;border-radius:12px;color:#334155!important}
    .td-agenda a:hover{background:var(--td-soft)}
    .td-agenda-ic{width:32px;height:32px;border-radius:10px;display:grid;place-items:center;flex:0 0 auto;font-size:12px}
    .td-agenda-title{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0;flex:1}
    .td-agenda-time{font-size:11px;font-weight:700;color:var(--td-muted);white-space:nowrap}

    /* Insight */
    .td-insight{display:flex;gap:12px;align-items:flex-start;padding:14px 16px;border-radius:18px;border:1px solid var(--insight-border);background:var(--insight-bg)}
    .td-insight-ic{width:38px;height:38px;border-radius:12px;background:#fff;display:grid;place-items:center;color:var(--insight-fg);flex:0 0 auto}
    .td-insight-kicker{font-size:10px;letter-spacing:.08em;text-transform:uppercase;font-weight:800;color:var(--insight-fg)}
    .td-insight-title{font-size:14px;font-weight:700;margin-top:2px;color:#172033}
    .td-insight-msg{font-size:12px;line-height:1.45;color:#475569;margin-top:3px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
    .td-insight-foot{display:flex;align-items:center;gap:12px;margin-top:8px}
    .td-insight-foot a,.td-insight-foot button{font-size:12px;font-weight:700;color:var(--insight-fg)!important;background:none;border:0;padding:0;cursor:pointer}

    /* Rings row */
    .td-rings{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
    .td-ring-card{display:flex;align-items:center;gap:12px;padding:14px;border-radius:18px;background:#fff;border:1px solid var(--td-line);color:var(--td-ink)!important;transition:transform .15s ease,box-shadow .15s ease;min-width:0}
    .td-ring-card:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(15,23,42,.07)}
    .td-ring-card .td-ring{--size:58px}
    .td-ring-card .td-ring::before{inset:6px}
    .td-ring-card .td-ring-num{font-size:13px}
    .td-ring-label{font-size:12px;font-weight:700;color:var(--td-muted)}
    .td-ring-text{font-size:14px;font-weight:700;color:var(--td-ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .td-ring-card.empty .td-ring-text{color:var(--td-brand)}

    /* Launcher */
    .td-launcher{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:10px}
    .td-app{display:flex;flex-direction:column;align-items:center;gap:7px;padding:12px 4px;border-radius:16px;color:#334155!important;font-size:11.5px;font-weight:600;text-align:center;transition:background .15s ease,transform .15s ease;min-width:0}
    .td-app:hover{background:var(--td-soft);transform:translateY(-2px)}
    .td-app-ic{width:46px;height:46px;border-radius:15px;display:grid;place-items:center;font-size:18px;color:var(--app-c);background:color-mix(in srgb,var(--app-c) 11%,#fff);box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--app-c) 16%,transparent)}
    .td-app-name{max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .td-launcher:not(.expanded) .td-app:nth-child(n+9){display:none}
    .td-more-apps{display:flex;justify-content:center;margin-top:6px}

    /* "More" collapsibles */
    .td-more{border:1px solid var(--td-line);border-radius:18px;background:#fff;overflow:hidden;margin-bottom:10px}
    .td-more>summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:12px;padding:14px 16px;user-select:none}
    .td-more>summary::-webkit-details-marker{display:none}
    .td-more>summary:hover{background:#fbfcfe}
    .td-more-ic{width:34px;height:34px;border-radius:11px;display:grid;place-items:center;background:var(--td-soft);color:var(--td-brand);flex:0 0 auto}
    .td-more-title{font-size:14px;font-weight:700;flex:1;min-width:0}
    .td-more-badge{font-size:11px;font-weight:700;color:var(--td-muted)}
    .td-more-chev{color:#94a3b8;transition:transform .2s ease}
    .td-more[open] .td-more-chev{transform:rotate(180deg)}
    .td-more-body{padding:4px 16px 16px;animation:tdFadeUp .2s ease}

    .md-finance-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}
    .md-finance-card{--fc-bg:#f8fafc;--fc-fg:#475569;display:block;border-radius:14px;padding:11px 12px;background:var(--fc-bg);color:#172033!important;min-width:0;transition:transform .15s ease}
    .md-finance-card:hover{transform:translateY(-1px)}
    .md-finance-card.income{--fc-bg:#ecfdf5;--fc-fg:#047857}.md-finance-card.expenses{--fc-bg:#fff1f2;--fc-fg:#be123c}.md-finance-card.budgets{--fc-bg:#eff6ff;--fc-fg:#1d4ed8}.md-finance-card.savings{--fc-bg:#f5f3ff;--fc-fg:#6d28d9}.md-finance-card.debts{--fc-bg:#fff7ed;--fc-fg:#c2410c}.md-finance-card.goals{--fc-bg:#ecfeff;--fc-fg:#0e7490}
    .md-finance-name{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:var(--fc-fg)}
    .md-finance-value{font-size:15px;font-weight:800;margin-top:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .md-finance-small{font-size:10.5px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

    .td-list-links{display:grid;gap:6px}
    .td-list-links a,.td-list-links button{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;border:1px solid var(--td-line);background:#fff;color:#334155!important;font-size:13px;font-weight:600;text-align:left;width:100%;cursor:pointer}
    .td-list-links a:hover,.td-list-links button:hover{background:var(--td-soft)}
    .td-list-links small{display:block;font-size:11px;color:var(--td-muted);font-weight:500}

    .td-growth{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
    .td-growth-card{padding:14px;border-radius:14px;border:1px solid var(--td-line);background:#fff}
    .td-growth-kicker{font-size:10px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:#94a3b8}
    .td-growth-title{font-size:13px;font-weight:700;margin-top:3px}
    .td-bar{height:6px;border-radius:999px;background:#e9eef5;overflow:hidden;margin-top:10px}
    .td-bar>span{display:block;height:100%;border-radius:inherit;background:var(--bar,#0f766e)}
    .td-growth-step{display:flex;align-items:center;gap:6px;font-size:11px;color:#64748b;font-weight:600;margin-top:6px}
    .td-growth-step.complete{color:#15803d}
    .md-growth-action{display:inline-flex;align-items:center;gap:6px;margin-top:10px;border:0;border-radius:10px;min-height:34px;padding:0 12px;color:#fff;font-size:12px;font-weight:700;cursor:pointer}
    .md-growth-action.violet{background:#6d28d9}.md-growth-action.sky{background:#0369a1}
    .td-private{display:flex;align-items:center;gap:7px;margin-top:10px;font-size:11px;color:#047857;font-weight:600}

    /* Steps card (compact) */
    #md-live-steps-card{margin-bottom:0}

    /* Engagement dialogs (unchanged behaviour) */
    .md-engagement-dialog{width:min(92vw,560px);max-width:560px;border:0;border-radius:18px;padding:0;overflow:hidden;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.25)}.md-engagement-dialog::backdrop{background:rgba(15,23,42,.55);backdrop-filter:blur(3px)}.md-engagement-dialog-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:15px 16px;border-bottom:1px solid #eef2f7}.md-engagement-dialog-title{font-size:16px;font-weight:800;color:#172033}.md-engagement-dialog-sub{font-size:11px;color:#64748b;margin-top:2px}.md-engagement-dialog-close{width:34px;height:34px;border-radius:10px;border:1px solid #e2e8f0;background:#fff;color:#64748b}.md-engagement-dialog-body{padding:15px 16px;max-height:min(70vh,620px);overflow-y:auto}.md-engagement-dialog-foot{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid #eef2f7;background:#f8fafc}.md-engagement-fields{display:grid;gap:10px}.md-engagement-field label{display:block;font-size:11px;font-weight:700;color:#475569;margin-bottom:4px}.md-review-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.md-review-metric{padding:10px;border-radius:12px;border:1px solid #e2e8f0;background:#f8fafc}.md-review-metric-label{font-size:10px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.05em}.md-review-metric-value{font-size:15px;font-weight:800;color:#172033;margin-top:2px}

    @@property --p{syntax:'<number>';inherits:false;initial-value:0}
    @keyframes tdPop{0%{transform:scale(.7)}60%{transform:scale(1.15)}100%{transform:scale(1)}}
    @keyframes tdRipple{0%{opacity:.6;transform:scale(.8)}100%{opacity:0;transform:scale(1.5)}}
    @keyframes tdFadeUp{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
    @keyframes tdFlicker{0%,100%{transform:rotate(-3deg) scale(1)}50%{transform:rotate(3deg) scale(1.08)}}
    @media (prefers-reduced-motion: reduce){.td *{animation:none!important;transition:none!important}}

    @media (max-width:1024px){
        .td-start-list{grid-template-columns:repeat(3,minmax(0,1fr))}
        .td-launcher{grid-template-columns:repeat(6,minmax(0,1fr))}
        .td-launcher:not(.expanded) .td-app:nth-child(n+7){display:none}
        .md-finance-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
    }
    @media (max-width:860px){
        .td-grid{grid-template-columns:1fr}
        .td-rings{grid-template-columns:repeat(2,minmax(0,1fr))}
        .td-growth{grid-template-columns:1fr}
    }
    @media (max-width:640px){
        .td-hero{padding:18px 16px 16px;border-radius:20px}
        .td-hello{font-size:22px}
        .td-hero-body{gap:14px}
        .td-hero .td-ring{--size:88px}
        .td-ring-num{font-size:19px}
        .td-start-list{grid-template-columns:1fr 1fr}
        .td-launcher{grid-template-columns:repeat(4,minmax(0,1fr));gap:4px}
        .td-launcher:not(.expanded) .td-app:nth-child(n+7){display:flex}
        .td-launcher:not(.expanded) .td-app:nth-child(n+9){display:none}
        .td-ring-card{padding:12px;gap:10px}
        .td-ring-card .td-ring{--size:48px}
        .td-ring-card .td-ring-num{font-size:11px}
        .md-finance-grid{grid-template-columns:1fr 1fr}
        .td-pad{padding:14px}
        .td-dots{gap:3px}
        .td-dot{width:22px}
    }
    @media (max-width:380px){
        .td-dot{width:19px}
        .td-start-list{grid-template-columns:1fr}
    }
</style>

<div class="td" id="md-today">

    {{-- 1. Hero: greeting, today's progress ring, streak, day rhythm --}}
    <section class="td-hero td-section" id="daily-rhythm-section">
        <div class="td-hero-top">
            <div class="min-w-0">
                <div class="td-date"><i class="fa-solid {{ $tdGreetingIcon }}"></i>{{ $tdNow->format('l, j F') }}</div>
                <h1 class="td-hello">{{ $tdGreeting }}, {{ $firstName }}</h1>
            </div>
            <div class="td-hero-actions">
                @if (Route::has('user-guide'))
                    <a href="{{ route('user-guide') }}" class="td-icon-btn" title="How to use My Digital Diary" aria-label="User guide"><i class="fa-regular fa-circle-question"></i></a>
                @endif
                @php
                    $bell = $notificationCenter ?? ['items'=>[], 'unread_count'=>0, 'action_count'=>0, 'badge_count'=>0];
                    $bellTones = [
                        'emerald'=>['#ecfdf5','#047857'], 'sky'=>['#f0f9ff','#0369a1'],
                        'violet'=>['#f5f3ff','#6d28d9'], 'amber'=>['#fffbeb','#b45309'],
                        'rose'=>['#fff1f2','#be123c'], 'slate'=>['#f8fafc','#475569'],
                    ];
                @endphp
                <details class="md-notif" id="dashboard-notification-bell">
                    <summary class="td-icon-btn" aria-label="Notifications" title="Notifications">
                        <i class="fa-regular fa-bell"></i>
                        @if(($bell['badge_count'] ?? 0) > 0)
                            <span class="md-notif-badge">{{ min(99, (int) $bell['badge_count']) }}{{ ($bell['badge_count'] ?? 0) > 99 ? '+' : '' }}</span>
                        @endif
                    </summary>
                    <div class="md-notif-panel">
                        <div class="md-notif-head">
                            <div>
                                <div class="md-notif-title">{{ in_array((string)auth()->user()->role, ['admin','super_admin'], true) ? 'Admin notifications' : 'Notifications' }}</div>
                                <div class="md-notif-sub">
                                    @if(in_array((string)auth()->user()->role, ['admin','super_admin'], true))
                                        {{ (int)($bell['action_count'] ?? 0) }} need attention · {{ (int)($bell['unread_count'] ?? 0) }} unread
                                    @else
                                        {{ (int)($bell['unread_count'] ?? 0) }} unread
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="md-notif-list">
                            @forelse($bell['items'] ?? [] as $notificationItem)
                                @php $nt = $bellTones[$notificationItem['tone'] ?? 'slate'] ?? $bellTones['slate']; @endphp
                                @if(($notificationItem['source'] ?? '') === 'database' && !empty($notificationItem['unread']))
                                    <form method="POST" action="{{ route('notifications.read', $notificationItem['database_id']) }}" class="m-0">
                                        @csrf
                                        <input type="hidden" name="redirect" value="{{ $notificationItem['url'] }}">
                                        <button type="submit" class="md-notif-item unread w-full text-left border-0">
                                @else
                                    <a href="{{ $notificationItem['url'] }}" class="md-notif-item {{ !empty($notificationItem['unread']) ? 'unread' : '' }}">
                                @endif
                                        <span class="md-notif-icon" style="background:{{ $nt[0] }};color:{{ $nt[1] }}"><i class="fa-solid {{ $notificationItem['icon'] ?? 'fa-bell' }}"></i></span>
                                        <span class="md-notif-copy">
                                            <span class="flex items-center gap-2 min-w-0">
                                                <span class="md-notif-item-title">{{ $notificationItem['title'] }}</span>
                                                @if(!empty($notificationItem['action_required']))<span class="md-notif-action">Action</span>@endif
                                            </span>
                                            <span class="md-notif-msg">{{ $notificationItem['message'] }}</span>
                                            <span class="md-notif-time">{{ optional($notificationItem['created_at'] ?? null)->diffForHumans() }}</span>
                                        </span>
                                        @if(!empty($notificationItem['unread']))<span class="md-notif-dot"></span>@endif
                                @if(($notificationItem['source'] ?? '') === 'database' && !empty($notificationItem['unread']))
                                        </button>
                                    </form>
                                @else
                                    </a>
                                @endif
                            @empty
                                <div class="md-notif-empty"><i class="fa-regular fa-bell-slash text-xl block mb-2"></i>You're all caught up.</div>
                            @endforelse
                        </div>
                        <div class="md-notif-foot">
                            <a href="{{ route('notifications.index') }}">View all</a>
                            @if(($bell['unread_count'] ?? 0) > 0)
                                <form method="POST" action="{{ route('notifications.read-all') }}" class="m-0">@csrf<button type="submit">Mark all read</button></form>
                            @endif
                        </div>
                    </div>
                </details>
            </div>
        </div>

        <div class="td-hero-body">
            <div class="td-ring" id="td-today-ring" style="--p:{{ $tdTaskPercent }}" role="img" aria-label="{{ $tdTaskDone }} of {{ $tdTaskTotal }} tasks done today">
                <div>
                    <div class="td-ring-num"><span id="td-done-count">{{ $tdTaskDone }}</span>/<span id="td-total-count">{{ $tdTaskTotal }}</span></div>
                    <div class="td-ring-sub">done today</div>
                </div>
            </div>

            <div class="min-w-0">
                <div class="td-streak">
                    <span class="td-streak-pill {{ $tdStreak > 0 ? '' : 'cold' }}" title="Best streak: {{ $bestGrowthStreak }} days">
                        <span class="td-flame" aria-hidden="true">🔥</span>
                        {{ $tdStreak }} day{{ $tdStreak === 1 ? '' : 's' }}
                    </span>
                    <div class="td-dots" aria-label="Active days this week">
                        @foreach ($tdWeekDots as $dot)
                            <div class="td-dot {{ $dot['active'] ? 'on' : '' }} {{ $dot['today'] ? 'today' : '' }}" title="{{ $dot['title'] }}{{ $dot['active'] ? ' · active' : '' }}">
                                <span></span>{{ $dot['label'] }}
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="td-nudge" id="td-nudge">
                    @if ($tdActiveToday)
                        @if ($tdTaskTotal > 0 && $tdTaskDone >= $tdTaskTotal)
                            Everything done. Enjoy your evening.
                        @else
                            You showed up today. Keep going.
                        @endif
                    @elseif ($tdStreakAlive && $growthStreak > 0)
                        Tick off one task to keep your streak.
                    @else
                        One small action today starts a streak.
                    @endif
                </div>
                <div class="td-routine">
                    <button type="button" class="td-btn {{ $startDayCompleted ? 'td-btn-done' : (! $tdEvening ? 'td-btn-primary' : 'td-btn-ghost') }}" data-routine-stats="start">
                        <i class="fa-solid {{ $startDayCompleted ? 'fa-circle-check' : 'fa-sun' }}"></i> Start my day
                    </button>
                    <button type="button" class="td-btn {{ $closeDayCompleted ? 'td-btn-done' : ($tdEvening ? 'td-btn-primary' : 'td-btn-ghost') }}" data-routine-stats="end">
                        <i class="fa-solid {{ $closeDayCompleted ? 'fa-circle-check' : 'fa-moon' }}"></i> Close my day
                    </button>
                </div>
            </div>
        </div>

        @if ($engagementCelebration)
            <div class="td-celebrate">{{ data_get($engagementCelebration, 'title') }}</div>
        @endif
    </section>

    {{-- 2. Get started (first-run, dismissible) --}}
    <section class="td-start td-section" id="td-get-started" hidden
             data-server-done="{{ $tdGetStartedDone }}" data-server-total="{{ count($tdGetStarted) }}">
        <div class="flex items-center justify-between gap-3">
            <div class="td-h2"><i class="fa-solid fa-rocket"></i> Get started <span class="td-more-badge" id="td-start-count"></span></div>
            <button type="button" class="td-link" id="td-start-dismiss" style="background:none;border:0;cursor:pointer">Hide</button>
        </div>
        <div class="td-start-bar"><span id="td-start-bar" style="width:0%"></span></div>
        <div class="td-start-list">
            @foreach ($tdGetStarted as $step)
                @if ($step['url'])
                    <a href="{{ $step['url'] }}" class="td-step {{ $step['done'] ? 'done' : '' }}" data-step="{{ $step['key'] }}" data-done="{{ $step['done'] ? '1' : '0' }}">
                        <i class="fa-solid {{ $step['done'] ? 'fa-check' : $step['icon'] }} td-step-ic"></i>
                        <span>{{ $step['label'] }}</span>
                    </a>
                @else
                    <button type="button" class="td-step {{ $step['done'] ? 'done' : '' }}" data-step="{{ $step['key'] }}" data-done="{{ $step['done'] ? '1' : '0' }}" id="td-step-{{ $step['key'] }}">
                        <i class="fa-solid {{ $step['done'] ? 'fa-check' : $step['icon'] }} td-step-ic"></i>
                        <span>{{ $step['label'] }}</span>
                    </button>
                @endif
            @endforeach
            <button type="button" class="td-step" data-step="install" data-done="0" id="td-step-install">
                <i class="fa-solid fa-mobile-screen td-step-ic"></i>
                <span>Install the app</span>
            </button>
        </div>
        <p class="td-start-msg" id="td-start-msg" role="status" hidden></p>
    </section>

    {{-- 3. Today: tasks + coming up --}}
    <div class="td-grid td-section">
        <section class="td-card td-pad" id="dashboard-tabbed-sections" aria-labelledby="td-tasks-title">
            <div class="td-head">
                <h2 class="td-h2" id="td-tasks-title"><i class="fa-solid fa-list-check"></i> Today’s tasks</h2>
                <a href="{{ route('daily-planner.index', ['new' => 1]) }}" class="td-link"><i class="fa-solid fa-plus mr-1"></i>Add</a>
            </div>

            @if ($tdTasks->isNotEmpty())
                <ul class="td-tasks" id="td-task-list">
                    @foreach ($tdTasks as $item)
                        @php
                            $isPlannerItem = $item instanceof \App\Models\DailyPlanItem && $item->id;
                            $taskTitle = data_get($item, 'title') ?? data_get($item, 'name') ?? data_get($item, 'task') ?? 'Daily task';
                            $taskTime = data_get($item, 'start_time') ?? data_get($item, 'due_time') ?? data_get($item, 'time');
                            try {
                                $taskTimeLabel = $taskTime ? \Illuminate\Support\Carbon::parse($taskTime)->format('g:i A') : 'Anytime';
                            } catch (\Throwable $e) {
                                $taskTimeLabel = (string) $taskTime;
                            }
                            $taskPriority = data_get($item, 'priority');
                        @endphp
                        <li class="td-task">
                            @if ($isPlannerItem)
                                <button type="button" class="td-check" aria-pressed="false"
                                        aria-label="Mark “{{ $taskTitle }}” done"
                                        data-toggle-url="{{ route('daily-planner.items.toggle', $item->id) }}"
                                        data-date="{{ substr((string) (data_get($item, 'occurrence_date') ?: $tdToday), 0, 10) }}">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                            @else
                                <span class="td-check" aria-hidden="true"></span>
                            @endif
                            <a href="{{ route('daily-planner.index') }}" class="td-task-copy">
                                <div class="td-task-title" title="{{ $taskTitle }}">{{ $taskTitle }}</div>
                                <div class="td-task-meta">
                                    <span class="td-prio" style="background:{{ $tdPriorityColor($taskPriority) }}" title="{{ ucfirst((string) ($taskPriority ?: 'normal')) }} priority"></span>
                                    {{ $taskTimeLabel }}
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="td-alldone" id="td-alldone"><i class="fa-solid fa-champagne-glasses"></i> All done for now. Nice work!</div>
                @if ($todayFocus->count() > $tdTasks->count() || $tdTaskTotal - $tdTaskDone > $tdTasks->count())
                    <div class="mt-3 text-center"><a href="{{ route('daily-planner.index') }}" class="td-link">See all in planner <i class="fa-solid fa-arrow-right ml-1"></i></a></div>
                @endif
            @elseif ($tdTaskTotal > 0)
                <div class="td-empty">
                    <i class="fa-solid fa-champagne-glasses" style="color:#16a34a"></i>
                    <p>All of today’s tasks are done.</p>
                    <a href="{{ route('daily-planner.index') }}" class="td-btn td-btn-ghost">Plan tomorrow</a>
                </div>
            @else
                <div class="td-empty">
                    <i class="fa-regular fa-calendar-plus"></i>
                    <p>No tasks yet. What’s one thing you want done today?</p>
                    <a href="{{ route('daily-planner.index', ['new' => 1]) }}" class="td-btn td-btn-primary"><i class="fa-solid fa-plus"></i> Add a task</a>
                </div>
            @endif
        </section>

        <div class="grid gap-4">
            <section class="td-card td-pad" aria-labelledby="td-agenda-title">
                <div class="td-head">
                    <h2 class="td-h2" id="td-agenda-title"><i class="fa-regular fa-clock"></i> Coming up</h2>
                    <a href="{{ route('reminders.index') }}" class="td-link">Reminders</a>
                </div>
                @if ($tdAgenda->isNotEmpty())
                    <ul class="td-agenda">
                        @foreach ($tdAgenda->take(4) as $row)
                            @php $tone = $toneMap[$row['tone']] ?? $toneMap['slate']; @endphp
                            <li>
                                <a href="{{ $row['url'] }}">
                                    <span class="td-agenda-ic" style="background:{{ $tone['bg'] }};color:{{ $tone['fg'] }}"><i class="fa-solid {{ $row['icon'] }}"></i></span>
                                    <span class="td-agenda-title">{{ $row['title'] }}</span>
                                    <span class="td-agenda-time">{{ $row['time'] ? $row['time']->format('g:i A') : 'Today' }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    @if ($tdAgenda->count() > 4)
                        <div class="mt-2 text-xs text-slate-500 text-center">+{{ $tdAgenda->count() - 4 }} more today</div>
                    @endif
                @else
                    <div class="td-empty" style="padding:16px 12px">
                        <i class="fa-regular fa-bell"></i>
                        <p>Nothing else today.</p>
                        <a href="{{ route('reminders.index', ['new' => 1]) }}" class="td-link"><i class="fa-solid fa-plus mr-1"></i>Set a reminder</a>
                    </div>
                @endif
            </section>

            <section class="td-insight" style="--insight-bg:{{ $insightTone['bg'] }};--insight-fg:{{ $insightTone['fg'] }};--insight-border:{{ $insightTone['border'] }}">
                <div class="td-insight-ic"><i class="fa-solid {{ $dailyInsight['icon'] ?? 'fa-lightbulb' }}"></i></div>
                <div class="min-w-0 flex-1">
                    <div class="td-insight-kicker">Today’s insight</div>
                    <div class="td-insight-title">{{ $dailyInsight['title'] ?? 'Your insight is on its way.' }}</div>
                    @if (! empty($dailyInsight['message']))
                        <div class="td-insight-msg">{{ $dailyInsight['message'] }}</div>
                    @endif
                    <div class="td-insight-foot">
                        <a href="{{ $dailyInsight['route'] ?? route('daily-planner.index') }}">{{ $dailyInsight['action'] ?? 'Open planner' }} <i class="fa-solid fa-arrow-right ml-1"></i></a>
                        @if (Route::has('dashboard.today-insight.refresh'))
                            <form method="POST" action="{{ route('dashboard.today-insight.refresh') }}" class="inline m-0">
                                @csrf
                                <button type="submit" title="New insight" aria-label="Refresh insight"><i class="fa-solid fa-rotate"></i></button>
                            </form>
                        @endif
                    </div>
                </div>
            </section>

            @if ($tdMemories->isNotEmpty())
                <section class="td-memory" aria-labelledby="td-memory-title">
                    <div class="td-head" style="margin-bottom:0">
                        <h2 class="td-h2" id="td-memory-title"><i class="fa-solid fa-clock-rotate-left" style="color:#d97706"></i> On this day</h2>
                        @if ($tdMemoryMore)
                            <a href="{{ $tdMemoryMore }}" class="td-link">See more</a>
                        @endif
                    </div>
                    <ul class="td-memory-list">
                        @foreach ($tdMemories as $memory)
                            @php $tone = $toneMap[$memory['tone']] ?? $toneMap['slate']; @endphp
                            <li>
                                @if (! empty($memory['url']))
                                    <a href="{{ $memory['url'] }}" class="td-memory-item">
                                @else
                                    <div class="td-memory-item">
                                @endif
                                    <span class="td-memory-ic" style="background:{{ $tone['bg'] }};color:{{ $tone['fg'] }}"><i class="fa-solid {{ $memory['icon'] }}"></i></span>
                                    <span class="min-w-0">
                                        <span class="td-memory-when">{{ $memory['when'] }}</span>
                                        <span class="td-memory-text">{{ $memory['text'] }}</span>
                                    </span>
                                @if (! empty($memory['url']))
                                    </a>
                                @else
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($tdMemoryComparison)
                        <p class="td-memory-note">{{ $tdMemoryComparison }}</p>
                    @endif
                </section>
            @endif
        </div>
    </div>

    {{-- 4. Progress at a glance --}}
    <section class="td-section" aria-labelledby="td-progress-title">
        <div class="td-head">
            <h2 class="td-h2" id="td-progress-title"><i class="fa-solid fa-chart-simple"></i> Progress</h2>
            @if (Route::has('monthly-review'))
                <a href="{{ route('monthly-review') }}" class="td-link">Month in review</a>
            @endif
        </div>
        <div class="td-rings">
            @foreach ($tdRings as $ring)
                <a href="{{ $ring['url'] }}" class="td-ring-card {{ $ring['empty'] ? 'empty' : '' }}" title="{{ $ring['hint'] }}">
                    <div class="td-ring" style="--p:{{ $ring['empty'] ? 0 : min(100, max(0, $ring['value'])) }};--c:{{ $ring['color'] }}">
                        <div class="td-ring-num">
                            @if ($ring['empty'])
                                <i class="fa-solid fa-plus" style="color:{{ $ring['color'] }}"></i>
                            @else
                                {{ $ring['value'] }}{{ $ring['label'] === 'Money health' ? '' : '%' }}
                            @endif
                        </div>
                    </div>
                    <div class="min-w-0">
                        <div class="td-ring-label">{{ $ring['label'] }}</div>
                        <div class="td-ring-text">{{ $ring['text'] }}</div>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <div class="td-section">
        @include('dashboard.partials.live-steps-card', ['stepData' => $stepData ?? []])
    </div>

    {{-- 5. Everything else, one tap away --}}
    <section class="td-card td-pad td-section" aria-labelledby="td-apps-title">
        <div class="td-head">
            <h2 class="td-h2" id="td-apps-title"><i class="fa-solid fa-grip"></i> Apps</h2>
        </div>
        <nav class="td-launcher" id="td-launcher" aria-label="All features">
            @foreach ($tdLauncher as $tool)
                <a href="{{ route($tool[3]) }}" class="td-app" style="--app-c:{{ $tool[2] }}">
                    <span class="td-app-ic"><i class="fa-solid {{ $tool[1] }}"></i></span>
                    <span class="td-app-name">{{ $tool[0] }}</span>
                </a>
            @endforeach
        </nav>
        <div class="td-more-apps">
            <button type="button" class="td-btn td-btn-ghost" id="td-more-apps" aria-controls="td-launcher" aria-expanded="false">
                <i class="fa-solid fa-ellipsis"></i> <span>All apps</span>
            </button>
        </div>
    </section>

    <div class="td-section" id="td-more-sections">
        <details class="td-more" data-td-more="money">
            <summary>
                <span class="td-more-ic"><i class="fa-solid fa-wallet"></i></span>
                <span class="td-more-title">Money this month</span>
                <span class="td-more-badge">{{ $money($monthlyExpenses ?? 0) }} spent</span>
                <i class="fa-solid fa-chevron-down td-more-chev"></i>
            </summary>
            <div class="td-more-body">
                <div class="md-finance-grid">
                    @foreach($financeCards as $card)
                        <a href="{{ $card['route'] }}" class="md-finance-card {{ $card['theme'] }}">
                            <div class="md-finance-name"><i class="fa-solid {{ $card['icon'] }}"></i>{{ $card['title'] }}</div>
                            <div class="md-finance-value">{{ $money($card['monthly']) }}</div>
                            <div class="md-finance-small">Overall {{ $money($card['overall']) }}</div>
                        </a>
                    @endforeach
                </div>
            </div>
        </details>

        <details class="td-more" data-td-more="actions">
            <summary>
                <span class="td-more-ic"><i class="fa-solid fa-compass"></i></span>
                <span class="td-more-title">Suggested next steps</span>
                @if ($nextActions->isNotEmpty())<span class="td-more-badge">{{ $nextActions->count() }}</span>@endif
                <i class="fa-solid fa-chevron-down td-more-chev"></i>
            </summary>
            <div class="td-more-body">
                @if ($nextActions->isNotEmpty())
                    <div class="td-list-links">
                        @foreach ($nextActions as $action)
                            @php $routeName = $action['route'] ?? null; @endphp
                            <a href="{{ $routeName && Route::has($routeName) ? route($routeName) : (Route::has('goal-intelligence') ? route('goal-intelligence') : route('personal-goals.index')) }}">
                                <i class="fa-solid {{ ($action['state'] ?? '') === 'overdue' ? 'fa-triangle-exclamation text-rose-500' : 'fa-arrow-trend-up text-amber-500' }}"></i>
                                <span class="min-w-0">{{ $action['title'] ?? 'Next action' }}@if (! empty($action['message']))<small>{{ $action['message'] }}</small>@endif</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-500">Nothing urgent. You’re on track.</p>
                @endif
            </div>
        </details>

        <details class="td-more" data-td-more="reviews">
            <summary>
                <span class="td-more-ic"><i class="fa-solid fa-chart-line"></i></span>
                <span class="td-more-title">Reviews</span>
                <i class="fa-solid fa-chevron-down td-more-chev"></i>
            </summary>
            <div class="td-more-body">
                <div class="td-list-links">
                    <button type="button" data-engagement-review="week"><i class="fa-solid fa-calendar-week text-violet-600"></i><span>My week in review</span></button>
                    <button type="button" data-engagement-review="month"><i class="fa-solid fa-share-nodes text-sky-600"></i><span>My month in review</span></button>
                    <a href="{{ route('activity') }}"><i class="fa-solid fa-clock-rotate-left text-slate-500"></i><span>Recent activity</span></a>
                </div>
            </div>
        </details>

        @if(!empty($growth))
            @php
                $growthActivation = is_array(data_get($growth, 'activation')) ? data_get($growth, 'activation') : [];
                $growthChallenge = is_array(data_get($growth, 'challenge')) ? data_get($growth, 'challenge') : [];
                $growthReferral = is_array(data_get($growth, 'referral')) ? data_get($growth, 'referral') : [];
                $growthChallengePercent = min(100, max(0, (int) data_get($growthChallenge, 'progress_percent', 0)));
            @endphp
            <details class="td-more" data-td-more="growth" id="growth-strategy-section">
                <summary>
                    <span class="td-more-ic" style="color:#d97706;background:#fffbeb"><i class="fa-solid fa-flag-checkered"></i></span>
                    <span class="td-more-title">30-day challenge &amp; invites</span>
                    @if (data_get($growthChallenge, 'joined'))<span class="td-more-badge">{{ $growthChallengePercent }}%</span>@endif
                    <i class="fa-solid fa-chevron-down td-more-chev"></i>
                </summary>
                <div class="td-more-body">
                    <div class="td-growth">
                        <article class="td-growth-card">
                            <div class="td-growth-kicker">30-day challenge</div>
                            <div class="td-growth-title">{{ data_get($growthChallenge, 'title', '30 Days With My Digital Diary') }}</div>
                            @if(data_get($growthChallenge, 'joined'))
                                <div class="td-bar" style="--bar:#7c3aed"><span style="width:{{ $growthChallengePercent }}%"></span></div>
                                <div class="td-growth-step">{{ (int) data_get($growthChallenge, 'meaningful_days', 0) }} of 30 days</div>
                            @elseif(Route::has('growth.challenge.join'))
                                <form method="POST" action="{{ route('growth.challenge.join') }}">
                                    @csrf
                                    <button type="submit" class="md-growth-action violet"><i class="fa-solid fa-flag-checkered"></i> Join</button>
                                </form>
                            @endif
                        </article>

                        <article class="td-growth-card">
                            <div class="td-growth-kicker">Setup</div>
                            <div class="td-growth-title">{{ (int) data_get($growthActivation, 'completed', 0) }}/{{ (int) data_get($growthActivation, 'total', 3) }} done</div>
                            <div class="td-bar"><span style="width:{{ min(100, max(0, (int) data_get($growthActivation, 'percent', 0))) }}%"></span></div>
                            @foreach(data_get($growthActivation, 'steps', []) as $step)
                                @php $step = is_array($step) ? $step : (array) $step; @endphp
                                <div class="td-growth-step {{ !empty($step['complete']) ? 'complete' : '' }}">
                                    <i class="fa-solid {{ !empty($step['complete']) ? 'fa-circle-check' : 'fa-circle' }}"></i>{{ $step['label'] ?? 'Setup step' }}
                                </div>
                            @endforeach
                        </article>

                        <article class="td-growth-card">
                            <div class="flex items-center gap-2">
                                @if($dashboardSystemLogoUrl)
                                    <img src="{{ $dashboardSystemLogoUrl }}" alt="" class="w-6 h-6 object-contain">
                                @endif
                                <div class="td-growth-kicker">Invite a friend</div>
                            </div>
                            <div class="td-growth-title">{{ (int) data_get($growthReferral, 'conversions', 0) }} joined from your invites</div>
                            @if(Route::has('growth.referral'))
                                <button type="button" id="md-growth-referral-button" class="md-growth-action sky" data-referral-url="{{ route('growth.referral') }}">
                                    <i class="fa-solid fa-share-nodes"></i> Invite
                                </button>
                            @endif
                        </article>
                    </div>
                    <div class="td-private"><i class="fa-solid fa-lock"></i> Your diary and money records are never shared.</div>
                </div>
            </details>
        @endif
    </div>
</div>

<dialog id="md-engagement-checkin-modal" class="md-engagement-dialog">
    <form method="dialog" id="md-engagement-checkin-form">
        <div class="md-engagement-dialog-head">
            <div>
                <div class="md-engagement-dialog-title" id="md-engagement-checkin-title">Start My Day</div>
                <div class="md-engagement-dialog-sub" id="md-engagement-checkin-subtitle">Choose what matters most today.</div>
            </div>
            <button type="button" class="md-engagement-dialog-close" data-engagement-dialog-close aria-label="Close">&times;</button>
        </div>
        <div class="md-engagement-dialog-body">
            <div class="md-engagement-fields">
                <div class="md-engagement-field" id="md-engagement-reflection-wrap">
                    <label for="md-engagement-reflection">Reflection / accomplishment</label>
                    <textarea id="md-engagement-reflection" class="pm-input" rows="3" placeholder="What matters most today?"></textarea>
                </div>
                <div class="md-engagement-field hidden" id="md-engagement-gratitude-wrap">
                    <label for="md-engagement-gratitude">Gratitude</label>
                    <textarea id="md-engagement-gratitude" class="pm-input" rows="2" placeholder="What are you grateful for today?"></textarea>
                </div>
                <div class="md-engagement-field hidden" id="md-engagement-tomorrow-wrap">
                    <label for="md-engagement-tomorrow">Tomorrow’s first priority</label>
                    <textarea id="md-engagement-tomorrow" class="pm-input" rows="2" placeholder="What should matter first tomorrow?"></textarea>
                </div>
            </div>
        </div>
        <div class="md-engagement-dialog-foot">
            <button type="button" class="apple-btn apple-btn-small" data-engagement-dialog-close>Cancel</button>
            <button type="button" class="btn-primary text-white px-4 py-2 rounded-lg text-sm font-bold" id="md-engagement-save-checkin">
                Save
            </button>
        </div>
    </form>
</dialog>

<dialog id="md-engagement-review-modal" class="md-engagement-dialog">
    <div class="md-engagement-dialog-head">
        <div>
            <div class="md-engagement-dialog-title" id="md-engagement-review-title">My Week in Review</div>
            <div class="md-engagement-dialog-sub">Progress worth noticing and sharing.</div>
        </div>
        <button type="button" class="md-engagement-dialog-close" data-engagement-review-close aria-label="Close">&times;</button>
    </div>
    <div class="md-engagement-dialog-body">
        <div id="md-engagement-review-content" class="md-review-metrics"></div>
    </div>
    <div class="md-engagement-dialog-foot">
        <button type="button" class="apple-btn apple-btn-small" data-engagement-review-close>Close</button>
        <button type="button" class="btn-primary text-white px-4 py-2 rounded-lg text-sm font-bold" id="md-engagement-share-review">
            <i class="fa-solid fa-share-nodes mr-1"></i> Share
        </button>
    </div>
</dialog>

<script id="md-engagement-runtime">
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const checkinModal = document.getElementById('md-engagement-checkin-modal');
    const reviewModal = document.getElementById('md-engagement-review-modal');
    const checkinTitle = document.getElementById('md-engagement-checkin-title');
    const checkinSubtitle = document.getElementById('md-engagement-checkin-subtitle');
    const reflection = document.getElementById('md-engagement-reflection');
    const gratitude = document.getElementById('md-engagement-gratitude');
    const tomorrow = document.getElementById('md-engagement-tomorrow');
    const gratitudeWrap = document.getElementById('md-engagement-gratitude-wrap');
    const tomorrowWrap = document.getElementById('md-engagement-tomorrow-wrap');
    const saveCheckin = document.getElementById('md-engagement-save-checkin');
    const reviewTitle = document.getElementById('md-engagement-review-title');
    const reviewContent = document.getElementById('md-engagement-review-content');
    const shareReview = document.getElementById('md-engagement-share-review');

    let checkinType = 'start-day';
    let shareText = '';

    function openCheckin(type) {
        if (!checkinModal) return;

        checkinType = type;
        const closing = type === 'close-day';

        checkinTitle.textContent = closing ? 'Close My Day' : 'Start My Day';
        checkinSubtitle.textContent = closing
            ? 'Take 60 seconds to close today and prepare tomorrow.'
            : 'Choose the outcome that deserves your best attention today.';

        reflection.placeholder = closing
            ? 'What moved forward today?'
            : 'What matters most today?';

        gratitudeWrap.classList.toggle('hidden', !closing);
        tomorrowWrap.classList.toggle('hidden', !closing);

        reflection.value = '';
        gratitude.value = '';
        tomorrow.value = '';

        checkinModal.showModal();
    }

    async function saveDailyCheckin() {
        const payload = {};

        if (reflection.value.trim()) payload.reflection = reflection.value.trim();

        if (checkinType === 'close-day') {
            if (gratitude.value.trim()) payload.gratitude = gratitude.value.trim();
            if (tomorrow.value.trim()) payload.tomorrow_focus = tomorrow.value.trim();
        }

        saveCheckin.disabled = true;
        const original = saveCheckin.innerHTML;
        saveCheckin.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Saving';

        try {
            const response = await fetch(`/engagement/checkin/${checkinType}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message || 'Could not save your daily check-in.');
            }

            checkinModal.close();
            window.location.reload();
        } catch (error) {
            alert(error.message || 'Could not save your daily check-in.');
            saveCheckin.disabled = false;
            saveCheckin.innerHTML = original;
        }
    }

    function metric(label, value) {
        return `
            <div class="md-review-metric">
                <div class="md-review-metric-label">${label}</div>
                <div class="md-review-metric-value">${value}</div>
            </div>
        `;
    }

    async function openReview(period) {
        if (!reviewModal) return;

        try {
            const response = await fetch(
                `/engagement/review/${period}?_=${Date.now()}`,
                {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/json',
                        'Cache-Control': 'no-cache'
                    }
                }
            );

            if (!response.ok) throw new Error('Could not load your review.');

            const json = await response.json();
            const data = json.data || {};
            const streak = data.streak || {};
            const title = period === 'week' ? 'My Week in Review' : 'My Month in Review';

            reviewTitle.textContent = title;

            const metrics = [
                ['Tasks', `${data.tasks_completed || 0}/${data.tasks_total || 0}`],
                ['Completion', `${data.completion_percent || 0}%`],
                ['Meaningful days', `${data.meaningful_days || 0}`],
                ['Exercise', `${data.exercise_sessions || 0} sessions`],
                ['Income', `UGX ${Number(data.income || 0).toLocaleString()}`],
                ['Expenses', `UGX ${Number(data.expenses || 0).toLocaleString()}`],
                ['Current streak', `${streak.current || 0} days`],
                ['Best streak', `${streak.best || 0} days`]
            ];

            reviewContent.innerHTML = metrics.map(item => metric(item[0], item[1])).join('');

            shareText = [
                title,
                '',
                ...metrics.slice(0, 4).map(item => `${item[0]}: ${item[1]}`),
                '',
                'My Digital Diary'
            ].join('\n');

            reviewModal.showModal();
        } catch (error) {
            alert(error.message || 'Could not load your review.');
        }
    }

    window.mdOpenDailyCheckin = openCheckin;

    document.querySelectorAll('[data-engagement-checkin]').forEach(button => {
        button.addEventListener('click', () => openCheckin(button.dataset.engagementCheckin));
    });

    document.querySelectorAll('[data-engagement-review]').forEach(button => {
        button.addEventListener('click', () => openReview(button.dataset.engagementReview));
    });

    document.querySelectorAll('[data-engagement-dialog-close]').forEach(button => {
        button.addEventListener('click', () => checkinModal?.close());
    });

    document.querySelectorAll('[data-engagement-review-close]').forEach(button => {
        button.addEventListener('click', () => reviewModal?.close());
    });

    saveCheckin?.addEventListener('click', saveDailyCheckin);

    shareReview?.addEventListener('click', async () => {
        if (!shareText) return;

        if (navigator.share) {
            try {
                await navigator.share({
                    title: reviewTitle?.textContent || 'My Digital Diary',
                    text: shareText
                });
                return;
            } catch (_) {}
        }

        try {
            await navigator.clipboard.writeText(shareText);
            const original = shareReview.innerHTML;
            shareReview.innerHTML = '<i class="fa-solid fa-check mr-1"></i> Copied';
            setTimeout(() => shareReview.innerHTML = original, 1500);
        } catch (_) {
            alert(shareText);
        }
    });
})();
</script>


<script id="md-growth-runtime">
(function () {
    'use strict';

    const button = document.getElementById('md-growth-referral-button');
    if (!button) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    button.addEventListener('click', async () => {
        const endpoint = button.dataset.referralUrl;
        if (!endpoint) return;

        button.disabled = true;
        const original = button.innerHTML;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Preparing';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({channel: 'web'})
            });

            if (!response.ok) {
                const json = await response.json().catch(() => ({}));
                throw new Error(json.message || 'Could not create your invite.');
            }

            const json = await response.json();
            const data = json.data || {};
            const shareText = [
                data.share_text || 'Join me on My Digital Diary.',
                data.url || ''
            ].filter(Boolean).join('\n');

            if (navigator.share) {
                try {
                    await navigator.share({
                        title: 'My Digital Diary',
                        text: shareText
                    });
                    return;
                } catch (_) {}
            }

            try {
                await navigator.clipboard.writeText(shareText);
                button.innerHTML = '<i class="fa-solid fa-check"></i> Invite copied';
                setTimeout(() => button.innerHTML = original, 1600);
            } catch (_) {
                window.prompt('Copy your invite:', shareText);
            }
        } catch (error) {
            alert(error.message || 'Could not create your invite.');
        } finally {
            button.disabled = false;
            if (!button.innerHTML.includes('Invite copied')) {
                button.innerHTML = original;
            }
        }
    });
})();
</script>

@php
    // Pre-compute simple scalar arrays before JSON serialization. Keeping
    // method calls and nested expressions out of @json prevents Blade parser
    // issues and makes the dashboard safe even when routine data is empty.
    $startDaySummary = is_array($startDaySummary ?? null) ? $startDaySummary : [];
    $endDaySummary = is_array($endDaySummary ?? null) ? $endDaySummary : [];

    $startDayPopupStats = [
        'priorities' => collect($startDaySummary['priorities'] ?? [])->count(),
        'meetings' => collect($startDaySummary['meetings'] ?? [])->count(),
        'tasks_due' => collect($startDaySummary['tasks_due'] ?? [])->count(),
        'reminders' => collect($startDaySummary['reminders'] ?? [])->count(),
        'relationships' => collect($startDaySummary['relationships'] ?? [])->count(),
        'debts_due' => collect(data_get($startDaySummary, 'financial_commitments.debts_due', []))->count(),
        'savings_goals' => collect(data_get($startDaySummary, 'financial_commitments.savings_goals', []))->count(),
        'completed' => (bool) ($startDayCompleted ?? false),
    ];

    $endDayPopupStats = [
        'completed_tasks' => collect($endDaySummary['completed_tasks'] ?? [])->count(),
        'incomplete_tasks' => collect($endDaySummary['incomplete_tasks'] ?? [])->count(),
        'meetings_completed' => collect($endDaySummary['meetings_completed'] ?? [])->count(),
        'income_today' => (float) ($endDaySummary['income_today'] ?? 0),
        'expenses_today' => (float) ($endDaySummary['expenses_today'] ?? 0),
        'completed' => (bool) ($closeDayCompleted ?? false),
    ];
@endphp

<dialog id="md-routine-stats-modal" class="rounded-2xl p-0 w-[min(94vw,620px)] backdrop:bg-slate-900/50">
    <div class="p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <div id="md-routine-stats-kicker" class="text-xs font-bold uppercase tracking-wide text-slate-500">Daily rhythm</div>
                <h3 id="md-routine-stats-title" class="text-xl font-black text-slate-900 mt-1">Today Statistics</h3>
                <p id="md-routine-stats-copy" class="text-sm text-slate-500 mt-1"></p>
            </div>
            <button type="button" id="md-routine-stats-close" class="w-9 h-9 rounded-lg border bg-white">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div id="md-routine-stats-grid" class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-5"></div>

        <div class="flex flex-wrap justify-end gap-2 mt-5">
            <button type="button" id="md-routine-stats-checkin" class="px-4 py-2.5 rounded-lg bg-slate-900 text-white font-bold">
                Continue
            </button>
        </div>
    </div>
</dialog>

<script id="md-routine-stats-runtime">
(() => {
    const modal = document.getElementById('md-routine-stats-modal');
    const title = document.getElementById('md-routine-stats-title');
    const copy = document.getElementById('md-routine-stats-copy');
    const grid = document.getElementById('md-routine-stats-grid');
    const close = document.getElementById('md-routine-stats-close');
    const checkin = document.getElementById('md-routine-stats-checkin');

    if (!modal || !title || !copy || !grid || !checkin) return;

    const start = @json($startDayPopupStats);
    const end = @json($endDayPopupStats);

    const money = value => `UGX ${Number(value || 0).toLocaleString()}`;
    let current = 'start';

    function card(label, value) {
        return `<div class="rounded-xl border bg-slate-50 p-3">
            <div class="text-[11px] text-slate-500">${label}</div>
            <div class="font-black text-lg text-slate-900 mt-1">${value}</div>
        </div>`;
    }

    function openStats(type) {
        current = type;
        const isStart = type === 'start';
        const data = isStart ? start : end;

        title.textContent = isStart ? 'Start My Day Statistics' : 'Close My Day Statistics';
        copy.textContent = isStart
            ? 'See what is waiting for you today, including recurring Daily Planner tasks.'
            : 'See how today finished before recording your reflection.';

        grid.innerHTML = isStart
            ? [
                card('Priorities', data.priorities),
                card('Meetings', data.meetings),
                card('Project tasks', data.tasks_due),
                card('Reminders', data.reminders),
                card('Follow-ups', data.relationships),
                card('Debts due', data.debts_due),
                card('Savings goals', data.savings_goals),
              ].join('')
            : [
                card('Completed', data.completed_tasks),
                card('Carry forward', data.incomplete_tasks),
                card('Meetings done', data.meetings_completed),
                card('Income today', money(data.income_today)),
                card('Expenses today', money(data.expenses_today)),
              ].join('');

        checkin.innerHTML = data.completed
            ? '<i class="fa-solid fa-check mr-1"></i> Already completed'
            : (isStart
                ? '<i class="fa-solid fa-sun mr-1"></i> Start My Day'
                : '<i class="fa-solid fa-moon mr-1"></i> Close My Day');
        checkin.disabled = !!data.completed;

        modal.showModal();
    }

    document.querySelectorAll('[data-routine-stats]').forEach(element => {
        element.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();
            openStats(element.dataset.routineStats);
        });
        element.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openStats(element.dataset.routineStats);
            }
        });
    });

    close?.addEventListener('click', () => modal.close());

    // Deep link from the morning / evening phone reminder:
    // /dashboard?routine=start (Plan your day) or ?routine=close (Close your day).
    try {
        const url = new URL(window.location.href);
        const routine = url.searchParams.get('routine');
        if (routine === 'start' || routine === 'close') {
            url.searchParams.delete('routine');
            window.history.replaceState(null, '', url.pathname + url.search + url.hash);
            openStats(routine === 'start' ? 'start' : 'end');
        }
    } catch (_) {}

    checkin.addEventListener('click', () => {
        if (checkin.disabled) return;
        modal.close();
        window.mdOpenDailyCheckin?.(current === 'start' ? 'start-day' : 'close-day');
    });
})();
</script>

<script id="td-today-runtime">
(() => {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const store = {
        get(key) { try { return localStorage.getItem(key); } catch (_) { return null; } },
        set(key, value) { try { localStorage.setItem(key, value); } catch (_) {} },
    };

    /* ---- Quick check-off ------------------------------------------------ */
    const ring = document.getElementById('td-today-ring');
    const doneEl = document.getElementById('td-done-count');
    const totalEl = document.getElementById('td-total-count');
    const allDone = document.getElementById('td-alldone');
    const nudge = document.getElementById('td-nudge');

    function updateCounts(delta) {
        if (!doneEl || !totalEl) return;
        const total = Number(totalEl.textContent) || 0;
        const done = Math.max(0, Math.min(total, (Number(doneEl.textContent) || 0) + delta));
        doneEl.textContent = done;
        if (ring) {
            ring.style.setProperty('--p', total ? Math.round((done / total) * 100) : 0);
            ring.setAttribute('aria-label', `${done} of ${total} tasks done today`);
        }
        const open = document.querySelectorAll('#td-task-list .td-task:not(.is-done)').length;
        allDone?.classList.toggle('show', open === 0);
        if (nudge && delta > 0) nudge.textContent = open === 0 ? 'Everything done. Enjoy the rest of your day.' : 'Nice. Keep the momentum going.';
    }

    document.querySelectorAll('.td-check[data-toggle-url]').forEach(button => {
        button.addEventListener('click', async () => {
            if (button.disabled) return;
            const row = button.closest('.td-task');
            const wasDone = row.classList.contains('is-done');

            // Optimistic: feel instant, roll back if the server says no.
            row.classList.toggle('is-done', !wasDone);
            button.setAttribute('aria-pressed', String(!wasDone));
            updateCounts(wasDone ? -1 : 1);
            button.disabled = true;

            try {
                const response = await fetch(button.dataset.toggleUrl, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ occurrence_date: button.dataset.date, respond: 'json' }),
                });
                if (!response.ok) throw new Error('toggle failed');
            } catch (_) {
                row.classList.toggle('is-done', wasDone);
                button.setAttribute('aria-pressed', String(wasDone));
                updateCounts(wasDone ? 1 : -1);
                alert('Could not update that task. Please try again.');
            } finally {
                button.disabled = false;
            }
        });
    });

    /* ---- Get started checklist ------------------------------------------ */
    const start = document.getElementById('td-get-started');
    const DISMISS_KEY = 'md.getStarted.dismissed';

    function pwaInstalled() {
        const api = window.pmPwa;
        if (store.get('pm_pwa_installed') === '1') return true;
        try { return !!(api && api.state().installed); } catch (_) { return false; }
    }

    function renderStart() {
        if (!start) return;
        const installStep = document.getElementById('td-step-install');
        const installed = pwaInstalled();
        if (installStep) {
            installStep.dataset.done = installed ? '1' : '0';
            installStep.classList.toggle('done', installed);
            const icon = installStep.querySelector('.td-step-ic');
            if (icon) icon.className = `fa-solid ${installed ? 'fa-check' : 'fa-mobile-screen'} td-step-ic`;
        }

        const steps = Array.from(start.querySelectorAll('[data-step]'));
        const done = steps.filter(step => step.dataset.done === '1').length;
        const count = document.getElementById('td-start-count');
        const bar = document.getElementById('td-start-bar');
        if (count) count.textContent = `${done}/${steps.length}`;
        if (bar) bar.style.width = `${Math.round((done / Math.max(1, steps.length)) * 100)}%`;

        start.hidden = done >= steps.length || store.get(DISMISS_KEY) === '1';
    }

    document.getElementById('td-start-dismiss')?.addEventListener('click', () => {
        store.set(DISMISS_KEY, '1');
        if (start) start.hidden = true;
    });

    document.getElementById('td-step-install')?.addEventListener('click', () => {
        const api = window.pmPwa;
        let state = {};
        try { state = api ? api.state() : {}; } catch (_) {}

        if (state.installed) return;
        if (api && state.canPromptDirectly) { api.install(); return; }

        // iOS / Safari: reuse the existing "Add to Home Screen" instructions.
        const root = document.getElementById('pm-pwa-root');
        const dialog = document.getElementById('pm-pwa-ios-dialog');
        if (state.isIos && root && dialog) {
            root.hidden = false;
            dialog.hidden = false;
            document.getElementById('pm-pwa-ios-close-button')?.focus();
            return;
        }

        window.location.href = @json(Route::has('tips') ? route('tips') : route('help.show'));
    });

    /* ---- Daily reminders: one tap ------------------------------------- */
    const remindersStep = document.getElementById('td-step-daily-reminders');
    const startMsg = document.getElementById('td-start-msg');
    const say = text => {
        if (!startMsg) return;
        startMsg.textContent = text || '';
        startMsg.hidden = !text;
    };
    const markRemindersDone = () => {
        if (!remindersStep) return;
        remindersStep.dataset.done = '1';
        remindersStep.classList.add('done');
        const icon = remindersStep.querySelector('.td-step-ic');
        if (icon) icon.className = 'fa-solid fa-check td-step-ic';
    };

    remindersStep?.addEventListener('click', async () => {
        const api = window.pmPush;
        if (!api || remindersStep.getAttribute('aria-busy') === 'true') return;

        let current = {};
        try { current = api.state(); } catch (_) {}
        if (current.enabled) {
            markRemindersDone();
            say('Daily reminders are already on for this device.');
            return;
        }

        remindersStep.setAttribute('aria-busy', 'true');
        const result = await api.enable();
        remindersStep.removeAttribute('aria-busy');

        if (result.ok) {
            markRemindersDone();
            say('Daily reminders are on. See you in the morning.');
            // Let the confirmation be read before a finished checklist hides.
            setTimeout(renderStart, 3000);
            return;
        }

        say(api.message(result.reason));
        if (result.reason === 'ios-install') api.showInstallHelp();
    });

    renderStart();
    // public/js/pwa.js is deferred, so window.pmPwa only exists from DOMContentLoaded.
    const bindPwa = () => {
        renderStart();
        try { window.pmPwa?.onChange?.(renderStart); } catch (_) {}
    };
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', bindPwa, { once: true }) : bindPwa();
    window.addEventListener('appinstalled', () => { store.set('pm_pwa_installed', '1'); renderStart(); });

    /* ---- App launcher ---------------------------------------------------- */
    const launcher = document.getElementById('td-launcher');
    const moreApps = document.getElementById('td-more-apps');
    if (launcher && moreApps) {
        const visibleCount = () => Array.from(launcher.children).filter(el => getComputedStyle(el).display !== 'none').length;
        const syncMore = () => {
            const expanded = launcher.classList.contains('expanded');
            moreApps.hidden = !expanded && visibleCount() >= launcher.children.length;
            moreApps.setAttribute('aria-expanded', String(expanded));
            moreApps.querySelector('span').textContent = expanded ? 'Fewer apps' : 'All apps';
            moreApps.querySelector('i').className = `fa-solid ${expanded ? 'fa-chevron-up' : 'fa-ellipsis'}`;
        };
        moreApps.addEventListener('click', () => { launcher.classList.toggle('expanded'); syncMore(); });
        window.addEventListener('resize', syncMore, { passive: true });
        syncMore();
    }

    /* ---- Remember which "more" sections are open ------------------------ */
    document.querySelectorAll('[data-td-more]').forEach(details => {
        const key = `md.more.${details.dataset.tdMore}`;
        if (store.get(key) === '1') details.open = true;
        details.addEventListener('toggle', () => store.set(key, details.open ? '1' : '0'));
    });
})();
</script>

@endsection
