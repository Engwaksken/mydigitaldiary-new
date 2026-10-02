{{--
    Full-page create/edit fallback. The index page opens a modal for this
    by default now, but this route (GET .../create, GET .../{id}/edit)
    still works if linked to directly — progressive enhancement, not a
    replacement.
--}}
@extends('layouts.app')

@section('title', ($item->exists ? 'Edit' : 'New') . ' ' . $title)

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-{{ $accent ?? 'indigo' }}-100 text-{{ $accent ?? 'indigo' }}-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="{{ $icon ?? 'fa-solid fa-table-list' }} text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="min-w-0 text-xl sm:text-2xl font-bold text-slate-800 tracking-tight">{{ $item->exists ? 'Edit' : 'New' }} {{ $title }}</h1>
    </div>

    <form method="POST"
          action="{{ $item->exists ? route($routeName . '.update', $item->id) : route($routeName . '.store') }}"
          class="pm-card-bg w-full max-w-xl mx-auto rounded-xl shadow-sm border border-slate-100 p-4 sm:p-6 space-y-5">
        @csrf
        @if ($item->exists)
            @method('PUT')
        @endif

        @include('crud._fields', ['fields' => $fields, 'item' => $item])

        <div class="flex flex-col-reverse items-stretch gap-3 pt-4 border-t border-slate-100 sm:flex-row sm:items-center">
            <button type="submit" class="inline-flex justify-center items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all sm:order-1">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <span>Save</span>
            </button>
            <a href="{{ route($routeName . '.index') }}" class="inline-flex justify-center px-5 py-2.5 text-sm text-slate-500 hover:text-slate-700 transition-colors sm:order-2">Cancel</a>
        </div>
    </form>
@endsection
