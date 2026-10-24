@extends('layouts.app')

@section('title', 'Post Performance')

@section('content')
@php
    $platformLabels = [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'x' => 'X',
        'tiktok' => 'TikTok',
        'linkedin' => 'LinkedIn',
        'whatsapp_status' => 'WhatsApp Status',
        'whatsapp_channel' => 'WhatsApp Channel',
    ];

    $metricsCollection = collect($metrics ?? []);
    $metricsByPlatform = $metricsCollection->keyBy('platform');
    $snapshotRows = collect($snapshots ?? []);
    $platformList = collect((array) ($post->platforms ?? []))
        ->filter()
        ->values();

    $totals = [
        'views' => (int) $metricsCollection->sum('views'),
        'reach' => (int) $metricsCollection->sum('reach'),
        'impressions' => (int) $metricsCollection->sum('impressions'),
        'engagements' => (int) $metricsCollection->sum('engagements'),
    ];
@endphp

<div class="space-y-4" id="social-media-analytics">
    @include('social-media-planner.partials.navigation-tabs')

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc space-y-1 pl-5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @unless($analyticsStorageReady ?? false)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Analytics storage is not fully ready yet. The page is available, but manual metric updates require the latest social media analytics migration.
        </div>
    @endunless

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="text-xs font-black uppercase tracking-[.12em] text-slate-400">Post Performance</div>
            <h1 class="mt-1 text-xl font-black text-slate-900">{{ $post->title ?: 'Social Media Post' }}</h1>
            <p class="mt-1 text-sm text-slate-500">Review performance, update metrics and view recent metric history.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if(Route::has('social-media-planner.analytics.sync'))
                <form method="POST" action="{{ route('social-media-planner.analytics.sync', $post) }}">
                    @csrf
                    <button type="submit" class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold">
                        <i class="fa-solid fa-rotate mr-1"></i> Sync Analytics
                    </button>
                </form>
            @endif

            <a href="{{ route('social-media-planner.reports.index') }}" class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold">
                <i class="fa-solid fa-arrow-left mr-1"></i> Reports
            </a>
        </div>
    </div>

    <section class="apple-surface rounded-2xl overflow-hidden">
        <div class="px-4 pt-2 sm:px-5">
            <div class="smp-subtabs" role="tablist" aria-label="Post analytics sections">
                <button type="button" class="smp-subtab is-active" data-smp-analytics-tab="overview" aria-selected="true">
                    <i class="fa-solid fa-chart-pie"></i> Overview
                </button>
                <button type="button" class="smp-subtab" data-smp-analytics-tab="update" aria-selected="false">
                    <i class="fa-solid fa-pen-to-square"></i> Update Metrics
                </button>
                <button type="button" class="smp-subtab" data-smp-analytics-tab="history" aria-selected="false">
                    <i class="fa-solid fa-clock-rotate-left"></i> History
                </button>
            </div>
        </div>

        <div class="p-4 sm:p-5">
            <div class="smp-tab-panel" data-smp-analytics-panel="overview">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach([
                        ['Views', $totals['views'], 'fa-eye'],
                        ['Reach', $totals['reach'], 'fa-users-viewfinder'],
                        ['Impressions', $totals['impressions'], 'fa-chart-simple'],
                        ['Engagements', $totals['engagements'], 'fa-heart'],
                    ] as [$label, $value, $icon])
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-2xl font-black text-slate-900">{{ number_format((int) $value) }}</div>
                                    <div class="mt-1 text-xs font-bold text-slate-500">{{ $label }}</div>
                                </div>
                                <i class="fa-solid {{ $icon }} text-lg text-teal-600"></i>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Platform</th>
                                <th class="px-4 py-3">Views</th>
                                <th class="px-4 py-3">Reach</th>
                                <th class="px-4 py-3">Likes</th>
                                <th class="px-4 py-3">Comments</th>
                                <th class="px-4 py-3">Shares</th>
                                <th class="px-4 py-3">Engagement</th>
                                <th class="px-4 py-3">Last Updated</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($platformList as $platform)
                                @php
                                    $m = $metricsByPlatform->get($platform);
                                    $syncedAt = data_get($m, 'synced_at');
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 font-black text-slate-800">{{ $platformLabels[$platform] ?? ucwords(str_replace('_', ' ', $platform)) }}</td>
                                    <td class="px-4 py-3">{{ number_format((int) data_get($m, 'views', 0)) }}</td>
                                    <td class="px-4 py-3">{{ number_format((int) data_get($m, 'reach', 0)) }}</td>
                                    <td class="px-4 py-3">{{ number_format((int) data_get($m, 'likes', 0)) }}</td>
                                    <td class="px-4 py-3">{{ number_format((int) data_get($m, 'comments', 0)) }}</td>
                                    <td class="px-4 py-3">{{ number_format((int) data_get($m, 'shares', 0)) }}</td>
                                    <td class="px-4 py-3 font-bold text-teal-700">{{ number_format((float) data_get($m, 'engagement_rate', 0), 2) }}%</td>
                                    <td class="px-4 py-3 text-xs text-slate-500">
                                        @if($syncedAt)
                                            {{ \Illuminate\Support\Carbon::parse($syncedAt)->timezone(auth()->user()?->timezone ?: 'Africa/Kampala')->format('d M Y, g:i A') }}
                                        @else
                                            Not yet synced
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">This post has no selected platforms.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="smp-tab-panel" data-smp-analytics-panel="update" hidden>
                @forelse($platformList as $platform)
                    @php $m = $metricsByPlatform->get($platform); @endphp
                    <div class="border-b border-slate-200 py-5 first:pt-0 last:border-0 last:pb-0">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 class="font-black text-slate-900">{{ $platformLabels[$platform] ?? ucwords(str_replace('_', ' ', $platform)) }}</h2>
                                <p class="text-xs text-slate-500">Enter metrics manually when provider API metrics are unavailable.</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                                {{ number_format((float) data_get($m, 'engagement_rate', 0), 2) }}% engagement
                            </span>
                        </div>

                        <form method="POST" action="{{ route('social-media-planner.analytics.update', $post) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="platform" value="{{ $platform }}">

                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                                @foreach([
                                    'views' => 'Views',
                                    'reach' => 'Reach',
                                    'impressions' => 'Impressions',
                                    'likes' => 'Likes / Reactions',
                                    'comments' => 'Comments',
                                    'shares' => 'Shares',
                                    'saves' => 'Saves',
                                    'clicks' => 'Clicks',
                                    'replies' => 'Replies',
                                ] as $name => $label)
                                    <div>
                                        <label class="text-xs font-bold text-slate-700">{{ $label }}</label>
                                        <input type="number" min="0" name="{{ $name }}" value="{{ old($name, data_get($m, $name, 0)) }}" class="pm-input mt-1 w-full">
                                    </div>
                                @endforeach

                                <div>
                                    <label class="text-xs font-bold text-slate-700">External Post ID</label>
                                    <input name="external_post_id" value="{{ old('external_post_id', data_get($m, 'external_post_id', '')) }}" class="pm-input mt-1 w-full">
                                </div>
                            </div>

                            <div class="mt-4 flex justify-end">
                                <button type="submit" class="btn-primary rounded-xl px-5 py-2.5 text-sm font-bold text-white" @disabled(!($analyticsStorageReady ?? false))>
                                    Update {{ $platformLabels[$platform] ?? 'Platform' }} Metrics
                                </button>
                            </div>
                        </form>
                    </div>
                @empty
                    <div class="py-10 text-center text-sm text-slate-400">This post has no platforms to update.</div>
                @endforelse
            </div>

            <div class="smp-tab-panel" data-smp-analytics-panel="history" hidden>
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full min-w-[780px] text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Captured</th>
                                <th class="px-4 py-3">Platform</th>
                                <th class="px-4 py-3">Views</th>
                                <th class="px-4 py-3">Reach</th>
                                <th class="px-4 py-3">Impressions</th>
                                <th class="px-4 py-3">Engagements</th>
                                <th class="px-4 py-3">Engagement %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($snapshotRows as $snapshot)
                                <tr>
                                    <td class="px-4 py-3 text-xs text-slate-500">
                                        {{ data_get($snapshot, 'captured_at') ? \Illuminate\Support\Carbon::parse(data_get($snapshot, 'captured_at'))->timezone(auth()->user()?->timezone ?: 'Africa/Kampala')->format('d M Y, g:i A') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 font-bold">{{ $platformLabels[data_get($snapshot, 'platform')] ?? ucwords(str_replace('_', ' ', (string) data_get($snapshot, 'platform', ''))) }}</td>
                                    <td class="px-4 py-3">{{ number_format((int) data_get($snapshot, 'views', 0)) }}</td>
                                    <td class="px-4 py-3">{{ number_format((int) data_get($snapshot, 'reach', 0)) }}</td>
                                    <td class="px-4 py-3">{{ number_format((int) data_get($snapshot, 'impressions', 0)) }}</td>
                                    <td class="px-4 py-3">{{ number_format((int) data_get($snapshot, 'engagements', 0)) }}</td>
                                    <td class="px-4 py-3 font-bold text-teal-700">{{ number_format((float) data_get($snapshot, 'engagement_rate', 0), 2) }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-slate-400">No metric history has been captured yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('[data-smp-analytics-tab]');
    const panels = document.querySelectorAll('[data-smp-analytics-panel]');

    function activate(name) {
        tabs.forEach((tab) => {
            const active = tab.dataset.smpAnalyticsTab === name;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        panels.forEach((panel) => {
            panel.hidden = panel.dataset.smpAnalyticsPanel !== name;
        });

        try { sessionStorage.setItem('mdd-social-analytics-tab', name); } catch (_) {}
    }

    tabs.forEach((tab) => tab.addEventListener('click', () => activate(tab.dataset.smpAnalyticsTab)));

    let initial = 'overview';
    try { initial = sessionStorage.getItem('mdd-social-analytics-tab') || initial; } catch (_) {}
    @if($errors->any()) initial = 'update'; @endif
    activate(initial);
});
</script>
@endsection
