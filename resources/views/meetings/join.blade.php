@extends('layouts.app')

@section('title', 'Meeting: ' . $meeting->title)

@section('content')
    {{-- A <section>, not a <main>: layouts/app.blade.php already owns the
         page's only <main> landmark, and two of them is a landmark-structure
         failure (WCAG 1.3.1). Classes and spacing are unchanged; public/
         css/responsive.css matches `section` and `main > *` with the same
         declarations, so mobile rendering is identical. --}}
    <section class="max-w-2xl mx-auto px-4 py-8" aria-label="Meeting details">
        <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 shadow-sm">
            <p class="text-sm font-semibold text-emerald-700">My Digital Diary meeting</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ $meeting->title }}</h1>
            <p class="mt-3 text-slate-700">
                {{ $meeting->start_at?->format('D, M j, Y · g:i A') }}
                @if ($meeting->end_at)
                    – {{ $meeting->end_at->format('g:i A') }}
                @endif
            </p>
            @if ($meeting->location)
                <p class="mt-2 text-slate-600">{{ $meeting->location }}</p>
            @endif
            @if ($meeting->notes)
                <div class="mt-4 whitespace-pre-line text-slate-700">{{ $meeting->notes }}</div>
            @endif
            @if (!empty($externalUrl))
                <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-slate-700">
                    <h2 class="font-semibold text-slate-900">You’re leaving My Digital Diary</h2>
                    <p class="mt-1 text-sm">This meeting is hosted at <strong>{{ parse_url($externalUrl, PHP_URL_HOST) }}</strong>.</p>
                    <p class="mt-1 break-all text-sm text-slate-600">{{ $externalUrl }}</p>
                    <a href="{{ $externalUrl }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white hover:bg-emerald-700">
                        Continue to external meeting
                    </a>
                </div>
            @endif
            <a href="{{ route('meetings.index') }}" class="mt-6 inline-flex rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white hover:bg-emerald-700">
                Open my meetings
            </a>
        </section>
    </section>
@endsection
