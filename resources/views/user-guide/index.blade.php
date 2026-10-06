<!DOCTYPE html>
<html lang="en">
@php
    $siteSettings = $siteSettings ?? new \App\Models\SiteSetting(['site_name' => 'My Digital Diary']);
@endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Guide - {{ $siteSettings->site_name }}</title>
    @include('partials.favicon')
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700;900&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.auth-styles')
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
@include('partials.accessibility-widget')

<header class="bg-white border-b border-slate-200 sticky top-0 z-30">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-4">
        <a href="/" class="flex items-center gap-3 min-w-0">
            @if ($siteSettings->logoUrl())
                <span class="w-11 h-11 rounded-full bg-white border border-slate-200 shadow-sm p-1.5 flex items-center justify-center overflow-hidden shrink-0">
                    <img src="{{ $siteSettings->logoUrl() }}" alt="{{ $siteSettings->site_name }}" class="w-full h-full object-contain">
                </span>
            @else
                <span class="w-11 h-11 rounded-xl btn-primary text-white flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-book-open"></i>
                </span>
            @endif
            <span class="min-w-0">
                <span class="block text-sm text-slate-500">{{ $siteSettings->site_name }}</span>
                <span class="block font-bold text-lg truncate">User Guide</span>
            </span>
        </a>

        <div class="flex items-center gap-2">
            @auth
                <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium hover:bg-slate-50">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium hover:bg-slate-50">Log in</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium">Create account</a>
                @endif
            @endauth
        </div>
    </div>
</header>

<main class="max-w-6xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
    <section class="rounded-3xl p-6 sm:p-10 mb-8 text-white" style="background: linear-gradient(135deg, var(--brand-1), var(--brand-2));">
        <div class="max-w-3xl">
            <div class="inline-flex items-center gap-2 bg-white/15 px-3 py-1.5 rounded-full text-sm mb-4">
                <i class="fa-solid fa-compass"></i>
                Getting started
            </div>
            <h1 class="text-3xl sm:text-4xl font-black tracking-tight mb-4">Your guide to {{ $siteSettings->site_name }}</h1>
            <p class="text-white/90 leading-relaxed text-base sm:text-lg">
                Learn how to create an account, sign in securely, organise your day, manage your personal information and find the tools you need without having to guess where anything is.
            </p>
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-[260px_minmax(0,1fr)] gap-7 items-start">
        <aside class="lg:sticky lg:top-24 bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400 px-2 mb-2">In this guide</p>
            <nav class="space-y-1 text-sm">
                @foreach ([
                    ['start', 'fa-user-plus', 'Create an account'],
                    ['login', 'fa-right-to-bracket', 'Log in & OTP'],
                    ['password', 'fa-key', 'Forgot password'],
                    ['dashboard', 'fa-table-columns', 'Dashboard'],
                    ['planner', 'fa-calendar-check', 'Daily & Annual Plans'],
                    ['activity', 'fa-clock-rotate-left', 'Activity & Login Activity'],
                    ['profile', 'fa-user-gear', 'Profile & security'],
                    ['help', 'fa-circle-question', 'Getting help'],
                ] as [$id, $icon, $label])
                    <a href="#{{ $id }}" class="flex items-center gap-2 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-[var(--brand-1)]">
                        <i class="fa-solid {{ $icon }} w-4 text-center"></i>
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="space-y-6">
            <section id="start" class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm scroll-mt-28">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-3"><i class="fa-solid fa-user-plus text-[var(--brand-1)]"></i>Create an account</h2>
                <ol class="space-y-3 text-sm leading-relaxed text-slate-600 list-decimal pl-5">
                    <li>Open the registration page and enter your name, email address and password.</li>
                    <li>Accept the privacy terms after reviewing the Privacy Policy.</li>
                    <li>Submit the form and complete any email verification requested by the system.</li>
                    <li>After verification, sign in and complete your profile so the system can personalise your experience.</li>
                </ol>
            </section>

            <section id="login" class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm scroll-mt-28">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-3"><i class="fa-solid fa-right-to-bracket text-[var(--brand-1)]"></i>Log in and OTP verification</h2>
                <div class="space-y-3 text-sm leading-relaxed text-slate-600">
                    <p>Enter your registered email and password on the login page. When OTP protection is enabled, a six-digit verification code is sent to your email before the login is completed.</p>
                    <p>Enter the code on the OTP page before it expires. Use <strong>Resend code</strong> if you did not receive it, or <strong>Start over</strong> to return to login and use another account.</p>
                    <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-4 text-emerald-800">
                        <i class="fa-solid fa-shield-halved mr-2"></i>
                        Successful sign-ins are recorded in <strong>Activity → Login Activity</strong>, where you can review the date, device/browser and IP address used.
                    </div>
                </div>
            </section>

            <section id="password" class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm scroll-mt-28">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-3"><i class="fa-solid fa-key text-[var(--brand-1)]"></i>Forgot or reset your password</h2>
                <p class="text-sm leading-relaxed text-slate-600">Select <strong>Forgot password?</strong> on the login page, enter your registered email and follow the reset link sent to your inbox. Choose a password you do not reuse on other services.</p>
            </section>

            <section id="dashboard" class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm scroll-mt-28">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-3"><i class="fa-solid fa-table-columns text-[var(--brand-1)]"></i>Using the Dashboard</h2>
                <p class="text-sm leading-relaxed text-slate-600 mb-4">The Dashboard gives you a quick picture of the information that matters today. Use it to check financial summaries, upcoming reminders, plans, projects, meetings, wellness records and recent activity.</p>
                <p class="text-sm leading-relaxed text-slate-600">Use the sidebar to move between modules. On phones, open the menu using the navigation button at the top of the screen.</p>
            </section>

            <section id="planner" class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm scroll-mt-28">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-3"><i class="fa-solid fa-calendar-check text-[var(--brand-1)]"></i>Daily Planner and Annual Plans</h2>
                <div class="grid sm:grid-cols-2 gap-4 text-sm text-slate-600">
                    <div class="rounded-xl border border-slate-100 p-4">
                        <h3 class="font-bold text-slate-800 mb-2">Daily Planner</h3>
                        <p>Choose a date, add tasks with their times and priorities, mark work complete, save day notes and use Past Tasks to review previous days.</p>
                    </div>
                    <div class="rounded-xl border border-slate-100 p-4">
                        <h3 class="font-bold text-slate-800 mb-2">Annual Plans</h3>
                        <p>Set larger goals for the year, update progress as you work and mark goals complete when they reach 100%.</p>
                    </div>
                </div>
            </section>

            <section id="activity" class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm scroll-mt-28">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-3"><i class="fa-solid fa-clock-rotate-left text-[var(--brand-1)]"></i>Activity and Login Activity</h2>
                <div class="space-y-3 text-sm leading-relaxed text-slate-600">
                    <p><strong>Activity Log</strong> shows recent work across your modules such as expenses, income, plans, completed tasks, meetings and reminders.</p>
                    <p><strong>Login Activity</strong> is your security-focused history of successful sign-ins. Review it regularly and change your password if you notice a device, IP address or time you do not recognise.</p>
                </div>
            </section>

            <section id="profile" class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm scroll-mt-28">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-3"><i class="fa-solid fa-user-gear text-[var(--brand-1)]"></i>Profile and account security</h2>
                <ul class="space-y-2 text-sm leading-relaxed text-slate-600 list-disc pl-5">
                    <li>Update your profile name, email and photo from My Profile.</li>
                    <li>Change your password if you suspect someone else knows it.</li>
                    <li>Review Login Activity for unfamiliar sign-ins.</li>
                    <li>Use Privacy & Data options when you need to export or remove your information.</li>
                </ul>
            </section>

            <section id="help" class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm scroll-mt-28">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-3"><i class="fa-solid fa-circle-question text-[var(--brand-1)]"></i>Getting help</h2>
                <p class="text-sm leading-relaxed text-slate-600">If something does not behave as expected, first refresh the page and check your internet connection. Keep a screenshot of any error message and the page you were using so support can identify the problem more quickly.</p>
            </section>
        </div>
    </div>
</main>

<footer class="border-t border-slate-200 bg-white mt-10">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 flex flex-wrap gap-4 justify-between text-sm text-slate-500">
        <span>&copy; {{ now()->year }} {{ $siteSettings->site_name }}</span>
        <div class="flex gap-4">
            <a href="{{ route('privacy-policy') }}" class="hover:text-[var(--brand-1)]">Privacy Policy</a>
            <a href="{{ route('login') }}" class="hover:text-[var(--brand-1)]">Log in</a>
        </div>
    </div>
</footer>
</body>
</html>
