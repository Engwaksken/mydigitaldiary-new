@extends('layouts.app')

@section('title', 'Feedback')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-comment-dots text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Feedback</h1>
    </div>

    <div class="flex gap-2 mb-4 text-sm">
        <a href="{{ route('admin.feedback.index') }}"
           class="px-3 py-1 rounded-md {{ !$status ? 'bg-[var(--brand-1)] text-white' : 'bg-white border border-slate-300 text-slate-600' }}">
            All
        </a>
        @foreach (['new' => 'New', 'reviewed' => 'Reviewed', 'resolved' => 'Resolved'] as $value => $label)
            <a href="{{ route('admin.feedback.index', ['status' => $value]) }}"
               class="px-3 py-1 rounded-md {{ $status === $value ? 'bg-[var(--brand-1)] text-white' : 'bg-white border border-slate-300 text-slate-600' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="space-y-4">
        @forelse ($items as $item)
            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-3">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs uppercase tracking-wide font-semibold text-slate-400">
                                {{ ucwords(str_replace('_', ' ', $item->category)) }}
                            </span>
                            @if ($item->rating)
                                <span class="text-amber-500 text-xs" aria-label="Rated {{ $item->rating }} out of 5">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <i class="fa-solid fa-star{{ $i > $item->rating ? '-o' : '' }}" aria-hidden="true"></i>
                                    @endfor
                                </span>
                            @endif
                        </div>
                        <h2 class="font-semibold text-slate-800">{{ $item->subject }}</h2>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ $item->user->name ?? 'Unknown user' }} ({{ $item->user->email ?? '-' }}) &middot;
                            {{ $item->created_at->format('Y-m-d H:i') }}
                        </p>
                    </div>
                    @php
                        $badgeColor = match ($item->status) {
                            'resolved' => 'bg-emerald-100 text-emerald-700',
                            'reviewed' => 'bg-amber-100 text-amber-700',
                            default => 'bg-slate-100 text-slate-600',
                        };
                    @endphp
                    <span class="text-xs font-medium px-2.5 py-1 rounded-full whitespace-nowrap {{ $badgeColor }}">
                        {{ ucfirst($item->status) }}
                    </span>
                </div>

                <p class="text-sm text-slate-600 whitespace-pre-line mb-4">{{ $item->message }}</p>

                <form method="POST" action="{{ route('admin.feedback.update', $item->id) }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                    @csrf
                    <div class="flex-1">
                        <label for="admin_notes-{{ $item->id }}" class="block text-xs font-medium text-slate-500 mb-1">Admin notes (internal only)</label>
                        <input type="text" id="admin_notes-{{ $item->id }}" name="admin_notes" value="{{ $item->admin_notes }}"
                               class="pm-input text-sm">
                    </div>
                    <div>
                        <label for="status-{{ $item->id }}" class="block text-xs font-medium text-slate-500 mb-1">Status</label>
                        <select id="status-{{ $item->id }}" name="status"
                                class="pm-input text-sm">
                            <option value="new" @selected($item->status === 'new')>New</option>
                            <option value="reviewed" @selected($item->status === 'reviewed')>Reviewed</option>
                            <option value="resolved" @selected($item->status === 'resolved')>Resolved</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                            Save
                        </button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.feedback.destroy', $item->id) }}" class="mt-2"
                      data-confirm="Remove this feedback? This action cannot be undone." data-confirm-title="Delete feedback?" data-confirm-text="Delete">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-xs text-rose-500 hover:underline">Remove</button>
                </form>
            </div>
        @empty
            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-8 text-center text-slate-400">
                No feedback submitted yet.
            </div>
        @endforelse
    </div>

    <nav aria-label="Pagination" class="mt-4">
        {{ $items->links() }}
    </nav>
@endsection
