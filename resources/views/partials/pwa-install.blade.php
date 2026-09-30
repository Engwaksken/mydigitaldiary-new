{{--
    The install / update prompt.

    Renders nothing until public/js/pwa.js reports that the browser is actually
    willing to install this app. That matters: showing an "Install" button that
    does nothing is worse than showing no button, and whether the prompt is
    available is not knowable until the browser says so.

    Markup is hidden by default and revealed by the script at the bottom, so a
    browser without service workers (or with JS off) shows a blank box rather
    than a broken promise.
--}}
@php
    $pwaAppName = trim((string) ($siteSettings->site_name ?? '')) ?: 'My Digital Diary';
@endphp

<div id="pm-pwa-root" hidden>
    {{-- Install / update banner --}}
    <div
        id="pm-pwa-banner"
        role="status"
        aria-live="polite"
        class="fixed inset-x-0 bottom-0 z-[2147483000] px-3 pb-3 sm:px-4 sm:pb-4 pointer-events-none"
        hidden
    >
        <div class="mx-auto w-full max-w-xl pointer-events-auto">
            <div class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-3 sm:p-4 shadow-2xl">
                <img
                    src="{{ asset('icons/icon-192.png') }}"
                    alt=""
                    width="44"
                    height="44"
                    class="h-11 w-11 shrink-0 rounded-xl"
                >
                <div class="min-w-0 flex-1">
                    <p id="pm-pwa-banner-title" class="text-sm font-bold text-slate-900">
                        Install {{ $pwaAppName }}
                    </p>
                    <p id="pm-pwa-banner-text" class="mt-0.5 text-xs text-slate-600">
                        Add it to your home screen for quicker access.
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            id="pm-pwa-primary-button"
                            class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:brightness-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                            style="background-color: var(--brand-1, #00897B);"
                        >
                            <span id="pm-pwa-primary-label">Install</span>
                        </button>
                        <button
                            type="button"
                            id="pm-pwa-dismiss-button"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-300"
                        >
                            Not now
                        </button>
                    </div>
                </div>
                <button
                    type="button"
                    id="pm-pwa-close-button"
                    class="shrink-0 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-300"
                    aria-label="Dismiss the install prompt"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                        <path d="M18 6 6 18M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- iOS instructions: Safari has no beforeinstallprompt, so a button that
         calls install() would do nothing at all there. --}}
    <div
        id="pm-pwa-ios-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="pm-pwa-ios-title"
        class="fixed inset-0 z-[2147483100] flex items-end justify-center bg-slate-900/50 p-4 sm:items-center"
        hidden
    >
        <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
            <h2 id="pm-pwa-ios-title" class="text-base font-bold text-slate-900">
                Install {{ $pwaAppName }}
            </h2>
            <ol class="mt-3 space-y-2.5 text-sm text-slate-700">
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-700">1</span>
                    <span>Tap the <strong>Share</strong> button in Safari's toolbar.</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-700">2</span>
                    <span>Scroll down and choose <strong>Add to Home Screen</strong>.</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-700">3</span>
                    <span>Tap <strong>Add</strong>. {{ $pwaAppName }} then opens from your home screen.</span>
                </li>
            </ol>
            <div class="mt-5 flex justify-end">
                <button
                    type="button"
                    id="pm-pwa-ios-close-button"
                    class="rounded-lg px-4 py-2 text-sm font-semibold text-white"
                    style="background-color: var(--brand-1, #00897B);"
                >
                    Got it
                </button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/pwa.js') }}" defer></script>
<script>
    (function () {
        'use strict';

        var root = document.getElementById('pm-pwa-root');
        var api = window.pmPwa;

        // No service workers, or the script never loaded: leave the page alone.
        if (!root || !api) { return; }

        var banner = document.getElementById('pm-pwa-banner');
        var title = document.getElementById('pm-pwa-banner-title');
        var text = document.getElementById('pm-pwa-banner-text');
        var primary = document.getElementById('pm-pwa-primary-button');
        var primaryLabel = document.getElementById('pm-pwa-primary-label');
        var dismiss = document.getElementById('pm-pwa-dismiss-button');
        var close = document.getElementById('pm-pwa-close-button');
        var dialog = document.getElementById('pm-pwa-ios-dialog');
        var dialogClose = document.getElementById('pm-pwa-ios-close-button');
        var appName = @json($pwaAppName);

        var lastFocus = null;

        function show(element) {
            if (element) { element.hidden = false; }
            root.hidden = false;
        }

        function hide(element) {
            if (element) { element.hidden = true; }
        }

        function render(state) {
            // An update outranks the install prompt: the app is already on the
            // phone, so what is worth saying is "there is a new version".
            if (state.updateWaiting) {
                title.textContent = 'Update available';
                text.textContent = 'A new version of ' + appName + ' is ready to install.';
                primaryLabel.textContent = 'Update now';
                primary.hidden = false;
                dismiss.hidden = true;
                hide(close);
                show(banner);
                return;
            }

            if (state.installed) {
                hide(banner);
                return;
            }

            if (state.dismissed) {
                hide(banner);
                return;
            }

            // Firefox and desktop Safari never fire beforeinstallprompt. An
            // install button there would be a lie, so nothing is shown at all.
            if (!state.canPromptDirectly && !state.isIos) {
                hide(banner);
                return;
            }

            title.textContent = 'Install ' + appName;
            text.textContent = state.canPromptDirectly
                ? 'Add it to your home screen for quicker access.'
                : 'Add it to your home screen to open it like an app.';
            primaryLabel.textContent = state.canPromptDirectly ? 'Install' : 'How to install';
            primary.hidden = false;
            dismiss.hidden = false;
            hide(close);
            show(banner);
        }

        function openDialog() {
            lastFocus = document.activeElement;
            show(dialog);
            if (dialogClose) { dialogClose.focus(); }
        }

        function closeDialog() {
            hide(dialog);
            if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
        }

        primary.addEventListener('click', function () {
            var state = api.state();

            if (state.updateWaiting) {
                if (api.update()) {
                    hide(banner);
                }
                return;
            }

            if (state.canPromptDirectly) {
                api.install();
                return;
            }

            openDialog();
        });

        dismiss.addEventListener('click', function () { api.dismiss(); });
        close.addEventListener('click', function () { api.dismiss(); });
        dialogClose.addEventListener('click', closeDialog);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !dialog.hidden) {
                closeDialog();
            }
        });

        api.onChange(render);
        render(api.state());

        // Look for a new build once the page has settled, rather than on load,
        // so the check never competes with the first paint.
        window.setTimeout(function () { api.checkForUpdate(); }, 8000);
    })();
</script>
