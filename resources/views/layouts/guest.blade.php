<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'My Digital Diary'))</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    {{-- Design tokens for new components --}}
    <x-design-tokens />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="stylesheet" href="{{ asset('css/guest.css') }}">
    <link rel="stylesheet" href="{{ asset('css/professional-forms.css') }}">

    {{-- PWA install metadata. Needed here too: the manifest is fetched from the
         login page, so without this the app cannot be installed until after the
         user has already signed in. No banner on this layout on purpose. --}}
    @include('partials.pwa-head')

    @stack('styles')
</head>
<body class="antialiased">
    @include('partials.password-toggle')

    <main class="pm-guest-shell">
        <div class="pm-guest-wrap">
            <a href="{{ url('/') }}" class="pm-guest-brand" aria-label="{{ config('app.name', 'My Digital Diary') }} home">
                <span class="pm-guest-logo">
                    <x-application-logo class="w-14 h-14 fill-current text-[var(--brand-1)]" />
                </span>
                <span class="pm-guest-brand-name">{{ config('app.name', 'My Digital Diary') }}</span>
            </a>

            <section class="pm-guest-card">
                {{ $slot }}
            </section>

            <footer class="pm-guest-footer">
                <span>&copy; {{ date('Y') }} {{ config('app.name', 'My Digital Diary') }}.</span>
                @if (Route::has('privacy-policy'))
                    <span> &middot; </span><a href="{{ route('privacy-policy') }}">Privacy</a>
                @endif
                <span> &middot; </span><a href="{{ Route::has('terms-of-use') ? route('terms-of-use') : url('/terms-of-use') }}">Terms</a>
            </footer>
        </div>
    </main>

    @stack('scripts')
</body>
</html>