{{--
    Reminder-specific enhancements.

    1. Makes the New/Edit Reminder dialog fully usable on phones:
       - the dialog is constrained to the visual viewport;
       - header and footer remain visible;
       - only the form body scrolls;
       - Save/Cancel stay reachable even with a long form;
       - safe-area padding is respected on phones with gesture bars/notches;
       - the layout re-measures when the mobile keyboard opens.

    2. Adds a "Specific item(s)" multi-select after Related Module and
       loads the user's records for the chosen module.
--}}

@push('styles')
<style id="pm-reminder-responsive-modal-fix">
    /* ================================================================
       REMINDER MODAL — AUTHORITATIVE RESPONSIVE CONTRACT
       Scoped to .pm-reminder-dialog so other CRUD modules are untouched.
       ================================================================ */
    dialog.pm-reminder-dialog {
        box-sizing: border-box !important;
        width: min(680px, calc(100vw - 24px)) !important;
        max-width: min(680px, calc(100vw - 24px)) !important;
        height: auto !important;
        max-height: min(860px, calc(100dvh - 24px)) !important;
        margin: auto !important;
        padding: 0 !important;
        overflow: hidden !important;
        border: 0 !important;
        border-radius: 20px !important;
        background: #fff !important;
        box-shadow: 0 24px 70px rgba(15, 23, 42, .28) !important;
        overscroll-behavior: contain;
    }

    dialog.pm-reminder-dialog[open] {
        display: block !important;
    }

    dialog.pm-reminder-dialog::backdrop {
        background: rgba(15, 23, 42, .56);
        backdrop-filter: blur(3px);
        -webkit-backdrop-filter: blur(3px);
    }

    dialog.pm-reminder-dialog > #crud-modal-form,
    dialog.pm-reminder-dialog .pm-modal-form {
        display: flex !important;
        flex-direction: column !important;
        width: 100% !important;
        height: 100% !important;
        max-height: inherit !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
        background: #fff;
    }

    dialog.pm-reminder-dialog .pm-modal-header {
        position: relative !important;
        inset: auto !important;
        z-index: 5 !important;
        flex: 0 0 auto !important;
        width: 100% !important;
        padding: 18px 20px !important;
        border-bottom: 1px solid #e2e8f0 !important;
        background: #fff !important;
    }

    dialog.pm-reminder-dialog .pm-modal-heading {
        min-width: 0 !important;
    }

    dialog.pm-reminder-dialog .pm-modal-title,
    dialog.pm-reminder-dialog .pm-modal-description {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    dialog.pm-reminder-dialog .pm-modal-body {
        position: relative !important;
        display: block !important;
        flex: 1 1 auto !important;
        min-height: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        padding: 20px !important;
        overflow-x: hidden !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-y: contain;
        scrollbar-gutter: stable;
        background: #fff;
    }

    dialog.pm-reminder-dialog .pm-modal-body > * + * {
        margin-top: 16px;
    }

    dialog.pm-reminder-dialog .pm-modal-body input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),
    dialog.pm-reminder-dialog .pm-modal-body select,
    dialog.pm-reminder-dialog .pm-modal-body textarea {
        box-sizing: border-box !important;
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
    }

    dialog.pm-reminder-dialog #pm-reminder-items-wrapper {
        width: 100%;
        min-width: 0;
    }

    dialog.pm-reminder-dialog #pm-reminder-item-ids {
        min-height: 112px;
    }

    dialog.pm-reminder-dialog .pm-modal-section {
        box-sizing: border-box;
        width: 100%;
        min-width: 0;
    }

    dialog.pm-reminder-dialog .pm-modal-footer {
        position: relative !important;
        inset: auto !important;
        z-index: 6 !important;
        display: flex !important;
        flex: 0 0 auto !important;
        align-items: center !important;
        justify-content: flex-end !important;
        gap: 10px !important;
        width: 100% !important;
        padding: 14px 20px calc(14px + env(safe-area-inset-bottom, 0px)) !important;
        border-top: 1px solid #e2e8f0 !important;
        background: #fff !important;
        box-shadow: 0 -8px 22px rgba(15, 23, 42, .04);
    }

    dialog.pm-reminder-dialog .pm-modal-footer .pm-btn-cancel,
    dialog.pm-reminder-dialog .pm-modal-footer .pm-btn-save {
        width: auto !important;
        min-width: 118px !important;
        min-height: 44px !important;
        margin: 0 !important;
        white-space: nowrap !important;
    }

    @media (max-width: 640px) {
        dialog.pm-reminder-dialog {
            width: calc(100vw - 16px) !important;
            max-width: calc(100vw - 16px) !important;
            /* JS updates --pm-reminder-vh using visualViewport when available. */
            height: min(760px, calc(var(--pm-reminder-vh, 100dvh) - 16px)) !important;
            max-height: calc(var(--pm-reminder-vh, 100dvh) - 16px) !important;
            margin: 8px auto !important;
            border-radius: 18px !important;
        }

        dialog.pm-reminder-dialog .pm-modal-header {
            padding: 14px 14px 12px !important;
        }

        dialog.pm-reminder-dialog .pm-modal-icon {
            width: 40px !important;
            height: 40px !important;
            flex: 0 0 40px !important;
        }

        dialog.pm-reminder-dialog .pm-modal-title {
            font-size: 1rem !important;
            line-height: 1.3 !important;
        }

        dialog.pm-reminder-dialog .pm-modal-description {
            margin-top: 2px !important;
            font-size: 11px !important;
            line-height: 1.4 !important;
        }

        dialog.pm-reminder-dialog .pm-modal-close {
            flex: 0 0 36px !important;
            width: 36px !important;
            height: 36px !important;
        }

        dialog.pm-reminder-dialog .pm-modal-body {
            padding: 16px 14px 24px !important;
        }

        dialog.pm-reminder-dialog .pm-modal-body > * + * {
            margin-top: 14px;
        }

        /* 16px avoids Safari/Chrome focus zoom on phone form controls. */
        dialog.pm-reminder-dialog .pm-modal-body input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),
        dialog.pm-reminder-dialog .pm-modal-body select,
        dialog.pm-reminder-dialog .pm-modal-body textarea {
            font-size: 16px !important;
        }

        dialog.pm-reminder-dialog .pm-modal-footer {
            padding: 12px 14px calc(12px + env(safe-area-inset-bottom, 0px)) !important;
        }

        dialog.pm-reminder-dialog .pm-modal-footer .pm-btn-cancel,
        dialog.pm-reminder-dialog .pm-modal-footer .pm-btn-save {
            flex: 1 1 0 !important;
            width: 50% !important;
            min-width: 0 !important;
            min-height: 46px !important;
            justify-content: center !important;
        }
    }

    @media (max-width: 380px) {
        dialog.pm-reminder-dialog .pm-modal-header {
            padding-left: 12px !important;
            padding-right: 12px !important;
        }

        dialog.pm-reminder-dialog .pm-modal-body {
            padding-left: 12px !important;
            padding-right: 12px !important;
        }

        dialog.pm-reminder-dialog .pm-modal-footer {
            padding-left: 12px !important;
            padding-right: 12px !important;
            gap: 8px !important;
        }
    }
</style>
@endpush

<script>
    (function () {
        'use strict';

        var dialog = document.getElementById('crud-modal');
        var moduleField = null;

        function pmSetReminderVisualViewportHeight() {
            var height = window.visualViewport
                ? window.visualViewport.height
                : window.innerHeight;

            document.documentElement.style.setProperty(
                '--pm-reminder-vh',
                Math.max(320, Math.round(height)) + 'px'
            );
        }

        function pmResetReminderScroll() {
            if (!dialog || !dialog.classList.contains('pm-reminder-dialog')) { return; }
            var body = dialog.querySelector('.pm-modal-body');
            if (body) {
                body.scrollTop = 0;
            }
        }

        function pmEscapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function pmInjectReminderItemsUi() {
            moduleField = document.getElementById('field-module');
            if (!moduleField || document.getElementById('pm-reminder-items-wrapper')) { return; }

            var moduleWrapper = moduleField.closest('div');
            if (!moduleWrapper || !moduleWrapper.parentNode) { return; }

            var wrapper = document.createElement('div');
            wrapper.id = 'pm-reminder-items-wrapper';
            wrapper.style.display = 'none';
            wrapper.innerHTML =
                '<label for="pm-reminder-item-ids" class="block text-sm font-medium text-slate-700 mb-1">Specific item(s)</label>' +
                '<select id="pm-reminder-item-ids" name="item_ids[]" multiple size="4" class="pm-input"></select>' +
                '<p class="text-xs text-slate-400 mt-1">Hold Ctrl/Cmd to select more than one. Leave nothing selected to keep this reminder general to the whole module.</p>';

            moduleWrapper.parentNode.insertBefore(wrapper, moduleWrapper.nextSibling);

            moduleField.addEventListener('change', function () {
                pmLoadReminderItemsForModule(moduleField.value);
            });

            if (moduleField.value) {
                pmLoadReminderItemsForModule(moduleField.value);
            }
        }

        function pmLoadReminderItemsForModule(module) {
            var wrapper = document.getElementById('pm-reminder-items-wrapper');
            var select = document.getElementById('pm-reminder-item-ids');
            if (!wrapper || !select) { return; }

            if (!module || module === 'budget' || module === 'custom') {
                wrapper.style.display = 'none';
                select.innerHTML = '';
                return;
            }

            select.innerHTML = '<option disabled>Loading...</option>';
            wrapper.style.display = 'block';

            fetch('{{ route('reminders.items-for-module') }}?module=' + encodeURIComponent(module), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(function (response) {
                    if (!response.ok) { throw new Error('Request failed'); }
                    return response.json();
                })
                .then(function (items) {
                    if (!Array.isArray(items) || !items.length) {
                        select.innerHTML = '<option disabled>No records found in this module yet.</option>';
                        return;
                    }

                    select.innerHTML = items.map(function (item) {
                        return '<option value="' + pmEscapeHtml(item.id) + '">' +
                            pmEscapeHtml(item.label) +
                            '</option>';
                    }).join('');
                })
                .catch(function () {
                    select.innerHTML = '<option disabled>Could not load items.</option>';
                });
        }

        function pmPrepareReminderDialog() {
            pmSetReminderVisualViewportHeight();
            pmInjectReminderItemsUi();
            window.requestAnimationFrame(pmResetReminderScroll);
        }

        document.addEventListener('DOMContentLoaded', function () {
            pmSetReminderVisualViewportHeight();
            pmInjectReminderItemsUi();

            if (dialog) {
                dialog.addEventListener('close', function () {
                    var body = dialog.querySelector('.pm-modal-body');
                    if (body) { body.scrollTop = 0; }
                });
            }
        });

        /* The shared CRUD buttons use inline openCrudCreateModal/
           openCrudEditModal handlers. Running after their click lets us
           size and reset the reminder dialog after showModal(). */
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest(
                '[onclick*="openCrudCreateModal"], [onclick*="openCrudEditModal"]'
            );

            if (trigger) {
                window.setTimeout(pmPrepareReminderDialog, 0);
            }
        });

        window.addEventListener('resize', pmSetReminderVisualViewportHeight, { passive: true });
        window.addEventListener('orientationchange', function () {
            window.setTimeout(pmSetReminderVisualViewportHeight, 120);
        });

        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', pmSetReminderVisualViewportHeight, { passive: true });
            window.visualViewport.addEventListener('scroll', pmSetReminderVisualViewportHeight, { passive: true });
        }
    })();
</script>
