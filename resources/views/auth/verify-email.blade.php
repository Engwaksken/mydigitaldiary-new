<x-guest-layout>
    @php
        $verificationUser = auth()->user();
        $emailUpdateErrors = $errors->getBag('emailUpdate');
        $deletionErrors = $errors->getBag('userDeletion');
    @endphp

    <div class="space-y-1">
        <h1 class="text-lg font-semibold text-slate-800">Verify your email</h1>
        <p class="text-sm text-slate-500 leading-relaxed">
            We sent a verification link to
            <strong class="font-semibold text-slate-700 break-all" data-testid="verification-email">{{ $verificationUser->email }}</strong>.
            Open it to start using your diary. Check spam too, if it hasn't shown up.
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div role="status" class="mt-4 rounded-md bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            A new verification link has been sent to {{ $verificationUser->email }}.
        </div>
    @endif

    <x-input-error :messages="$errors->get('verification')" class="mt-4" />

    <form method="POST" action="{{ route('verification.send') }}" class="mt-6" id="verificationResendForm">
        @csrf

        <x-primary-button class="w-full justify-center gap-2" id="verificationResendButton">
            <i class="fa-solid fa-paper-plane text-xs" aria-hidden="true"></i>
            Resend verification email
        </x-primary-button>
    </form>

    <details class="mt-6 rounded-lg border border-slate-200 px-4 py-3" @if ($emailUpdateErrors->any()) open @endif>
        <summary class="cursor-pointer text-sm font-medium text-slate-700">Wrong email address?</summary>

        <form method="POST" action="{{ route('verification.email.update') }}" class="mt-4">
            @csrf
            @method('PUT')

            <x-input-label for="verification-email-update" value="Email" />
            <input id="verification-email-update" name="email" type="email" required autocomplete="email"
                   value="{{ old('email') }}" placeholder="you@example.com"
                   class="pm-input block mt-1 w-full"
                   @if ($emailUpdateErrors->has('email')) aria-invalid="true" aria-describedby="verification-email-update-error" @endif>
            <x-input-error :messages="$emailUpdateErrors->get('email')" id="verification-email-update-error" class="mt-2" />

            <x-primary-button class="w-full mt-4 justify-center gap-2">
                <i class="fa-solid fa-envelope text-xs" aria-hidden="true"></i>
                Update email and send link
            </x-primary-button>
        </form>
    </details>

    <details class="mt-3 rounded-lg border border-slate-200 px-4 py-3" @if ($deletionErrors->any()) open @endif>
        <summary class="cursor-pointer text-sm font-medium text-slate-700">Delete this account</summary>

        <form method="POST" action="{{ route('profile.destroy') }}" class="mt-4">
            @csrf
            @method('DELETE')

            <x-input-label for="verification-delete-password" value="Password" />
            <input id="verification-delete-password" name="password" type="password" required autocomplete="current-password"
                   placeholder="Your password to confirm"
                   class="pm-input block mt-1 w-full"
                   @if ($deletionErrors->has('password')) aria-invalid="true" aria-describedby="verification-delete-password-error" @endif>
            <x-input-error :messages="$deletionErrors->get('password')" id="verification-delete-password-error" class="mt-2" />

            <x-danger-button class="w-full mt-4 justify-center">
                Delete account permanently
            </x-danger-button>
        </form>
    </details>

    <div class="mt-6 text-center">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="auth-link-muted text-sm">Log out</button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('verificationResendForm');
            const button = document.getElementById('verificationResendButton');

            if (!form || !button) return;

            // Stops a double-click sending two emails; the server also
            // rate-limits (one per minute per account, 6 per minute per route).
            form.addEventListener('submit', function () {
                if (button.disabled) return;
                button.disabled = true;
                button.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs" aria-hidden="true"></i> Sending...';
            });
        });
    </script>
</x-guest-layout>
