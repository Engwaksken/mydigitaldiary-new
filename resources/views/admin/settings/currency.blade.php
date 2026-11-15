@extends('layouts.app')

@section('title', 'Currency Settings')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Currency Settings</h1>
        <p class="text-sm text-slate-500 mt-1">
            Set the base currency, default display currency and live-rate refresh interval.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.settings.currency.update') }}"
          class="pm-card-bg rounded-xl border border-slate-100 shadow-sm p-5 space-y-5">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-sm font-semibold text-slate-700">Base Currency</label>
                <select name="base_currency" class="pm-input mt-1">
                    @foreach($currencies as $code => $meta)
                        <option value="{{ $code }}" @selected(old('base_currency', $settings->base_currency) === $code)>
                            {{ $code }} - {{ $meta['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-sm font-semibold text-slate-700">Default Display Currency</label>
                <select name="display_currency" class="pm-input mt-1">
                    @foreach($currencies as $code => $meta)
                        <option value="{{ $code }}" @selected(old('display_currency', $settings->display_currency) === $code)>
                            {{ $code }} - {{ $meta['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-sm font-semibold text-slate-700">Rate Refresh</label>
                <select name="refresh_minutes" class="pm-input mt-1">
                    @foreach([15,30,60,120,360,720,1440] as $minutes)
                        <option value="{{ $minutes }}" @selected((int) old('refresh_minutes', $settings->refresh_minutes) === $minutes)>
                            {{ $minutes }} minutes
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4">
            <input type="checkbox" name="allow_user_selection" value="1"
                   @checked(old('allow_user_selection', $settings->allow_user_selection))>
            <span>
                <span class="block text-sm font-semibold text-slate-700">Allow users to choose their display currency</span>
                <span class="block text-xs text-slate-500 mt-1">
                    Mobile users can select USD, UGX or another supported currency.
                </span>
            </span>
        </label>

        <div class="flex justify-end">
            <button class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-semibold">
                Save Currency Settings
            </button>
        </div>
    </form>
</div>
@endsection
