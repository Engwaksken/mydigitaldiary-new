<x-guest-layout>
    <h1 class="text-lg font-semibold text-slate-800 mb-1">Create an account</h1>
    <p class="text-sm text-slate-500 mb-6">Takes about a minute.</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <x-auth-field name="name" label="Name" type="text" placeholder="e.g. Jane Doe" required autofocus autocomplete="name" />

        <div class="mt-4">
            <x-auth-field name="email" label="Email" type="email" placeholder="you@example.com" required autocomplete="username" />
        </div>

        <div class="mt-4">
            <x-auth-field name="password" label="Password" type="password" placeholder="At least 8 characters" required autocomplete="new-password" />
        </div>

        <div class="mt-4">
            <x-auth-field name="password_confirmation" label="Confirm Password" type="password" placeholder="Type it again" required autocomplete="new-password" />
        </div>

        {{-- Required, unticked by default this app stores health/financial/relationship data, so consent isn't assumed. --}}
        <div class="mt-4">
            <div class="flex items-start gap-2">
                <input id="data_consent" name="data_consent" type="checkbox" value="1" required aria-required="true" aria-describedby="data_consent-hint"
                       class="mt-1 rounded border-gray-300 text-[var(--brand-1)] shadow-sm focus:ring-2 focus:ring-[var(--brand-2)] focus:ring-offset-2">
                <label for="data_consent" class="text-sm text-gray-600">
                    I agree to the
                    <a href="{{ Route::has('terms-of-use') ? route('terms-of-use') : url('/terms-of-use') }}" target="_blank" rel="noopener noreferrer" class="auth-link">Terms of Use<span class="sr-only"> (opens in a new tab)</span></a>
                    and consent to the storage and processing of my data as described in the
                    <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener noreferrer" class="auth-link">Privacy Policy<span class="sr-only"> (opens in a new tab)</span></a>.
                    I can manage exports and account deletion from Privacy &amp; Data after registration.
                </label>
            </div>
            <p id="data_consent-hint" class="sr-only">This checkbox is required to create an account.</p>
            <x-input-error :messages="$errors->get('data_consent')" class="mt-2" />
        </div>

        <x-primary-button class="w-full mt-6 justify-center gap-2"><i class="fa-solid fa-user-plus text-xs" aria-hidden="true"></i> Register</x-primary-button>
    </form>

    <p class="text-sm text-slate-600 text-center mt-6">
        Already have an account?
        <a href="{{ route('login') }}" class="auth-link font-medium">Log in</a>
    </p>

    @include('partials.auth-app-download')
</x-guest-layout>
