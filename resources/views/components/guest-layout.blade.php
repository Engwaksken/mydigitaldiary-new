{{-- Shared layout for login, register, OTP, forgot/reset password. --}}
<!DOCTYPE html>
<html lang="en">
@php
    $siteSettings = $siteSettings ?? \App\Models\SiteSetting::current();
    $authLogo = $siteSettings->logoDataUri() ?: $siteSettings->logoUrl();
@endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $siteSettings->site_name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,400;0,700;1,400&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.pwa-head')
    @include('partials.auth-styles')

    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body { background: color-mix(in srgb, var(--brand-1) 6%, white 94%); }

        .auth-page-shell { min-height: 100vh; display: flex; }
        .auth-side { background: var(--brand-1) !important; }
        .auth-side-inner { width: 100%; max-width: 420px; margin: 0 auto; }
        .auth-form-section {
            flex: 1; position: relative; display: flex; flex-direction: column;
            align-items: center; justify-content: center; padding: 32px 20px;
            background: linear-gradient(180deg, color-mix(in srgb, var(--brand-1) 6%, white 94%) 0%, #fff 100%);
        }
        .auth-form-section::before, .auth-form-section::after { display: none !important; content: none !important; }
        .auth-card-wrapper {
            position: relative; z-index: 2; width: 100%; max-width: 450px; overflow: hidden;
            background: #fff; border: 1px solid color-mix(in srgb, var(--brand-1) 15%, #e2e8f0 85%);
            border-radius: 18px; box-shadow: 0 12px 35px rgba(15,23,42,.07), 0 3px 10px rgba(15,23,42,.04);
        }
        .guest-logo-circle, .guest-logo-circle-sm {
            display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;
            background: #fff; border-radius: 9999px;
        }
        .guest-logo-circle { width: 100px; height: 100px; padding: 12px; box-shadow: 0 9px 25px rgba(15,23,42,.15); }
        .guest-logo-circle-sm { width: 82px; height: 82px; padding: 10px; border: 1px solid #e2e8f0; box-shadow: 0 7px 20px rgba(15,23,42,.08); }
        .guest-logo-circle img, .guest-logo-circle-sm img { width: 100%; height: 100%; display: block; object-fit: contain; filter: none !important; }
        .guest-logo-fallback { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; border-radius: inherit; color: var(--brand-1); font-size: 2rem; }
        .auth-card-header { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 30px 24px 24px; background: #fff; border-bottom: 1px solid #f1f5f9; }
        .auth-brand-link { display: flex; flex-direction: column; align-items: center; gap: 10px; color: inherit; text-decoration: none; }
        .auth-brand-name { margin: 0; color: var(--brand-1); font-size: 1.1rem; font-weight: 700; line-height: 1.3; text-align: center; }
        .auth-card-content { padding: 30px 26px; background: #fff; }
        .auth-footer-links { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 10px 16px; margin-top: 18px; font-size: .8125rem; color: #64748b; }
        .auth-footer-links a { display: inline-flex; align-items: center; gap: 6px; color: #64748b; text-decoration: none; }
        .auth-footer-links a:hover { color: var(--brand-1); }
        .auth-guide-callout { margin-top: 28px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,.18); }
        .auth-guide-callout a { display: inline-flex; align-items: center; gap: 8px; color: white; font-size: .875rem; font-weight: 700; text-decoration: none; }
        .auth-guide-callout a:hover { text-decoration: underline; }

        @media (max-width: 1023px) {
            .auth-page-shell { display: block; }
            .auth-form-section { min-height: 100vh; padding: 24px 16px; background: linear-gradient(180deg, color-mix(in srgb, var(--brand-1) 10%, white 90%) 0%, #fff 100%); }
        }
        @media (max-width: 640px) {
            .auth-form-section { padding: 18px 12px; }
            .auth-card-wrapper { border-radius: 16px; }
            .auth-card-header { padding: 24px 18px 20px; }
            .auth-card-content { padding: 24px 20px; }
            .guest-logo-circle-sm { width: 72px; height: 72px; padding: 8px; }
        }
    </style>
</head>
<body class="text-slate-900 antialiased">
@include('partials.accessibility-widget')
@include('partials.password-toggle')

<div class="auth-page-shell">
    <aside class="hidden lg:flex lg:w-2/5 auth-side text-white flex-col justify-center px-12 py-16">
        <div class="auth-side-inner">
            <div class="flex flex-col items-center gap-3 mb-10 text-center">
                @if ($authLogo)
                    <div class="guest-logo-circle"><img src="{{ $authLogo }}" onerror="this.closest('.guest-logo-circle, .guest-logo-circle-sm')?.classList.add('logo-load-error'); this.style.display='none';" alt="{{ $siteSettings->site_name }} logo"></div>
                @else
                    <div class="guest-logo-circle"><div class="guest-logo-fallback"><i class="fa-solid fa-chart-line"></i></div></div>
                @endif
                <span class="font-semibold text-lg">{{ $siteSettings->site_name }}</span>
            </div>

            <h1 class="text-2xl font-semibold leading-snug mb-4">One place for the parts of life you're already keeping track of.</h1>
            <p class="text-white/75 text-sm mb-8 leading-relaxed">Keep your money, health, plans, relationships, goals and reminders organised in one place.</p>

            <ul class="space-y-4 text-sm text-white/85">
                <li class="flex items-start gap-3"><span class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center shrink-0"><i class="fa-solid fa-wallet"></i></span><span class="pt-1">Keep track of income, expenses, budgets, savings and financial goals.</span></li>
                <li class="flex items-start gap-3"><span class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center shrink-0"><i class="fa-solid fa-heart-pulse"></i></span><span class="pt-1">Record meals, exercise, sleep and your personal wellbeing.</span></li>
                <li class="flex items-start gap-3"><span class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center shrink-0"><i class="fa-solid fa-list-check"></i></span><span class="pt-1">Plan your day, annual goals and important projects.</span></li>
                <li class="flex items-start gap-3"><span class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center shrink-0"><i class="fa-solid fa-bell"></i></span><span class="pt-1">Receive timely reminders when something needs your attention.</span></li>
            </ul>

            <div class="mt-7 rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-xs text-white/80 leading-5">
                <div class="font-semibold text-white mb-1"><i class="fa-solid fa-shield-halved mr-1"></i> Privacy-first by design</div>
                Your diary entries are not shared with other users by default. We do not sell private diary data for advertising, and you control which areas AI features may use.
            </div>

            <div class="auth-guide-callout">
                <p class="text-white/65 text-xs mb-2">New to {{ $siteSettings->site_name }}?</p>
                <a href="{{ route('user-guide') }}"><i class="fa-solid fa-book-open"></i> Open the User Guide <i class="fa-solid fa-arrow-right text-xs"></i></a>
            </div>
        </div>
    </aside>

    <main class="auth-form-section lg:w-3/5">
        <div class="auth-card-wrapper">
            <div class="auth-card-header">
                <a href="/" class="auth-brand-link" aria-label="{{ $siteSettings->site_name }}">
                    @if ($authLogo)
                        <div class="guest-logo-circle-sm"><img src="{{ $authLogo }}" onerror="this.closest('.guest-logo-circle, .guest-logo-circle-sm')?.classList.add('logo-load-error'); this.style.display='none';" alt="{{ $siteSettings->site_name }} logo"></div>
                    @else
                        <div class="guest-logo-circle-sm"><div class="guest-logo-fallback"><i class="fa-solid fa-chart-line"></i></div></div>
                    @endif
                    <span class="auth-brand-name">{{ $siteSettings->site_name }}</span>
                </a>
            </div>

            <div class="auth-card-content">{{ $slot }}</div>
        </div>

        <div class="auth-footer-links" aria-label="Help links">
            <a href="{{ route('user-guide') }}"><i class="fa-solid fa-book-open"></i> User Guide</a>
            @if (Route::has('privacy-policy'))
                <a href="{{ route('privacy-policy') }}"><i class="fa-solid fa-shield-halved"></i> Privacy Policy</a>
            @endif
        </div>
    </main>
</div>
</body>
</html>
