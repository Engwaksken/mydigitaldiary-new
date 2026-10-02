{{--
    Progressive Web App metadata.

    Every layout needs this, not just the authenticated one: without a manifest
    link and the theme-colour meta tags a browser will not offer the app for
    install, no matter how good the install button on the page is.

    The favicon link above is left alone — layouts/app.blade.php already emits
    the admin's uploaded favicon, and this must never take precedence over it.
--}}
@php
    /*
     * The theme colour is per-user (User::themeColor()), not a constant: the
     * Android status bar and the iOS title bar should match the brand the
     * user actually picked. This partial is included by four layouts (the
     * authenticated shell, both guest layouts and the error layout), so it
     * has to be safe with no authenticated user, and while an exception is
     * being rendered. Every lookup is guarded and falls back to the app
     * defaults used in layouts/app.blade.php: #00897B (--brand-1) and
     * #006B60 (--brand-1-dark).
     */
    $pwaThemeLight = '#00897B';
    $pwaThemeDark = '#006B60';

    try {
        $pwaUser = auth()->user();

        if ($pwaUser) {
            if (method_exists($pwaUser, 'themeColor')) {
                $candidate = $pwaUser->themeColor();
                if (is_string($candidate) && preg_match('/\A#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})\z/', $candidate)) {
                    $pwaThemeLight = $candidate;
                }
            }

            if (method_exists($pwaUser, 'themeColorDark')) {
                $candidate = $pwaUser->themeColorDark();
                if (is_string($candidate) && preg_match('/\A#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})\z/', $candidate)) {
                    $pwaThemeDark = $candidate;
                }
            }
        }
    } catch (\Throwable $e) {
        // A missing DB column or a malformed stored colour must never take the
        // page down. Keep the literals above.
    }
@endphp
<link rel="manifest" href="{{ route('pwa.manifest') }}">
{{-- One hardcoded colour cannot be right for both schemes. The unqualified
     tag is the fallback for user agents that ignore the media attribute;
     modern browsers use the light/dark pair below. --}}
<meta name="theme-color" content="{{ $pwaThemeLight }}">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="{{ $pwaThemeLight }}">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="{{ $pwaThemeDark }}">
{{-- The app has no dark mode. Declaring "light" stops the UA from
     auto-darkening form controls and scrollbars out from under the design. --}}
<meta name="color-scheme" content="light">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $siteSettings->site_name ?? 'My Digital Diary' }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-touch-icon.png') }}">
