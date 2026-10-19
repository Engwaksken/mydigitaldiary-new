<x-guest-layout>
    <div class="text-center space-y-3">
        <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
            <x-icon name="circle-check" class="text-2xl" />
        </span>

        <h1 class="text-lg font-semibold text-slate-800">Email verified</h1>

        <p class="text-sm text-slate-500 leading-relaxed">
            Thanks, <strong class="font-semibold text-slate-700 break-all">{{ $email }}</strong> is confirmed.
            You can now return to the {{ config('app.name', 'My Digital Diary') }} app and carry on, or sign in here on the web.
        </p>
    </div>

    <div class="mt-6">
        <a href="{{ route('login') }}" class="btn-primary inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white">
            <x-icon name="right-to-bracket" class="text-xs" />
            Sign in on the web
        </a>
    </div>
</x-guest-layout>
