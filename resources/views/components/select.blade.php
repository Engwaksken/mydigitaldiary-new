{{--
    Standardized select dropdown.

    Props:
        name        : select name (required)
        label       : label text (required)
        options     : array of [value => label] pairs
        value       : selected value
        required    : whether the field is required
        help        : short hint, shown as the placeholder when none is given
        placeholder : placeholder option text
        disabled    : disabled state
        error       : error message

    Same styling as the input component.

    Usage:
        <x-select name="category" label="Category" :options="['a' => 'Option A', 'b' => 'Option B']" />
--}}
@props([
    'name',
    'label',
    'options' => [],
    'value' => null,
    'required' => false,
    'help' => null,
    'placeholder' => null,
    'disabled' => false,
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

    <select
        id="{{ $fieldId }}"
        name="{{ $name }}"
        @if ($required) required @endif
        @if ($disabled) disabled @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        aria-invalid="{{ $error ? 'true' : 'false' }}"
        class="pm-input {{ $error ? 'border-red-400 focus:border-red-400 focus:ring-red-200' : '' }}"
    >
        @if ($placeholder)
            <option value="" @if ($value === null || $value === '') selected @endif disabled>{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @if ((string) $value === (string) $optionValue) selected @endif>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>


    @if ($error)
        <p id="{{ $errorId }}" class="mt-1.5 text-xs font-medium text-red-600" role="alert">{{ $error }}</p>
    @endif
</div>
