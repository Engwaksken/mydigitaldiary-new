{{--
    Form section wrapper - wraps form fields in a card with a heading.

    Props:
        title       : section title (required)
        description : optional description text

    Slot: form fields.

    Usage:
        <x-form-section title="Personal Details" description="Update your profile information.">
            <x-input name="name" label="Name" />
        </x-form-section>
--}}
@props([
    'title',
    'description' => null,
])

<section class="pm-form-section rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
    <header class="px-5 sm:px-6 py-4 border-b border-slate-100">
        <h2 class="pm-heading text-base font-bold text-slate-900">{{ $title }}</h2>
        @if ($description)
            <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
        @endif
    </header>

    <div class="px-5 sm:px-6 py-5">
        {{ $slot }}
    </div>
</section>
