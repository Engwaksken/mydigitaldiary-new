{{--
    Drop-in replacement for Breeze's default input-error component
    (plain red-600 text) — adds an icon and matches this app's rose error
    color used everywhere else, plus role="alert" so screen readers
    announce it without the user needing to hunt for it.
--}}
@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'text-sm text-rose-600 space-y-1']) }} role="alert">
        @foreach ((array) $messages as $message)
            <li class="flex items-center gap-1.5">
                <x-icon name="circle-exclamation" class="text-xs" />
                <span>{{ $message }}</span>
            </li>
        @endforeach
    </ul>
@endif
