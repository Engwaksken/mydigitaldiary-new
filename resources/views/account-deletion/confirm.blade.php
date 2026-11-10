<x-guest-layout>
    <h1 class="text-xl font-bold text-slate-900 mb-3">Delete your account</h1>

    @if ($user->scheduled_deletion_at)
        <p class="text-sm text-slate-600 mb-4">
            The account for {{ $user->email }} is scheduled for permanent deletion on
            <strong>{{ $user->scheduled_deletion_at->format('j F Y') }}</strong>.
        </p>
        <p class="text-sm text-slate-600">
            Changed your mind? Sign in before that date and open <strong>Privacy &amp; Data</strong> to cancel the deletion.
        </p>
    @else
        <p class="text-sm text-slate-600 mb-4">
            Permanently delete the account for <strong>{{ $user->email }}</strong> and all of its data?
            Deletion happens 30 days after you confirm, and you can cancel during that time by signing in.
        </p>

        <form method="POST" action="{{ $actionUrl }}" class="space-y-3">
            @csrf
            <div>
                <label for="reason" class="block text-sm font-medium text-slate-700 mb-1">Why are you leaving? (optional)</label>
                <textarea id="reason" name="reason" rows="3" maxlength="2000"
                          class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('reason') }}</textarea>
            </div>
            <label class="flex items-start gap-2 text-sm text-slate-700">
                <input type="checkbox" name="confirm_delete" value="1" required class="mt-1">
                <span>I understand my account and all of its data will be permanently deleted.</span>
            </label>
            @error('confirm_delete')
                <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold">
                Delete my account
            </button>
        </form>
    @endif
</x-guest-layout>
