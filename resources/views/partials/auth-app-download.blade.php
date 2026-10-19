{{--
    "Get the app" block for the login, register and forgot-password pages.

    - Store buttons (Google Play / App Store) render only when their URL is set
      in config/services.php (MOBILE_APP_ANDROID_URL / MOBILE_APP_IOS_URL).
    - The Install button is for the installable web app. Like the in-app prompt
      (partials/pwa-install), it only appears once public/js/pwa.js reports the
      browser can really install; iPhone/iPad, and Android before its browser
      offers the prompt, get the Add to Home Screen step instead.
    - The whole block hides when there is nothing actionable, or when the app
      is already installed and this page is running inside it.
--}}
@php
    $appAndroidUrl = config('services.mobile_app.android_url');
    $appIosUrl = config('services.mobile_app.ios_url');
    $appName = trim((string) ($siteSettings->site_name ?? '')) ?: config('app.name', 'My Digital Diary');

    // Show the right hint from the first paint, from the User-Agent, instead
    // of revealing it from JavaScript after load: that late reveal grew the
    // card and was most of the layout shift Lighthouse measured on phones.
    // The script below still corrects it (install prompt available, iPadOS
    // with a desktop UA, already running as the installed app).
    $appUserAgent = (string) request()->userAgent();
    $appShowAndroidHint = (bool) preg_match('/Android/i', $appUserAgent);
    $appShowIosHint = (bool) preg_match('/iPad|iPhone|iPod/', $appUserAgent);
@endphp

<div id="pm-auth-app" class="mt-6 rounded-xl border border-slate-200 bg-slate-50/70 p-4" @unless ($appAndroidUrl || $appIosUrl || $appShowAndroidHint || $appShowIosHint) hidden @endunless>
    <div class="flex items-center gap-3">
        <img src="{{ app(\App\Services\PwaIconService::class)->url('icon-192') }}" alt="" width="36" height="36" class="h-9 w-9 rounded-lg shrink-0">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-800">Get the {{ $appName }} app</p>
            <p class="text-xs text-slate-500">Open your diary from your home screen in one tap.</p>
        </div>
    </div>

    <div class="mt-3 flex flex-wrap gap-2">
        @if ($appAndroidUrl)
            <a href="{{ $appAndroidUrl }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                <x-icon name="google-play" /> Get it on Google Play
            </a>
        @endif
        @if ($appIosUrl)
            <a href="{{ $appIosUrl }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                <x-icon name="apple" /> Download on the App Store
            </a>
        @endif
        <button type="button" id="pm-auth-app-install" hidden
                class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold text-white hover:brightness-95"
                style="background-color: var(--brand-1, #00897B);">
            <x-icon name="download" /> Download app
        </button>
    </div>

    <p id="pm-auth-app-ios" class="mt-3 text-xs text-slate-600" @unless ($appShowIosHint) hidden @endunless>
        <x-icon name="arrow-up-from-bracket" />
        On iPhone or iPad: tap <strong>Share</strong>, then <strong>Add to Home Screen</strong>.
    </p>

    <p id="pm-auth-app-android" class="mt-3 text-xs text-slate-600" @unless ($appShowAndroidHint) hidden @endunless>
        <x-icon name="ellipsis-vertical" />
        On Android: open the browser menu <strong>&#8942;</strong>, then <strong>Install app</strong> or <strong>Add to Home screen</strong>.
    </p>
</div>

@once
    <script src="{{ asset('js/pwa.js') }}" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var root = document.getElementById('pm-auth-app');
            var install = document.getElementById('pm-auth-app-install');
            var iosHint = document.getElementById('pm-auth-app-ios');
            var androidHint = document.getElementById('pm-auth-app-android');
            // Android browsers can hold back their install prompt until the site
            // has been used a little; until then, show the menu step instead.
            var isAndroid = /Android/i.test(window.navigator.userAgent);
            var hasStoreLinks = {{ ($appAndroidUrl || $appIosUrl) ? 'true' : 'false' }};
            if (!root || !window.pmPwa) { return; }

            function render(state) {
                if (state.installed) {
                    // Already running as the installed app: nothing to download.
                    root.hidden = true;
                    return;
                }
                install.hidden = !state.canPromptDirectly;
                iosHint.hidden = !(state.isIos && !state.canPromptDirectly);
                androidHint.hidden = !(isAndroid && !state.canPromptDirectly);
                root.hidden = !(hasStoreLinks || !install.hidden || !iosHint.hidden || !androidHint.hidden);
            }

            install.addEventListener('click', function () {
                window.pmPwa.install().then(function () { render(window.pmPwa.state()); });
            });

            window.pmPwa.onChange(render);
            render(window.pmPwa.state());
        });
    </script>
@endonce
