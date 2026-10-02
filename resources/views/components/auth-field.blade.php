{{--
    Label + input + hint + error in one tag instead of hand-assembling all four every time.

    Ids: an explicitly passed `id` is used verbatim; otherwise one is derived
    from `name` plus a per-render counter, so repeating the component on a page
    cannot produce duplicate ids. The label, the input and the error message are
    all wired to the resolved id.
--}}
@props([
    'name',
    'label',
    'type' => 'text',
    'placeholder' => null,
    'hint' => null,
    'value' => null,
    'required' => false,
    'autofocus' => false,
    'autocomplete' => null,
    'icon' => null,
])

@php
    $errorMessages = $errors->get($name);
    $hasError = count($errorMessages) > 0;
    $fieldValue = $value ?? old($name);

    // DOM id for the control, plus the id of the message element it points at.
    // This used to hardcode id="{{ $name }}" AND echo {{ $attributes }}, so a
    // caller passing `id` produced two id attributes on one element and the HTML
    // parser silently kept the first — the caller's id was ignored. It also
    // meant two <x-auth-field name="x"> on one page collided. Now an explicitly
    // passed `id` always wins, and otherwise the id is derived from $name plus
    // a per-render counter (stored on the request, so it is stable across a
    // re-render rather than random). The id is consumed from the bag so it can
    // never be emitted twice.
    $passedId = $attributes->get('id');
    $counterKey = 'pm.field_id_seq.' . $name;
    $sequence = (int) request()->attributes->get($counterKey, 0) + 1;
    request()->attributes->set($counterKey, $sequence);

    $fieldId = $passedId ?: $name . '-' . $sequence;
    $errorId = $fieldId . '-error';
    $attributes = $attributes->except('id');

    // A sensible icon by field name/type if the caller didn't pick one.
    $resolvedIcon = $icon ?? match (true) {
        $type === 'email' => 'fa-solid fa-envelope',
        $type === 'password' => 'fa-solid fa-lock',
        $name === 'name' => 'fa-solid fa-user',
        $name === 'code' => 'fa-solid fa-shield-halved',
        default => null,
    };

    $inputClass = 'pm-input block mt-1 w-full' . ($resolvedIcon ? ' has-icon' : '');
@endphp

<div>
    <x-input-label :for="$fieldId" :value="$label" />

    <div class="{{ $resolvedIcon ? 'auth-field-icon-wrap' : '' }}">
        @if ($resolvedIcon)
            <span class="auth-field-icon"><i class="{{ $resolvedIcon }}" aria-hidden="true"></i></span>
        @endif

        {{-- A plain <input>, not a nested x-text-input call — attribute
             forwarding through an extra layer of component indirection
             was the actual cause of the field losing its styling
             entirely (this renders identically to how every other input
             in the app is built, just with the pm-input class applied
             directly). --}}
        <input
            id="{{ $fieldId }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ $fieldValue }}"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($required) required aria-required="true" @endif
            @if ($autofocus) autofocus @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
            {{ $attributes->merge(['class' => $inputClass]) }}
        >
    </div>

    @if ($hint)
        <p class="auth-hint">{{ $hint }}</p>
    @endif
    <x-input-error :messages="$errorMessages" id="{{ $errorId }}" class="mt-2" />
</div>
