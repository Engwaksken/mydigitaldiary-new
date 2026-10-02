@extends('layouts.app')

@section('title', 'Add API Key')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-key text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Add API Key</h1>
    </div>

    <form method="POST" action="{{ route('api-credentials.store') }}"
          class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 max-w-lg space-y-5">
        @csrf

        <div>
            <label for="label" class="block text-sm font-medium text-slate-700 mb-1">Label</label>
            <input type="text" id="label" name="label" value="{{ old('label') }}" placeholder="e.g. My Claude key"
                   required aria-required="true"
                   @error('label') aria-invalid="true" aria-describedby="label-error" @enderror
                   class="pm-input">
            @error('label')
                <p id="label-error" role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="provider" class="block text-sm font-medium text-slate-700 mb-1">Provider</label>
            <select id="provider" name="provider" class="pm-input">
                @foreach ($providers as $provider)
                    <option value="{{ $provider->key }}" @selected(old('provider', $providers->first()?->key) === $provider->key)>{{ $provider->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="api_key" class="block text-sm font-medium text-slate-700 mb-1">API Key</label>
            <input type="password" id="api_key" name="api_key" placeholder="sk-..."
                   required aria-required="true" autocomplete="off"
                    @error('api_key') aria-describedby="api_key-error" @enderror
                   @error('api_key') aria-invalid="true" @enderror
                   class="pm-input">
            @error('api_key')
                <p id="api_key-error" role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                Save
            </button>
            <a href="{{ route('api-credentials.index') }}" class="text-sm text-slate-500 hover:underline">Cancel</a>
        </div>
    </form>
@endsection
