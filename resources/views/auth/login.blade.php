<x-guest-layout>
    <h1 class="text-lg font-semibold text-slate-800 mb-1">Log in</h1>
    <p class="text-sm text-slate-500 mb-6">Welcome back enter your details below.</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <x-auth-field name="email" label="Email" type="email" placeholder="you@example.com" required autofocus autocomplete="username" />

        <div class="mt-4">
            <x-auth-field name="password" label="Password" type="password" placeholder="Your password" required autocomplete="current-password" />
        </div>

        <div class="flex items-center justify-between mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <x-checkbox id="remember_me" name="remember" />
                <span class="ms-2 text-sm text-slate-600">Remember me</span>
            </label>

            @if ($canResetPassword)
                <a href="{{ route('password.request') }}" class="auth-link text-sm">Forgot password?</a>
            @endif
        </div>

        <x-primary-button class="w-full mt-6 justify-center gap-2"><i class="fa-solid fa-right-to-bracket text-xs" aria-hidden="true"></i> Log in</x-primary-button>
    </form>

    <p class="text-sm text-slate-600 text-center mt-6">
        Don't have an account?
        <a href="{{ route('register') }}" class="auth-link font-medium">Sign up</a>
    </p>

    @include('partials.auth-app-download')
</x-guest-layout>
