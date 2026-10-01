{{--
    Shared behaviour for tabbed forms (long forms are split into tabs).
    Markup contract, scoped to the enclosing <form>:
      tab buttons : [data-pm-form-tab="N"] inside a [data-pm-form-tabs] tablist
      panels      : [data-pm-form-panel="N"] (all but the active one `hidden`)
    Include it next to any tabbed form: @include('partials.form-tabs').
--}}
@once
    <style>
        .pm-form-tabs {
            display: flex;
            gap: .25rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
            overflow-x: auto;
        }
        .pm-form-tab {
            padding: .6rem .85rem;
            margin-bottom: -1px;
            border: 0;
            border-bottom: 2px solid transparent;
            background: transparent;
            color: #64748b;
            font-size: .875rem;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
        }
        .pm-form-tab:hover { color: var(--brand-1); }
        .pm-form-tab.is-active { color: var(--brand-1); border-bottom-color: var(--brand-1); }
        .pm-form-panel { display: grid; gap: 1rem; min-height: 14rem; align-content: start; }
        .pm-form-panel[hidden] { display: none !important; }
    </style>
    <script>
        (function () {
            function selectFormTab(root, index, focus) {
                root.querySelectorAll('[data-pm-form-tab]').forEach(function (tab) {
                    var active = tab.dataset.pmFormTab === String(index);
                    tab.classList.toggle('is-active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                    tab.tabIndex = active ? 0 : -1;
                    if (active && focus) { tab.focus(); }
                });
                root.querySelectorAll('[data-pm-form-panel]').forEach(function (panel) {
                    panel.hidden = panel.dataset.pmFormPanel !== String(index);
                });
            }

            function scopeOf(el) { return el.closest('form') || el.closest('dialog') || document; }

            document.addEventListener('click', function (event) {
                var tab = event.target.closest && event.target.closest('[data-pm-form-tab]');
                if (tab) { selectFormTab(scopeOf(tab), tab.dataset.pmFormTab, false); }
            });

            document.addEventListener('keydown', function (event) {
                var tab = event.target.closest && event.target.closest('[data-pm-form-tab]');
                if (!tab || ['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(event.key) === -1) { return; }
                var tabs = Array.prototype.slice.call(tab.parentNode.querySelectorAll('[data-pm-form-tab]'));
                var i = tabs.indexOf(tab);
                if (event.key === 'ArrowRight') { i = (i + 1) % tabs.length; }
                if (event.key === 'ArrowLeft') { i = (i - 1 + tabs.length) % tabs.length; }
                if (event.key === 'Home') { i = 0; }
                if (event.key === 'End') { i = tabs.length - 1; }
                event.preventDefault();
                selectFormTab(scopeOf(tab), tabs[i].dataset.pmFormTab, true);
            });

            // A required field on a hidden tab can't show its validation
            // message, so open that tab first.
            document.addEventListener('invalid', function (event) {
                var panel = event.target.closest && event.target.closest('[data-pm-form-panel]');
                if (!panel || !panel.hidden) { return; }
                selectFormTab(scopeOf(panel), panel.dataset.pmFormPanel, false);
                window.setTimeout(function () { event.target.reportValidity && event.target.reportValidity(); }, 0);
            }, true);

            // Each time a dialog opens, show the tab holding an error, else the first tab.
            function watchDialog(dialog) {
                new MutationObserver(function () {
                    if (!dialog.open) { return; }
                    var invalid = dialog.querySelector('[data-pm-form-panel] [aria-invalid="true"]');
                    var panel = invalid ? invalid.closest('[data-pm-form-panel]') : null;
                    selectFormTab(dialog, panel ? panel.dataset.pmFormPanel : 0, false);
                }).observe(dialog, { attributes: true, attributeFilter: ['open'] });
            }

            function init() {
                document.querySelectorAll('[data-pm-form-tabs]').forEach(function (tabs) {
                    var dialog = tabs.closest('dialog');
                    if (dialog && !dialog.dataset.pmFormTabsWatched) {
                        dialog.dataset.pmFormTabsWatched = '1';
                        watchDialog(dialog);
                    }
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
    </script>
@endonce
