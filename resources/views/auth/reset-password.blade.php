{{--
    Note: posts to route('password.store') — current Breeze's name for
    this submission. If your routes/auth.php still has the older
    password.update name, change the form action below to match.
--}}
<x-guest-layout>
    <h1 class="text-lg font-semibold text-slate-800 mb-1">Choose a new password</h1>
    <p class="text-sm text-slate-500 mb-6">Pick something you haven't used here before.</p>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-auth-field name="email" label="Email" type="email" placeholder="you@example.com" :value="old('email', $request->email)" required autofocus autocomplete="username" />

        <div class="mt-4">
            <x-auth-field name="password" label="New Password" type="password" placeholder="At least 8 characters" required autocomplete="new-password" />
        </div>

        <div class="mt-4">
            <x-auth-field name="password_confirmation" label="Confirm New Password" type="password" placeholder="Type it again" required autocomplete="new-password" />
        </div>

        <x-primary-button class="w-full mt-6 justify-center gap-2"><x-icon name="key" class="text-xs" /> Reset Password</x-primary-button>
    </form>
</x-guest-layout>
