/*
 * Row action menus for .pm-dt tables (<details class="pm-dt-menu">).
 * Only one menu is open at a time; it closes on outside click, Escape or
 * after choosing an item, and opens upwards when near the viewport bottom.
 */
(function () {
    'use strict';

    function closeAll(except) {
        document.querySelectorAll('details.pm-dt-menu[open]').forEach(function (menu) {
            if (menu !== except) {
                menu.removeAttribute('open');
            }
        });
    }

    document.addEventListener('toggle', function (event) {
        var menu = event.target;
        if (!(menu instanceof HTMLElement) || !menu.matches('details.pm-dt-menu') || !menu.open) {
            return;
        }

        closeAll(menu);
        menu.classList.remove('is-up');

        var list = menu.querySelector('.pm-dt-menu-list');
        if (list) {
            var rect = list.getBoundingClientRect();
            if (rect.bottom > window.innerHeight - 8 && menu.getBoundingClientRect().top > rect.height + 16) {
                menu.classList.add('is-up');
            }
        }
    }, true);

    document.addEventListener('click', function (event) {
        var inside = event.target.closest ? event.target.closest('details.pm-dt-menu') : null;

        if (!inside) {
            closeAll(null);
            return;
        }

        if (event.target.closest('.pm-dt-menu-item')) {
            inside.removeAttribute('open');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        var open = document.querySelector('details.pm-dt-menu[open]');
        if (open) {
            open.removeAttribute('open');
            var summary = open.querySelector('summary');
            if (summary) {
                summary.focus();
            }
        }
    });
})();
