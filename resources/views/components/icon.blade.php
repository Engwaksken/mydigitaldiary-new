{{-- Inline SVG icon: <x-icon name="envelope" class="text-xs" />. See App\View\Icon. --}}
@props(['name'])
{!! \App\View\Icon::svg($name, (string) $attributes->get('class', ''), $attributes->except('class')->getAttributes()) !!}
