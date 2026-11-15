@extends('layouts.app')

@section('title', 'Statistics')

@section('content')
    <style>
        .stats-page {
            --stats-card-radius: 16px;
        }

        .stats-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }

        .stats-summary-card {
            position: relative;
            min-height: 148px;
            padding: 18px;
            border: 1px solid #e8edf4;
            border-left: 4px solid var(--stats-accent, #0f9488);
            border-radius: var(--stats-card-radius);
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .045);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        .stats-summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(15, 23, 42, .075);
        }

        .stats-summary-card::after {
            content: '';
            position: absolute;
            width: 90px;
            height: 90px;
            border-radius: 999px;
            right: -35px;
            top: -40px;
            background: var(--stats-soft, #ecfdf5);
            opacity: .75;
            pointer-events: none;
        }

        .stats-card-top,
        .stats-card-bottom {
            position: relative;
            z-index: 1;
        }

        .stats-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .stats-card-icon {
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            background: var(--stats-soft, #ecfdf5);
            color: var(--stats-accent, #0f9488);
            font-size: 15px;
        }

        .stats-card-label {
            margin-top: 11px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.25;
            font-weight: 500;
        }

        .stats-card-value {
            margin-top: 4px;
            color: #172033;
            font-size: clamp(22px, 2vw, 27px);
            line-height: 1.05;
            font-weight: 750;
            letter-spacing: -.025em;
        }

        .stats-card-value.is-money {
            color: var(--stats-accent, #0f9488);
        }

        .stats-card-note,
        .stats-card-link {
            min-height: 18px;
            margin-top: 9px;
            font-size: 11.5px;
            line-height: 1.35;
        }

        .stats-card-note {
            color: #94a3b8;
        }

        .stats-card-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--brand-1);
            font-weight: 600;
            text-decoration: none;
        }

        .stats-card-link:hover {
            text-decoration: underline;
        }

        .stats-detail-tabs {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px;
            margin-bottom: 16px;
            width: fit-content;
            max-width: 100%;
            overflow-x: auto;
            border: 1px solid #e5eaf1;
            border-radius: 12px;
            background: #f8fafc;
        }

        .stats-detail-tab {
            border: 0;
            border-radius: 9px;
            background: transparent;
            color: #64748b;
            padding: 9px 13px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            font-weight: 650;
            white-space: nowrap;
            transition: all .16s ease;
        }

        .stats-detail-tab[aria-selected="true"] {
            background: var(--brand-1);
            color: #fff;
            box-shadow: 0 5px 12px color-mix(in srgb, var(--brand-1) 20%, transparent);
        }

        .stats-detail-card {
            background: #fff;
            border: 1px solid #e8edf4;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
            padding: 18px;
        }

        @media (max-width: 1024px) {
            .stats-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .stats-summary-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .stats-summary-card {
                min-height: 128px;
                padding: 15px;
            }

            .stats-card-icon {
                width: 36px;
                height: 36px;
                flex-basis: 36px;
            }

            .stats-page-heading {
                margin-bottom: 16px !important;
            }
        }
    </style>

    <div class="stats-page">
        <div class="stats-page-heading flex items-center gap-3 mb-6">
            <div class="w-11 h-11 rounded-xl bg-violet-100 text-violet-600 flex items-center justify-center shadow-sm shrink-0">
                <i class="fa-solid fa-chart-line text-lg" aria-hidden="true"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Statistics</h1>
                <p class="text-sm text-slate-500 mt-0.5">A clear view of users, subscriptions and payment activity.</p>
            </div>
        </div>

        <div class="stats-summary-grid" aria-label="System statistics summary">
            <article class="stats-summary-card" style="--stats-accent:#3b82f6;--stats-soft:#eff6ff;">
                <div class="stats-card-top">
                    <div>
                        <span class="stats-card-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
                        <div class="stats-card-label">Total users</div>
                        <div class="stats-card-value">{{ number_format((int) $totalUsers) }}</div>
                    </div>
                </div>
                <div class="stats-card-bottom">
                    <div class="stats-card-note">All registered accounts</div>
                </div>
            </article>

            <article class="stats-summary-card" style="--stats-accent:#6366f1;--stats-soft:#eef2ff;">
                <div class="stats-card-top">
                    <div>
                        <span class="stats-card-icon"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></span>
                        <div class="stats-card-label">Admins</div>
                        <div class="stats-card-value">{{ number_format((int) $adminCount) }}</div>
                    </div>
                </div>
                <div class="stats-card-bottom">
                    <div class="stats-card-note">Administrative accounts</div>
                </div>
            </article>

            <article class="stats-summary-card" style="--stats-accent:#f43f5e;--stats-soft:#fff1f2;">
                <div class="stats-card-top">
                    <div>
                        <span class="stats-card-icon"><i class="fa-solid fa-user-slash" aria-hidden="true"></i></span>
                        <div class="stats-card-label">Suspended</div>
                        <div class="stats-card-value" @if($suspendedCount > 0) style="color:#e11d48" @endif>{{ number_format((int) $suspendedCount) }}</div>
                    </div>
                </div>
                <div class="stats-card-bottom">
                    <div class="stats-card-note">Accounts currently restricted</div>
                </div>
            </article>

            <article class="stats-summary-card" style="--stats-accent:#10b981;--stats-soft:#ecfdf5;">
                <div class="stats-card-top">
                    <div>
                        <span class="stats-card-icon"><i class="fa-solid fa-sack-dollar" aria-hidden="true"></i></span>
                        <div class="stats-card-label">Est. monthly revenue</div>
                        <div class="stats-card-value is-money">{{ format_money($estimatedMonthlyRevenue) }}</div>
                    </div>
                </div>
                <div class="stats-card-bottom">
                    <div class="stats-card-note">Active subscribers × base monthly price</div>
                </div>
            </article>

            <article class="stats-summary-card" style="--stats-accent:#14b8a6;--stats-soft:#f0fdfa;">
                <div class="stats-card-top">
                    <div>
                        <span class="stats-card-icon"><i class="fa-solid fa-money-bill-trend-up" aria-hidden="true"></i></span>
                        <div class="stats-card-label">Total collected</div>
                        <div class="stats-card-value is-money">{{ format_money($totalCollected) }}</div>
                    </div>
                </div>
                <div class="stats-card-bottom">
                    <div class="stats-card-note">Completed payments across all methods</div>
                </div>
            </article>

            <article class="stats-summary-card" style="--stats-accent:#f59e0b;--stats-soft:#fffbeb;">
                <div class="stats-card-top">
                    <div>
                        <span class="stats-card-icon"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></span>
                        <div class="stats-card-label">Pending verification</div>
                        <div class="stats-card-value" @if($pendingPaymentsCount > 0) style="color:#d97706" @endif>{{ number_format((int) $pendingPaymentsCount) }}</div>
                    </div>
                </div>
                <div class="stats-card-bottom">
                    <a href="{{ route('admin.payments.index', ['status' => 'pending']) }}" class="stats-card-link">
                        Review queue <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                    </a>
                </div>
            </article>
        </div>

        <div role="tablist" aria-label="Statistics detail sections" class="stats-detail-tabs">
            <button type="button" role="tab" id="pm-stats-tab-breakdown" aria-controls="pm-stats-panel-breakdown"
                    aria-selected="true" tabindex="0" data-tab="breakdown"
                    onclick="pmSelectStatsTab('breakdown')" onkeydown="pmStatsTabKeydown(event, 'breakdown')"
                    class="stats-detail-tab">
                <i class="fa-solid fa-table-list" aria-hidden="true"></i>
                <span>Breakdown</span>
            </button>
            <button type="button" role="tab" id="pm-stats-tab-chart" aria-controls="pm-stats-panel-chart"
                    aria-selected="false" tabindex="-1" data-tab="chart"
                    onclick="pmSelectStatsTab('chart')" onkeydown="pmStatsTabKeydown(event, 'chart')"
                    class="stats-detail-tab">
                <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                <span>Signups Chart</span>
            </button>
        </div>

        <div role="tabpanel" id="pm-stats-panel-breakdown" aria-labelledby="pm-stats-tab-breakdown" tabindex="0" class="pm-stats-panel">
            <div class="stats-detail-card max-w-xl">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <div>
                        <h2 class="font-semibold text-slate-800">Subscription mix</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Users grouped by current subscription status.</p>
                    </div>
                    <span class="stats-card-icon" style="--stats-accent:#0f9488;--stats-soft:#ecfdf5;">
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                    </span>
                </div>

                @if ($byStatus->isEmpty())
                    <div class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-6 text-center">
                        <p class="text-sm text-slate-500">No users yet.</p>
                    </div>
                @else
                    <div class="overflow-x-auto pm-admin-table-scroll">
                        <table class="w-full text-sm pm-admin-horizontal-table">
                            <caption class="sr-only">User count by subscription status.</caption>
                            <thead class="text-left text-slate-500 border-b border-slate-100">
                                <tr>
                                    <th scope="col" class="pb-2 font-medium">Status</th>
                                    <th scope="col" class="pb-2 text-right font-medium">Users</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($byStatus as $status => $count)
                                    <tr>
                                        <td class="py-2.5 text-slate-700">{{ ucfirst((string) $status) }}</td>
                                        <td class="py-2.5 text-right font-semibold text-slate-800">{{ number_format((int) $count) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div role="tabpanel" id="pm-stats-panel-chart" aria-labelledby="pm-stats-tab-chart" tabindex="0" class="pm-stats-panel" hidden>
            <div class="stats-detail-card">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <h2 class="font-semibold text-slate-800">Signups - last 30 days</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Daily new-account activity during the most recent 30-day period.</p>
                    </div>
                    <div class="text-xs text-slate-500 rounded-lg bg-slate-50 border border-slate-100 px-3 py-2">
                        Total: <strong class="text-slate-800">{{ number_format(array_sum($signupCounts)) }}</strong>
                    </div>
                </div>

                <div class="h-56">
                    <canvas id="signupChart" role="img"
                        aria-label="Line chart of daily signups over the last 30 days. Total signups in this period: {{ array_sum($signupCounts) }}. Peak day: {{ $signupLabels[array_search(max($signupCounts + [0]), $signupCounts)] ?? 'none' }} with {{ max($signupCounts + [0]) }} signups."
                    ></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script>
        var pmSignupChartInstance = null;

        function pmInitSignupChartIfNeeded() {
            if (pmSignupChartInstance) { return; }

            var canvas = document.getElementById('signupChart');
            if (!canvas || typeof Chart === 'undefined') { return; }

            pmSignupChartInstance = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: @json($signupLabels),
                    datasets: [{
                        label: 'Signups',
                        data: @json($signupCounts),
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,0.10)',
                        pointBackgroundColor: '#6366f1',
                        pointRadius: 2.5,
                        pointHoverRadius: 4,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.32,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: '#94a3b8', maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, color: '#94a3b8' },
                            grid: { color: 'rgba(148,163,184,.16)' },
                        },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: { displayColors: false },
                    },
                },
            });
        }

        function pmSelectStatsTab(key) {
            document.querySelectorAll('.stats-detail-tab').forEach(function (btn) {
                var isSelected = btn.dataset.tab === key;
                btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                btn.setAttribute('tabindex', isSelected ? '0' : '-1');
                if (isSelected) { btn.focus(); }
            });

            document.querySelectorAll('.pm-stats-panel').forEach(function (panel) {
                panel.hidden = panel.id !== 'pm-stats-panel-' + key;
            });

            if (key === 'chart') {
                window.setTimeout(pmInitSignupChartIfNeeded, 0);
            }
        }

        function pmStatsTabKeydown(event, currentKey) {
            var tabs = Array.prototype.map.call(document.querySelectorAll('.stats-detail-tab'), function (t) {
                return t.dataset.tab;
            });
            var index = tabs.indexOf(currentKey);
            var nextIndex = null;

            if (event.key === 'ArrowRight') { nextIndex = (index + 1) % tabs.length; }
            else if (event.key === 'ArrowLeft') { nextIndex = (index - 1 + tabs.length) % tabs.length; }
            else if (event.key === 'Home') { nextIndex = 0; }
            else if (event.key === 'End') { nextIndex = tabs.length - 1; }
            else { return; }

            event.preventDefault();
            pmSelectStatsTab(tabs[nextIndex]);
        }
    </script>
@endsection
