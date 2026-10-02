{{--
    Reusable alert system.

    Props:
        type        : success | error | warning | info  (default: info)
        message     : optional string message (falls back to slot)
        dismissible : whether to show a dismiss button (default: true)
        autoDismiss : auto-dismiss after 5s for success (default: true)

    Slot: message content (used when `message` prop is not provided).

    Accessibility:
        - role="alert" for errors/warnings, role="status" for success/info
        - aria-live="polite" for auto-dismissing alerts
        - Dismiss button labelled for screen readers

    Auto-dismiss (success only; errors never auto-dismiss):
        Timing lives in the shared `window.pmAutoDismiss(el, ms)` helper, wired
        up from the `data-auto-dismiss` attribute on the alert root rather than
        from an inline setTimeout, so it is testable and reusable. The timer
        pauses while the pointer is over the alert or focus is inside it and
        resumes for the remaining time when they leave; Escape dismisses early.
        If the helper is unavailable an inline fallback still auto-dismisses.

    Usage:
        <x-alert type="success" message="Saved successfully!" />
        <x-alert type="error">Something went wrong.</x-alert>
--}}
@props([
    'type' => 'info',
    'message' => null,
    'dismissible' => true,
    'autoDismiss' => true,
])

@php
    // Child sections render before their parent layout. Track success alerts
    // per request so a legacy page alert and the shared layout flash do not
    // announce the same session message twice.
    $skipDuplicateSuccess = false;

    if ($type === 'success' && filled($message)) {
        $renderedSuccessAlerts = request()->attributes->get('pm.rendered_success_alerts', []);
        $successKey = (string) $message;
        $skipDuplicateSuccess = in_array($successKey, $renderedSuccessAlerts, true);

        if (! $skipDuplicateSuccess) {
            $renderedSuccessAlerts[] = $successKey;
            request()->attributes->set('pm.rendered_success_alerts', $renderedSuccessAlerts);
        }
    }

    $config = [
        'success' => [
            'icon' => 'fa-circle-check',
            'role' => 'status',
            'classes' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            'iconClasses' => 'text-emerald-500',
        ],
        'error' => [
            'icon' => 'fa-circle-exclamation',
            'role' => 'alert',
            'classes' => 'bg-red-50 text-red-800 border-red-200',
            'iconClasses' => 'text-red-500',
        ],
        'warning' => [
            'icon' => 'fa-triangle-exclamation',
            'role' => 'alert',
            'classes' => 'bg-amber-50 text-amber-800 border-amber-200',
            'iconClasses' => 'text-amber-500',
        ],
        'info' => [
            'icon' => 'fa-circle-info',
            'role' => 'status',
            'classes' => 'bg-blue-50 text-blue-800 border-blue-200',
            'iconClasses' => 'text-blue-500',
        ],
    ][$type] ?? [
        'icon' => 'fa-circle-info',
        'role' => 'status',
        'classes' => 'bg-blue-50 text-blue-800 border-blue-200',
        'iconClasses' => 'text-blue-500',
    ];

    $shouldAutoDismiss = $autoDismiss && $type === 'success';
    $alertId = 'alert-' . \Illuminate\Support\Str::random(8);
@endphp

@if (! $skipDuplicateSuccess)
<div
    id="{{ $alertId }}"
    role="{{ $config['role'] }}"
    @if ($shouldAutoDismiss) aria-live="polite" @endif
    class="pm-alert relative flex items-start gap-3 rounded-xl border px-4 py-3 text-sm {{ $config['classes'] }}"
    @if ($shouldAutoDismiss)
        data-auto-dismiss="5000"
    @endif
>
    <i class="fa-solid {{ $config['icon'] }} mt-0.5 {{ $config['iconClasses'] }}" aria-hidden="true"></i>

    <div class="flex-1 min-w-0">
        @if ($message)
            {{ $message }}
        @else
            {{ $slot }}
        @endif
    </div>

    @if ($dismissible)
        <button
            type="button"
            class="shrink-0 inline-flex items-center justify-center w-6 h-6 rounded-md text-current/60 hover:text-current/90 hover:bg-black/5 transition-colors"
            aria-label="Dismiss alert"
            onclick="(function (b) { var a = b.closest('.pm-alert'); if (!a) return; if (a.pmAutoDismiss) { a.pmAutoDismiss.dismiss(); } else { a.remove(); } })(this)"
        >
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    @endif
</div>

@if ($shouldAutoDismiss)
    <script>
        (function () {
            // Shared, reusable auto-dismiss behaviour. Defined once per page
            // (guarded), so every alert on the page reuses the same logic
            // instead of each one carrying its own inline copy.
            //
            // Deliberately a plain window function rather than Alpine data or
            // an inline setTimeout: the alert can be rendered by a layout, a
            // partial or a page, in any order, so there is no reliable parent
            // scope to hang behaviour off. It also makes the behaviour
            // drivable from a test (el.pmAutoDismiss.pause() / .dismiss()).
            if (typeof window.pmAutoDismiss !== 'function') {
                var activeAlerts = [];
                var escapeListenerInstalled = false;

                function onAlertEscape(e) {
                    if (e.key !== 'Escape' && e.key !== 'Esc') return;
                    var openDialogs = document.querySelectorAll('dialog[open]');
                    if (openDialogs.length) return;
                    for (var i = activeAlerts.length - 1; i >= 0; i--) {
                        var candidate = activeAlerts[i];
                        if (!candidate.isConnected) continue;
                        if (!candidate.pmAutoDismiss || typeof candidate.pmAutoDismiss.dismiss !== 'function') continue;
                        if (!candidate.hasAttribute('data-auto-dismiss')) continue;
                        if (candidate.style.opacity === '0') continue;
                        candidate.pmAutoDismiss.dismiss();
                        return;
                    }
                }

                window.pmAutoDismiss = function (el, ms) {
                    if (!el || el.pmAutoDismissBound) return;
                    el.pmAutoDismissBound = true;

                    var FADE_MS = 300;
                    var total = typeof ms === 'number' && ms > 0 ? ms : 5000;
                    var remaining = total;
                    var timer = null;
                    var startedAt = 0;
                    var paused = false;
                    var finished = false;
                    var removalTimer = null;

                    function cleanup() {
                        cancel();
                        if (removalTimer !== null) { clearTimeout(removalTimer); removalTimer = null; }
                        el.removeEventListener('mouseenter', pause);
                        el.removeEventListener('mouseleave', resume);
                        el.removeEventListener('focusin', pause);
                        el.removeEventListener('focusout', onFocusOut);
                        var index = activeAlerts.indexOf(el);
                        if (index !== -1) activeAlerts.splice(index, 1);
                        if (activeAlerts.length === 0 && escapeListenerInstalled) {
                            document.removeEventListener('keydown', onAlertEscape);
                            escapeListenerInstalled = false;
                        }
                        if (observer) observer.disconnect();
                        el.pmAutoDismiss = null;
                    }

                    function cancel() {
                        if (timer !== null) { clearTimeout(timer); timer = null; }
                    }

                    function dismiss() {
                        if (finished) return;
                        finished = true;
                        cancel();
                        el.style.transition = 'opacity ' + (FADE_MS / 1000) + 's ease';
                        el.style.opacity = '0';
                        removalTimer = setTimeout(function () {
                            if (el.parentNode) el.parentNode.removeChild(el);
                            cleanup();
                        }, FADE_MS);
                    }

                    function onFocusOut(e) {
                        if (!e.relatedTarget || !el.contains(e.relatedTarget)) resume();
                    }

                    function resume() {
                        if (finished || !paused) return;
                        paused = false;
                        startedAt = Date.now();
                        timer = setTimeout(dismiss, remaining);
                    }

                    function pause() {
                        if (finished || paused) return;
                        paused = true;
                        cancel();
                        remaining -= (Date.now() - startedAt);
                        if (remaining < 0) remaining = 0;
                    }

                    // Suspend while the pointer is over the alert or focus is
                    // inside it, so a message cannot disappear while it is
                    // being read or while its dismiss button is being reached
                    // for. Resumes for the time that is left, not a full
                    // restart, so hovering never extends it indefinitely.
                    el.addEventListener('mouseenter', pause);
                    el.addEventListener('mouseleave', resume);
                    el.addEventListener('focusin', pause);
                    el.addEventListener('focusout', onFocusOut);

                    // Use one shared listener for all live alerts. Only the
                    // alert containing focus is eligible, so Escape elsewhere
                    // (including in another dialog) is left untouched.
                    activeAlerts.push(el);
                    if (!escapeListenerInstalled) {
                        document.addEventListener('keydown', onAlertEscape);
                        escapeListenerInstalled = true;
                    }

                    // Also release timers/listeners when another script removes
                    // this alert directly instead of using its dismiss button.
                    var observer = new MutationObserver(function () {
                        if (!el.isConnected) cleanup();
                    });
                    observer.observe(document.documentElement, { childList: true, subtree: true });

                    el.pmAutoDismiss = { pause: pause, resume: resume, dismiss: dismiss };

                    if (!('setTimeout' in window)) return;
                    startedAt = Date.now();
                    timer = setTimeout(dismiss, remaining);
                };
            }

            var el = document.getElementById('{{ $alertId }}');
            if (!el) return;

            if (typeof window.pmAutoDismiss === 'function') {
                window.pmAutoDismiss(el, parseInt(el.getAttribute('data-auto-dismiss'), 10) || 5000);
                return;
            }

            // Progressive-enhancement fallback: if the shared helper above was
            // unavailable (stripped, blocked, or overwritten by another script)
            // the alert still auto-dismisses, just without pause-on-interaction.
            setTimeout(function () {
                el.style.transition = 'opacity 0.3s ease';
                el.style.opacity = '0';
                setTimeout(function () { el.remove(); }, 300);
            }, 5000);
        })();
    </script>
@endif
@endif
