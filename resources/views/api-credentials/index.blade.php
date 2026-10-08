@extends('layouts.app')

@section('title', 'AI API Keys')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shadow-sm shrink-0">
                <i class="fa-solid fa-key text-xl" aria-hidden="true"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">AI API Keys</h1>
        </div>
        <button type="button" onclick="document.getElementById('api-credential-modal').showModal()"
                class="inline-flex items-center justify-center gap-2 btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            <span>Add API Key</span>
        </button>
    </div>

    <p class="text-sm text-slate-500 mb-4">
        Bring your own Anthropic or OpenAI API key to power the <a href="{{ route('ai-plans.index') }}" class="text-[var(--brand-1)] hover:underline">AI Planner</a>.
        Keys are encrypted at rest and only the last 4 characters are ever shown again.
    </p>

    @if ($hasOwnActiveKey)
        <x-alert type="success" :dismissible="false" :autoDismiss="false" class="mb-4">
            You're using your own API key — unlimited AI plan generations.
        </x-alert>
    @elseif ($settings->hasDefaultAiKey())
        <x-alert type="warning" :dismissible="false" :autoDismiss="false" class="mb-4">
            <span class="flex items-center justify-between gap-3 flex-wrap">
                <span>
                Using the free shared plan — {{ $sharedUsedThisMonth }} of {{ $settings->default_ai_free_limit_per_month }} used this month.
                </span>
                <button type="button" onclick="document.getElementById('api-credential-modal').showModal()"
                        class="text-xs font-medium underline hover:no-underline">
                    Add your own key for unlimited use &rarr;
                </button>
            </span>
        </x-alert>
    @else
        <x-alert type="info" :dismissible="false" :autoDismiss="false" class="mb-4">
            No free shared plan is configured — add your own API key below to use the AI Planner.
        </x-alert>
    @endif

    <div class="pm-dt-wrap" role="region" aria-label="API keys table">
        <table class="pm-dt">
            <caption class="sr-only">Your saved AI provider API keys, with activate and delete actions for each.</caption>
            <thead>
                <tr>
                    <th scope="col">Key</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="pm-dt-actions"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($credentials as $credential)
                    <tr>
                        <td class="pm-dt-main">
                            <span class="pm-dt-title" title="{{ $credential->label }}">{{ $credential->label }}</span>
                            <span class="pm-dt-sub">
                                <span>{{ $providers->firstWhere('key', $credential->provider)?->name ?? $credential->provider }}</span>
                                <span class="font-mono">•••• {{ substr($credential->api_key, -4) }}</span>
                            </span>
                        </td>
                        <td class="pm-dt-aux">
                            @if ($credential->is_active)
                                <span class="pm-dt-pill is-green">Active</span>
                            @else
                                <span class="pm-dt-pill is-slate">Inactive</span>
                            @endif
                        </td>
                        <td class="pm-dt-actions">
                            <div class="inline-flex items-center gap-1">
                                @unless ($credential->is_active)
                                    <form action="{{ route('api-credentials.activate', $credential->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="pm-dt-icon-btn text-[var(--brand-1)]" title="Activate">
                                            <i class="fa-solid fa-power-off text-xs" aria-hidden="true"></i>
                                            <span class="sr-only">Activate {{ $credential->label }}</span>
                                        </button>
                                    </form>
                                @endunless
                                <button type="button"
                                        onclick="pmOpenApiCredentialDeleteModal({{ json_encode(route('api-credentials.destroy', $credential->id)) }}, {{ json_encode($credential->label) }})"
                                        class="pm-dt-icon-btn text-rose-500" title="Delete">
                                    <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
                                    <span class="sr-only">Delete {{ $credential->label }}</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="pm-dt-empty"><td colspan="3"><x-empty-state icon="fa-solid fa-key" title="No API keys yet" message="Add one to enable the AI Planner." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($credentials instanceof \Illuminate\Contracts\Pagination\Paginator && $credentials->hasPages())
        <div class="mt-4">{{ $credentials->withQueryString()->links() }}</div>
    @endif

    {{-- Add API Key modal — replaces the old full-page /create navigation.
         The dedicated route/view still exist for direct-URL access, same
         progressive-enhancement pattern as every other module. --}}
    <dialog id="api-credential-modal" aria-labelledby="api-credential-modal-title" class="rounded-2xl p-0 pm-dialog-lg shadow-2xl backdrop:bg-slate-900/50">
        <form method="POST" action="{{ route('api-credentials.store') }}" class="p-6 space-y-5">
            @csrf

            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-key text-sm" aria-hidden="true"></i>
                    </div>
                    <h2 id="api-credential-modal-title" class="text-lg font-bold text-slate-800">Add API Key</h2>
                </div>
                <button type="button" onclick="document.getElementById('api-credential-modal').close()"
                        class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" aria-label="Close dialog">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

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

            <div class="flex items-center gap-3 pt-2 border-t border-slate-100 mt-2">
                <button type="submit" class="inline-flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    <span>Save</span>
                </button>
                <button type="button" onclick="document.getElementById('api-credential-modal').close()" class="text-sm text-slate-500 hover:text-slate-700 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </dialog>

    {{-- Shared delete-confirmation modal, same pattern as crud/index.blade.php. --}}
    <dialog id="api-credential-delete-modal" aria-labelledby="api-credential-delete-title" class="rounded-2xl p-6 pm-dialog-sm shadow-2xl backdrop:bg-slate-900/50">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            </div>
            <h2 id="api-credential-delete-title" class="text-lg font-bold text-slate-800">Remove API key?</h2>
        </div>
        <p id="api-credential-delete-desc" class="text-sm text-slate-600 mb-5">This action cannot be undone.</p>
        <form method="POST" id="api-credential-delete-form">
            @csrf
            @method('DELETE')
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('api-credential-delete-modal').close()" class="text-sm text-slate-500 hover:text-slate-700 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="inline-flex items-center gap-2 bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                    <span>Remove</span>
                </button>
            </div>
        </form>
    </dialog>

    <script>
        function pmOpenApiCredentialDeleteModal(actionUrl, label) {
            var dialog = document.getElementById('api-credential-delete-modal');
            document.getElementById('api-credential-delete-form').action = actionUrl;
            document.getElementById('api-credential-delete-desc').textContent =
                'Remove "' + label + '"? This action cannot be undone.';
            dialog.showModal();
        }

        // Reopen the Add API Key modal automatically if this page just
        // reloaded after a failed validation on it (old() input present),
        // same pattern as the generic crud modal.
        document.addEventListener('DOMContentLoaded', function () {
            @if ($errors->any() && (old('label') !== null || old('provider') !== null))
                document.getElementById('api-credential-modal').showModal();
            @endif
        });
    </script>
@endsection
