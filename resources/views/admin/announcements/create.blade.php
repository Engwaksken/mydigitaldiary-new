@extends('layouts.app')

@section('title', 'New Announcement')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-bullhorn text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">New Announcement</h1>
    </div>

    @php
        $tab = $errors->hasAny(['title', 'body', 'type']) ? 0
            : ($errors->hasAny(['feature_key', 'link_url', 'cta_label']) ? 1
                : ($errors->hasAny(['send_push', 'send_email', 'skip_already_told', 'when', 'scheduled_for']) ? 2 : 0));
        $tabs = ['Message', 'Feature & link', 'Delivery', 'Preview'];
        $type = old('type', '');
        $sendPush = (bool) old('send_push', true);
        $sendEmail = (bool) old('send_email', false);
        $skipAlreadyTold = (bool) old('skip_already_told', true);
        $when = old('when', 'now') === 'later' ? 'later' : 'now';
        $counts = $recipientCounts ?? ['in_app' => 0, 'push' => 0, 'email' => 0];
    @endphp

    <form method="POST" action="{{ route('admin.announcements.store') }}" id="announcement-form"
          class="max-w-2xl pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 space-y-5"
          data-confirm="Send this announcement now? It cannot be undone." data-confirm-title="Send announcement?" data-confirm-text="Send now">
        @csrf

        @if ($selectionToken)
            <input type="hidden" name="selection" value="{{ $selectionToken }}">
            <div class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" data-announcement-recipients>
                <div class="min-w-0">
                    <p class="font-semibold break-words">
                        <i class="fa-solid fa-user-check mr-1" aria-hidden="true"></i>
                        Sending to {{ number_format($selectionCount) }} {{ str('user')->plural($selectionCount) }} matching: {{ $selectionDescription }}
                    </p>
                </div>
                <a href="{{ route('admin.announcements.create') }}" class="shrink-0 text-xs font-semibold text-sky-700 underline">Send to everyone instead</a>
            </div>
            @error('selection')<p role="alert" class="text-sm text-rose-600">{{ $message }}</p>@enderror
        @elseif ($recipients)
            @foreach ($recipients as $recipient)
                <input type="hidden" name="recipient_ids[]" value="{{ $recipient->id }}">
            @endforeach
            <div class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" data-announcement-recipients>
                <div class="min-w-0">
                    <p class="font-semibold">
                        <i class="fa-solid fa-user-check mr-1" aria-hidden="true"></i>
                        Sending to {{ $recipients->count() }} selected {{ str('user')->plural($recipients->count()) }}
                    </p>
                    <p class="mt-0.5 text-xs text-sky-800 break-words">
                        {{ $recipients->take(6)->pluck('name')->join(', ') }}@if ($recipients->count() > 6) and {{ $recipients->count() - 6 }} more @endif
                    </p>
                </div>
                <a href="{{ route('admin.announcements.create') }}" class="shrink-0 text-xs font-semibold text-sky-700 underline">Send to everyone instead</a>
            </div>
        @endif

        <div class="pm-form-tabs" role="tablist" aria-label="Announcement sections" data-pm-form-tabs>
            @foreach ($tabs as $i => $label)
                <button type="button" role="tab" id="announcement-tab-{{ $i }}" aria-controls="announcement-panel-{{ $i }}" data-pm-form-tab="{{ $i }}"
                        aria-selected="{{ $tab === $i ? 'true' : 'false' }}" tabindex="{{ $tab === $i ? 0 : -1 }}"
                        class="pm-form-tab {{ $tab === $i ? 'is-active' : '' }}">{{ $label }}</button>
            @endforeach
        </div>

        {{-- Message --}}
        <div class="pm-form-panel" role="tabpanel" id="announcement-panel-0" aria-labelledby="announcement-tab-0" data-pm-form-panel="0" @if ($tab !== 0) hidden @endif>
            <div>
                <label for="type" class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                <select id="type" name="type" class="pm-input">
                    <option value="">Choose a type (optional)</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('type')<p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="title" class="block text-sm font-medium text-slate-700 mb-1">Title</label>
                <input type="text" id="title" name="title" value="{{ old('title') }}" placeholder="e.g. Meet the new Team Chat"
                       required aria-required="true" maxlength="255" class="pm-input" @error('title') aria-invalid="true" @enderror>
                @error('title')<p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="body" class="block text-sm font-medium text-slate-700 mb-1">Message</label>
                <textarea id="body" name="body" rows="6" required aria-required="true" maxlength="2000" placeholder="What's new and how it helps"
                          class="pm-input" @error('body') aria-invalid="true" @enderror>{{ old('body') }}</textarea>
                @error('body')<p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Feature & link --}}
        <div class="pm-form-panel" role="tabpanel" id="announcement-panel-1" aria-labelledby="announcement-tab-1" data-pm-form-panel="1" @if ($tab !== 1) hidden @endif>
            <div>
                <label for="feature_key" class="block text-sm font-medium text-slate-700 mb-1">Feature</label>
                <select id="feature_key" name="feature_key" class="pm-input" @error('feature_key') aria-invalid="true" @enderror>
                    <option value="">Choose a feature (optional)</option>
                    @foreach ($features as $key => $feature)
                        <option value="{{ $key }}" @selected(old('feature_key') === $key)>{{ $feature['label'] }}</option>
                    @endforeach
                </select>
                @error('feature_key')<p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="link_url" class="block text-sm font-medium text-slate-700 mb-1">Link</label>
                <input type="text" id="link_url" name="link_url" value="{{ old('link_url') }}" maxlength="255"
                       placeholder="/meetings or https://… (defaults to the feature)" class="pm-input" @error('link_url') aria-invalid="true" @enderror>
                @error('link_url')<p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="cta_label" class="block text-sm font-medium text-slate-700 mb-1">Button label</label>
                <input type="text" id="cta_label" name="cta_label" value="{{ old('cta_label') }}" maxlength="40"
                       placeholder="Explore now" class="pm-input" @error('cta_label') aria-invalid="true" @enderror>
                @error('cta_label')<p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Delivery --}}
        <div class="pm-form-panel" role="tabpanel" id="announcement-panel-2" aria-labelledby="announcement-tab-2" data-pm-form-panel="2" @if ($tab !== 2) hidden @endif>
            <div id="already-told-notice" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 space-y-3" @if (! $alreadyToldNotice) hidden @endif>
                <p class="flex items-start gap-2">
                    <i class="fa-solid fa-clock-rotate-left mt-0.5" aria-hidden="true"></i>
                    <span id="already-told-text">{{ $alreadyToldNotice }}</span>
                </p>
            </div>

            <label id="skip-already-told-row" class="rounded-lg border border-slate-200 p-4 text-sm text-slate-700 flex items-center gap-3 cursor-pointer" @if ($type !== 'new_feature') hidden @endif>
                <input type="hidden" name="skip_already_told" value="0">
                <input type="checkbox" id="skip_already_told" name="skip_already_told" value="1" @checked($skipAlreadyTold) class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-1)]">
                <span class="flex-1">Skip users who were already told about this feature</span>
            </label>

            <div class="rounded-lg border border-slate-200 p-4 text-sm text-slate-700 flex items-center gap-3" data-count-row="in_app">
                <i class="fa-solid fa-bell text-slate-400 w-5 text-center" aria-hidden="true"></i>
                <span class="flex-1">In-app notification</span>
                <span class="text-slate-500">{{ number_format($counts['in_app']) }} users</span>
            </div>

            <label class="rounded-lg border border-slate-200 p-4 text-sm text-slate-700 flex items-center gap-3 cursor-pointer" data-count-row="push">
                <input type="hidden" name="send_push" value="0">
                <input type="checkbox" id="send_push" name="send_push" value="1" @checked($sendPush) class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-1)]">
                <span class="flex-1">Send push notification</span>
                <span class="text-slate-500">{{ number_format($counts['push']) }} with a device</span>
            </label>

            <label class="rounded-lg border border-slate-200 p-4 text-sm text-slate-700 flex items-center gap-3 cursor-pointer" data-count-row="email">
                <input type="hidden" name="send_email" value="0">
                <input type="checkbox" id="send_email" name="send_email" value="1" @checked($sendEmail) class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-1)]">
                <span class="flex-1">Also send email</span>
                <span class="text-slate-500">{{ number_format($counts['email']) }} opted in</span>
            </label>

            <fieldset class="rounded-lg border border-slate-200 p-4 text-sm text-slate-700 space-y-3">
                <legend class="px-1 text-sm font-medium text-slate-700">When to send</legend>
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="when" value="now" @checked($when === 'now') class="border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-1)]">
                        Send now
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="when" value="later" @checked($when === 'later') class="border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-1)]">
                        Schedule for later
                    </label>
                </div>
                <div id="scheduled-for-row" class="flex flex-wrap items-center gap-2" @if ($when !== 'later') hidden @endif>
                    <label for="scheduled_for" class="sr-only">Date and time to send</label>
                    <input type="datetime-local" id="scheduled_for" name="scheduled_for" step="60" min="{{ $minScheduledFor }}"
                           value="{{ old('scheduled_for') }}" placeholder="2026-10-20 09:00"
                           class="pm-input w-full sm:w-auto" @error('scheduled_for') aria-invalid="true" @enderror>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">24h · {{ $timezone }}</span>
                </div>
                @error('scheduled_for')<p role="alert" class="text-sm text-rose-600">{{ $message }}</p>@enderror
                @error('when')<p role="alert" class="text-sm text-rose-600">{{ $message }}</p>@enderror
            </fieldset>
        </div>

        {{-- Preview --}}
        <div class="pm-form-panel" role="tabpanel" id="announcement-panel-3" aria-labelledby="announcement-tab-3" data-pm-form-panel="3" @if ($tab !== 3) hidden @endif>
            <div>
                <h2 class="text-sm font-semibold text-slate-700 mb-2">Notification</h2>
                <div class="rounded-xl border border-slate-200 bg-white p-4 flex gap-3" id="preview-notification">
                    <div class="w-10 h-10 rounded-xl shrink-0 flex items-center justify-center" style="background:#f5f3ff;color:#6d28d9">
                        <i class="fa-solid fa-bullhorn" id="preview-icon" aria-hidden="true"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-slate-900" id="preview-title">Announcement</h3>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-violet-100 text-violet-700" id="preview-badge" hidden>New feature</span>
                        </div>
                        <p class="text-sm text-slate-600 mt-1 whitespace-pre-line" id="preview-body"></p>
                        <span class="inline-block mt-3 btn-primary text-white px-3 py-1.5 rounded-lg text-xs font-semibold" id="preview-cta" hidden></span>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-2" id="preview-push"></p>
            </div>

            <div>
                <div class="flex items-center justify-between gap-3 mb-2">
                    <h2 class="text-sm font-semibold text-slate-700">Email</h2>
                    <button type="button" id="preview-email-refresh" class="text-sm font-semibold text-[var(--brand-1)]">Refresh</button>
                </div>
                <iframe id="preview-email" title="Email preview" class="w-full rounded-xl border border-slate-200 bg-white" style="height:520px" sandbox=""></iframe>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" id="announcement-submit" class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                {{ $when === 'later' ? 'Schedule announcement' : 'Send announcement' }}
            </button>
            <button type="button" data-announcement-preview class="text-sm font-semibold text-[var(--brand-1)]">Preview</button>
            <a href="{{ route('admin.announcements.index') }}" class="text-sm text-slate-500 hover:underline">Cancel</a>
        </div>
    </form>

    @include('partials.form-tabs')

    <script>
        (function () {
            var form = document.getElementById('announcement-form');
            if (!form) { return; }
            var features = @json(collect($features)->map(fn ($f) => ['label' => $f['label'], 'icon' => $f['icon']]));
            var counts = @json($counts);
            var previewUrl = @json(route('admin.announcements.preview'));
            var audienceUrl = @json(route('admin.announcements.audience'));
            var timezone = @json($timezone);
            var $ = function (id) { return document.getElementById(id); };

            function values() {
                return {
                    type: $('type').value || 'general',
                    title: $('title').value.trim(),
                    body: $('body').value.trim(),
                    feature: $('feature_key').value,
                    cta: $('cta_label').value.trim(),
                    push: $('send_push').checked,
                    email: $('send_email').checked
                };
            }

            function render() {
                var v = values();
                var isFeature = v.type === 'new_feature';
                var feature = features[v.feature] || null;
                var cta = v.cta || (isFeature ? 'Explore now' : '');
                var icon = isFeature && feature ? feature.icon : 'fa-solid fa-bullhorn';

                $('preview-icon').className = icon;
                $('preview-title').textContent = v.title || 'Announcement';
                $('preview-body').textContent = v.body || 'Your message appears here.';
                $('preview-badge').hidden = !isFeature;
                $('preview-cta').hidden = cta === '';
                $('preview-cta').textContent = cta;

                var pushTitle = (isFeature ? 'New: ' : '') + (v.title || 'Announcement');
                var pushBody = v.body.replace(/\s+/g, ' ');
                if (pushBody.length > 120) { pushBody = pushBody.slice(0, 120).trimEnd() + '...'; }
                $('preview-push').textContent = v.push
                    ? 'Push: "' + pushTitle + '" - ' + (pushBody || '...')
                    : 'Push notification is off.';

                var channels = ['in-app (' + counts.in_app + ')'];
                if (v.push) { channels.push('push (' + counts.push + ')'); }
                if (v.email) { channels.push('email (' + counts.email + ')'); }

                var later = whenValue() === 'later';
                $('scheduled-for-row').hidden = !later;
                $('skip-already-told-row').hidden = !isFeature || !v.feature;
                $('announcement-submit').textContent = later ? 'Schedule announcement' : 'Send announcement';
                if (later) {
                    var at = ($('scheduled_for').value || '').replace('T', ' ');
                    form.dataset.confirm = 'Schedule "' + (v.title || 'this announcement') + '" for ' + (at || 'the chosen time') + ' (' + timezone + ') by ' + channels.join(', ') + '? You can cancel it until it sends.';
                    form.dataset.confirmTitle = 'Schedule announcement?';
                    form.dataset.confirmText = 'Schedule';
                } else {
                    form.dataset.confirm = 'Send "' + (v.title || 'this announcement') + '" now by ' + channels.join(', ') + '? It cannot be undone.';
                    form.dataset.confirmTitle = 'Send announcement?';
                    form.dataset.confirmText = 'Send now';
                }
            }

            function whenValue() {
                var checked = form.querySelector('input[name="when"]:checked');
                return checked ? checked.value : 'now';
            }

            function setCount(channel, text) {
                var row = form.querySelector('[data-count-row="' + channel + '"] span.text-slate-500');
                if (row) { row.textContent = text; }
            }

            var audienceTimer = null;
            var audienceKey = null;
            function refreshAudience() {
                var params = new URLSearchParams();
                params.set('type', $('type').value || 'general');
                params.set('feature_key', $('feature_key').value);
                params.set('skip_already_told', $('skip_already_told').checked ? '1' : '0');
                var selection = form.querySelector('input[name="selection"]');
                if (selection) { params.set('selection', selection.value); }
                var ids = Array.prototype.map.call(form.querySelectorAll('input[name="recipient_ids[]"]'), function (i) { return i.value; });
                if (ids.length) { params.set('recipients', ids.join(',')); }
                var key = params.toString();
                if (key === audienceKey) { return; }
                audienceKey = key;

                fetch(audienceUrl + '?' + key, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
                    .then(function (data) {
                        if (key !== audienceKey) { return; }
                        counts = { in_app: data.in_app, push: data.push, email: data.email };
                        var fmt = function (n) { return Number(n).toLocaleString('en-GB'); };
                        setCount('in_app', fmt(data.in_app) + ' users');
                        setCount('push', fmt(data.push) + ' with a device');
                        setCount('email', fmt(data.email) + ' opted in');
                        $('already-told-notice').hidden = !data.notice;
                        $('already-told-text').textContent = (data.notice || '') + (data.notice ? (data.skipping ? ' They will be skipped.' : ' They will be told again.') : '');
                        render();
                    })
                    .catch(function () { audienceKey = null; });
            }

            function scheduleAudience() {
                window.clearTimeout(audienceTimer);
                audienceTimer = window.setTimeout(refreshAudience, 250);
            }

            var timer = null;
            function refreshEmail() {
                var data = new FormData(form);
                fetch(previewUrl, {
                    method: 'POST',
                    body: data,
                    headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) { return r.ok ? r.text() : Promise.reject(r); })
                  .then(function (html) { $('preview-email').srcdoc = html; })
                  .catch(function () { $('preview-email').srcdoc = '<p style="font-family:sans-serif;color:#be123c;padding:16px">Preview unavailable. Check the link and button label.</p>'; });
            }

            form.addEventListener('input', function () {
                render();
                window.clearTimeout(timer);
                timer = window.setTimeout(refreshEmail, 600);
            });
            form.addEventListener('change', function (event) {
                render();
                if (event.target && ['type', 'feature_key', 'skip_already_told'].indexOf(event.target.id) !== -1) { scheduleAudience(); }
            });
            $('preview-email-refresh').addEventListener('click', refreshEmail);
            document.addEventListener('click', function (event) {
                if (!event.target.closest) { return; }
                if (event.target.closest('[data-announcement-preview]')) { $('announcement-tab-3').click(); return; }
                if (event.target.closest('#announcement-tab-3')) { refreshEmail(); }
            });

            render();
            refreshEmail();
            if ($('type').value === 'new_feature') { refreshAudience(); }
        })();
    </script>
@endsection
