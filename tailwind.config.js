import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // Explicit, not implicit. Nothing in the app currently ships a `dark:`
    // variant, so this is behaviour-neutral today; it is declared here so a
    // future `dark:` utility follows the media query by default rather than
    // requiring someone to re-derive the intent.
    darkMode: 'media',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // 'Figtree' was never loaded by any layout, so `font-sans`
                // silently fell through to the system stack. 'Poppins' is the
                // webfont actually loaded in layouts/app.blade.php and
                // layouts/guest.blade.php, and it already matches the
                // `body { font-family: 'Poppins', ... }` in public/css/app.css.
                sans: ['Poppins', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
