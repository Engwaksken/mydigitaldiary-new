@extends('layouts.app')

@section('title', 'Appreciation emails')

@section('content')
    @php
        $fmt = fn ($at) => $at ? $at->copy()->timezone($timezone)->format('D j M Y, H:i') : '—';
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-700 shadow-sm">
                <i class="fa-regular fa-heart text-xl" aria-hidden="true"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-800">Appreciation emails</h1>
                <p class="text-sm text-slate-500">Weekly run: {{ $weeklySchedule }} · one per person per week · times in {{ $timezone }}</p>
            </div>
        </div>
        <a href="{{ route('admin.settings.edit', ['tab' => 'weekly-email']) }}" class="text-sm font-semibold text-[var(--brand-1)] hover:underline">
            <i class="fa-solid fa-gear mr-1" aria-hidden="true"></i>Weekly email settings
        </a>
    </div>

    <x-summary-strip class="mb-6" label="Appreciation summary" :stats="[
        ['label' => 'Sent this week ('.$currentWeek.')', 'value' => number_format($stats['this_week']), 'icon' => 'fa-regular fa-heart', 'color' => 'teal'],
        ['label' => 'Sent in total', 'value' => number_format($stats['total']), 'icon' => 'fa-solid fa-envelope-circle-check', 'color' => 'emerald'],
        ['label' => 'Scheduled', 'value' => number_format($stats['scheduled']), 'icon' => 'fa-regular fa-clock', 'color' => 'sky'],
    ]" />

    @if ($scheduled->isNotEmpty())
        <section class="pm-card-bg mb-6 rounded-xl border border-slate-100 shadow-sm">
            <h2 class="border-b border-slate-100 px-5 py-4 font-semibold text-slate-800">Scheduled</h2>
            <ul class="divide-y divide-slate-100">
                @foreach ($scheduled as $campaign)
                    <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between" data-campaign="{{ $campaign->id }}">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-800">
                                <span class="mr-1 rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-bold text-sky-700">
                                    <i class="fa-regular fa-clock mr-0.5" aria-hidden="true"></i>Scheduled for {{ \App\Services\SendTime::format($campaign->scheduled_for) }}
                                </span>
                                {{ number_format(count($campaign->userIds())) }} {{ str('user')->plural(count($campaign->userIds())) }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                By {{ $campaign->creator->name ?? 'Unknown' }}
                                · {{ number_format(count((array) $campaign->drafts)) }} previewed {{ str('message')->plural(count((array) $campaign->drafts)) }} kept, the rest written when it sends
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.appreciation.campaigns.send-now', $campaign) }}"
                                  data-confirm="Send this appreciation email now? Eligibility and the once-a-week rule are checked as it sends." data-confirm-title="Send now?" data-confirm-text="Send now" data-confirm-danger="false">
                                @csrf
                                <button type="submit" class="btn-primary rounded-lg px-3 py-1.5 text-xs font-semibold text-white"><i class="fa-solid fa-paper-plane mr-1" aria-hidden="true"></i>Send now</button>
                            </form>
                            <form method="POST" action="{{ route('admin.appreciation.campaigns.cancel', $campaign) }}"
                                  data-confirm="Cancel this scheduled appreciation email? Nothing will be sent." data-confirm-title="Cancel appreciation email?" data-confirm-text="Cancel it">
                                @csrf
                                <button type="submit" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($recentCampaigns->isNotEmpty())
        <details class="pm-card-bg mb-6 rounded-xl border border-slate-100 shadow-sm">
            <summary class="cursor-pointer px-5 py-4 font-semibold text-slate-800">Recent admin sends</summary>
            <ul class="divide-y divide-slate-100 border-t border-slate-100">
                @foreach ($recentCampaigns as $campaign)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
                        <span class="min-w-0 text-slate-700">
                            {{ $campaign->statusLabel() }} · {{ $fmt($campaign->sent_at ?? $campaign->updated_at) }} · by {{ $campaign->creator->name ?? 'Unknown' }}
                        </span>
                        <span class="text-xs text-slate-500">
                            {{ number_format($campaign->sent_count) }} sent · {{ number_format($campaign->skipped_count) }} skipped · {{ number_format($campaign->failed_count) }} failed
                        </span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif

    <section class="pm-card-bg rounded-xl border border-slate-100 shadow-sm">
        <form method="GET" class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-[1fr_220px_auto]">
            <input name="search" value="{{ $search }}" class="pm-input w-full" placeholder="Search name or email" aria-label="Search by user">
            <select name="week" class="pm-input w-full" aria-label="Week">
                <option value="">Choose a week (optional)</option>
                @foreach ($weeks as $weekKey)
                    <option value="{{ $weekKey }}" @selected($week === $weekKey)>{{ $weekKey }}{{ $weekKey === $currentWeek ? ' (this week)' : '' }}</option>
                @endforeach
            </select>
            <button class="apple-btn rounded-xl px-4 py-2 text-sm font-bold">Filter</button>
        </form>

        <ul class="divide-y divide-slate-100">
            @forelse ($logs as $log)
                <li class="grid gap-2 p-4 md:grid-cols-[minmax(0,1.2fr)_90px_minmax(0,2fr)_auto] md:items-center">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-slate-800">
                            @if ($log->user)
                                <a href="{{ route('admin.users.show', $log->user) }}" class="hover:underline">{{ $log->user->name }}</a>
                            @else
                                Deleted user
                            @endif
                        </p>
                        <p class="truncate text-xs text-slate-500">{{ $log->user->email ?? '' }}</p>
                    </div>
                    <div class="text-xs font-semibold text-slate-600">{{ $log->week_key }}</div>
                    <div class="min-w-0">
                        <p class="truncate text-sm text-slate-700" title="{{ $log->subject }}">{{ $log->subject ?: '—' }}</p>
                        <p class="mt-0.5 flex flex-wrap gap-1.5 text-[11px]">
                            <span class="rounded-full {{ $log->source === 'ai' ? 'bg-violet-50 text-violet-700' : 'bg-slate-100 text-slate-600' }} px-2 py-0.5 font-semibold">{{ $log->sourceLabel() }}</span>
                            <span class="rounded-full {{ $log->originLabel() === 'Weekly run' ? 'bg-teal-50 text-teal-700' : 'bg-sky-50 text-sky-700' }} px-2 py-0.5 font-semibold">{{ $log->originLabel() }}</span>
                        </p>
                    </div>
                    <div class="text-xs text-slate-500 md:text-right">
                        {{ $log->sent_at ? $fmt($log->sent_at) : 'Sending…' }}
                    </div>
                </li>
            @empty
                <li class="p-8 text-center text-sm text-slate-400">No appreciation emails {{ $search !== '' || $week !== '' ? 'match these filters' : 'sent yet' }}.</li>
            @endforelse
        </ul>

        <div class="p-4">{{ $logs->links() }}</div>
    </section>
@endsection
