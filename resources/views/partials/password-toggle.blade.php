{{--
    Adds a show/hide "eye" icon to EVERY password field on the page,
    automatically not per-view markup changes. Works because it just
    scans for input[type="password"] after the page loads and wraps each
    one, so it applies to login, register, the delete-account
    confirmation, profile password changes, anywhere a <input type="password">
    exists, without needing to touch each of those views individually.
--}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var eyeIcon = @js(\App\View\Icon::svg('eye', 'text-sm'));
        var eyeSlashIcon = @js(\App\View\Icon::svg('eye-slash', 'text-sm'));

        document.querySelectorAll('input[type="password"]').forEach(function (input) {
            if (input.dataset.pmPasswordToggled) { return; }
            input.dataset.pmPasswordToggled = 'true';

            var wrapper = document.createElement('div');
            wrapper.style.position = 'relative';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);

            var existingPadding = window.getComputedStyle(input).paddingRight;
            input.style.paddingRight = 'calc(' + existingPadding + ' + 1.75rem)';

            var toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.setAttribute('aria-label', 'Show password');
            toggle.className = 'absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 transition-colors';
            toggle.innerHTML = eyeIcon;

            toggle.addEventListener('click', function () {
                var isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                toggle.innerHTML = isPassword
                    ? eyeSlashIcon
                    : eyeIcon;
                toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            });

            wrapper.appendChild(toggle);
        });
    });
</script>
