{{--
    Standardized text input.

    Props:
        name         : input name (required)
        label        : label text (required)
        type         : input type (default: text)
        value        : default value
        required     : whether the field is required
        help         : short hint, shown as the placeholder when none is given
        placeholder  : placeholder text
        disabled     : disabled state
        readonly     : readonly state
        autocomplete : autocomplete attribute
        error        : error message (shows red border + message)

    Uses the existing .pm-input class as base styling.

    Accessibility: aria-invalid and aria-describedby wired up.

    Usage:
        <x-input name="email" label="Email" type="email" required help="We'll never share your email." />
--}}
@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'help' => null,
    'placeholder' => null,
    'disabled' => false,
    'readonly' => false,
    'autocomplete' => null,
    'error' => null,
])

@php
    $fieldId = $attributes->get('id', $name);
    // Hints live inside the field (placeholder), never as text under it.
    $placeholder = $placeholder ?: $help;
    $errorId = $error ? $fieldId . '-error' : null;
    $describedBy = $errorId;
@endphp

<div class="pm-field">
    <label for="{{ $fieldId }}" class="block text-sm font-medium text-slate-700 mb-1.5">
        {{ $label }}
        @if ($required)
            <span class="text-red-500" aria-hidden="true">*</span>
            <span class="sr-only">(required)</span>
        @endif
    </label>

    <input
        id="{{ $fieldId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $value }}"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($required) required @endif
        @if ($disabled) disabled @endif
        @if ($readonly) readonly @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        aria-invalid="{{ $error ? 'true' : 'false' }}"
        class="pm-input {{ $error ? 'border-red-400 focus:border-red-400 focus:ring-red-200' : '' }}"
    >


    @if ($error)
        <p id="{{ $errorId }}" class="mt-1.5 text-xs font-medium text-red-600" role="alert">{{ $error }}</p>
    @endif
</div>
