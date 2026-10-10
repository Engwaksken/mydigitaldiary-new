/*
 * My Digital Diary — PWA install + service worker registration.
 *
 * Plain ES5-ish browser script, served from /js/pwa.js rather than bundled, for
 * the same reason /js/time-12h.js is: layouts/app.blade.php loads its assets
 * from public/ and never goes through Vite, so anything added to
 * resources/js/app.js would simply not be on the page.
 *
 * What it does:
 *   1. registers the service worker,
 *   2. captures beforeinstallprompt so a custom button can trigger it (browsers
 *      only allow that prompt to be used once, and never more than a few
 *      seconds after it fires),
 *   3. surfaces "Update available" when a new build is waiting to take over,
 *   4. tells the page whether the app is already installed.
 *
 * Exposed on window.pmPwa so the Blade install component can bind to it.
 */
(function () {
    'use strict';

    var DISMISSED_KEY = 'pm_pwa_install_dismissed';
    var INSTALLED_KEY = 'pm_pwa_installed';
    var WAITING_KEY = 'pm_pwa_update_waiting';

    var listeners = { change: [] };

    function onChange(handler) {
        listeners.change.push(handler);
    }

    function emit() {
        for (var i = 0; i < listeners.change.length; i++) {
            try {
                listeners.change[i](snapshot());
            } catch (error) {
                /* One broken listener must not silence the rest. */
            }
        }
    }

    /* Standalone: launched from the home screen, or already an installed app. */
    function isInstalled() {
        try {
            if (localStorage.getItem(INSTALLED_KEY) === '1') {
                return true;
            }
        } catch (error) {
            /* Private browsing can refuse localStorage; the checks below still work. */
        }

        return window.matchMedia('(display-mode: standalone)').matches ||
            window.matchMedia('(display-mode: fullscreen)').matches ||
            window.matchMedia('(display-mode: minimal-ui)').matches ||
            window.navigator.standalone === true;
    }

    /*
     * iOS Safari has no beforeinstallprompt at all, so an "Install" button
     * would silently do nothing there. Detect it and show the Share → Add to
     * Home Screen steps instead of a button that lies.
     */
    function isIos() {
        var ua = window.navigator.userAgent;

        if (/iPad|iPhone|iPod/.test(ua)) {
            return true;
        }

        // iPadOS 13+ reports a desktop UA but is still Safari on a touch device.
        return window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1;
    }

    function isDismissed() {
        try {
            return localStorage.getItem(DISMISSED_KEY) === '1';
        } catch (error) {
            return false;
        }
    }

    function setDismissed(value) {
        try {
            if (value) {
                localStorage.setItem(DISMISSED_KEY, '1');
            } else {
                localStorage.removeItem(DISMISSED_KEY);
            }
        } catch (error) {
            /* Nothing to do; the banner simply reappears next visit. */
        }

        emit();
    }

    var state = {
        deferredPrompt: null,
        registration: null,
        updateWaiting: false,
        installError: null
    };

    function snapshot() {
        return {
            canInstall: !!state.deferredPrompt || isIos(),
            canPromptDirectly: !!state.deferredPrompt,
            isIos: isIos(),
            installed: isInstalled(),
            dismissed: isDismissed(),
            updateWaiting: state.updateWaiting
        };
    }

    window.pmPwa = {
        onChange: onChange,
        state: snapshot,
        isInstalled: isInstalled,
        isIos: isIos,
        dismiss: function () { setDismissed(true); },
        undismiss: function () { setDismissed(false); },

        /*
         * Fires the browser's own install dialog. Resolves to true when the app
         * was installed, false when the user declined. On iOS (and anywhere the
         * browser withheld the prompt) it resolves false without pretending.
         */
        install: function () {
            var prompt = state.deferredPrompt;

            if (!prompt) {
                return Promise.resolve(false);
            }

            // The prompt is single-use; holding onto it would make the button
            // stop working the second time it is tapped.
            state.deferredPrompt = null;
            emit();

            var promptResult;
            try {
                promptResult = prompt.prompt();
            } catch (error) {
                return Promise.resolve(false);
            }

            // Modern browsers resolve prompt() without a choice; the outcome
            // lives on userChoice. Older implementations may return it directly.
            return Promise.resolve(promptResult)
                .then(function (choice) {
                    return choice || prompt.userChoice || null;
                })
                .then(function (choice) {
                    return choice && choice.outcome === 'accepted';
                })
                .catch(function () {
                    return false;
                });
        },

        /* Hands control to the waiting worker, which fires on SKIP_WAITING. */
        update: function () {
            var waiting = state.registration && state.registration.waiting;

            if (!waiting) {
                return false;
            }

            state.updateWaiting = false;
            emit();

            waiting.postMessage({ type: 'SKIP_WAITING' });

            return true;
        },

        checkForUpdate: function () {
            if (!state.registration) {
                return;
            }

            state.registration.update().catch(function () {
                /* Offline: the next online visit will check again. */
            });
        }
    };

    window.addEventListener('beforeinstallprompt', function (event) {
        /*
         * Suppress Chrome's own mini-infobar so the app can offer the install
         * in one consistent place. Its user-choice signal is still recorded in
         * the prompt event, so a user who said "no" here is not asked again by
         * the browser on the next page.
         */
        event.preventDefault();
        state.deferredPrompt = event;
        emit();
    });

    window.addEventListener('appinstalled', function () {
        try {
            localStorage.setItem(INSTALLED_KEY, '1');
        } catch (error) {
            /* Ignore: display-mode still reports standalone. */
        }

        state.deferredPrompt = null;
        emit();
    });

    function watchRegistration(registration) {
        state.registration = registration;
        state.updateWaiting = !!registration.waiting;

        if (registration.waiting) {
            try {
                sessionStorage.setItem(WAITING_KEY, '1');
            } catch (error) {
                /* Ignore. */
            }
        }

        emit();

        registration.addEventListener('updatefound', function () {
            var installing = registration.installing;

            if (!installing) {
                return;
            }

            installing.addEventListener('statechange', function () {
                if (installing.state !== 'installed') {
                    return;
                }

                if (window.navigator.serviceWorker.controller) {
                    // A controller already existed, so this install is a new
                    // build waiting behind the old one. Ask; never reload on
                    // the user's behalf while they may be typing into a form.
                    state.updateWaiting = true;
                    emit();
                } else {
                    state.deferredPrompt = null;
                    emit();
                }
            });
        });
    }

    if ('serviceWorker' in window.navigator) {
        window.addEventListener('load', function () {
            window.navigator.serviceWorker.register('/sw.js', { scope: '/' })
                .then(watchRegistration)
                .catch(function () {
                    /*
                     * No service worker means no offline shell. The app still
                     * works and the install banner simply never appears —
                     * this must not break the page.
                     */
                });
        });
    }

    emit();
})();
