@extends('layouts.app')

@section('title', 'Announcement details')

@section('content')
    @php
        $fmt = fn ($at) => $at ? $at->copy()->timezone($timezone)->format('D j M Y, H:i') : '—';
        $feature = $announcement->feature();
    @endphp

    <a href="{{ route('admin.announcements.index') }}" class="text-sm text-[var(--brand-1)] hover:underline">&larr; All announcements</a>

    <div class="mt-2 mb-6 flex flex-wrap items-start justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shadow-sm shrink-0">
                <i class="{{ $feature['icon'] ?? 'fa-solid fa-bullhorn' }} text-xl" aria-hidden="true"></i>
            </div>
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight break-words">{{ $announcement->title }}</h1>
                <p class="text-sm text-slate-500">{{ $announcement->statusLabel() }} · times in {{ $timezone }}</p>
            </div>
        </div>

        @if ($announcement->isScheduled())
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.announcements.send-now', $announcement) }}"
                      data-confirm="Send this announcement now?" data-confirm-title="Send now?" data-confirm-text="Send now" data-confirm-danger="false">
                    @csrf
                    <button type="submit" class="btn-primary text-white px-4 py-2 rounded-lg text-sm font-semibold"><i class="fa-solid fa-paper-plane mr-1" aria-hidden="true"></i>Send now</button>
                </form>
                <form method="POST" action="{{ route('admin.announcements.cancel', $announcement) }}"
                      data-confirm="Cancel this scheduled announcement? Nothing will be sent." data-confirm-title="Cancel announcement?" data-confirm-text="Cancel it">
                    @csrf
                    <button type="submit" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                </form>
            </div>
        @endif
    </div>

    <x-summary-strip class="mb-6" label="Delivery summary" :stats="[
        ['label' => 'Reached', 'value' => number_format($stats['recipients']), 'icon' => 'fa-solid fa-users', 'color' => 'sky'],
        ['label' => 'In-app', 'value' => number_format($stats['in_app']), 'icon' => 'fa-solid fa-bell', 'color' => 'violet'],
        ['label' => 'Push', 'value' => number_format($stats['push']), 'icon' => 'fa-solid fa-mobile-screen', 'color' => 'teal'],
        ['label' => 'Email', 'value' => number_format($stats['email']), 'icon' => 'fa-solid fa-envelope', 'color' => 'emerald'],
        ['label' => 'Failed', 'value' => number_format($stats['failed']), 'icon' => 'fa-solid fa-triangle-exclamation', 'color' => 'rose'],
    ]" />

    <div class="grid gap-6 lg:grid-cols-[320px_1fr]">
        <section class="pm-card-bg rounded-xl border border-slate-100 p-5 shadow-sm min-w-0">
            <h2 class="font-semibold text-slate-800 mb-3">Details</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Type</dt><dd class="text-right">{{ $announcement->typeLabel() }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Feature</dt><dd class="text-right">{{ $feature['label'] ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Audience</dt><dd class="text-right">{{ $announcement->audienceLabel() }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Channels</dt><dd class="text-right">{{ implode(', ', $announcement->channels()) }}</dd></div>
                @if ($announcement->isNewFeature())
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Already told</dt>
                        <dd class="text-right">
                            @if ($announcement->skip_already_told)
                                Skipped{{ $announcement->skipped_count ? ' ('.number_format($announcement->skipped_count).')' : '' }}
                            @else
                                Included
                            @endif
                        </dd>
                    </div>
                @endif
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Status</dt><dd class="text-right">{{ $announcement->statusLabel() }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Scheduled for</dt><dd class="text-right">{{ $fmt($announcement->scheduled_for) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Sent</dt><dd class="text-right">{{ $fmt($announcement->sent_at) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Created</dt><dd class="text-right">{{ $fmt($announcement->created_at) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">By</dt><dd class="text-right">{{ $announcement->creator->name ?? 'Unknown' }}</dd></div>
            </dl>
            <p class="mt-4 border-t border-slate-100 pt-4 text-sm text-slate-600 whitespace-pre-line break-words">{{ $announcement->body }}</p>
        </section>

        <section class="pm-card-bg rounded-xl border border-slate-100 shadow-sm min-w-0">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4">
                <h2 class="font-semibold text-slate-800">Recipients</h2>
                <form method="GET" class="flex w-full gap-2 sm:w-auto">
                    <input name="search" value="{{ $search }}" class="pm-input w-full sm:w-64" placeholder="Search name or email" aria-label="Search recipients">
                    <button class="apple-btn rounded-xl px-4 py-2 text-sm font-bold">Search</button>
                </form>
            </div>

            <ul class="divide-y divide-slate-100">
                @forelse ($deliveries as $delivery)
                    <li class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-800">{{ $delivery->user->name ?? 'Deleted user' }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $delivery->user->email ?? '' }}</p>
                        </div>
                        <div class="flex flex-wrap gap-1.5 text-[11px]">
                            @foreach (['in_app_at' => 'In-app', 'push_at' => 'Push', 'email_at' => 'Email'] as $column => $label)
                                @if ($delivery->{$column})
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-700" title="{{ $fmt($delivery->{$column}) }}">
                                        {{ $label }} · {{ $delivery->{$column}->copy()->timezone($timezone)->format('j M, H:i') }}
                                    </span>
                                @endif
                            @endforeach
                            @foreach ($delivery->failedChannelLabels() as $label)
                                <span class="rounded-full bg-rose-50 px-2 py-0.5 font-semibold text-rose-700">{{ $label }} failed</span>
                            @endforeach
                        </div>
                    </li>
                @empty
                    <li class="p-8 text-center text-sm text-slate-400">
                        {{ $search !== '' ? 'No recipients match that search.' : ($announcement->isScheduled() ? 'Not sent yet.' : 'No deliveries recorded.') }}
                    </li>
                @endforelse
            </ul>

            <div class="p-4">{{ $deliveries->links() }}</div>
        </section>
    </div>
@endsection
