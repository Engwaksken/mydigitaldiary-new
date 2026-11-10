<x-guest-layout>
    <h1 class="text-xl font-bold text-slate-900 mb-3">Delete your account</h1>

    <p class="text-sm text-slate-600 mb-4">
        You can ask us to delete your {{ $siteSettings->site_name ?? config('app.name') }} account and all of its data at any time.
    </p>

    <div class="text-sm text-slate-600 mb-5 space-y-2">
        <p class="font-semibold text-slate-800">What gets deleted</p>
        <p>
            Your profile and everything you created: diary entries, notes, plans, goals, tasks, reminders,
            meetings and recordings, financial records, files and settings.
        </p>
        <p>
            Deletion happens <strong>30 days</strong> after you confirm. During that time you can cancel by signing in
            and opening <strong>Privacy &amp; Data</strong>. Payment records we are required by law to keep may be
            retained after deletion.
        </p>
    </div>

    <div class="text-sm text-slate-600 mb-5 space-y-1">
        <p class="font-semibold text-slate-800">Delete from the app or website</p>
        <p>Sign in, open the menu, choose <strong>Privacy &amp; Data</strong>, then <strong>Delete My Account</strong>.</p>
        @auth
            <p><a href="{{ route('privacy.show') }}" class="font-semibold text-[var(--brand-1)] underline">Go to Privacy &amp; Data</a></p>
        @endauth
    </div>

    <p class="font-semibold text-slate-800 text-sm mb-2">Can't sign in? Request deletion by email</p>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('account-deletion.send') }}" class="space-y-3">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Account email</label>
            <input id="email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}"
                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            @error('email')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="btn-primary text-white px-5 py-2.5 rounded-xl text-sm font-semibold">
            Email me a confirmation link
        </button>
    </form>

    <p class="text-xs text-slate-500 mt-5">
        Read our <a href="{{ route('privacy-policy') }}" class="underline">Privacy Policy</a> to learn how we handle your data.
    </p>
</x-guest-layout>
