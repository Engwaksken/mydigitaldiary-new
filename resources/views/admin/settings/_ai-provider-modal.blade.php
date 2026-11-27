{{--
    Shared markup for both the "Add Provider" modal and each custom
    provider's own edit modal. Built-ins never get an edit modal at all
    (see edit.blade.php's @foreach ...->reject->isBuiltIn()).

    Uses the standard pm-modal header/body/footer structure: the global
    modal-responsive.css strips padding from a bare `dialog > form`, so a
    form without these wrappers renders edge to edge.
--}}
@php
    // Only show old input/errors in the modal that was actually submitted.
    $isSubmitted = old('modal_id') === $modalId;
    $value = fn (string $field) => $isSubmitted ? old($field, $provider->{$field}) : $provider->{$field};
    $fieldError = fn (string $field) => $isSubmitted ? $errors->first($field) : null;
    $reopen = $isSubmitted && $errors->any();
    $witu = \App\Services\Ai\ProviderEndpoint::class;
@endphp

<dialog id="{{ $modalId }}"
        aria-labelledby="{{ $modalId }}-title"
        class="pm-modal-shell pm-dialog"
        data-close-on-backdrop="true"
        @if ($reopen) data-initial-show="true" @endif>
    <form method="POST" action="{{ $action }}" class="pm-modal-content">
        @csrf
        @if ($method)
            @method($method)
        @endif
        <input type="hidden" name="modal_id" value="{{ $modalId }}">

        <header class="pm-modal-header">
            <div class="pm-modal-heading">
                <div class="pm-modal-icon"><i class="fa-solid fa-robot" aria-hidden="true"></i></div>
                <div class="pm-modal-title-wrap">
                    <h2 id="{{ $modalId }}-title" class="pm-modal-title">{{ $provider->exists ? 'Edit' : 'Add' }} AI Provider</h2>
                    <p class="pm-modal-description">Any OpenAI-compatible chat-completions service.</p>
                </div>
            </div>
            <button type="button" class="pm-modal-close" data-modal-close aria-label="Close dialog">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>

        <div class="pm-modal-body space-y-4">
            @unless ($provider->exists)
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <span class="text-slate-600">Connecting WITU AI? Fill in its settings for you.</span>
                    <button type="button"
                            class="text-sm font-semibold text-[var(--brand-1)] hover:underline"
                            data-witu-preset
                            data-url="{{ $witu::WITU_BASE_URL }}">
                        Use WITU AI preset
                    </button>
                </div>
            @endunless

            @if ($isSubmitted && $errors->has('provider'))
                <p role="alert" class="text-sm text-rose-600">{{ $errors->first('provider') }}</p>
            @endif

            <div>
                <label for="name-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Display Name</label>
                <input type="text" id="name-{{ $modalId }}" name="name" value="{{ $value('name') }}"
                       placeholder="e.g. WITU AI, Groq, Mistral" required aria-required="true"
                       @if ($fieldError('name')) aria-invalid="true" @endif
                       class="pm-input">
                @if ($fieldError('name'))<p class="text-sm text-rose-600 mt-1">{{ $fieldError('name') }}</p>@endif
            </div>

            <div>
                <label for="key-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Key</label>
                <input type="text" id="key-{{ $modalId }}" name="key" value="{{ $value('key') }}"
                       placeholder="e.g. witu" required aria-required="true"
                       @if ($fieldError('key')) aria-invalid="true" @endif
                       class="pm-input font-mono text-sm"
                       {{ $provider->exists ? 'readonly' : '' }}>
                @if ($fieldError('key'))<p class="text-sm text-rose-600 mt-1">{{ $fieldError('key') }}</p>@endif
                <p class="text-xs text-slate-400 mt-1">Letters, numbers, dashes, underscores only. Can't be changed once set.</p>
            </div>

            <div>
                <label for="api_base_url-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">API Base URL</label>
                <input type="url" id="api_base_url-{{ $modalId }}" name="api_base_url" value="{{ $value('api_base_url') }}"
                       placeholder="https://api.groq.com/openai/v1" required aria-required="true"
                       @if ($fieldError('api_base_url')) aria-invalid="true" @endif
                       class="pm-input">
                @if ($fieldError('api_base_url'))<p class="text-sm text-rose-600 mt-1">{{ $fieldError('api_base_url') }}</p>@endif
                <p class="text-xs text-slate-400 mt-1">
                    A base URL ending in /v1, or the full chat-completions endpoint.
                    For WITU AI use {{ $witu::WITU_BASE_URL }}.
                </p>
            </div>

            <div>
                <label for="default_model-{{ $modalId }}" class="block text-sm font-medium text-slate-700 mb-1">Model Name</label>
                <input type="text" id="default_model-{{ $modalId }}" name="default_model" value="{{ $value('default_model') }}"
                       placeholder="e.g. qwen2.5:1.5b" required aria-required="true"
                       @if ($fieldError('default_model')) aria-invalid="true" @endif
                       class="pm-input">
                @if ($fieldError('default_model'))<p class="text-sm text-rose-600 mt-1">{{ $fieldError('default_model') }}</p>@endif
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" id="is_enabled-{{ $modalId }}" name="is_enabled" value="1"
                       @checked($isSubmitted ? old('is_enabled') : ($provider->exists ? $provider->is_enabled : true))
                       class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                <label for="is_enabled-{{ $modalId }}" class="text-sm text-slate-700">Enabled (selectable by users)</label>
            </div>
        </div>

        <footer class="pm-modal-footer">
            <button type="button" data-modal-close class="text-sm text-slate-500 hover:text-slate-700 px-4 py-2.5">
                Cancel
            </button>
            <button type="submit" class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                Save
            </button>
        </footer>
    </form>
</dialog>

<script>
    (function () {
        var dialog = document.getElementById(@json($modalId));
        if (!dialog) return;

        dialog.querySelectorAll('[data-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () { dialog.close(); });
        });

        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) dialog.close();
        });

        var preset = dialog.querySelector('[data-witu-preset]');
        if (preset) {
            preset.addEventListener('click', function () {
                var set = function (name, value) {
                    var field = dialog.querySelector('[name="' + name + '"]');
                    if (field && !field.readOnly) field.value = value;
                };
                set('name', 'WITU AI');
                set('key', 'witu');
                set('api_base_url', preset.dataset.url);
                if (!dialog.querySelector('[name="default_model"]').value) set('default_model', 'qwen2.5:1.5b');
            });
        }

        if (dialog.dataset.initialShow === 'true' && typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    })();
</script>
