<!DOCTYPE html>
<html lang="en">
@php
    $siteSettings = $siteSettings ?? new \App\Models\SiteSetting(['site_name' => 'Personal Monitor']);
    $themeUser = auth()->user();
@endphp
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- viewport-fit=cover is required, not optional: without it the browser
         resolves env(safe-area-inset-*) to 0px on notched phones and the
         safe-area handling in modals.css / modal-responsive.css never fires.
         Keep this in sync with layouts/guest.blade.php. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', $siteSettings->site_name ?? 'Personal Monitor')</title>

    {{-- External assets --}}
    {{-- Both the Tailwind CDN runtime and the Font Awesome stylesheet are
         render-blocking third-party origins, so warm the connections before
         the requests that need them. --}}
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- &display=swap: without it Google applies font-display:auto, which
         blocks text rendering for ~3s while the webfont downloads (FOIT). --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    {{-- Design tokens for new components --}}
    <x-design-tokens />

    {{-- Brand color overrides (dynamic per-user theme) --}}
    <style>
        :root {
            --brand-1: {{ $themeUser ? $themeUser->themeColor() : '#00897B' }};
            --brand-2: {{ $themeUser ? $themeUser->themeColorLight() : '#73BEB6' }};
            --brand-1-dark: {{ $themeUser ? $themeUser->themeColorDark() : '#006B60' }};
            --brand-2-dark: {{ $themeUser ? $themeUser->themeColorLightDark() : '#62A29B' }};
            --brand-1-tint-10: {{ $themeUser ? $themeUser->themeColorTint(0.1) : 'rgba(0, 137, 123, 0.1)' }};
            --brand-1-tint-20: {{ $themeUser ? $themeUser->themeColorTint(0.2) : 'rgba(0, 137, 123, 0.2)' }};
            --brand-2-tint-25: {{ $themeUser ? $themeUser->themeColorLightTint(0.25) : 'rgba(115, 190, 182, 0.25)' }};
        }
    </style>

    {{-- Extracted CSS files --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('css/modals.css') }}">
    <link rel="stylesheet" href="{{ asset('css/modal-responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('css/professional-forms.css') }}">

    {{-- Global professional 12-hour time controls --}}
    <link rel="stylesheet" href="{{ asset('css/time-12h.css') }}">

    {{-- Shared list tables (no sideways scrolling) + compact summary strip --}}
    <link rel="stylesheet" href="{{ asset('css/data-table.css') }}?v={{ filemtime(public_path('css/data-table.css')) }}">

    {{-- PWA install metadata (manifest link, theme colour, iOS icons) --}}
    @include('partials.pwa-head')
    @stack('styles')
</head>
<body class="pm-professional-shell text-slate-800 {{ request()->routeIs('admin.*') ? 'pm-admin-page' : '' }}">

@include('partials.accessibility-widget')
@include('partials.password-toggle')

<a href="#main-content" class="sr-only-focusable bg-[var(--brand-1)] text-white px-4 py-2 rounded-md z-50 fixed top-2 left-2">
    Skip to main content
</a>

{{-- Subscription status banners --}}
@auth
    @if (auth()->user()->isSuspended())
        <x-alert type="error" :dismissible="false" :autoDismiss="false">
            Your account has been suspended.
            <a href="{{ route('subscription.show') }}" class="underline font-medium">View details</a>.
        </x-alert>
    @elseif (auth()->user()->onTrial())
        <x-alert type="warning" :dismissible="false" :autoDismiss="false">
            {{ auth()->user()->trialDaysLeft() }} day(s) left in your free trial.
            <a href="{{ route('subscription.show') }}" class="underline font-medium">Subscribe</a> to keep uninterrupted access.
        </x-alert>
    @elseif (auth()->user()->subscription_status !== 'active' && ! auth()->user()->isAdmin())
        <x-alert type="error" :dismissible="false" :autoDismiss="false">
            Your subscription has expired. Please
            <a href="{{ route('subscription.show') }}" class="underline font-medium">renew your subscription</a>
            to continue using premium features.
        </x-alert>
    @endif
@endauth

<div id="pm-app-shell" class="min-h-screen md:flex md:items-stretch">
@auth
    @include('partials.app-layout-sidebar')
@endauth

    <div class="flex-1 min-w-0 w-full">
        <main id="main-content" class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 xl:px-10 py-5 sm:py-6 lg:py-8">
            @include('partials.flash-messages')
            @yield('content')
        </main>

        <footer class="pm-layout-footer w-full max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 xl:px-10 py-8 text-center text-xs">
            <a href="{{ route('privacy-policy') }}">Privacy Policy</a>
            <span class="mx-2">&middot;</span>
            <a href="{{ Route::has('terms-of-use') ? route('terms-of-use') : url('/terms-of-use') }}">Terms of Use</a>
        </footer>
    </div>
</div>

@auth
    {{-- Phone-only bottom bar: Today, Planner, quick add, Goals, More --}}
    @unless ((string) auth()->user()->role === 'support')
        @include('partials.mobile-bottom-nav')
    @endunless

    {{-- In-app reminder alarm --}}
    <dialog id="reminder-alarm-modal" aria-labelledby="reminder-alarm-title" class="rounded-2xl p-6 pm-dialog-sm shadow-2xl backdrop:bg-slate-900/50">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-bell" aria-hidden="true"></i>
            </div>
            <h2 id="reminder-alarm-title" class="text-lg font-bold text-slate-800">Reminder</h2>
        </div>
        <p id="reminder-alarm-message" class="text-sm text-slate-600 mb-5"></p>
        <div class="flex justify-end">
            <button type="button" id="reminder-alarm-dismiss" class="inline-flex items-center gap-2 btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                <span>Dismiss</span>
            </button>
        </div>
    </dialog>

    <script>
        (function () {
            var DISMISSED_KEY = 'pm_dismissed_reminder_alarms';
            var DUE_NOW_URL = @json(route('reminders.due-now'));

            function getDismissed() {
                try { return JSON.parse(localStorage.getItem(DISMISSED_KEY) || '[]'); }
                catch (e) { return []; }
            }

            function markDismissed(id) {
                var list = getDismissed();
                if (list.indexOf(id) === -1) { list.push(id); }
                localStorage.setItem(DISMISSED_KEY, JSON.stringify(list));
            }

            var pmAudioCtx = null;

            function pmUnlockAudioContext() {
                if (pmAudioCtx) { return; }
                try {
                    var Ctx = window.AudioContext || window.webkitAudioContext;
                    pmAudioCtx = new Ctx();
                } catch (e) { /* Web Audio unsupported */ }
            }

            ['click', 'keydown', 'touchstart'].forEach(function (evt) {
                document.addEventListener(evt, pmUnlockAudioContext, { once: true, passive: true });
            });

            function playAlarmTone() {
                if (!pmAudioCtx) { pmUnlockAudioContext(); }
                if (!pmAudioCtx) { return; }

                try {
                    if (pmAudioCtx.state === 'suspended') {
                        pmAudioCtx.resume();
                    }
                    [880, 660].forEach(function (freq, i) {
                        var osc = pmAudioCtx.createOscillator();
                        var gain = pmAudioCtx.createGain();
                        osc.type = 'sine';
                        osc.frequency.value = freq;
                        gain.gain.value = 0.15;
                        osc.connect(gain);
                        gain.connect(pmAudioCtx.destination);
                        var start = pmAudioCtx.currentTime + i * 0.25;
                        osc.start(start);
                        osc.stop(start + 0.2);
                    });
                } catch (e) { /* fail silently */ }
            }

            function showAlarm(reminder) {
                var dialog = document.getElementById('reminder-alarm-modal');
                if (!dialog || dialog.open) { return; }
                document.getElementById('reminder-alarm-title').textContent = reminder.title;
                document.getElementById('reminder-alarm-message').textContent =
                    reminder.message || 'This is your scheduled reminder.';
                dialog.dataset.reminderId = reminder.id;
                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                    playAlarmTone();
                }
            }

            function checkDueReminders() {
                fetch(DUE_NOW_URL, { headers: { 'Accept': 'application/json' } })
                    .then(function (response) { return response.ok ? response.json() : []; })
                    .then(function (reminders) {
                        var dismissed = getDismissed();
                        var due = (reminders || []).filter(function (r) {
                            return dismissed.indexOf(r.id) === -1;
                        });
                        if (due.length > 0) { showAlarm(due[0]); }
                    })
                    .catch(function () { /* a network blip shouldn't spam errors */ });
            }

            document.addEventListener('DOMContentLoaded', function () {
                var dismissBtn = document.getElementById('reminder-alarm-dismiss');
                if (dismissBtn) {
                    dismissBtn.addEventListener('click', function () {
                        var dialog = document.getElementById('reminder-alarm-modal');
                        markDismissed(parseInt(dialog.dataset.reminderId, 10));
                        dialog.close();
                    });
                }

                checkDueReminders();
                setInterval(checkDueReminders, 60000);
            });
        })();
    </script>
@endauth

{{-- Voice dictation (speech-to-text) --}}
<script>
    var pmActiveRecognition = null;
    var pmActiveButton = null;
    var pmActiveField = null;

    function pmStopDictation() {
        if (pmActiveRecognition) {
            pmActiveRecognition.stop();
        }
    }

    function pmStartDictation(fieldId, buttonEl) {
        var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) { return; }

        if (pmActiveButton === buttonEl) {
            pmStopDictation();
            return;
        }

        pmStopDictation();

        var field = document.getElementById(fieldId);
        if (!field) { return; }

        var recognition = new SpeechRecognition();
        recognition.lang = document.documentElement.lang || 'en-US';
        recognition.continuous = true;
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;

        pmActiveRecognition = recognition;
        pmActiveButton = buttonEl;
        pmActiveField = field;

        buttonEl.setAttribute('aria-pressed', 'true');
        buttonEl.classList.add('text-rose-600');
        buttonEl.classList.remove('text-slate-400');

        var lastProcessedIndex = -1;

        recognition.onresult = function (event) {
            var startAt = Math.max(event.resultIndex, lastProcessedIndex + 1);

            for (var i = startAt; i < event.results.length; i++) {
                if (event.results[i].isFinal) {
                    var transcript = event.results[i][0].transcript;
                    field.value = field.value ? (field.value.replace(/\s+$/, '') + ' ' + transcript) : transcript;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                }
                lastProcessedIndex = i;
            }
        };

        var reset = function () {
            buttonEl.setAttribute('aria-pressed', 'false');
            buttonEl.classList.remove('text-rose-600');
            buttonEl.classList.add('text-slate-400');
            if (pmActiveRecognition === recognition) {
                pmActiveRecognition = null;
                pmActiveButton = null;
                pmActiveField = null;
            }
        };
        recognition.onend = reset;
        recognition.onerror = reset;

        recognition.start();
    }

    document.addEventListener('click', function (event) {
        if (!pmActiveButton) { return; }
        if (event.target === pmActiveButton || pmActiveButton.contains(event.target)) { return; }
        pmStopDictation();
    }, true);

    document.addEventListener('focusin', function (event) {
        if (!pmActiveField) { return; }
        if (event.target !== pmActiveField) {
            pmStopDictation();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        var supported = !!(window.SpeechRecognition || window.webkitSpeechRecognition);
        if (!supported) { return; }
        document.querySelectorAll('.pm-voice-input-btn').forEach(function (btn) {
            btn.classList.remove('hidden');
        });
    });
</script>

<script>
    (function () {
        var toggle = document.getElementById('sidebar-toggle');
        var sidebar = document.getElementById('sidebar');
        if (!toggle || !sidebar) { return; }

        toggle.addEventListener('click', function () {
            var isHidden = sidebar.classList.contains('hidden');
            if (isHidden) {
                sidebar.classList.remove('hidden');
                sidebar.classList.add('flex', 'flex-col');
            } else {
                sidebar.classList.add('hidden');
                sidebar.classList.remove('flex', 'flex-col');
            }
            toggle.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
        });
    })();
</script>

<script>
    // Sidebar group dropdowns — server-rendered initial state + localStorage persistence
    function pmToggleSidebarGroup(groupKey) {
        var panel = document.getElementById('sidebar-group-' + groupKey);
        var btn = document.getElementById('sidebar-group-btn-' + groupKey);
        var chevron = document.querySelector('.pm-sidebar-chevron[data-group="' + groupKey + '"]');
        if (!panel || !btn) { return; }

        var willOpen = panel.hasAttribute('hidden');
        if (willOpen) {
            panel.removeAttribute('hidden');
        } else {
            panel.setAttribute('hidden', '');
        }
        btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        if (chevron) { chevron.style.transform = willOpen ? '' : 'rotate(-90deg)'; }

        try {
            localStorage.setItem('pm-sidebar-group-' + groupKey, willOpen ? '1' : '0');
        } catch (e) { /* localStorage unavailable */ }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.pm-sidebar-chevron').forEach(function (chevron) {
            var groupKey = chevron.dataset.group;
            var stored = null;
            try { stored = localStorage.getItem('pm-sidebar-group-' + groupKey); } catch (e) { /* ignore */ }
            if (stored === null) { return; }

            var panel = document.getElementById('sidebar-group-' + groupKey);
            var btn = document.getElementById('sidebar-group-btn-' + groupKey);
            if (!panel || !btn) { return; }

            var shouldBeOpen = stored === '1';
            if (shouldBeOpen) {
                panel.removeAttribute('hidden');
                chevron.style.transform = '';
            } else {
                panel.setAttribute('hidden', '');
                chevron.style.transform = 'rotate(-90deg)';
            }
            btn.setAttribute('aria-expanded', shouldBeOpen ? 'true' : 'false');
        });
    });
</script>

{{-- Flash message auto-dismiss (5s fade + remove) --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.pm-flash-message').forEach(function (message) {
            setTimeout(function () {
                message.style.opacity = '0';
                message.addEventListener('transitionend', function () {
                    message.remove();
                }, { once: true });
            }, 5000);
        });
    });
</script>

{{-- Motion runtime: intersection-based reveal + number countup --}}
<script id="pm-motion-runtime">
(() => {
    const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const visibleObserver = !reduced && 'IntersectionObserver' in window
        ? new IntersectionObserver((entries, obs) => entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('pm-motion-visible');
            obs.unobserve(entry.target);
        }), {threshold: .08, rootMargin: '40px 0px'})
        : null;

    const reveal = (el, delay = 0) => {
        if (!el || el.dataset.pmMotionReady) return;
        el.dataset.pmMotionReady = '1';
        if (reduced) { el.classList.add('pm-motion-visible'); return; }
        el.classList.add('pm-motion-enter');
        if (delay) el.style.animationDelay = `${delay}ms`;
        visibleObserver ? visibleObserver.observe(el) : el.classList.add('pm-motion-visible');
    };

    document.querySelectorAll('.quick-card,.stat-card,.pm-stat-card,.dashboard-card,.insight-card').forEach((el, i) => reveal(el, Math.min(i * 55, 330)));
    document.querySelectorAll('canvas,.chart-container,.apexcharts-canvas,.chartjs-render-monitor,.recharts-wrapper').forEach(el => {
        if (visibleObserver) visibleObserver.observe(el); else el.classList.add('pm-motion-visible');
    });

    const numberSelectors = '[data-countup],.stat-value,.pm-stat-value,.metric-value,.summary-value,.dashboard-stat-value';
    const parseNumber = (text) => {
        const match = String(text).match(/-?[\d][\d,]*(?:\.\d+)?/);
        if (!match) return null;
        const raw = match[0];
        const value = Number(raw.replace(/,/g,''));
        if (!Number.isFinite(value)) return null;
        return {raw, value, index: match.index ?? 0, decimals: (raw.split('.')[1] || '').length};
    };
    const animateNumber = (el) => {
        if (reduced || el.dataset.pmCounted) return;
        const original = el.textContent || '';
        const parsed = parseNumber(original);
        if (!parsed) return;
        el.dataset.pmCounted = '1';
        el.classList.add('pm-counting');
        const prefix = original.slice(0, parsed.index);
        const suffix = original.slice(parsed.index + parsed.raw.length);
        const start = performance.now();
        const duration = Math.min(1200, Math.max(520, Math.abs(parsed.value) > 100000 ? 1050 : 760));
        const format = (v) => Number(v).toLocaleString(undefined,{minimumFractionDigits:parsed.decimals,maximumFractionDigits:parsed.decimals});
        const tick = (now) => {
            const t = Math.min(1,(now-start)/duration);
            const eased = 1-Math.pow(1-t,3);
            el.textContent = prefix + format(parsed.value*eased) + suffix;
            if (t < 1) requestAnimationFrame(tick); else el.textContent = original;
        };
        requestAnimationFrame(tick);
    };
    const countObserver = !reduced && 'IntersectionObserver' in window
        ? new IntersectionObserver((entries, obs) => entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            animateNumber(entry.target); obs.unobserve(entry.target);
        }), {threshold:.35}) : null;
    document.querySelectorAll(numberSelectors).forEach(el => countObserver ? countObserver.observe(el) : animateNumber(el));
})();
</script>

@auth
{{-- Global engagement helper --}}
<script id="pm-engagement-global-runtime">
(function () {
    'use strict';

    window.pmEngagement = window.pmEngagement || {
        refresh: function () {
            window.dispatchEvent(new CustomEvent('pm:engagement-refresh'));
        }
    };

    const originalFetch = window.fetch;

    if (originalFetch && !window.__pmEngagementFetchPatched) {
        window.fetch = async function () {
            const response = await originalFetch.apply(this, arguments);

            try {
                const request = arguments[0];
                const options = arguments[1] || {};
                const method = String(options.method || 'GET').toUpperCase();
                const url = typeof request === 'string'
                    ? request
                    : (request && request.url ? request.url : '');

                const meaningfulPath = /(daily-planner|expense|saving|contribution|health|wellbeing|exercise|goal|meeting|spiritual)/i.test(url);

                if (
                    response.ok &&
                    ['POST', 'PUT', 'PATCH'].includes(method) &&
                    meaningfulPath &&
                    !/engagement\/meaningful-action/i.test(url)
                ) {
                    window.pmEngagement.refresh();
                }
            } catch (_) {}

            return response;
        };

        window.__pmEngagementFetchPatched = true;
    }
})();
</script>

    @unless(auth()->user()->isAdmin())
        @include('partials.support-chat-widget')
    @endunless
@endauth

@include('partials.confirm-modal')

{{-- Global modal positioning runtime --}}
<script id="pm-global-modal-position-runtime">
(function () {
    'use strict';

    const desktopQuery = window.matchMedia('(min-width: 641px)');

    function isDesktop() {
        return desktopQuery.matches;
    }

    function forceNativeDialogPosition(dialog) {
        if (!dialog || dialog.tagName !== 'DIALOG' || !dialog.open) {
            return;
        }

        if (!isDesktop()) {
            [
                'position', 'top', 'left', 'right', 'bottom',
                'margin', 'transform', 'transform-origin', 'z-index'
            ].forEach(function (property) {
                dialog.style.removeProperty(property);
            });
            return;
        }

        dialog.style.setProperty('position', 'fixed', 'important');
        dialog.style.setProperty('top', '50%', 'important');
        dialog.style.setProperty('left', '50%', 'important');
        dialog.style.setProperty('right', 'auto', 'important');
        dialog.style.setProperty('bottom', 'auto', 'important');
        dialog.style.setProperty('margin', '0', 'important');
        dialog.style.setProperty('transform', 'translate3d(-50%, -50%, 0)', 'important');
        dialog.style.setProperty('transform-origin', '50% 50%', 'important');
        dialog.style.setProperty('z-index', '2147483000', 'important');
    }

    const LEGACY_OVERLAY_PROPS = [
        'position', 'top', 'right', 'bottom', 'left', 'width', 'height',
        'display', 'align-items', 'justify-content', 'padding', 'overflow', 'z-index'
    ];
    const LEGACY_PANEL_PROPS = ['position', 'top', 'right', 'bottom', 'left', 'margin', 'transform'];

    /*
     * Undo the inline !important positioning once an overlay closes.
     * Overlays that close only by dropping their show/open/is-open class
     * (e.g. Daily Planner's .dp-modal-backdrop) would otherwise stay
     * visible, because the inline display:flex !important beats the
     * stylesheet's display:none.
     */
    function releaseLegacyOverlay(overlay) {
        if (overlay.dataset.pmOverlayForced !== '1') {
            return;
        }

        // Only drop the !important values set by forceLegacyOverlayPosition;
        // a page script's own inline style (e.g. display:none) is kept.
        const release = (el, props) => props.forEach((prop) => {
            if (el.style.getPropertyPriority(prop) === 'important') {
                el.style.removeProperty(prop);
            }
        });

        delete overlay.dataset.pmOverlayForced;
        release(overlay, LEGACY_OVERLAY_PROPS);

        const panel = overlay.querySelector(
            ':scope > .modal, :scope > .modal-dialog, :scope > .app-dialog, ' +
            ':scope > .ajax-dialog, :scope > .church-modal-dialog, :scope > .birds-modal-dialog, ' +
            ':scope > .dp-modal-panel, :scope > .sc-modal-panel'
        );

        if (panel) {
            release(panel, LEGACY_PANEL_PROPS);
        }
    }

    function forceLegacyOverlayPosition(overlay) {
        if (!overlay) {
            return;
        }

        const isVisible =
            overlay.classList.contains('show') ||
            overlay.classList.contains('open') ||
            overlay.classList.contains('is-open');

        if (!isVisible || !isDesktop()) {
            releaseLegacyOverlay(overlay);
            return;
        }

        overlay.dataset.pmOverlayForced = '1';

        overlay.style.setProperty('position', 'fixed', 'important');
        overlay.style.setProperty('top', '0', 'important');
        overlay.style.setProperty('right', '0', 'important');
        overlay.style.setProperty('bottom', '0', 'important');
        overlay.style.setProperty('left', '0', 'important');
        overlay.style.setProperty('width', '100vw', 'important');
        overlay.style.setProperty('height', 'var(--pm-modal-vh)', 'important');
        overlay.style.setProperty('display', 'flex', 'important');
        overlay.style.setProperty('align-items', 'center', 'important');
        overlay.style.setProperty('justify-content', 'center', 'important');
        overlay.style.setProperty('padding', '24px', 'important');
        overlay.style.setProperty('overflow', 'auto', 'important');
        overlay.style.setProperty('z-index', '2147482990', 'important');

        const panel = overlay.querySelector(
            ':scope > .modal, :scope > .modal-dialog, :scope > .app-dialog, ' +
            ':scope > .ajax-dialog, :scope > .church-modal-dialog, :scope > .birds-modal-dialog, ' +
            ':scope > .dp-modal-panel, :scope > .sc-modal-panel'
        );

        if (panel) {
            panel.style.setProperty('position', 'relative', 'important');
            panel.style.setProperty('top', 'auto', 'important');
            panel.style.setProperty('right', 'auto', 'important');
            panel.style.setProperty('bottom', 'auto', 'important');
            panel.style.setProperty('left', 'auto', 'important');
            panel.style.setProperty('margin', 'auto', 'important');
            panel.style.setProperty('transform', 'none', 'important');
        }
    }

    function normaliseAllOpenModals() {
        document.querySelectorAll('dialog[open]').forEach(forceNativeDialogPosition);

        document.querySelectorAll(
            '.modal-overlay, .app-modal, .ajax-modal, .member-modal-overlay, .church-modal, .birds-modal, ' +
            '.dp-modal-backdrop, .sc-modal'
        ).forEach(forceLegacyOverlayPosition);
    }

    if (window.HTMLDialogElement && !HTMLDialogElement.prototype.__pmPositionPatched) {
        const originalShowModal = HTMLDialogElement.prototype.showModal;
        const originalShow = HTMLDialogElement.prototype.show;

        HTMLDialogElement.prototype.showModal = function () {
            const result = originalShowModal.apply(this, arguments);
            forceNativeDialogPosition(this);
            requestAnimationFrame(() => forceNativeDialogPosition(this));
            return result;
        };

        HTMLDialogElement.prototype.show = function () {
            const result = originalShow.apply(this, arguments);
            forceNativeDialogPosition(this);
            requestAnimationFrame(() => forceNativeDialogPosition(this));
            return result;
        };

        Object.defineProperty(HTMLDialogElement.prototype, '__pmPositionPatched', {
            value: true,
            configurable: false,
            enumerable: false,
            writable: false
        });
    }

    const observer = new MutationObserver(function (mutations) {
        let shouldNormalise = false;

        mutations.forEach(function (mutation) {
            if (mutation.type === 'childList' && mutation.addedNodes.length) {
                shouldNormalise = true;
                return;
            }

            if (
                mutation.type === 'attributes' &&
                ['open', 'class'].includes(mutation.attributeName)
            ) {
                shouldNormalise = true;
            }
        });

        if (shouldNormalise) {
            requestAnimationFrame(normaliseAllOpenModals);
        }
    });

    function start() {
        normaliseAllOpenModals();

        observer.observe(document.documentElement, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['open', 'class']
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }

    desktopQuery.addEventListener
        ? desktopQuery.addEventListener('change', normaliseAllOpenModals)
        : desktopQuery.addListener(normaliseAllOpenModals);

    window.addEventListener('resize', normaliseAllOpenModals);

    document.addEventListener('click', function () {
        requestAnimationFrame(normaliseAllOpenModals);
    }, true);
})();
</script>

{{-- Admin horizontal tables auto-wrapping --}}
<script id="pm-admin-horizontal-tables-script-20260819">
document.addEventListener('DOMContentLoaded', function () {
    if (!document.body.classList.contains('pm-admin-page')) {
        return;
    }

    document.querySelectorAll('#main-content table').forEach(function (table) {
        if (
            table.classList.contains('fc-scrollgrid') ||
            table.closest('.fc') ||
            table.classList.contains('pm-no-admin-horizontal')
        ) {
            return;
        }

        table.classList.add('pm-admin-horizontal-table');

        var currentWrap = table.closest('.pm-admin-table-scroll');

        if (!currentWrap) {
            var parent = table.parentElement;

            if (
                parent &&
                (
                    parent.classList.contains('overflow-x-auto') ||
                    parent.classList.contains('table-responsive') ||
                    parent.classList.contains('apple-table-wrap') ||
                    parent.classList.contains('pm-horizontal-table-wrap')
                )
            ) {
                parent.classList.add('pm-admin-table-scroll');
                currentWrap = parent;
            } else {
                var wrapper = document.createElement('div');
                wrapper.className = 'pm-admin-table-scroll';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
                currentWrap = wrapper;
            }
        }

        if (
            window.matchMedia('(max-width: 767.98px)').matches &&
            currentWrap &&
            !currentWrap.previousElementSibling?.classList.contains(
                'pm-admin-table-swipe-hint'
            )
        ) {
            var hint = document.createElement('div');
            hint.className = 'pm-admin-table-swipe-hint';
            hint.innerHTML =
                '<i class="fa-solid fa-arrows-left-right" aria-hidden="true"></i>' +
                '<span>Swipe sideways to view all columns.</span>';

            currentWrap.parentNode.insertBefore(hint, currentWrap);
        }
    });
});
</script>

{{-- 12-hour time controls --}}
<script src="{{ asset('js/time-12h.js') }}" defer></script>
<script src="{{ asset('js/data-table.js') }}?v={{ filemtime(public_path('js/data-table.js')) }}" defer></script>
@stack('scripts')

{{-- Modal viewport sync runtime --}}
<script id="pm-modal-viewport-sync-20260820">
(function () {
    'use strict';

    const root = document.documentElement;

    function syncModalViewport() {
        const viewport = window.visualViewport;
        const height = viewport && viewport.height ? viewport.height : window.innerHeight;
        if (height > 0) {
            root.style.setProperty('--pm-modal-vh', `${Math.round(height)}px`);
        }
    }

    function markModalState() {
        const hasNative = !!document.querySelector('dialog[open]');
        const hasLegacy = !!document.querySelector(
            '.modal-overlay.show, .modal-overlay.open, .modal-overlay.is-open,' +
            '.app-modal.show, .app-modal.open, .app-modal.is-open,' +
            '.ajax-modal.show, .ajax-modal.open, .ajax-modal.is-open,' +
             '.member-modal-overlay.show, .member-modal-overlay.open, .member-modal-overlay.is-open,' +
             '.church-modal.show, .church-modal.open, .church-modal.is-open,' +
             '.birds-modal.show, .birds-modal.open, .birds-modal.is-open,' +
             '.dp-modal-backdrop.is-open, .sc-modal.open,' +
             '.pm-component-modal-shell[style*="display: block"]'
        );

        document.documentElement.classList.toggle('pm-modal-open', hasNative || hasLegacy);
        document.body.classList.toggle('pm-modal-open', hasNative || hasLegacy);
    }

    syncModalViewport();
    markModalState();
    window.addEventListener('resize', syncModalViewport, { passive: true });
    window.addEventListener('orientationchange', syncModalViewport, { passive: true });

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', syncModalViewport, { passive: true });
        window.visualViewport.addEventListener('scroll', syncModalViewport, { passive: true });
    }

    const observer = new MutationObserver(() => {
        syncModalViewport();
        markModalState();
    });

    observer.observe(document.body, {
        subtree: true,
        attributes: true,
        attributeFilter: ['open', 'class', 'style']
    });

    document.addEventListener('focusin', (event) => {
        if (event.target.closest('dialog, .modal-overlay, .app-modal, .ajax-modal, .member-modal-overlay, .church-modal, .birds-modal, .dp-modal-backdrop, .sc-modal, .pm-component-modal-shell')) {
            setTimeout(syncModalViewport, 60);
            setTimeout(syncModalViewport, 260);
        }
    });
})();
</script>

{{-- Install / update prompt for the installable web app --}}
@include('partials.pwa-install')

</body>
</html>
