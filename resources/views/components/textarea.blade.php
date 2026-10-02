{{--
    Standardized textarea.

    Props:
        name        : textarea name (required)
        label       : label text (required)
        value       : default value
        rows        : number of rows (default: 3)
        required    : whether the field is required
        help        : short hint, shown as the placeholder when none is given
        placeholder : placeholder text
        disabled    : disabled state
        error       : error message

    Same styling as the input component.

    Usage:
        <x-textarea name="bio" label="Bio" rows="4" />
--}}
@props([
    'name',
    'label',
    'value' => null,
    'rows' => 3,
    'required' => false,
    'help' => null,
    'placeholder' => null,
    'disabled' => false,
    'error' => null,
])

@php
    // DOM id for the control. An explicitly passed `id` always wins (read out of
    // the attribute bag rather than echoed, so no duplicate id attribute).
    // Otherwise derive one from $name plus a per-render counter so two
    // <x-textarea name="x"> on one page cannot collide and leave <label for>
    // pointing at the wrong control. The counter lives on the request, so ids
    // are identical on every render of the same page.
    $passedId = $attributes->get('id');
    $counterKey = 'pm.field_id_seq.' . $name;
    $sequence = (int) request()->attributes->get($counterKey, 0) + 1;
    request()->attributes->set($counterKey, $sequence);

    $fieldId = $passedId ?: $name . '-' . $sequence;

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

    <textarea
        id="{{ $fieldId }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($required) required @endif
        @if ($disabled) disabled @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        aria-invalid="{{ $error ? 'true' : 'false' }}"
        class="pm-input resize-y {{ $error ? 'border-red-400 focus:border-red-400 focus:ring-red-200' : '' }}"
    >{{ $value }}</textarea>


    @if ($error)
        <p id="{{ $errorId }}" class="mt-1.5 text-xs font-medium text-red-600" role="alert">{{ $error }}</p>
    @endif
</div>
