{{--
    Progressive Web App metadata.

    Every layout needs this, not just the authenticated one: without a manifest
    link and the theme-colour meta tags a browser will not offer the app for
    install, no matter how good the install button on the page is.

    The favicon link above is left alone — layouts/app.blade.php already emits
    the admin's uploaded favicon, and this must never take precedence over it.
--}}
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="#00897B">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $siteSettings->site_name ?? 'My Digital Diary' }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
