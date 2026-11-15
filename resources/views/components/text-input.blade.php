{{--
    Drop-in replacement for Breeze's default text-input component
    (indigo-500 focus ring) - reskinned to the brand palette. Uses the
    plain-CSS .pm-input class (defined in guest-layout.blade.php) rather
    than Tailwind's border-slate-300/shadow-sm utilities - inputs were
    reported rendering with no visible border/background at all on some
    pages, and a hand-written CSS rule can't silently fail to apply the
    way a utility class theoretically could.
--}}
@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'pm-input']) }}>
