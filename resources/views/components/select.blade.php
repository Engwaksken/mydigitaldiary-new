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
    // DOM id for the control. An explicitly passed `id` always wins (read out of
    // the attribute bag rather than echoed, so no duplicate id attribute).
    // Otherwise derive one from $name plus a per-render counter so two
    // <x-select name="x"> on one page cannot collide and leave <label for>
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
