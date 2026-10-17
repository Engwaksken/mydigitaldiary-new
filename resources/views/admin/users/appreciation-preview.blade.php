@extends('layouts.app')

@section('title', 'Preview Appreciation Email')

@section('content')
    @php
        $selectedCount = $eligibleCount + $skippedTotal;
        $timezone = \App\Services\SendTime::timezone();
        $when = old('when', 'now') === 'later' ? 'later' : 'now';
        $baseQuery = $selectionToken ? ['selection' => $selectionToken] : ['ids' => $ids];
    @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-700 shadow-sm">
                <i class="fa-regular fa-heart text-xl" aria-hidden="true"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-800">Preview Appreciation Email</h1>
                <p class="text-sm text-slate-500">Week {{ $weekKey }} · each person gets their own message</p>
            </div>
        </div>
        <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-slate-500 hover:underline">
            <i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i>Back to users
        </a>
    </div>

    <div class="mb-6 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900" data-weekly-rule>
        <p class="font-semibold"><i class="fa-solid fa-shield-heart mr-1" aria-hidden="true"></i>Once per week</p>
        <p class="mt-0.5 text-teal-800">
            Each person gets at most one appreciation email per week (Monday to Sunday), whether it comes from the weekly run
            ({{ $weeklySchedule }}) or from here. Anyone already sent one this week is skipped.
        </p>
        @if ($selectionDescription)
            <p class="mt-2 font-semibold break-words">Selected: {{ number_format($selectedCount) }} {{ str('user')->plural($selectedCount) }} matching {{ $selectionDescription }}</p>
        @endif
    </div>

    @if ($errors->any())
        <x-alert type="error" :dismissible="false" :autoDismiss="false">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-[320px_1fr]">
        <aside class="min-w-0 space-y-4">
            <section class="pm-card-bg rounded-xl border border-slate-100 p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold text-slate-800">
                    Will receive it <span class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">{{ number_format($eligibleCount) }}</span>
                </h2>

                @if ($eligibleCount > 50 || $search !== '')
                    <form method="GET" class="mb-3 flex gap-2">
                        @foreach ($baseQuery as $name => $value)
                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                        @endforeach
                        <input name="q" value="{{ $search }}" class="pm-input w-full" placeholder="Search name or email" aria-label="Search recipients">
                        <button class="apple-btn rounded-xl px-3 py-2 text-sm font-bold" aria-label="Search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
                    </form>
                @endif

                @forelse ($eligible as $user)
                    <button type="button"
                            class="appreciation-recipient mb-1 flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-50"
                            data-preview-url="{{ route('admin.users.appreciation.preview-email', $user) }}"
                            aria-pressed="false">
                        <span class="min-w-0">
                            <span class="block truncate font-semibold text-slate-800">{{ $user->name }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ $user->email }}</span>
                        </span>
                        <i class="fa-solid fa-chevron-right text-xs text-slate-300" aria-hidden="true"></i>
                    </button>
                @empty
                    <p class="text-sm text-slate-500">
                        {{ $search !== '' ? 'Nobody matches that search.' : 'None of the selected users can receive it this week.' }}
                    </p>
                @endforelse

                @if ($eligible->hasPages())
                    <div class="mt-3 text-sm">{{ $eligible->links() }}</div>
                @endif
            </section>

            @if ($skippedTotal > 0)
                <section class="pm-card-bg rounded-xl border border-slate-100 p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-bold text-slate-800">
                        Skipped <span class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">{{ number_format($skippedTotal) }}</span>
                    </h2>
                    <div class="space-y-2">
                        @foreach ($skippedGroups as $group)
                            <details class="rounded-lg border border-slate-100 px-3 py-2" data-skipped-reason="{{ $group['reason'] }}">
                                <summary class="flex cursor-pointer items-center justify-between gap-2 text-sm">
                                    <span class="font-semibold text-amber-700">{{ $group['reason'] }}</span>
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-700">{{ number_format($group['count']) }}</span>
                                </summary>
                                <ul class="mt-2 space-y-1">
                                    @foreach ($group['users'] as $row)
                                        <li class="truncate text-sm text-slate-700" title="{{ $row['email'] }}">{{ $row['name'] }}</li>
                                    @endforeach
                                    @if ($group['count'] > count($group['users']))
                                        <li class="text-xs text-slate-400">and {{ number_format($group['count'] - count($group['users'])) }} more</li>
                                    @endif
                                </ul>
                            </details>
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>

        <section class="pm-card-bg min-w-0 rounded-xl border border-slate-100 shadow-sm">
            @if ($eligibleCount > 0)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Subject</p>
                        <p id="appreciation-subject" class="truncate font-semibold text-slate-800">Choose a person to preview their email</p>
                        <p id="appreciation-source" class="text-xs text-slate-500">Messages are only written for the people you open; the rest are written when it sends.</p>
                    </div>
                    <button type="button" id="appreciation-regenerate" disabled
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-50">
                        <i class="fa-solid fa-rotate" aria-hidden="true"></i> Write a new message
                    </button>
                </div>

                <iframe id="appreciation-frame" title="Email preview" class="block h-[640px] w-full rounded-b-xl bg-slate-50" sandbox=""></iframe>
            @else
                <div class="p-10 text-center text-sm text-slate-500">There is nothing to preview this week.</div>
            @endif
        </section>
    </div>

    @if ($selectedCount > 0)
        <form method="POST" action="{{ route('admin.users.appreciation.send') }}" id="appreciation-send-form"
              class="pm-card-bg mt-6 space-y-4 rounded-xl border border-slate-100 p-4 shadow-sm sm:p-5"
              data-confirm="Send the appreciation email to {{ number_format($eligibleCount) }} {{ str('user')->plural($eligibleCount) }} now? It cannot be undone."
              data-confirm-title="Send appreciation email?" data-confirm-text="Send now">
            @csrf
            @if ($selectionToken)
                <input type="hidden" name="selection" value="{{ $selectionToken }}">
            @else
                @foreach (explode(',', (string) $ids) as $id)
                    @if ((int) $id > 0)
                        <input type="hidden" name="ids[]" value="{{ (int) $id }}">
                    @endif
                @endforeach
            @endif

            <fieldset class="space-y-3 text-sm text-slate-700">
                <legend class="mb-1 text-sm font-semibold text-slate-800">When to send</legend>
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    <label class="inline-flex cursor-pointer items-center gap-2">
                        <input type="radio" name="when" value="now" @checked($when === 'now') class="border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-1)]">
                        Send now
                    </label>
                    <label class="inline-flex cursor-pointer items-center gap-2">
                        <input type="radio" name="when" value="later" @checked($when === 'later') class="border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-1)]">
                        Schedule for later
                    </label>
                </div>
                <div id="appreciation-scheduled-row" class="flex flex-wrap items-center gap-2" @if ($when !== 'later') hidden @endif>
                    <label for="scheduled_for" class="sr-only">Date and time to send</label>
                    <input type="datetime-local" id="scheduled_for" name="scheduled_for" step="60" min="{{ \App\Services\SendTime::minInput() }}"
                           value="{{ old('scheduled_for') }}" placeholder="2026-10-20 09:00" class="pm-input w-full sm:w-auto">
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">24h · {{ $timezone }}</span>
                </div>
            </fieldset>

            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
                <a href="{{ route('admin.users.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700">Cancel</a>
                <button type="submit" id="appreciation-submit" class="btn-primary inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-white">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    <span id="appreciation-submit-label">Send to {{ number_format($eligibleCount) }} {{ str('user')->plural($eligibleCount) }}</span>
                </button>
            </div>
        </form>
    @endif

    <script>
        (function () {
            var form = document.getElementById('appreciation-send-form');
            if (form) {
                var sendLabel = @json('Send to '.number_format($eligibleCount).' '.str('user')->plural($eligibleCount));
                var scheduleLabel = @json('Schedule for '.number_format($selectedCount).' '.str('user')->plural($selectedCount));
                var sendConfirm = form.dataset.confirm;
                var timezone = @json($timezone);
                var sync = function () {
                    var checked = form.querySelector('input[name="when"]:checked');
                    var later = checked && checked.value === 'later';
                    document.getElementById('appreciation-scheduled-row').hidden = !later;
                    document.getElementById('appreciation-submit-label').textContent = later ? scheduleLabel : sendLabel;
                    var at = (document.getElementById('scheduled_for').value || '').replace('T', ' ');
                    form.dataset.confirm = later
                        ? 'Schedule the appreciation email for ' + (at || 'the chosen time') + ' (' + timezone + ')? Who is eligible, and the once-a-week rule, are checked when it sends. Previewed messages are kept exactly as shown.'
                        : sendConfirm;
                    form.dataset.confirmTitle = later ? 'Schedule appreciation email?' : 'Send appreciation email?';
                    form.dataset.confirmText = later ? 'Schedule' : 'Send now';
                };
                form.addEventListener('change', sync);
                form.addEventListener('input', sync);
                sync();
            }

            var buttons = document.querySelectorAll('.appreciation-recipient');
            var frame = document.getElementById('appreciation-frame');
            if (!buttons.length || !frame) { return; }
            var subject = document.getElementById('appreciation-subject');
            var source = document.getElementById('appreciation-source');
            var regenerate = document.getElementById('appreciation-regenerate');
            var current = null;

            function load(button, fresh) {
                current = button;
                buttons.forEach(function (b) {
                    var active = b === button;
                    b.setAttribute('aria-pressed', active ? 'true' : 'false');
                    b.classList.toggle('bg-slate-100', active);
                });
                subject.textContent = fresh ? 'Writing a new message…' : 'Loading…';
                source.textContent = '';
                regenerate.disabled = true;

                var url = button.dataset.previewUrl + (fresh ? '?regenerate=1' : '');
                fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Preview unavailable');
                        return response.json();
                    })
                    .then(function (data) {
                        if (current !== button) return;
                        subject.textContent = data.subject;
                        source.textContent = (data.source === 'ai' ? 'Written by AI for this person' : 'Built-in message (AI unavailable)') + ' · this exact text is sent';
                        frame.srcdoc = data.html;
                    })
                    .catch(function () {
                        if (current !== button) return;
                        subject.textContent = 'Could not load the preview. Try again.';
                    })
                    .finally(function () { regenerate.disabled = false; });
            }

            buttons.forEach(function (button) {
                button.addEventListener('click', function () { load(button, false); });
            });
            regenerate.addEventListener('click', function () { if (current) load(current, true); });
        })();
    </script>
@endsection
