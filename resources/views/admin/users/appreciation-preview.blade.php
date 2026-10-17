@extends('layouts.app')

@section('title', 'Preview Appreciation Email')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
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

    <div class="grid gap-6 lg:grid-cols-[300px_1fr]">
        <aside class="space-y-4">
            <section class="pm-card-bg rounded-xl border border-slate-100 p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold text-slate-800">
                    Will receive it <span class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">{{ $eligible->count() }}</span>
                </h2>

                @forelse ($eligible as $user)
                    <button type="button"
                            class="appreciation-recipient mb-1 flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-50"
                            data-preview-url="{{ route('admin.users.appreciation.preview-email', $user) }}"
                            aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
                        <span class="min-w-0">
                            <span class="block truncate font-semibold text-slate-800">{{ $user->name }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ $user->email }}</span>
                        </span>
                        <i class="fa-solid fa-chevron-right text-xs text-slate-300" aria-hidden="true"></i>
                    </button>
                @empty
                    <p class="text-sm text-slate-500">None of the selected users can receive it this week.</p>
                @endforelse
            </section>

            @if ($skipped->isNotEmpty())
                <section class="pm-card-bg rounded-xl border border-slate-100 p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-bold text-slate-800">
                        Skipped <span class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">{{ $skipped->count() }}</span>
                    </h2>
                    <ul class="space-y-2">
                        @foreach ($skipped as $row)
                            <li class="flex items-start justify-between gap-2 text-sm">
                                <span class="min-w-0 truncate text-slate-700">{{ $row['user']->name }}</span>
                                <span class="shrink-0 text-xs text-amber-700">{{ $row['reason'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>

        <section class="pm-card-bg min-w-0 rounded-xl border border-slate-100 shadow-sm">
            @if ($eligible->isNotEmpty())
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Subject</p>
                        <p id="appreciation-subject" class="truncate font-semibold text-slate-800">Loading…</p>
                        <p id="appreciation-source" class="text-xs text-slate-500"></p>
                    </div>
                    <button type="button" id="appreciation-regenerate"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                        <i class="fa-solid fa-rotate" aria-hidden="true"></i> Write a new message
                    </button>
                </div>

                <iframe id="appreciation-frame" title="Email preview" class="block h-[640px] w-full rounded-b-xl bg-slate-50" sandbox=""></iframe>
            @else
                <div class="p-10 text-center text-sm text-slate-500">There is nothing to preview.</div>
            @endif
        </section>
    </div>

    @if ($eligible->isNotEmpty())
        <form method="POST" action="{{ route('admin.users.appreciation.send') }}"
              class="mt-6 flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end"
              data-confirm="Send the appreciation email to {{ $eligible->count() }} {{ str('user')->plural($eligible->count()) }} now? It cannot be undone."
              data-confirm-title="Send appreciation email?" data-confirm-text="Send now">
            @csrf
            @foreach ($eligible as $user)
                <input type="hidden" name="ids[]" value="{{ $user->id }}">
            @endforeach
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700">Cancel</a>
            <button type="submit" class="btn-primary inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-white">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                Send to {{ $eligible->count() }} {{ str('user')->plural($eligible->count()) }}
            </button>
        </form>

        <script>
            (function () {
                var buttons = document.querySelectorAll('.appreciation-recipient');
                var frame = document.getElementById('appreciation-frame');
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
                            source.textContent = data.source === 'ai' ? 'Written by AI for this person' : 'Built-in message (AI unavailable)';
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

                if (buttons.length) load(buttons[0], false);
            })();
        </script>
    @endif
@endsection
