@extends('layouts.app')

@section('title', 'Social Media Reports')

@section('content')
@include('social-media-planner.partials.report-styles')

@php
    $s = $report['summary'] ?? [];
    $platforms = $report['platform_breakdown'] ?? [];
    $analytics = $report['analytics'] ?? [];
    $posts = $report['posts'] ?? collect();
    $sync = $report['sync'] ?? [];

    $summaryCards = [
        ['Total Posts', $s['total_posts'] ?? 0, 'fa-layer-group', 'smr-teal'],
        ['Drafts', $s['draft'] ?? 0, 'fa-file-pen', 'smr-slate'],
        ['Scheduled', $s['scheduled'] ?? 0, 'fa-clock', 'smr-amber'],
        ['Ready to Post', $s['ready_to_post'] ?? 0, 'fa-paper-plane', 'smr-sky'],
        ['Published', $s['published'] ?? 0, 'fa-circle-check', 'smr-emerald'],
        ['Overdue', $s['overdue'] ?? 0, 'fa-triangle-exclamation', 'smr-rose'],
        ['Failed', $s['failed'] ?? 0, 'fa-circle-xmark', 'smr-rose'],
    ];

    $analyticsCards = [
        ['Views', $analytics['total_views'] ?? 0, 'fa-eye', 'smr-blue'],
        ['Reach', $analytics['total_reach'] ?? 0, 'fa-users-viewfinder', 'smr-violet'],
        ['Impressions', $analytics['total_impressions'] ?? 0, 'fa-chart-simple', 'smr-blue'],
        ['Likes', $analytics['total_likes'] ?? 0, 'fa-heart', 'smr-rose'],
        ['Comments', $analytics['total_comments'] ?? 0, 'fa-comments', 'smr-sky'],
        ['Shares', $analytics['total_shares'] ?? 0, 'fa-share-nodes', 'smr-teal'],
        ['Saves', $analytics['total_saves'] ?? 0, 'fa-bookmark', 'smr-amber'],
        ['Clicks', $analytics['total_clicks'] ?? 0, 'fa-arrow-pointer', 'smr-blue'],
        ['Engagement %', number_format((float) ($analytics['engagement_rate'] ?? 0), 2), 'fa-chart-line', 'smr-emerald'],
    ];
@endphp

<div class="space-y-4">
    @include('social-media-planner.partials.navigation-tabs')
    <div class="apple-surface rounded-2xl p-4 sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-xs font-black uppercase tracking-[.12em] text-slate-400">
                    Communication Insights
                </div>
                <h1 class="mt-1 text-xl font-black text-slate-900">
                    Social Media Post Reports
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $report['period_start'] ?? '-' }}
                    -
                    {{ $report['period_end'] ?? '-' }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
@if(Route::has('social-media-planner.reports.sync'))
                <form method="POST" action="{{ route('social-media-planner.reports.sync') }}" class="inline">
                    @csrf
                    <button type="submit"
                            class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold">
                        <i class="fa-solid fa-rotate mr-1"></i>
                        Sync Analytics
                    </button>
                </form>
                @endif

                <a href="{{ route('social-media-planner.reports.csv', request()->query()) }}"
                   class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold">
                    <i class="fa-solid fa-file-csv mr-1"></i>
                    CSV
                </a>

                <a href="{{ route('social-media-planner.reports.pdf', request()->query()) }}"
                   class="btn-primary rounded-xl px-4 py-2.5 text-sm font-bold text-white">
                    <i class="fa-solid fa-file-pdf mr-1"></i>
                    PDF Report
                </a>
            </div>
        </div>
    </div>

    <div class="apple-surface rounded-2xl p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-xs font-black uppercase tracking-[.12em] text-slate-400">Automatic Analytics Sync</div>
                <div class="mt-1 text-sm font-bold text-slate-800">
                    {{ number_format((int) ($sync['api_synced_rows'] ?? 0)) }} API-synced post/platform record(s)
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    @if(!empty($sync['last_synced_at']))
                        Last synced {{ \Illuminate\Support\Carbon::parse($sync['last_synced_at'])->timezone(auth()->user()?->timezone ?: 'Africa/Kampala')->format('d M Y, g:i A') }}
                    @else
                        No automatic analytics have been fetched yet.
                    @endif
                    @if(($sync['error_rows'] ?? 0) > 0)
                        · {{ number_format((int) $sync['error_rows']) }} record(s) need API/account attention.
                    @endif
                </div>
            </div>
            <div class="rounded-xl border border-teal-100 bg-teal-50 px-3 py-2 text-xs font-bold text-teal-700">
                <i class="fa-solid fa-clock-rotate-left mr-1"></i>
                Automatic refresh every 30 minutes
            </div>
        </div>
    </div>

    <form method="GET"
          action="{{ route('social-media-planner.reports.index') }}"
          class="apple-surface rounded-2xl p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <select name="period" class="pm-input">
                @foreach([
                    'today' => 'Today',
                    'week' => 'This Week',
                    'month' => 'This Month',
                    'year' => 'This Year',
                    'all' => 'All Time',
                    'custom' => 'Custom',
                ] as $value => $label)
                    <option value="{{ $value }}"
                        @selected(($f['period'] ?? 'month') === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            <select name="platform" class="pm-input">
                <option value="">All Platforms</option>
                @foreach([
                    'instagram'=>'Instagram',
                    'facebook'=>'Facebook',
                    'x'=>'X (Twitter)',
                    'tiktok'=>'TikTok',
                    'linkedin'=>'LinkedIn',
                    'whatsapp_status'=>'WhatsApp Status',
                    'whatsapp_channel'=>'WhatsApp Channel',
                ] as $value => $label)
                    <option value="{{ $value }}"
                        @selected(($f['platform'] ?? null) === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            <select name="status" class="pm-input">
                <option value="">All Statuses</option>
                @foreach([
                    'draft'=>'Draft',
                    'scheduled'=>'Scheduled',
                    'ready_to_share'=>'Ready to Post',
                    'published'=>'Published',
                    'failed'=>'Failed',
                ] as $value => $label)
                    <option value="{{ $value }}"
                        @selected(($f['status'] ?? null) === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            <input type="date"
                   name="from"
                   value="{{ $f['from'] ?? '' }}"
                   class="pm-input">

            <div class="flex gap-2">
                <input type="date"
                       name="to"
                       value="{{ $f['to'] ?? '' }}"
                       class="pm-input min-w-0 flex-1">

                <button type="submit"
                        class="btn-primary rounded-xl px-4 font-bold text-white">
                    <i class="fa-solid fa-filter mr-1"></i>
                    Filter
                </button>
            </div>
        </div>
    </form>

    <section class="apple-surface rounded-2xl overflow-hidden">
        <div class="px-4 pt-2 sm:px-5">
            <div class="smp-subtabs" role="tablist" aria-label="Social media report sections">
                <button type="button" class="smp-subtab is-active" data-smp-report-tab="summary"><i class="fa-solid fa-chart-column"></i> Summary</button>
                <button type="button" class="smp-subtab" data-smp-report-tab="performance"><i class="fa-solid fa-chart-line"></i> Performance</button>
                <button type="button" class="smp-subtab" data-smp-report-tab="platforms"><i class="fa-solid fa-share-nodes"></i> Platforms</button>
                <button type="button" class="smp-subtab" data-smp-report-tab="history"><i class="fa-solid fa-list-check"></i> Post History</button>
            </div>
        </div>
        <div class="p-4 sm:p-5">
            <div class="smp-tab-panel" data-smp-report-panel="summary">    <section>
        <div class="mb-3 flex items-center gap-2">
            <i class="fa-solid fa-chart-column text-teal-600"></i>
            <h2 class="smr-section-title">Publishing Summary</h2>
        </div>

        <div class="smr-grid">
            @foreach($summaryCards as [$label, $value, $icon, $class])
                <article class="smr-card {{ $class }}">
                    <div class="smr-card-head">
                        <div>
                            <div class="smr-value">
                                {{ is_numeric($value) ? number_format((float) $value, 0) : $value }}
                            </div>
                            <div class="smr-label">{{ $label }}</div>
                        </div>

                        <span class="smr-icon">
                            <i class="fa-solid {{ $icon }}"></i>
                        </span>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

</div>
            <div class="smp-tab-panel" data-smp-report-panel="performance" hidden>    <section>
        <div class="mb-3 flex items-center gap-2">
            <i class="fa-solid fa-chart-line text-teal-600"></i>
            <h2 class="smr-section-title">Performance Analytics</h2>
        </div>

        <div class="smr-grid">
            @foreach($analyticsCards as [$label, $value, $icon, $class])
                <article class="smr-card {{ $class }}">
                    <div class="smr-card-head">
                        <div>
                            <div class="smr-value">
                                @if($label === 'Engagement %')
                                    {{ $value }}%
                                @else
                                    {{ number_format((float) $value, 0) }}
                                @endif
                            </div>
                            <div class="smr-label">{{ $label }}</div>
                        </div>

                        <span class="smr-icon">
                            <i class="fa-solid {{ $icon }}"></i>
                        </span>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

</div>
            <div class="smp-tab-panel" data-smp-report-panel="platforms" hidden>    <div class="grid gap-4 lg:grid-cols-2">
        <section class="p-0">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-share-nodes text-teal-600"></i>
                <h2 class="font-black">Posts by Platform</h2>
            </div>

            <div class="mt-4 space-y-3">
                @foreach([
                    'instagram'=>'Instagram',
                    'facebook'=>'Facebook',
                    'x'=>'X (Twitter)',
                    'tiktok'=>'TikTok',
                    'linkedin'=>'LinkedIn',
                    'whatsapp_status'=>'WhatsApp Status',
                    'whatsapp_channel'=>'WhatsApp Channel',
                ] as $key => $label)
                    <div class="flex items-center justify-between rounded-xl border-l-4 border-teal-600 bg-slate-50 px-3 py-2">
                        <span class="font-bold text-slate-700">{{ $label }}</span>
                        <strong>{{ number_format((int) ($platforms[$key] ?? 0)) }}</strong>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="p-0">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-heart-pulse text-teal-600"></i>
                <h2 class="font-black">Publishing Health</h2>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="smr-card smr-emerald">
                    <div class="smr-value">{{ number_format((int) ($s['published'] ?? 0)) }}</div>
                    <div class="smr-label">Published</div>
                </div>

                <div class="smr-card smr-sky">
                    <div class="smr-value">{{ number_format((int) ($s['ready_to_post'] ?? 0)) }}</div>
                    <div class="smr-label">Ready</div>
                </div>

                <div class="smr-card smr-amber">
                    <div class="smr-value">{{ number_format((int) ($s['scheduled'] ?? 0)) }}</div>
                    <div class="smr-label">Scheduled</div>
                </div>

                <div class="smr-card smr-rose">
                    <div class="smr-value">{{ number_format((int) ($s['overdue'] ?? 0)) }}</div>
                    <div class="smr-label">Overdue</div>
                </div>
            </div>
        </section>
    </div>

</div>
            <div class="smp-tab-panel" data-smp-report-panel="history" hidden>    <section class="overflow-hidden">
        <div class="border-b border-slate-100 p-4">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-list-check text-teal-600"></i>
                <h2 class="font-black">Post History</h2>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="smr-table">
                <thead>
                    <tr>
                        <th>Post</th>
                        <th>Platforms</th>
                        <th>Status</th>
                        <th>Scheduled</th>
                        <th>Posted</th>
                        <th>Views</th>
                        <th>Reach</th>
                        <th>Engagement</th>
                        <th>Last Sync</th>
                        <th class="text-right">Performance</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($posts as $post)
                        <tr>
                            <td>
                                <strong>{{ $post->title }}</strong>
                                <div class="mt-1 max-w-lg truncate text-xs text-slate-500">
                                    {{ $post->caption }}
                                </div>
                            </td>

                            <td>
                                {{ collect($post->platforms ?? [])
                                    ->map(fn($x) => ucwords(str_replace('_', ' ', $x)))
                                    ->join(', ') }}
                            </td>

                            <td>
                                <span class="smr-badge">
                                    {{ $post->status === 'ready_to_share'
                                        ? 'Ready to Post'
                                        : ucwords(str_replace('_', ' ', $post->status)) }}
                                </span>
                            </td>

                            <td>
                                {{ optional($post->scheduled_at)->format('d M Y H:i') ?: '-' }}
                            </td>

                            <td>
                                {{ optional($post->published_at)->format('d M Y H:i') ?: '-' }}
                            </td>

                            @php
                                $postMetrics = $post->metrics ?? collect();
                                $postViews = $postMetrics->sum('views');
                                $postReach = $postMetrics->sum('reach');
                                $postEngagements = $postMetrics->sum('engagements');
                                $postLastSync = $postMetrics->filter(fn($m) => $m->synced_at)->sortByDesc('synced_at')->first()?->synced_at;
                            @endphp
                            <td>{{ number_format((int) $postViews) }}</td>
                            <td>{{ number_format((int) $postReach) }}</td>
                            <td>{{ number_format((int) $postEngagements) }}</td>
                            <td class="whitespace-nowrap text-xs text-slate-500">
                                {{ $postLastSync ? $postLastSync->timezone(auth()->user()?->timezone ?: 'Africa/Kampala')->format('d M, g:i A') : '-' }}
                            </td>

                            <td class="text-right">
                                <a href="{{ route('social-media-planner.analytics.show', $post) }}"
                                   class="text-xs font-bold text-teal-700">
                                    <i class="fa-solid fa-chart-line mr-1"></i>
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10"
                                class="p-8 text-center text-slate-400">
                                No posts found for this report.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
        </div>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const tabs = document.querySelectorAll('[data-smp-report-tab]');
        const panels = document.querySelectorAll('[data-smp-report-panel]');
        function activate(name) {
            tabs.forEach(tab => tab.classList.toggle('is-active', tab.dataset.smpReportTab === name));
            panels.forEach(panel => panel.hidden = panel.dataset.smpReportPanel !== name);
            try { sessionStorage.setItem('mdd-social-report-tab', name); } catch (_) {}
        }
        tabs.forEach(tab => tab.addEventListener('click', () => activate(tab.dataset.smpReportTab)));
        let initial = 'summary';
        try { initial = sessionStorage.getItem('mdd-social-report-tab') || initial; } catch (_) {}
        activate(initial);
    });
    </script>
</div>
@endsection
