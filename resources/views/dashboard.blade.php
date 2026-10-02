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

<style>
    .md-home{--home-primary:var(--brand-1,#0f766e);color:#0f172a}.md-home *{box-sizing:border-box}html:not(.pm-a11y-underline-links) .md-home a{text-decoration:none!important}
    .md-dashboard-section{margin-bottom:16px}.md-section-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px}.md-section-title{display:flex;align-items:center;gap:7px;font-size:14px;font-weight:800;color:#172033}.md-section-title i{color:var(--home-primary)}.md-section-link{font-size:11px;font-weight:800;color:var(--home-primary)!important;white-space:nowrap}
    .md-shell{background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 6px 18px rgba(15,23,42,.04)}
    .md-welcome{padding:14px 16px}.md-welcome-row{display:flex;align-items:center;justify-content:space-between;gap:14px}.md-welcome h1{font-size:20px;line-height:1.2;font-weight:800;margin:2px 0}.md-welcome-copy{font-size:12px;color:#64748b}.md-muted{color:#64748b}.md-header-actions{display:flex;align-items:center;gap:8px}.md-icon-btn{width:38px;height:38px;border:1px solid #dbe3ee;border-radius:10px;background:#fff;color:#475569;display:inline-flex;align-items:center;justify-content:center}.md-card-btn{height:38px;border-radius:10px;padding:0 13px;background:var(--home-primary);border:1px solid var(--home-primary);color:#fff!important;display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:800;box-shadow:0 5px 12px color-mix(in srgb,var(--home-primary) 20%,transparent)}
    .md-notif{position:relative}.md-notif>summary{list-style:none;cursor:pointer;position:relative}.md-notif>summary::-webkit-details-marker{display:none}.md-notif-badge{position:absolute;right:-5px;top:-5px;min-width:18px;height:18px;padding:0 4px;border-radius:999px;background:#e11d48;color:#fff;font-size:9px;font-weight:900;display:flex;align-items:center;justify-content:center;border:2px solid #fff}.md-notif-panel{position:absolute;z-index:80;right:0;top:46px;width:min(390px,calc(100vw - 28px));max-height:520px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 18px 45px rgba(15,23,42,.18);overflow:hidden}.md-notif-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:13px 14px;border-bottom:1px solid #eef2f7;background:#fff}.md-notif-title{font-size:13px;font-weight:900;color:#172033}.md-notif-sub{font-size:10px;color:#64748b;margin-top:1px}.md-notif-list{max-height:390px;overflow-y:auto}.md-notif-item{display:flex;gap:10px;padding:11px 13px;border-bottom:1px solid #f1f5f9;color:#334155!important}.md-notif-item:hover{background:#f8fafc}.md-notif-item.unread{background:#f0f9ff80}.md-notif-icon{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex:0 0 auto}.md-notif-copy{min-width:0;flex:1}.md-notif-item-title{font-size:11px;font-weight:800;color:#172033;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.md-notif-msg{font-size:10px;line-height:1.35;color:#64748b;margin-top:2px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.md-notif-time{font-size:9px;color:#94a3b8;margin-top:4px}.md-notif-dot{width:7px;height:7px;border-radius:50%;background:#0ea5e9;margin-top:5px;flex:0 0 auto}.md-notif-action{font-size:8px;font-weight:900;color:#b45309;background:#fffbeb;border:1px solid #fde68a;border-radius:999px;padding:2px 6px;white-space:nowrap}.md-notif-foot{padding:10px 13px;background:#f8fafc;display:flex;align-items:center;justify-content:space-between;gap:8px}.md-notif-foot a,.md-notif-foot button{font-size:10px;font-weight:800;color:var(--home-primary)!important}.md-notif-empty{padding:28px 16px;text-align:center;color:#94a3b8;font-size:11px}
    .md-progress-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.md-progress-card{border:1px solid #e2e8f0;border-radius:15px;background:#fff;padding:12px 13px;box-shadow:0 5px 14px rgba(15,23,42,.04);min-width:0}.md-progress-card.health{border-left:4px solid #0f766e}.md-progress-card.week{border-left:4px solid #7c3aed}.md-progress-head{display:flex;align-items:center;justify-content:space-between;gap:10px}.md-progress-kicker{font-size:11px;color:#64748b;font-weight:700}.md-progress-main{font-size:19px;font-weight:900;color:#172033;line-height:1.2;margin-top:2px}.md-progress-icon{width:40px;height:40px;border-radius:12px;background:#ccfbf1;color:#0f766e;display:flex;align-items:center;justify-content:center;font-size:17px;flex:0 0 auto}.md-progress-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin-top:11px}.md-progress-metrics.four{grid-template-columns:repeat(4,minmax(0,1fr))}.md-progress-label{font-size:10px;color:#64748b;margin-bottom:2px}.md-progress-value{font-size:12px;font-weight:800;color:#334155;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.md-progress-period{font-size:11px;color:#64748b;margin-top:4px}
    .md-shortcuts{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px}.md-shortcut{border-radius:13px;padding:9px 10px;border:1px solid var(--c-border);background:var(--c-bg);min-height:70px;display:flex;align-items:center;gap:8px;transition:.16s ease;min-width:0}.md-shortcut:hover{transform:translateY(-1px);box-shadow:0 7px 16px rgba(15,23,42,.05)}.md-shortcut-icon{width:32px;height:32px;border-radius:9px;background:#fff9;display:flex;align-items:center;justify-content:center;color:var(--c-fg);font-size:13px;flex:0 0 auto}.md-shortcut-copy{min-width:0}.md-shortcut-title{font-size:11px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.md-shortcut-sub{font-size:9px;color:#64748b;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .md-insight{padding:12px 14px;border-radius:15px;border:1px solid var(--insight-border);background:var(--insight-bg)}.md-insight-inner{display:flex;align-items:center;gap:11px}.md-insight-icon{width:38px;height:38px;border-radius:11px;background:rgba(255,255,255,.88);display:flex;align-items:center;justify-content:center;color:var(--insight-fg);font-size:16px;flex:0 0 auto}.md-insight-kicker{font-size:9px;letter-spacing:.07em;font-weight:800;color:var(--insight-fg)}.md-insight-title{font-size:13px;font-weight:800;margin-top:2px}.md-insight-message{font-size:11px;line-height:1.4;color:#64748b;margin-top:2px}.md-insight-action{font-size:11px;font-weight:800;white-space:nowrap;color:var(--insight-fg)!important}
    .md-tab-wrap{padding:6px}.md-tabs{display:flex;align-items:center;gap:6px;background:#f4f7fb;border-radius:12px;padding:5px;overflow-x:auto;scrollbar-width:none}.md-tabs::-webkit-scrollbar{display:none}.md-tab-btn{border:0;background:transparent;color:#64748b;padding:8px 12px;border-radius:9px;font-size:11px;font-weight:800;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;cursor:pointer;transition:.16s ease}.md-tab-btn:hover{color:#334155;background:#fff}.md-tab-btn.active{background:var(--home-primary);color:#fff;box-shadow:0 4px 10px color-mix(in srgb,var(--home-primary) 22%,transparent)}.md-tab-panel{display:none;padding:11px 7px 5px}.md-tab-panel.active{display:block}.md-tab-meta{font-size:10px;color:#94a3b8;margin-left:auto}.md-panel-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin:0 4px 9px}.md-panel-title{font-size:12px;font-weight:800;color:#334155}.md-empty{padding:16px;border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;text-align:center;color:#64748b;font-size:11px}
    .md-focus-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px}.md-focus-item{display:flex;align-items:center;gap:8px;min-width:0;padding:10px;border:1px solid #e2e8f0;border-left:4px solid var(--accent,#0f766e);border-radius:12px;background:#fff;transition:.15s ease}.md-focus-item:hover{background:#f8fafc;transform:translateY(-1px)}.md-focus-num{width:28px;height:28px;border-radius:9px;background:#f1f5f9;color:#475569;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:900;flex:0 0 auto}.md-row-copy{min-width:0;flex:1}.md-row-title{font-size:11px;font-weight:800;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.md-row-sub{font-size:9px;color:#64748b;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.md-chevron{color:#94a3b8;font-size:9px}
    .md-tools-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px}.md-tool-card{border:1px solid #e2e8f0;border-left:4px solid var(--tool-accent);border-radius:12px;background:#fff;padding:9px 10px;display:flex;align-items:center;gap:8px;min-width:0;transition:.15s ease}.md-tool-card:hover{transform:translateY(-1px);box-shadow:0 5px 13px rgba(15,23,42,.05)}.md-tool-icon{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex:0 0 auto;font-size:13px}
    .md-finance-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px}.md-finance-card{--fc-bg:#f8fafc;--fc-fg:#475569;--fc-border:#cbd5e1;position:relative;overflow:hidden;border:1px solid var(--fc-border);border-left:4px solid var(--fc-fg);border-radius:12px;padding:9px 10px;min-width:0;transition:.16s ease;box-shadow:0 4px 12px rgba(15,23,42,.035);background:var(--fc-bg);color:#172033!important}.md-finance-card:hover{transform:translateY(-1px);box-shadow:0 7px 15px rgba(15,23,42,.065)}.md-finance-card.income{--fc-bg:#ecfdf5;--fc-fg:#047857;--fc-border:#a7f3d0}.md-finance-card.expenses{--fc-bg:#fff1f2;--fc-fg:#be123c;--fc-border:#fecdd3}.md-finance-card.budgets{--fc-bg:#eff6ff;--fc-fg:#1d4ed8;--fc-border:#bfdbfe}.md-finance-card.savings{--fc-bg:#f5f3ff;--fc-fg:#6d28d9;--fc-border:#ddd6fe}.md-finance-card.debts{--fc-bg:#fff7ed;--fc-fg:#c2410c;--fc-border:#fed7aa}.md-finance-card.goals{--fc-bg:#ecfeff;--fc-fg:#0e7490;--fc-border:#a5f3fc}.md-finance-top{display:flex;align-items:center;gap:6px;margin-bottom:6px}.md-finance-icon{width:28px;height:28px;border-radius:8px;background:#fff;color:var(--fc-fg);display:flex;align-items:center;justify-content:center;flex:0 0 auto;font-size:11px}.md-finance-name{font-size:11px;font-weight:800;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.md-finance-value{font-size:13px;font-weight:900;color:#172033;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.md-finance-small{font-size:9px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

    /* Daily rhythm / retention loop */
    .md-rhythm{padding:14px 15px;background:linear-gradient(135deg,color-mix(in srgb,var(--home-primary) 7%,#fff),#fff);border:1px solid color-mix(in srgb,var(--home-primary) 18%,#e2e8f0)}
    .md-rhythm-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.md-rhythm-eyebrow{font-size:9px;text-transform:uppercase;letter-spacing:.1em;font-weight:900;color:#94a3b8}.md-rhythm-title{font-size:15px;font-weight:900;color:#172033;margin-top:1px}.md-streak-pill{display:inline-flex;align-items:center;gap:6px;padding:7px 10px;border-radius:999px;background:#fffbeb;color:#b45309;border:1px solid #fde68a;font-size:10px;font-weight:900;white-space:nowrap}.md-streak-best{font-size:9px;color:#94a3b8;margin-left:4px}
    .md-rhythm-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}.md-rhythm-card{border:1px solid #e2e8f0;border-radius:14px;background:#fff;padding:12px;min-width:0;box-shadow:0 4px 12px rgba(15,23,42,.035)}.md-rhythm-card.start{border-color:#a7f3d0;background:#ecfdf580}.md-rhythm-card.progress{border-color:#ddd6fe;background:#f5f3ff80}.md-rhythm-card.close{border-color:#bae6fd;background:#f0f9ff80}.md-rhythm-card-head{display:flex;align-items:flex-start;gap:9px}.md-rhythm-icon{width:34px;height:34px;border-radius:10px;background:#fff;display:flex;align-items:center;justify-content:center;flex:0 0 auto;box-shadow:0 3px 10px rgba(15,23,42,.05)}.md-rhythm-card.start .md-rhythm-icon{color:#047857}.md-rhythm-card.progress .md-rhythm-icon{color:#6d28d9}.md-rhythm-card.close .md-rhythm-icon{color:#0369a1}.md-rhythm-label{font-size:9px;font-weight:900;letter-spacing:.07em;text-transform:uppercase;color:#64748b}.md-rhythm-main{font-size:13px;font-weight:900;color:#172033;margin-top:2px}.md-rhythm-copy{font-size:10px;line-height:1.35;color:#64748b;margin-top:3px}.md-rhythm-action{margin-top:10px;display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:34px;padding:0 11px;border:0;border-radius:10px;font-size:10px;font-weight:900;cursor:pointer}.md-rhythm-action.start{background:#047857;color:#fff}.md-rhythm-action.close{background:#0369a1;color:#fff}.md-rhythm-action[disabled]{opacity:.55;cursor:default}.md-rhythm-bar{height:7px;border-radius:999px;background:#ede9fe;overflow:hidden;margin-top:10px}.md-rhythm-bar>span{display:block;height:100%;border-radius:inherit;background:#7c3aed}.md-review-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;margin-top:9px}.md-review-card{border:1px solid #e2e8f0;border-radius:13px;background:#fff;padding:11px 12px;display:flex;align-items:center;justify-content:space-between;gap:10px;text-align:left;cursor:pointer}.md-review-card:hover{border-color:var(--home-primary);box-shadow:0 5px 12px rgba(15,23,42,.05)}.md-review-kicker{font-size:9px;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;font-weight:800}.md-review-title{font-size:11px;font-weight:900;color:#172033;margin-top:2px}.md-review-copy{font-size:9px;color:#64748b;margin-top:2px}.md-celebration{margin-top:9px;padding:10px 12px;border-radius:12px;border:1px solid #fde68a;background:#fffbeb;color:#92400e}.md-celebration-title{font-size:11px;font-weight:900}.md-celebration-copy{font-size:10px;margin-top:2px}.md-tomorrow{margin-top:9px;padding:10px 12px;border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc}.md-tomorrow-title{font-size:10px;font-weight:900;color:#475569}.md-tomorrow-items{display:flex;flex-wrap:wrap;gap:6px;margin-top:7px}.md-tomorrow-chip{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:5px 8px;background:#fff;border:1px solid #e2e8f0;color:#475569;font-size:9px;font-weight:800}
    .md-engagement-dialog{width:min(92vw,560px);max-width:560px;border:0;border-radius:18px;padding:0;overflow:hidden;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.25)}.md-engagement-dialog::backdrop{background:rgba(15,23,42,.55);backdrop-filter:blur(3px)}.md-engagement-dialog-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:15px 16px;border-bottom:1px solid #eef2f7}.md-engagement-dialog-title{font-size:16px;font-weight:900;color:#172033}.md-engagement-dialog-sub{font-size:10px;color:#64748b;margin-top:2px}.md-engagement-dialog-close{width:34px;height:34px;border-radius:10px;border:1px solid #e2e8f0;background:#fff;color:#64748b}.md-engagement-dialog-body{padding:15px 16px;max-height:min(70vh,620px);overflow-y:auto}.md-engagement-dialog-foot{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid #eef2f7;background:#f8fafc}.md-engagement-fields{display:grid;gap:10px}.md-engagement-field label{display:block;font-size:10px;font-weight:900;color:#475569;margin-bottom:4px}.md-review-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.md-review-metric{padding:10px;border-radius:12px;border:1px solid #e2e8f0;background:#f8fafc}.md-review-metric-label{font-size:9px;color:#94a3b8;font-weight:800;text-transform:uppercase;letter-spacing:.05em}.md-review-metric-value{font-size:15px;font-weight:900;color:#172033;margin-top:2px}


    /* Business growth + communication */
    .md-growth-section{padding:14px 15px}
    .md-growth-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
    .md-growth-eyebrow{font-size:9px;text-transform:uppercase;letter-spacing:.1em;font-weight:900;color:#94a3b8}
    .md-growth-title{font-size:15px;font-weight:900;color:#172033;margin-top:2px}
    .md-growth-copy{font-size:10px;line-height:1.4;color:#64748b;margin-top:3px}
    .md-growth-private{display:inline-flex;align-items:center;gap:5px;border:1px solid #a7f3d0;background:#ecfdf5;color:#047857;padding:6px 9px;border-radius:999px;font-size:9px;font-weight:900;white-space:nowrap}
    .md-growth-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin-top:12px}
    .md-growth-card{border:1px solid #e2e8f0;border-radius:14px;background:#fff;padding:12px;min-width:0}
    .md-growth-card.challenge{background:#f5f3ff80;border-color:#ddd6fe}
    .md-growth-card.referral{background:#f0f9ff80;border-color:#bae6fd}
    .md-growth-card-kicker{font-size:9px;text-transform:uppercase;letter-spacing:.07em;font-weight:900;color:#94a3b8}
    .md-growth-card-title{font-size:12px;font-weight:900;color:#172033;margin-top:3px}
    .md-growth-card-copy{font-size:10px;line-height:1.35;color:#64748b;margin-top:4px}
    .md-growth-card-meta{font-size:9px;font-weight:900;color:#6d28d9;margin-top:6px}
    .md-growth-progress{height:7px;border-radius:999px;background:#e2e8f0;overflow:hidden;margin-top:10px}
    .md-growth-progress>span{display:block;height:100%;border-radius:inherit;background:#0f766e}
    .md-growth-progress.violet{background:#ede9fe}
    .md-growth-progress.violet>span{background:#7c3aed}
    .md-growth-steps{display:grid;gap:5px;margin-top:9px}
    .md-growth-step{display:flex;align-items:center;gap:6px;font-size:9px;color:#64748b;font-weight:800}
    .md-growth-step.complete{color:#047857}
    .md-growth-action{display:inline-flex;align-items:center;justify-content:center;gap:6px;margin-top:10px;border:0;border-radius:10px;min-height:34px;padding:0 11px;color:#fff;font-size:10px;font-weight:900;cursor:pointer}
    .md-growth-action.violet{background:#6d28d9}
    .md-growth-action.sky{background:#0369a1}
    .md-growth-trust{display:flex;align-items:flex-start;gap:7px;margin-top:10px;border:1px solid #a7f3d0;background:#ecfdf5;color:#065f46;border-radius:12px;padding:9px 10px;font-size:9.5px;line-height:1.4}

    @media(max-width:1200px){.md-shortcuts{grid-template-columns:repeat(3,minmax(0,1fr))}.md-finance-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.md-tools-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.md-focus-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:760px){.md-welcome-row{align-items:flex-start}.md-progress-grid{grid-template-columns:1fr}.md-progress-metrics.four{grid-template-columns:repeat(2,minmax(0,1fr))}.md-shortcuts{grid-template-columns:repeat(2,minmax(0,1fr))}.md-tools-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.md-finance-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.md-focus-grid{grid-template-columns:1fr}.md-rhythm-grid{grid-template-columns:1fr}.md-growth-grid{grid-template-columns:1fr}.md-card-btn span{display:none}.md-insight-inner{align-items:flex-start}.md-insight-action{display:none}.md-tab-wrap{padding:4px}.md-tab-panel{padding:10px 4px 4px}}
    @media(max-width:460px){.md-welcome{padding:12px}.md-welcome h1{font-size:18px}.md-shortcut{min-height:62px;padding:8px}.md-progress-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.md-finance-grid{grid-template-columns:1fr 1fr}}

    /* ------------------------------------------------------------------
       Dashboard usability redesign
       ------------------------------------------------------------------ */
    .md-home{
        width:min(1180px,100%);
        margin:0 auto;
        padding:6px 4px 28px;
    }
    .md-home .md-dashboard-section{margin-bottom:22px}
    .md-home .md-shell{
        border-radius:20px;
        border:1px solid #e5e7eb;
        box-shadow:0 8px 28px rgba(15,23,42,.055);
    }

    .md-welcome{
        padding:20px 22px;
        background:
            radial-gradient(circle at 95% 10%,color-mix(in srgb,var(--home-primary) 10%,transparent),transparent 35%),
            #fff;
    }
    .md-welcome h1{font-size:25px;letter-spacing:-.02em}
    .md-welcome-copy{font-size:13px;max-width:560px;line-height:1.6}
    .md-header-actions{gap:10px}
    .md-icon-btn,.md-card-btn{height:42px}

    .md-overview-nav{
        position:sticky;
        top:8px;
        z-index:40;
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:8px;
        margin:0 0 22px;
        padding:7px;
        border:1px solid #e2e8f0;
        border-radius:16px;
        background:rgba(255,255,255,.94);
        box-shadow:0 10px 28px rgba(15,23,42,.07);
        backdrop-filter:blur(14px);
    }
    .md-overview-link{
        border:0;
        min-height:42px;
        border-radius:11px;
        background:transparent;
        color:#64748b!important;
        display:flex;
        align-items:center;
        justify-content:center;
        gap:7px;
        font-size:12px;
        font-weight:800;
        cursor:pointer;
    }
    .md-overview-link:hover,
    .md-overview-link.active{
        background:#f0fdfa;
        color:var(--home-primary)!important;
    }

    /* Today is the primary surface. Give it air and prominence. */
    .md-rhythm{
        padding:20px!important;
        border:1px solid color-mix(in srgb,var(--home-primary) 22%,#e2e8f0)!important;
    }
    .md-rhythm-title{font-size:19px!important;line-height:1.25}
    .md-rhythm-eyebrow{font-size:10px!important;letter-spacing:.14em}
    .md-rhythm-grid{
        gap:12px!important;
        margin-top:16px!important;
    }
    .md-rhythm-card{
        min-height:126px;
        padding:16px!important;
        border-radius:16px!important;
    }

    /* Focus panel gets more space than utility content. */
    .md-tab-wrap{
        padding:0!important;
        overflow:hidden;
    }
    .md-tabs{
        padding:9px 10px!important;
        background:#f8fafc;
        border-bottom:1px solid #e2e8f0;
    }
    .md-tab-btn{
        min-height:42px!important;
        border-radius:10px!important;
    }
    .md-tab-panel{
        padding:18px!important;
        min-height:150px;
    }

    /* Secondary dashboard areas become calm, expandable groups. */
    .md-secondary-wrap{
        margin-bottom:20px;
        border:1px solid #e2e8f0;
        border-radius:18px;
        background:#fff;
        box-shadow:0 6px 18px rgba(15,23,42,.035);
        overflow:hidden;
    }
    .md-secondary-summary{
        list-style:none;
        cursor:pointer;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:16px;
        padding:16px 18px;
        user-select:none;
    }
    .md-secondary-summary::-webkit-details-marker{display:none}
    .md-secondary-summary-title{
        display:flex;
        align-items:center;
        gap:10px;
        font-size:14px;
        font-weight:900;
        color:#172033;
    }
    .md-secondary-summary-title i{
        width:34px;
        height:34px;
        border-radius:10px;
        display:grid;
        place-items:center;
        color:var(--home-primary);
        background:#f0fdfa;
    }
    .md-secondary-summary-copy{
        font-size:11px;
        color:#64748b;
        margin-top:2px;
    }
    .md-secondary-summary-chevron{
        color:#94a3b8;
        transition:transform .2s ease;
    }
    details[open]>.md-secondary-summary .md-secondary-summary-chevron{
        transform:rotate(180deg);
    }
    .md-secondary-content{padding:0 16px 16px}
    .md-secondary-content>.md-dashboard-section{
        margin:0!important;
        border:0!important;
        box-shadow:none!important;
    }

    /* Progress is useful, but not louder than today's plan. */
    .md-progress-grid{gap:12px}
    .md-progress-card{
        padding:16px;
        border-radius:16px;
        box-shadow:none;
        background:#fbfdff;
    }
    .md-progress-main{font-size:22px}
    .md-progress-metrics{gap:12px}

    /* Tool launcher: fewer visual blocks and stronger labels. */
    .md-shortcuts{
        grid-template-columns:repeat(4,minmax(0,1fr))!important;
        gap:10px!important;
    }
    .md-shortcut{
        min-height:92px!important;
        border-radius:15px!important;
        padding:14px!important;
        box-shadow:none!important;
        transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease;
    }
    .md-shortcut:hover{
        transform:translateY(-2px);
        box-shadow:0 10px 22px rgba(15,23,42,.07)!important;
    }
    .md-shortcut:nth-child(n+5){
        display:none;
    }
    .md-tools-expanded .md-shortcut:nth-child(n+5){
        display:flex;
    }
    .md-more-tools{
        margin-top:11px;
        display:flex;
        justify-content:flex-end;
    }
    .md-more-tools button{
        border:1px solid #dbe3ee;
        background:#fff;
        color:#475569;
        border-radius:10px;
        padding:8px 12px;
        font-size:11px;
        font-weight:800;
    }

    /* Growth/challenge should feel optional, not like another required task. */
    .md-growth-section,
    #growth-strategy-card{
        background:#fbfdff!important;
    }
    .md-growth-section .md-growth-grid,
    #growth-strategy-card .grid{
        gap:12px!important;
    }

    .md-insight{
        border-radius:17px!important;
        box-shadow:none!important;
    }

    /* Finance remains intentionally secondary and compact. */
    .md-finance-grid{
        gap:10px!important;
    }

    @media(max-width:900px){
        .md-home{padding-inline:2px}
        .md-shortcuts{grid-template-columns:repeat(2,minmax(0,1fr))!important}
        .md-progress-grid{grid-template-columns:1fr}
        .md-rhythm-grid{grid-template-columns:1fr!important}
        .md-overview-nav{top:4px}
    }

    @media(max-width:640px){
        .md-welcome{padding:16px}
        .md-welcome-row{align-items:flex-start}
        .md-welcome h1{font-size:21px}
        .md-welcome-copy{font-size:12px}
        .md-card-btn span{display:none}
        .md-overview-nav{gap:4px;padding:5px}
        .md-overview-link{font-size:10px;gap:5px}
        .md-overview-link i{font-size:12px}
        .md-rhythm{padding:15px!important}
        .md-tab-panel{padding:14px!important}
        .md-secondary-summary{padding:14px}
        .md-shortcut:nth-child(n+5){display:none}
    }


    .md-secondary-wrap[data-dashboard-secondary="growth"]{
        border-color:#fcd34d;
        background:linear-gradient(180deg,#fffef7 0%,#fff 100%);
    }
    .md-secondary-wrap[data-dashboard-secondary="growth"] .md-secondary-summary-title i{
        color:#d97706;
        background:#fffbeb;
    }

    /* ------------------------------------------------------------------
       Secondary dashboard cards:
       3 per row when closed, full width when opened.
       ------------------------------------------------------------------ */
    .md-secondary-row{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:14px;
        align-items:start;
        margin-bottom:24px;
    }

    .md-secondary-row .md-secondary-wrap{
        margin:0;
        min-width:0;
        border:1px solid #e2e8f0;
        border-radius:18px;
        background:#fff;
        box-shadow:0 6px 20px rgba(15,23,42,.045);
        overflow:hidden;
        transition:
            box-shadow .2s ease,
            border-color .2s ease,
            transform .2s ease;
    }

    .md-secondary-row .md-secondary-wrap:not([open]):hover{
        transform:translateY(-2px);
        box-shadow:0 12px 28px rgba(15,23,42,.07);
    }

    .md-secondary-row .md-secondary-wrap[open]{
        grid-column:1 / -1;
        width:100%;
        background:#fff;
        box-shadow:0 14px 34px rgba(15,23,42,.08);
    }

    .md-secondary-row .md-secondary-summary{
        min-height:104px;
        padding:16px 17px;
        align-items:flex-start;
        cursor:pointer;
    }

    .md-secondary-row .md-secondary-wrap[open] > .md-secondary-summary{
        min-height:auto;
        padding:15px 18px;
        border-bottom:1px solid #edf2f7;
        background:#fbfdff;
    }

    .md-secondary-row .md-secondary-summary-title{
        align-items:flex-start;
        font-size:14px;
    }

    .md-secondary-row .md-secondary-summary-title i{
        flex:0 0 auto;
    }

    .md-secondary-row .md-secondary-summary-copy{
        max-width:280px;
        line-height:1.45;
    }

    .md-secondary-row .md-secondary-wrap[open] .md-secondary-summary-copy{
        max-width:none;
    }

    .md-secondary-row .md-secondary-content{
        padding:0;
    }

    .md-secondary-row .md-secondary-wrap:not([open]) .md-secondary-content{
        display:none;
    }

    .md-secondary-row .md-secondary-wrap[open] .md-secondary-content{
        display:block;
        padding:18px;
        animation:mdSecondaryOpen .18s ease;
    }

    .md-secondary-row .md-secondary-wrap[open] .md-secondary-content > *{
        width:100%!important;
        max-width:none!important;
    }

    .md-secondary-row .md-secondary-wrap[open] .md-dashboard-section{
        width:100%;
        max-width:none;
        margin:0!important;
        border:0!important;
        border-radius:0!important;
        box-shadow:none!important;
        background:transparent!important;
    }

    .md-secondary-row .md-secondary-wrap[open] .md-shortcuts{
        grid-template-columns:repeat(4,minmax(0,1fr))!important;
    }

    .md-secondary-row .md-secondary-wrap[open] .md-progress-grid{
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
    }

    .md-secondary-row .md-secondary-wrap[open] .grid{
        width:100%;
    }

    .md-secondary-row .md-secondary-wrap[data-dashboard-secondary="growth"]{
        border-color:#f7c948;
        background:linear-gradient(180deg,#fffef8 0%,#fff 100%);
    }

    .md-secondary-row .md-secondary-wrap[data-dashboard-secondary="growth"][open]{
        border-color:#f3b61f;
    }

    @keyframes mdSecondaryOpen{
        from{opacity:.25;transform:translateY(-4px)}
        to{opacity:1;transform:translateY(0)}
    }

    @media(max-width:980px){
        .md-secondary-row{
            grid-template-columns:repeat(2,minmax(0,1fr));
        }

        .md-secondary-row .md-secondary-wrap[open]{
            grid-column:1 / -1;
        }

        .md-secondary-row .md-secondary-wrap[open] .md-shortcuts{
            grid-template-columns:repeat(2,minmax(0,1fr))!important;
        }
    }

    @media(max-width:680px){
        .md-secondary-row{
            grid-template-columns:1fr;
            gap:10px;
        }

        .md-secondary-row .md-secondary-summary{
            min-height:auto;
            padding:14px;
        }

        .md-secondary-row .md-secondary-wrap[open]{
            grid-column:1;
        }

        .md-secondary-row .md-secondary-wrap[open] .md-secondary-content{
            padding:12px;
        }

        .md-secondary-row .md-secondary-wrap[open] .md-shortcuts,
        .md-secondary-row .md-secondary-wrap[open] .md-progress-grid{
            grid-template-columns:1fr!important;
        }
    }

</style>

<div class="md-home">
    <section class="md-dashboard-section md-shell md-welcome">
        <div class="md-welcome-row">
            <div class="min-w-0">
                <div class="text-[10px] uppercase tracking-[.08em] text-slate-400 font-bold">My Digital Diary</div>
                <h1>Welcome back, {{ $firstName }}</h1>
                <div class="md-welcome-copy">See your progress, decide what matters today, and keep moving forward.</div>
            </div>
            <div class="md-header-actions">
                @php
                    $bell = $notificationCenter ?? ['items'=>[], 'unread_count'=>0, 'action_count'=>0, 'badge_count'=>0];
                    $bellTones = [
                        'emerald'=>['#ecfdf5','#047857'], 'sky'=>['#f0f9ff','#0369a1'],
                        'violet'=>['#f5f3ff','#6d28d9'], 'amber'=>['#fffbeb','#b45309'],
                        'rose'=>['#fff1f2','#be123c'], 'slate'=>['#f8fafc','#475569'],
                    ];
                @endphp
                <details class="md-notif" id="dashboard-notification-bell">
                    <summary class="md-icon-btn" aria-label="Notifications" title="Notifications">
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
                                        {{ (int)($bell['action_count'] ?? 0) }} item(s) need attention · {{ (int)($bell['unread_count'] ?? 0) }} unread
                                    @else
                                        {{ (int)($bell['unread_count'] ?? 0) }} unread notification(s)
                                    @endif
                                </div>
                            </div>
                            <i class="fa-regular fa-bell text-slate-400"></i>
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
                            <a href="{{ route('notifications.index') }}">View all notifications</a>
                            @if(($bell['unread_count'] ?? 0) > 0)
                                <form method="POST" action="{{ route('notifications.read-all') }}" class="m-0">@csrf<button type="submit">Mark all read</button></form>
                            @endif
                        </div>
                    </div>
                </details>
                <a href="{{ route('business-card.edit') }}" class="md-card-btn"><i class="fa-regular fa-address-card"></i><span>My Business Card</span></a>
            </div>
        </div>
    </section>

    @include('dashboard.partials.live-steps-card', ['stepData' => $stepData ?? []])

    <nav class="md-overview-nav" aria-label="Dashboard quick navigation">
        <a href="#daily-rhythm-section" class="md-overview-link active">
            <i class="fa-solid fa-sun"></i>
            <span>Today</span>
        </a>
        <a href="#dashboard-tabbed-sections" class="md-overview-link">
            <i class="fa-solid fa-list-check"></i>
            <span>Focus</span>
        </a>
        <button type="button" class="md-overview-link" data-dashboard-panel-toggle="progress">
            <i class="fa-solid fa-chart-line"></i>
            <span>Progress</span>
        </button>
        <button type="button" class="md-overview-link" data-dashboard-panel-toggle="tools">
            <i class="fa-solid fa-grid-2"></i>
            <span>Tools</span>
        </button>
        <button type="button" class="md-overview-link" data-dashboard-panel-toggle="growth">
            <i class="fa-solid fa-fire"></i>
            <span>Challenge</span>
        </button>
    </nav>


    @if(!empty($engagement))
    <section class="md-dashboard-section md-shell md-rhythm" id="daily-rhythm-section">
        <div class="md-rhythm-head">
            <div>
                <div class="md-rhythm-eyebrow">Daily rhythm</div>
                <div class="md-rhythm-title">Build progress worth returning to</div>
            </div>
            <div>
                <span class="md-streak-pill">
                    <span aria-hidden="true">🔥</span>
                    {{ $growthStreak }} day streak
                </span>
                <span class="md-streak-best">Best {{ $bestGrowthStreak }}</span>
            </div>
        </div>

        <div class="md-rhythm-grid">
            <article class="md-rhythm-card start" role="button" tabindex="0" data-routine-stats="start" style="cursor:pointer">
                <div class="md-rhythm-card-head">
                    <div class="md-rhythm-icon"><i class="fa-solid fa-sun"></i></div>
                    <div class="min-w-0">
                        <div class="md-rhythm-label">Start My Day</div>
                        <div class="md-rhythm-main">{{ $startDayCompleted ? 'Your day is started' : 'Choose what matters first' }}</div>
                        <div class="md-rhythm-copy">
                            {{ (int) data_get($engagement, 'start_day.focus_count', $todayFocus->count()) }} focus item(s) ready.
                        </div>
                    </div>
                </div>
                <button type="button"
                        class="md-rhythm-action start"
                        data-routine-stats="start">
                    <i class="fa-solid fa-chart-column"></i>
                    View Today Stats
                </button>
            </article>

            <article class="md-rhythm-card progress">
                <div class="md-rhythm-card-head">
                    <div class="md-rhythm-icon"><i class="fa-solid fa-chart-line"></i></div>
                    <div class="min-w-0">
                        <div class="md-rhythm-label">Meaningful Progress</div>
                        <div class="md-rhythm-main">
                            {{ (int) data_get($engagementProgress, 'tasks_completed', 0) }}/{{ (int) data_get($engagementProgress, 'tasks_total', 0) }} tasks
                        </div>
                        <div class="md-rhythm-copy">
                            {{ (int) data_get($engagementProgress, 'meaningful_actions', 0) }} meaningful action(s) recorded today.
                        </div>
                    </div>
                </div>
                <div class="md-rhythm-bar" aria-label="Today completion">
                    <span style="width:{{ min(100, max(0, (int) data_get($engagementProgress, 'completion_percent', 0))) }}%"></span>
                </div>
            </article>

            <article class="md-rhythm-card close" role="button" tabindex="0" data-routine-stats="end" style="cursor:pointer">
                <div class="md-rhythm-card-head">
                    <div class="md-rhythm-icon"><i class="fa-solid fa-moon"></i></div>
                    <div class="min-w-0">
                        <div class="md-rhythm-label">Close My Day</div>
                        <div class="md-rhythm-main">{{ $closeDayCompleted ? 'Today is closed' : 'Finish today with clarity' }}</div>
                        <div class="md-rhythm-copy">Capture wins, gratitude and tomorrow’s first priority.</div>
                    </div>
                </div>
                <button type="button"
                        class="md-rhythm-action close"
                        data-routine-stats="end">
                    <i class="fa-solid fa-chart-column"></i>
                    View Today Stats
                </button>
            </article>
        </div>

        <div class="md-review-grid">
            <button type="button" class="md-review-card" data-engagement-review="week">
                <span>
                    <span class="md-review-kicker">Weekly reflection</span>
                    <span class="md-review-title block">My Week in Review</span>
                    <span class="md-review-copy block">Tasks, money, health and consistency.</span>
                </span>
                <i class="fa-solid fa-arrow-right text-slate-300"></i>
            </button>
            <button type="button" class="md-review-card" data-engagement-review="month">
                <span>
                    <span class="md-review-kicker">Personal wrapped</span>
                    <span class="md-review-title block">My Month in Review</span>
                    <span class="md-review-copy block">A shareable snapshot of your progress.</span>
                </span>
                <i class="fa-solid fa-share-nodes text-slate-300"></i>
            </button>
        </div>

        @if($engagementCelebration)
            <div class="md-celebration">
                <div class="md-celebration-title">{{ data_get($engagementCelebration, 'title') }}</div>
                <div class="md-celebration-copy">{{ data_get($engagementCelebration, 'message') }}</div>
            </div>
        @endif

        @if($tomorrowFocus->isNotEmpty())
            <div class="md-tomorrow">
                <div class="md-tomorrow-title"><i class="fa-regular fa-calendar mr-1"></i> Tomorrow preview</div>
                <div class="md-tomorrow-items">
                    @foreach($tomorrowFocus as $tomorrowItem)
                        @php
                            $tomorrowTitle = is_array($tomorrowItem)
                                ? ($tomorrowItem['title'] ?? 'Tomorrow item')
                                : ($tomorrowItem->title ?? 'Tomorrow item');
                        @endphp
                        <span class="md-tomorrow-chip"><i class="fa-solid fa-arrow-right"></i>{{ $tomorrowTitle }}</span>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
    @endif


    @if(!empty($growth))
        @php
            $growthActivation = is_array(data_get($growth, 'activation'))
                ? data_get($growth, 'activation')
                : [];
            $growthChallenge = is_array(data_get($growth, 'challenge'))
                ? data_get($growth, 'challenge')
                : [];
            $growthReferral = is_array(data_get($growth, 'referral'))
                ? data_get($growth, 'referral')
                : [];
            $growthTrustMessage = (string) data_get(
                $growth,
                'trust.message',
                'Your personal diary, reflections and financial records are never included in shared progress cards.'
            );
            $growthActivationPercent = min(
                100,
                max(0, (int) data_get($growthActivation, 'percent', 0))
            );
            $growthChallengePercent = min(
                100,
                max(0, (int) data_get($growthChallenge, 'progress_percent', 0))
            );
        @endphp

        <section class="md-dashboard-section md-shell md-growth-section" id="growth-strategy-section">
            <div class="md-growth-head">
                <div>
                    <div class="md-growth-eyebrow">Take back your attention</div>
                    <div class="md-growth-title">Build your own progress, not just your feed.</div>
                    <div class="md-growth-copy">
                        A few intentional minutes each day can make your priorities, money and goals easier to manage.
                    </div>
                </div>
                <span class="md-growth-private">
                    <i class="fa-solid fa-shield-halved"></i>
                    Private by design
                </span>
            </div>

            <div class="md-growth-grid">
                <article class="md-growth-card">
                    <div class="md-growth-card-kicker">First value</div>
                    <div class="md-growth-card-title">
                        {{ (int) data_get($growthActivation, 'completed', 0) }}/{{ (int) data_get($growthActivation, 'total', 3) }}
                        setup actions complete
                    </div>

                    <div class="md-growth-progress">
                        <span style="width:{{ $growthActivationPercent }}%"></span>
                    </div>

                    <div class="md-growth-steps">
                        @foreach(data_get($growthActivation, 'steps', []) as $step)
                            @php
                                $step = is_array($step) ? $step : (array) $step;
                                $stepComplete = !empty($step['complete']);
                            @endphp
                            <div class="md-growth-step {{ $stepComplete ? 'complete' : '' }}">
                                <i class="fa-solid {{ $stepComplete ? 'fa-circle-check' : 'fa-circle' }}"></i>
                                <span>{{ $step['label'] ?? 'Setup step' }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="md-growth-card challenge">
                    <div class="md-growth-card-kicker">30-Day Challenge</div>
                    <div class="md-growth-card-title">
                        {{ data_get($growthChallenge, 'title', '30 Days With My Digital Diary') }}
                    </div>
                    <div class="md-growth-card-copy">
                        Plan, act, record and reflect consistently for 30 days.
                    </div>

                    @if(data_get($growthChallenge, 'joined'))
                        <div class="md-growth-progress violet">
                            <span style="width:{{ $growthChallengePercent }}%"></span>
                        </div>
                        <div class="md-growth-card-meta">
                            {{ (int) data_get($growthChallenge, 'meaningful_days', 0) }}
                            meaningful day(s)
                        </div>
                    @elseif(Route::has('growth.challenge.join'))
                        <form method="POST" action="{{ route('growth.challenge.join') }}" class="mt-3">
                            @csrf
                            <button type="submit" class="md-growth-action violet">
                                <i class="fa-solid fa-flag-checkered"></i>
                                Join Challenge
                            </button>
                        </form>
                    @endif
                </article>

                <article class="md-growth-card referral">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-11 h-11 shrink-0 rounded-xl bg-sky-50 border border-sky-100 flex items-center justify-center overflow-hidden">
                            @if($dashboardSystemLogoUrl)
                                <img src="{{ $dashboardSystemLogoUrl }}"
                                     alt="My Digital Diary"
                                     class="w-8 h-8 object-contain">
                            @else
                                <span class="text-[10px] font-black text-sky-700">MDD</span>
                            @endif
                        </div>
                        <div class="md-growth-card-kicker">Grow together</div>
                    </div>
                    <div class="md-growth-card-title">
                        Invite someone who wants more intentional days.
                    </div>
                    <div class="md-growth-card-copy">
                        {{ (int) data_get($growthReferral, 'conversions', 0) }}
                        friend(s) joined from your invites.
                    </div>

                    @if(Route::has('growth.referral'))
                        <button type="button"
                                id="md-growth-referral-button"
                                class="md-growth-action sky"
                                data-referral-url="{{ route('growth.referral') }}">
                            <i class="fa-solid fa-share-nodes"></i>
                            Invite a friend
                        </button>
                    @endif
                </article>
            </div>

            <div class="md-growth-trust">
                <i class="fa-solid fa-lock"></i>
                <span><strong>Privacy promise:</strong> {{ $growthTrustMessage }}</span>
            </div>
        </section>
    @endif

    @if(!empty($personalProgress))
    <section class="md-dashboard-section">
        <div class="md-section-head"><div class="md-section-title"><i class="fa-solid fa-chart-line"></i> Your Progress</div>@if(Route::has('monthly-review'))<a href="{{ route('monthly-review') }}" class="md-section-link">My Month in Review <i class="fa-solid fa-arrow-right ml-1"></i></a>@endif</div>
        <div class="md-progress-grid">
            <a href="{{ route('financial-planner.index') }}" class="md-progress-card health">
                <div class="md-progress-head">
                    <div class="min-w-0">
                        <div class="md-progress-kicker">Financial Health</div>
                        <div class="md-progress-main">{{ (int)($financialHealth['score'] ?? 0) }}/100 · {{ $financialHealth['label'] ?? 'Getting started' }}</div>
                        <div class="md-progress-period">{{ ($financialHealth['is_current_period'] ?? true) ? 'This month' : ($financialHealth['period'] ?? 'Latest recorded month') }}</div>
                    </div>
                    <div class="md-progress-icon"><i class="fa-solid fa-arrow-trend-up"></i></div>
                </div>
                <div class="md-progress-metrics">
                    <div><div class="md-progress-label">Income</div><div class="md-progress-value">{{ $money($financialHealth['monthly_income'] ?? 0) }}</div></div>
                    <div><div class="md-progress-label">Expenses</div><div class="md-progress-value">{{ $money($financialHealth['monthly_expenses'] ?? 0) }}</div></div>
                    <div><div class="md-progress-label">Saved</div><div class="md-progress-value">{{ $money($financialHealth['monthly_savings'] ?? 0) }}</div></div>
                </div>
            </a>

            <a href="{{ route('activity') }}" class="md-progress-card week">
                <div class="md-progress-kicker">Your Week in Review</div>
                <div class="md-progress-main">{{ (int)($weeklyReview['completion_percent'] ?? 0) }}% task completion</div>
                <div class="md-progress-period">{{ ($weeklyReview['is_current_period'] ?? true) ? 'This week · ' : 'Most recent week · ' }}{{ $weeklyReview['period'] ?? '' }}</div>
                <div class="md-progress-metrics four">
                    <div><div class="md-progress-label">Tasks</div><div class="md-progress-value">{{ (int)($weeklyReview['completed_tasks'] ?? 0) }}/{{ (int)($weeklyReview['total_tasks'] ?? 0) }}</div></div>
                    <div><div class="md-progress-label">Spent</div><div class="md-progress-value">{{ $money($weeklyReview['expenses'] ?? 0) }}</div></div>
                    <div><div class="md-progress-label">Saved</div><div class="md-progress-value">{{ $money($weeklyReview['saved'] ?? 0) }}</div></div>
                    <div><div class="md-progress-label">Exercise</div><div class="md-progress-value">{{ (int)($weeklyReview['exercise_sessions'] ?? 0) }} session{{ (int)($weeklyReview['exercise_sessions'] ?? 0) === 1 ? '' : 's' }}</div></div>
                </div>
            </a>
        </div>
    </section>
    @endif

    <section class="md-dashboard-section">
        <div class="md-section-head"><div class="md-section-title"><i class="fa-solid fa-bolt"></i> Start Here</div></div>
        @php
            $dashboardShortcuts = [
                [
                    'title' => 'Daily Planner',
                    'subtitle' => 'Plan today',
                    'icon' => 'fa-calendar-day',
                    'bg' => '#ecfdf5',
                    'fg' => '#047857',
                    'border' => '#a7f3d0',
                    'url' => route('daily-planner.index'),
                ],
                [
                    'title' => 'Reminders',
                    'subtitle' => ($upcomingReminderCount ?? 0).' upcoming',
                    'icon' => 'fa-bell',
                    'bg' => '#fffbeb',
                    'fg' => '#b45309',
                    'border' => '#fde68a',
                    'url' => route('reminders.index'),
                ],
                [
                    'title' => 'Meetings',
                    'subtitle' => 'Calendar & notes',
                    'icon' => 'fa-video',
                    'bg' => '#f5f3ff',
                    'fg' => '#6d28d9',
                    'border' => '#ddd6fe',
                    'url' => route('meetings.index'),
                ],
                [
                    'title' => 'Annual Plans',
                    'subtitle' => 'Goals & progress',
                    'icon' => 'fa-list-check',
                    'bg' => '#eff6ff',
                    'fg' => '#1d4ed8',
                    'border' => '#bfdbfe',
                    'url' => route('annual-plans.index'),
                ],
                [
                    'title' => 'Projects',
                    'subtitle' => ($activeProjects ?? 0).' active',
                    'icon' => 'fa-diagram-project',
                    'bg' => '#f0f9ff',
                    'fg' => '#0369a1',
                    'border' => '#bae6fd',
                    'url' => route('projects.index'),
                ],
                [
                    'title' => 'Health',
                    'subtitle' => 'Checkups & wellbeing',
                    'icon' => 'fa-heart-pulse',
                    'bg' => '#fff1f2',
                    'fg' => '#be123c',
                    'border' => '#fecdd3',
                    'url' => route('health-checkups.index'),
                ],
            ];

            if (Route::has('social-media-planner.index')) {
                $dashboardShortcuts[] = [
                    'title' => 'Social Planner',
                    'subtitle' => 'Plan & schedule posts',
                    'icon' => 'fa-bullhorn',
                    'bg' => '#f0f9ff',
                    'fg' => '#0369a1',
                    'border' => '#bae6fd',
                    'url' => route('social-media-planner.index'),
                ];
            }
        @endphp

        <div class="md-shortcuts">
            @foreach($dashboardShortcuts as $item)
                <a href="{{ $item['url'] }}"
                   class="md-shortcut"
                   style="--c-bg:{{ $item['bg'] }};--c-fg:{{ $item['fg'] }};--c-border:{{ $item['border'] }}">
                    <div class="md-shortcut-icon">
                        <i class="fa-solid {{ $item['icon'] }}"></i>
                    </div>
                    <div class="md-shortcut-copy">
                        <div class="md-shortcut-title">{{ $item['title'] }}</div>
                        <div class="md-shortcut-sub">{{ $item['subtitle'] }}</div>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <section class="md-insight md-dashboard-section" style="--insight-bg:{{ $insightTone['bg'] }};--insight-fg:{{ $insightTone['fg'] }};--insight-border:{{ $insightTone['border'] }}">
        <div class="md-insight-inner">
            <div class="md-insight-icon"><i class="fa-solid {{ $dailyInsight['icon'] ?? 'fa-wand-magic-sparkles' }}"></i></div>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1"><span class="md-insight-kicker">TODAY'S INSIGHT</span><span class="text-[10px] md-muted">{{ $dailyInsight['category'] ?? 'Today' }}</span><span class="text-[10px] text-slate-400">• AI generated · refreshes every 2 hours</span>@if(\Illuminate\Support\Facades\Route::has('dashboard.today-insight.refresh'))<form method="POST" action="{{ route('dashboard.today-insight.refresh') }}" class="inline">@csrf<button type="submit" class="text-[10px] text-[var(--brand-1)] font-semibold hover:underline"><i class="fa-solid fa-rotate mr-1"></i>Refresh</button></form>@endif</div>
                <div class="md-insight-title">{{ $dailyInsight['title'] ?? 'Your personalised insight is loading.' }}</div>
                <div class="md-insight-message">{{ $dailyInsight['message'] ?? 'My Digital Diary will use the active AI provider to generate an insight from your current data.' }}</div>
            </div>
            <a href="{{ $dailyInsight['route'] ?? route('daily-planner.index') }}" class="md-insight-action">{{ $dailyInsight['action'] ?? 'Open planner' }} <i class="fa-solid fa-arrow-right ml-1"></i></a>
        </div>
    </section>

    <section class="md-dashboard-section md-shell md-tab-wrap" id="dashboard-tabbed-sections" data-default-tab="{{ $defaultDashboardTab }}">
        <div class="md-tabs" role="tablist" aria-label="Dashboard sections">
            <button type="button" class="md-tab-btn" data-tab-target="focus" role="tab" aria-controls="dashboard-panel-focus"><i class="fa-regular fa-sun"></i> Today’s Focus @if($todayFocus->isNotEmpty())<span class="text-[9px] opacity-80">{{ $todayFocus->count() }}</span>@endif</button>
            <button type="button" class="md-tab-btn" data-tab-target="actions" role="tab" aria-controls="dashboard-panel-actions"><i class="fa-solid fa-compass"></i> Next Best Actions @if($nextActions->isNotEmpty())<span class="text-[9px] opacity-80">{{ $nextActions->count() }}</span>@endif</button>
            <button type="button" class="md-tab-btn" data-tab-target="tools" role="tab" aria-controls="dashboard-panel-tools"><i class="fa-solid fa-grip"></i> Tools</button>
            <button type="button" class="md-tab-btn" data-tab-target="finance" role="tab" aria-controls="dashboard-panel-finance"><i class="fa-solid fa-wallet"></i> Finance at a Glance</button>
        </div>

        <div class="md-tab-panel" id="dashboard-panel-focus" data-tab-panel="focus" role="tabpanel">
            <div class="md-panel-head"><div class="md-panel-title">Today’s Focus</div><div class="flex items-center gap-3"><a href="{{ route('daily-planner.index') }}" class="md-section-link">Open planner</a><a href="{{ route('activity') }}" class="md-section-link">Recent activity</a></div></div>
            @if($todayFocus->isNotEmpty())
                <div class="md-focus-grid">
                    @foreach($todayFocus as $item)
                        @php
                            $focusTitle = is_array($item)
                                ? ($item['title'] ?? $item['name'] ?? $item['task'] ?? 'Daily task')
                                : ($item->title ?? $item->name ?? $item->task ?? 'Daily task');

                            $focusTime = is_array($item)
                                ? ($item['start_time'] ?? $item['due_time'] ?? $item['time'] ?? null)
                                : ($item->start_time ?? $item->due_time ?? $item->time ?? null);

                            try {
                                $focusTimeLabel = $focusTime
                                    ? \Illuminate\Support\Carbon::parse($focusTime)->format('g:i A')
                                    : 'Today';
                            } catch (\Throwable $e) {
                                $focusTimeLabel = $focusTime ?: 'Today';
                            }
                        @endphp

                        <a href="{{ route('daily-planner.index') }}"
                           class="md-focus-item"
                           style="--accent:{{ ['#0f766e','#2563eb','#7c3aed','#d97706'][$loop->index % 4] }}">
                            <div class="md-focus-num">{{ $loop->iteration }}</div>
                            <div class="md-row-copy">
                                <div class="md-row-title" title="{{ $focusTitle }}">{{ $focusTitle }}</div>
                                <div class="md-row-sub">{{ $focusTimeLabel }}</div>
                            </div>
                            <i class="fa-solid fa-chevron-right md-chevron"></i>
                        </a>
                    @endforeach
                </div>
            @else
                <x-empty-state icon="fa-regular fa-circle-check" title="Nothing scheduled for today" message="Enjoy the breathing room or open your planner to set a priority." />
            @endif
        </div>

        <div class="md-tab-panel" id="dashboard-panel-actions" data-tab-panel="actions" role="tabpanel">
            <div class="md-panel-head"><div class="md-panel-title">Next Best Actions</div>@if(Route::has('goal-intelligence'))<a href="{{ route('goal-intelligence') }}" class="md-section-link">View all goals</a>@endif</div>
            @if($nextActions->isNotEmpty())
                <div class="md-focus-grid">
                    @foreach($nextActions as $action)
                        @php $routeName = $action['route'] ?? null; @endphp
                        <a href="{{ $routeName && Route::has($routeName) ? route($routeName) : '#' }}" class="md-focus-item" style="--accent:{{ ($action['state'] ?? '') === 'overdue' ? '#e11d48' : '#f59e0b' }}">
                            <div class="md-focus-num"><i class="fa-solid {{ ($action['state'] ?? '') === 'overdue' ? 'fa-triangle-exclamation' : 'fa-arrow-trend-up' }}"></i></div>
                            <div class="md-row-copy"><div class="md-row-title">{{ $action['title'] ?? 'Next action' }}</div><div class="md-row-sub">{{ $action['message'] ?? 'Keep making progress on this goal.' }}</div></div>
                            <i class="fa-solid fa-chevron-right md-chevron"></i>
                        </a>
                    @endforeach
                </div>
            @else
                <x-empty-state icon="fa-solid fa-check" title="No urgent next actions" message="You have no urgent next actions right now." />
            @endif
        </div>

        <div class="md-tab-panel" id="dashboard-panel-tools" data-tab-panel="tools" role="tabpanel">
            <div class="md-panel-head"><div class="md-panel-title">Tools</div><span class="md-tab-meta">Quick access</span></div>
            <div class="md-tools-grid">
                @php
                    $dashboardTools = [
                        ['Sign Document','Sign documents','fa-signature','#eef2ff','#4338ca',route('signature.show')],
                        ['My Business Card','View & share','fa-address-card','#f0fdfa','#0f766e',route('business-card.edit')],
                        ['Notes','Capture ideas','fa-note-sticky','#fffbeb','#b45309',route('notes.index')],
                        ['AI Planner','Smart advice','fa-wand-magic-sparkles','#f5f3ff','#6d28d9',route('ai-plans.index')],
                        ['Financial Planner','Plan finances','fa-wallet','#ecfdf5','#047857',route('financial-planner.index')],
                        ['Expenses','Track spending','fa-receipt','#fff1f2','#be123c',route('expenses.index')],
                        ['Education','Learning plans','fa-graduation-cap','#eef2ff','#4338ca',route('education-plans.index')],
                        ['Network Contacts','People & follow-ups','fa-address-book','#f0f9ff','#0369a1',route('network-contacts.index')],
                        ['Spiritual Growth','Reflection','fa-seedling','#fdf4ff','#a21caf',route('spiritual-practices.index')],
                    ];

                    if (Route::has('social-media-planner.index')) {
                        $dashboardTools[] = [
                            'Social Media Planner',
                            'Schedule posts',
                            'fa-bullhorn',
                            '#f0f9ff',
                            '#0369a1',
                            route('social-media-planner.index'),
                        ];
                    }
                @endphp

                @foreach($dashboardTools as $tool)
                    <a href="{{ $tool[5] }}" class="md-tool-card" style="--tool-accent:{{ $tool[4] }}">
                        <div class="md-tool-icon" style="background:{{ $tool[3] }};color:{{ $tool[4] }}"><i class="fa-solid {{ $tool[2] }}"></i></div>
                        <div class="md-row-copy"><div class="md-row-title">{{ $tool[0] }}</div><div class="md-row-sub">{{ $tool[1] }}</div></div>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="md-tab-panel" id="dashboard-panel-finance" data-tab-panel="finance" role="tabpanel">
            <div class="md-panel-head"><div class="md-panel-title">Finance at a Glance</div><a href="{{ route('financial-planner.index') }}" class="md-section-link">Open planner</a></div>
            <div class="md-finance-grid">
                @foreach($financeCards as $card)
                    <a href="{{ $card['route'] }}" class="md-finance-card {{ $card['theme'] }}" title="Open {{ $card['title'] }}">
                        <div class="md-finance-top"><div class="md-finance-icon"><i class="fa-solid {{ $card['icon'] }}"></i></div><div class="md-finance-name">{{ $card['title'] }}</div><i class="fa-solid fa-chevron-right md-chevron ml-auto"></i></div>
                        <div class="md-finance-small">This month</div>
                        <div class="md-finance-value">{{ $money($card['monthly']) }}</div>
                        <div class="md-finance-small mt-1">Overall {{ $money($card['overall']) }}</div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
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

<script>
(function () {
    const root = document.getElementById('dashboard-tabbed-sections');
    if (!root) return;

    const buttons = Array.from(root.querySelectorAll('[data-tab-target]'));
    const panels = Array.from(root.querySelectorAll('[data-tab-panel]'));
    const storageKey = 'myDigitalDiary.dashboardTab';
    const validTabs = buttons.map(button => button.dataset.tabTarget);
    let initial = root.dataset.defaultTab || 'tools';

    try {
        const remembered = sessionStorage.getItem(storageKey);
        if (remembered && validTabs.includes(remembered)) initial = remembered;
    } catch (_) {}

    function openTab(name, remember = true) {
        if (!validTabs.includes(name)) name = root.dataset.defaultTab || 'tools';

        buttons.forEach(button => {
            const active = button.dataset.tabTarget === name;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
            button.tabIndex = active ? 0 : -1;
            if (active) button.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        });

        panels.forEach(panel => panel.classList.toggle('active', panel.dataset.tabPanel === name));
        if (remember) {
            try { sessionStorage.setItem(storageKey, name); } catch (_) {}
        }
    }

    buttons.forEach(button => {
        button.addEventListener('click', () => openTab(button.dataset.tabTarget));
        button.addEventListener('keydown', event => {
            if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
            event.preventDefault();
            const index = buttons.indexOf(button);
            let next = index;
            if (event.key === 'ArrowRight') next = (index + 1) % buttons.length;
            if (event.key === 'ArrowLeft') next = (index - 1 + buttons.length) % buttons.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = buttons.length - 1;
            openTab(buttons[next].dataset.tabTarget);
            buttons[next].focus();
        });
    });

    openTab(initial, false);
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

    checkin.addEventListener('click', () => {
        if (checkin.disabled) return;
        modal.close();
        window.mdOpenDailyCheckin?.(current === 'start' ? 'start-day' : 'close-day');
    });
})();
</script>


<script id="md-dashboard-usability-runtime">
(() => {
    const root = document.querySelector('.md-home');
    if (!root) return;

    function findTopSection(label) {
        return Array.from(root.querySelectorAll(':scope > section')).find(section => {
            const title = section.querySelector('.md-section-title');
            return title && title.textContent.trim().toLowerCase().includes(label);
        });
    }

    function wrapSecondary(section, options) {
        if (!section || section.closest('.md-secondary-wrap')) return null;

        const details = document.createElement('details');
        details.className = 'md-secondary-wrap';
        details.dataset.dashboardSecondary = options.key;
        details.open = !!options.open;

        const summary = document.createElement('summary');
        summary.className = 'md-secondary-summary';
        summary.innerHTML = `
            <div>
                <div class="md-secondary-summary-title">
                    <i class="fa-solid ${options.icon}"></i>
                    <span>${options.title}</span>
                </div>
                <div class="md-secondary-summary-copy">${options.copy}</div>
            </div>
            <i class="fa-solid fa-chevron-down md-secondary-summary-chevron"></i>
        `;

        const content = document.createElement('div');
        content.className = 'md-secondary-content';

        section.parentNode.insertBefore(details, section);
        content.appendChild(section);
        details.appendChild(summary);
        details.appendChild(content);

        return details;
    }

    const progress = findTopSection('your progress');
    const tools = findTopSection('start here');
    const growth = document.getElementById('growth-strategy-section')
        || document.getElementById('growth-strategy-card');

    wrapSecondary(progress, {
        key: 'progress',
        icon: 'fa-chart-line',
        title: 'Progress & wellbeing',
        copy: 'Your financial health, task completion and review metrics.',
        open: false,
    });

    const toolWrap = wrapSecondary(tools, {
        key: 'tools',
        icon: 'fa-grid-2',
        title: 'Tools & shortcuts',
        copy: 'Open the features you need without crowding your daily view.',
        open: false,
    });

    const growthDetails = wrapSecondary(growth, {
        key: 'growth',
        icon: 'fa-seedling',
        title: 'Growth & challenge',
        copy: 'Join the 30-Day Challenge and review your activation and referral progress.',
        open: false,
    });

    const progressDetails = document.querySelector(
        '.md-secondary-wrap[data-dashboard-secondary="progress"]'
    );
    const toolsDetails = document.querySelector(
        '.md-secondary-wrap[data-dashboard-secondary="tools"]'
    );

    if (growthDetails || progressDetails || toolsDetails) {
        const row = document.createElement('div');
        row.className = 'md-secondary-row';

        const first = growthDetails || progressDetails || toolsDetails;
        first.parentNode.insertBefore(row, first);

        [growthDetails, progressDetails, toolsDetails].forEach(details => {
            if (details) row.appendChild(details);
        });

        row.querySelectorAll('.md-secondary-wrap').forEach(details => {
            details.addEventListener('toggle', () => {
                if (!details.open) return;

                row.querySelectorAll('.md-secondary-wrap').forEach(other => {
                    if (other !== details) other.open = false;
                });

                requestAnimationFrame(() => {
                    details.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start',
                    });
                });
            });
        });
    }

    if (tools) {
        tools.classList.remove('md-dashboard-section');
        const shortcuts = tools.querySelector('.md-shortcuts');
        if (shortcuts && shortcuts.children.length > 4) {
            const actions = document.createElement('div');
            actions.className = 'md-more-tools';
            actions.innerHTML = `
                <button type="button" data-more-tools>
                    <i class="fa-solid fa-ellipsis mr-1"></i>
                    Show all tools
                </button>
            `;
            tools.appendChild(actions);

            actions.querySelector('[data-more-tools]')?.addEventListener('click', event => {
                const expanded = shortcuts.classList.toggle('md-tools-expanded');
                event.currentTarget.innerHTML = expanded
                    ? '<i class="fa-solid fa-chevron-up mr-1"></i> Show fewer tools'
                    : '<i class="fa-solid fa-ellipsis mr-1"></i> Show all tools';
            });
        }
    }

    document.querySelectorAll('[data-dashboard-panel-toggle]').forEach(button => {
        button.addEventListener('click', () => {
            const key = button.dataset.dashboardPanelToggle;
            const details = document.querySelector(
                `.md-secondary-wrap[data-dashboard-secondary="${key}"]`
            );

            if (!details) return;

            document
                .querySelectorAll('.md-secondary-row .md-secondary-wrap')
                .forEach(other => {
                    if (other !== details) other.open = false;
                });

            details.open = true;
            details.scrollIntoView({
                behavior:'smooth',
                block:'start',
            });
        });
    });

    document.querySelectorAll('.md-overview-link[href^="#"]').forEach(link => {
        link.addEventListener('click', event => {
            const target = document.querySelector(link.getAttribute('href'));
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({behavior:'smooth',block:'start'});
        });
    });

    // Keep the first-screen experience focused on Today and Focus.
    const rhythm = document.getElementById('daily-rhythm-section');
    const tabs = document.getElementById('dashboard-tabbed-sections');
    if (rhythm && tabs && rhythm.compareDocumentPosition(tabs) & Node.DOCUMENT_POSITION_FOLLOWING) {
        // Existing order is already good; no move required.
    }
})();
</script>

@endsection
