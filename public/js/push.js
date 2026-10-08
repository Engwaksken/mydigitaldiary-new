/*
 * My Digital Diary — daily reminders (browser / installed web app push).
 *
 * Plain browser script served from /js/push.js, like /js/pwa.js, because the
 * app layout loads its scripts from public/ rather than through Vite.
 *
 * How it works:
 *   - layouts/app.blade.php sets window.pmPushConfig only when the Firebase
 *     web keys are configured on the server; without it, available() is
 *     false and every "Turn on daily reminders" control stays hidden.
 *   - enable() asks for notification permission, gets an FCM web token for
 *     the app's own service worker (/sw.js, which shows the notification),
 *     and stores it through POST /push/web-token (session + CSRF).
 *   - On later visits the token is refreshed quietly once a day (FCM tokens
 *     rotate) and re-registered whenever a different person signs in here.
 *
 * Exposed on window.pmPush; listeners get a fresh state() on every change.
 */
(function () {
    'use strict';

    var FIREBASE_VERSION = '10.12.2';
    var ENABLED_KEY = 'pm_push_enabled';
    var DEVICE_KEY = 'pm_push_device_id';
    var OWNER_KEY = 'pm_push_owner';
    var REFRESHED_KEY = 'pm_push_refreshed_at';

    var config = window.pmPushConfig || null;
    var listeners = [];
    var busy = false;

    function store(key, value) {
        try {
            if (value === undefined) {
                return localStorage.getItem(key);
            }
            if (value === null) {
                localStorage.removeItem(key);
            } else {
                localStorage.setItem(key, value);
            }
        } catch (error) {
            /* Private mode: the server copy of the token still works. */
        }
        return null;
    }

    function isIos() {
        var ua = window.navigator.userAgent;
        return /iPad|iPhone|iPod/.test(ua) ||
            (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
    }

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches ||
            window.navigator.standalone === true;
    }

    function supported() {
        return 'serviceWorker' in navigator && 'Notification' in window && 'PushManager' in window;
    }

    function state() {
        var permission = 'Notification' in window ? Notification.permission : 'unsupported';
        var available = !!config;
        // iOS only offers web push to an app added to the Home Screen.
        var needsInstall = available && isIos() && !isStandalone();

        return {
            available: available,
            supported: supported(),
            needsInstall: needsInstall,
            permission: permission,
            busy: busy,
            enabled: available && permission === 'granted' && store(ENABLED_KEY) === '1' &&
                store(OWNER_KEY) === String(config && config.userId)
        };
    }

    function emit() {
        var snapshot = state();
        for (var i = 0; i < listeners.length; i++) {
            try { listeners[i](snapshot); } catch (error) { /* keep going */ }
        }
    }

    function deviceId() {
        var id = store(DEVICE_KEY);
        if (!id) {
            id = 'web-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
            store(DEVICE_KEY, id);
        }
        return id;
    }

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function send(method, url, body) {
        return fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body)
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json().catch(function () { return {}; });
        });
    }

    function workerRegistration() {
        return navigator.serviceWorker.getRegistration('/').then(function (registration) {
            return registration || navigator.serviceWorker.register('/sw.js', { scope: '/' });
        }).then(function () {
            return navigator.serviceWorker.ready;
        });
    }

    function fetchToken() {
        var base = 'https://www.gstatic.com/firebasejs/' + FIREBASE_VERSION + '/';

        return Promise.all([
            import(base + 'firebase-app.js'),
            import(base + 'firebase-messaging.js'),
            workerRegistration()
        ]).then(function (modules) {
            var appSdk = modules[0];
            var messagingSdk = modules[1];
            var registration = modules[2];

            return messagingSdk.isSupported().then(function (ok) {
                if (!ok) {
                    var error = new Error('unsupported');
                    error.reason = 'unsupported';
                    throw error;
                }

                var existing = appSdk.getApps().filter(function (app) { return app.name === 'mdd-push'; })[0];
                var app = existing || appSdk.initializeApp(config.firebase, 'mdd-push');

                return messagingSdk.getToken(messagingSdk.getMessaging(app), {
                    vapidKey: config.vapidKey,
                    serviceWorkerRegistration: registration
                });
            });
        });
    }

    function register() {
        return fetchToken().then(function (token) {
            if (!token) {
                throw new Error('no token');
            }
            return send('POST', config.storeUrl, { device_id: deviceId(), fcm_token: token });
        }).then(function () {
            store(ENABLED_KEY, '1');
            store(OWNER_KEY, String(config.userId));
            store(REFRESHED_KEY, String(Date.now()));
        });
    }

    function fail(reason) {
        return { ok: false, reason: reason };
    }

    var MESSAGES = {
        'ios-install': 'On iPhone or iPad, add this app to your Home Screen first, then turn reminders on from the app.',
        unsupported: 'This browser can’t show reminders. Try Chrome, Edge or Firefox, or install the app.',
        denied: 'Notifications are blocked for this site. Allow them in your browser’s site settings, then try again.',
        dismissed: 'No problem. Tap again whenever you’re ready.',
        unavailable: 'Phone reminders aren’t available yet.',
        error: 'Couldn’t turn on reminders just now. Please try again.'
    };

    window.pmPush = {
        state: state,
        message: function (reason) { return MESSAGES[reason] || MESSAGES.error; },

        /* Opens the existing "Add to Home Screen" steps (partials/pwa-install). */
        showInstallHelp: function () {
            var root = document.getElementById('pm-pwa-root');
            var dialog = document.getElementById('pm-pwa-ios-dialog');

            if (!root || !dialog) {
                return false;
            }

            root.hidden = false;
            dialog.hidden = false;
            var close = document.getElementById('pm-pwa-ios-close-button');
            if (close) { close.focus(); }

            return true;
        },
        available: function () { return !!config; },
        onChange: function (handler) { listeners.push(handler); },

        /*
         * Must be called from a tap: browsers only show the permission
         * prompt in response to a user gesture.
         * Resolves { ok: true } or { ok: false, reason } where reason is
         * unavailable | unsupported | ios-install | denied | error.
         */
        enable: function () {
            var current = state();

            if (!current.available) { return Promise.resolve(fail('unavailable')); }
            if (current.needsInstall) { return Promise.resolve(fail('ios-install')); }
            if (!current.supported) { return Promise.resolve(fail('unsupported')); }
            if (current.permission === 'denied') { return Promise.resolve(fail('denied')); }

            busy = true;
            emit();

            var permission = current.permission === 'granted'
                ? Promise.resolve('granted')
                : Notification.requestPermission();

            return Promise.resolve(permission).then(function (result) {
                if (result !== 'granted') {
                    return fail(result === 'denied' ? 'denied' : 'dismissed');
                }

                return register().then(function () {
                    return { ok: true };
                });
            }).catch(function (error) {
                return fail(error && error.reason ? error.reason : 'error');
            }).then(function (outcome) {
                busy = false;
                emit();
                return outcome;
            });
        },

        /* Stops reminders on THIS device only; other devices keep theirs. */
        disable: function () {
            if (!config) {
                return Promise.resolve(fail('unavailable'));
            }

            busy = true;
            emit();

            return send('DELETE', config.destroyUrl, { device_id: deviceId() }).then(function () {
                store(ENABLED_KEY, null);
                return { ok: true };
            }).catch(function () {
                return fail('error');
            }).then(function (outcome) {
                busy = false;
                emit();
                return outcome;
            });
        }
    };

    /*
     * Quiet upkeep: refresh the token once a day (FCM rotates them). Never
     * prompts. If a different person is now signed in on this browser,
     * reminders are NOT carried over to them: they turn them on themselves.
     */
    function refresh() {
        if (!config || !supported() || Notification.permission !== 'granted' || store(ENABLED_KEY) !== '1') {
            return;
        }

        if (store(OWNER_KEY) !== String(config.userId)) {
            store(ENABLED_KEY, null);
            emit();
            return;
        }

        var last = Number(store(REFRESHED_KEY) || 0);

        if (Date.now() - last < 24 * 60 * 60 * 1000) {
            return;
        }

        register().then(emit).catch(function () { /* try again next visit */ });
    }

    if (document.readyState === 'complete') {
        refresh();
    } else {
        window.addEventListener('load', refresh);
    }

    emit();
})();
