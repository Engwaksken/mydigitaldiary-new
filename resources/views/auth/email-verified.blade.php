<x-guest-layout>
    <div class="text-center space-y-3">
        <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
            <i class="fa-solid fa-circle-check text-2xl" aria-hidden="true"></i>
        </span>

        <h1 class="text-lg font-semibold text-slate-800">Email verified</h1>

        <p class="text-sm text-slate-500 leading-relaxed">
            Thanks, <strong class="font-semibold text-slate-700 break-all">{{ $email }}</strong> is confirmed.
            You can now return to the {{ config('app.name', 'My Digital Diary') }} app and carry on, or sign in here on the web.
        </p>
    </div>

    <div class="mt-6">
        <a href="{{ route('login') }}" class="btn-primary inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white">
            <i class="fa-solid fa-right-to-bracket text-xs" aria-hidden="true"></i>
            Sign in on the web
        </a>
    </div>
</x-guest-layout>
