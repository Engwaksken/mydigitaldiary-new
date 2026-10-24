{{--
    Reusable alert system.

    Props:
        type        : success | error | warning | info  (default: info)
        message     : optional string message (falls back to slot)
        dismissible : whether to show a dismiss button (default: true)
        autoDismiss : success only — show for 5s, then fade out (default: true)

    Slot: message content (used when `message` prop is not provided).
    Extra attributes (e.g. class="mb-4") are merged onto the alert root.

    Auto-dismiss (success only; errors never auto-dismiss):
        The alert stays fully visible for 5 seconds, fades out over 0.5s and is
        then removed. Hovering or focusing the alert pauses the countdown and
        leaving resumes it with the time that was left. Escape or the dismiss
        button fades it out immediately. Driven by the shared, once-per-page
        `window.pmAutoDismiss(el)` helper via `data-auto-dismiss`.

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
    // per request so a page alert and the shared layout flash do not
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

    $styles = [
        'success' => ['icon' => 'fa-circle-check', 'role' => 'status', 'classes' => 'bg-emerald-50 text-emerald-800 border-emerald-200', 'iconClasses' => 'text-emerald-500'],
        'error' => ['icon' => 'fa-circle-exclamation', 'role' => 'alert', 'classes' => 'bg-red-50 text-red-800 border-red-200', 'iconClasses' => 'text-red-500'],
        'warning' => ['icon' => 'fa-triangle-exclamation', 'role' => 'alert', 'classes' => 'bg-amber-50 text-amber-800 border-amber-200', 'iconClasses' => 'text-amber-500'],
        'info' => ['icon' => 'fa-circle-info', 'role' => 'status', 'classes' => 'bg-blue-50 text-blue-800 border-blue-200', 'iconClasses' => 'text-blue-500'],
    ];
    $config = $styles[$type] ?? $styles['info'];

    $shouldAutoDismiss = $autoDismiss && $type === 'success';
    $alertId = 'alert-' . \Illuminate\Support\Str::random(8);
@endphp

@if (! $skipDuplicateSuccess)
<div
    id="{{ $alertId }}"
    role="{{ $config['role'] }}"
    @if ($shouldAutoDismiss) aria-live="polite" data-auto-dismiss="5000" @endif
    {{ $attributes->merge(['class' => 'pm-alert relative flex items-start gap-3 rounded-xl border px-4 py-3 text-sm ' . $config['classes']]) }}
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
            onclick="(function (a) { if (!a) return; if (a.pmAutoDismiss) { a.pmAutoDismiss.dismiss(); } else { a.remove(); } })(this.closest('.pm-alert'))"
        >
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    @endif
</div>

@if ($shouldAutoDismiss)
    <script>
        (function () {
            // Shared helper, defined once per page and reused by every
            // auto-dismissing alert (layout, partial or page, in any order).
            if (typeof window.pmAutoDismiss !== 'function') {
                var FADE_MS = 500;

                window.pmAutoDismiss = function (el, ms) {
                    if (!el || el.pmAutoDismiss) return;

                    var remaining = ms > 0 ? ms : 5000;
                    var startedAt = 0;
                    var timer = null;
                    var done = false;

                    function start() {
                        if (done || timer !== null) return;
                        startedAt = Date.now();
                        timer = setTimeout(dismiss, remaining);
                    }

                    function pause() {
                        if (done || timer === null) return;
                        clearTimeout(timer);
                        timer = null;
                        remaining = Math.max(0, remaining - (Date.now() - startedAt));
                    }

                    function dismiss() {
                        if (done) return;
                        done = true;
                        clearTimeout(timer);
                        document.removeEventListener('keydown', onKey);
                        el.style.transition = 'opacity ' + FADE_MS + 'ms ease';
                        el.style.opacity = '0';
                        setTimeout(function () { el.remove(); }, FADE_MS);
                    }

                    function onKey(e) {
                        if ((e.key === 'Escape' || e.key === 'Esc') && !document.querySelector('dialog[open]')) dismiss();
                    }

                    el.addEventListener('mouseenter', pause);
                    el.addEventListener('mouseleave', start);
                    el.addEventListener('focusin', pause);
                    el.addEventListener('focusout', function (e) {
                        if (!e.relatedTarget || !el.contains(e.relatedTarget)) start();
                    });
                    document.addEventListener('keydown', onKey);

                    el.pmAutoDismiss = { pause: pause, resume: start, dismiss: dismiss };
                    start();
                };
            }

            var el = document.getElementById('{{ $alertId }}');
            if (el) window.pmAutoDismiss(el, parseInt(el.getAttribute('data-auto-dismiss'), 10));
        })();
    </script>
@endif
@endif
