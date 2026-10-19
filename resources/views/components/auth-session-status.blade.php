{{-- Status message shown after e.g. requesting a password reset link. --}}
@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-emerald-600 flex items-center gap-2']) }}>
        <x-icon name="circle-check" />
        <span>{{ $status }}</span>
    </div>
@endif
