<x-guest-layout>
    <h1 class="text-xl font-bold text-slate-900 mb-3">Weekly appreciation emails</h1>

    @if ($unsubscribed)
        <p class="text-sm text-slate-600 mb-4">
            You have been unsubscribed. {{ $user->email }} will no longer receive the weekly appreciation email.
        </p>
        <p class="text-sm text-slate-600">
            Changed your mind? You can switch it back on any time under
            <a href="{{ route('profile.edit') }}" class="font-semibold text-[var(--brand-1)] underline">Profile &rsaquo; Personalisation</a>.
        </p>
    @else
        <p class="text-sm text-slate-600 mb-4">
            Stop sending the weekly appreciation email to {{ $user->email }}? Other reminders and account emails are not affected.
        </p>
        <form method="POST" action="{{ $actionUrl }}">
            @csrf
            <button type="submit" class="btn-primary text-white px-5 py-2.5 rounded-xl text-sm font-semibold">Unsubscribe</button>
        </form>
    @endif
</x-guest-layout>
