/**
 * Tailwind build for the guest layout (components/guest-layout.blade.php):
 * login, register, OTP, password reset and unsubscribe pages.
 *
 * Those pages used the Play CDN (cdn.tailwindcss.com), a ~400KB script that
 * blocks rendering and compiles CSS in the browser, which was most of the
 * phone PageSpeed cost. This compiles the same utilities ahead of time into
 * public/css/guest-tailwind.css. Like the CDN it uses the default theme and
 * no plugins, so pages render the same.
 *
 * Rebuild after changing classes in these views: npm run build:guest-css
 */
/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/components/**/*.blade.php',
        './resources/views/auth/**/*.blade.php',
        './resources/views/partials/**/*.blade.php',
        './resources/views/announcements/unsubscribe.blade.php',
        './resources/views/appreciation/unsubscribe.blade.php',
    ],
    plugins: [],
};
