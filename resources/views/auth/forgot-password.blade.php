<x-guest-layout>
    <h1 class="text-lg font-semibold text-slate-800 mb-1">Forgot your password?</h1>
    <p class="text-sm text-slate-500 mb-6">Enter your email and we'll send you a reset link.</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <x-auth-field name="email" label="Email" type="email" placeholder="you@example.com" required autofocus />

        <x-primary-button class="w-full mt-6 justify-center gap-2"><i class="fa-solid fa-paper-plane text-xs" aria-hidden="true"></i> Email Reset Link</x-primary-button>
    </form>

    <p class="text-sm text-center mt-6">
        <a href="{{ route('login') }}" class="auth-link-muted">Back to login</a>
    </p>

    @include('partials.auth-app-download')
</x-guest-layout>
