{{--
    Standardized modal using the native <dialog> element.

    New API props:
        id              : unique id for the dialog (required)
        title           : modal title (optional — shown in a header)
        size            : sm | md | lg | xl (default: md)
        closeOnBackdrop : close when backdrop is clicked (default: true)
        closeOnEscape   : close on Escape key (default: true)

    Slots:
        default : modal body content
        footer  : footer action buttons (optional)

    Backward-compatible with the legacy Breeze-style API:
        name      : event name for open-modal/close-modal dispatch
        show      : initial open state
        maxWidth  : sm|md|lg|xl|2xl (maps to size)
        focusable : focus first focusable on open

    Usage (new):
        <x-modal id="edit-modal" title="Edit Record" size="lg">
            <p>Body content</p>
            <x-slot name="footer">
                <x-button variant="secondary" onclick="document.getElementById('edit-modal').close()">Cancel</x-button>
                <x-button type="submit">Save</x-button>
            </x-slot>
        </x-modal>

    Usage (legacy):
        <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
            ...
        </x-modal>

    Accessibility: when `title` is set, the <dialog> gets an aria-labelledby
    pointing at the header <h2> (whose id is derived from the same $dialogId).
    Focus is returned to whatever had it before the dialog opened, on every
    close path.
--}}
@props([
    'id' => null,
    'title' => null,
    'size' => 'md',
    'closeOnBackdrop' => true,
    'closeOnEscape' => true,
    // Legacy props
    'name' => null,
    'show' => false,
    'maxWidth' => null,
    'focusable' => false,
])

@php
    // Resolve the dialog id: prefer the new `id` prop, fall back to `name`.
    $dialogId = $id ?? $name ?? 'modal-' . \Illuminate\Support\Str::random(6);

    // Accessible name for the dialog. Derived from the same $dialogId so it is
    // unique per dialog, and only wired up when there is actually a heading to
    // point at — a dangling aria-labelledby is worse than none at all.
    $dialogTitleId = $dialogId . '-title';

    // Map legacy maxWidth to size.
    if ($maxWidth) {
        $sizeMap = [
            'sm' => 'sm',
            'md' => 'md',
            'lg' => 'lg',
            'xl' => 'xl',
            '2xl' => 'xl',
        ];
        $size = $sizeMap[$maxWidth] ?? $size;
    }

    $sizeClasses = [
        'sm' => 'pm-dialog-sm',
        'md' => 'pm-dialog',
        'lg' => 'pm-dialog-lg',
        'xl' => 'pm-dialog-xl',
    ][$size] ?? 'pm-dialog';

    $hasFooter = ! empty(trim($footer ?? ''));
@endphp

<dialog
    id="{{ $dialogId }}"
    @if ($title) aria-labelledby="{{ $dialogTitleId }}" @endif
    class="pm-modal-shell {{ $sizeClasses }}"
    @if ($closeOnBackdrop) data-close-on-backdrop="true" @endif
    @if ($closeOnEscape) data-close-on-escape="true" @endif
    @if ($name) data-modal-name="{{ $name }}" @endif
    @if ($focusable) data-focusable="true" @endif
    @if ($show) data-initial-show="true" @endif
>
    <div class="pm-modal-content flex flex-col h-full max-h-full">
        @if ($title)
            <header class="pm-modal-header">
                <div class="pm-modal-heading">
                    <div class="pm-modal-title-wrap">
                        <h2 id="{{ $dialogTitleId }}" class="pm-modal-title">{{ $title }}</h2>
                    </div>
                </div>
                <button type="button" class="pm-modal-close" aria-label="Close modal" data-modal-close>
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </header>
        @endif

        <div class="pm-modal-body">
            {{ $slot }}
        </div>

        @if ($hasFooter)
            <footer class="pm-modal-footer">
                {{ $footer }}
            </footer>
        @endif
    </div>
</dialog>

<script>
    (function () {
        var dialog = document.getElementById('{{ $dialogId }}');
        if (!dialog) return;

        // Focus restoration. Capture synchronously before showModal(): native
        // dialogs do not emit an `open` event.
        // <dialog> moves focus into itself on open and
        // drops it back to <body> on close, which strands keyboard users at the
        // top of the document. Capture whatever had focus at open time and put
        // it back on close. Bound to the dialog's own open/close events rather
        // than to the close button so every close path is covered: the close
        // button, backdrop click, native Escape, and legacy close-modal all
        // fire `close`.
        var lastFocusedElement = null;

        function rememberFocus() {
            var active = document.activeElement;
            // Nothing meaningful to return to on an initial `show` render,
            // where focus is still wherever the document loaded.
            if (!active || active === document.body) { lastFocusedElement = null; return; }
            lastFocusedElement = active;
        }

        function restoreFocus() {
            var target = lastFocusedElement;
            lastFocusedElement = null;
            if (!target || typeof target.focus !== 'function') return;
            // The trigger may have been removed from the DOM while open.
            if (target.isConnected === false) return;
            try {
                target.focus({ preventScroll: true });
            } catch (e) {
                try { target.focus(); } catch (e2) { /* ignore */ }
            }
        }

        dialog.addEventListener('close', restoreFocus);

        function openDialog() {
            if (dialog.open) return;
            rememberFocus();
            dialog.showModal();
            if (dialog.dataset.focusable === 'true') {
                var focusables = dialog.querySelectorAll(
                    'a, button, input:not([type=hidden]), textarea, select, [tabindex]:not([tabindex=-1])'
                );
                var first = Array.prototype.find.call(focusables, function (el) {
                    return !el.hasAttribute('disabled');
                });
                if (first) setTimeout(function () { first.focus(); }, 50);
            }
        }

        // Open through the modal API so the dialog gets a modal top-layer and
        // focus is captured before the browser moves it into the dialog.
        if (dialog.dataset.initialShow === 'true') {
            openDialog();
        }

        var closeBtn = dialog.querySelector('[data-modal-close]');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () { dialog.close(); });
        }

        // Backdrop click closes when the dialog itself is the target.
        if (dialog.dataset.closeOnBackdrop === 'true') {
            dialog.addEventListener('click', function (e) {
                if (e.target === dialog) { dialog.close(); }
            });
        }

        // Escape is handled natively by <dialog>.

        // Legacy event listeners for open-modal / close-modal dispatch.
        var modalName = dialog.dataset.modalName;
        if (modalName) {
            window.addEventListener('open-modal', function (e) {
                if (e.detail === modalName) { openDialog(); }
            });
            window.addEventListener('close-modal', function (e) {
                if (e.detail === modalName) { dialog.close(); }
            });
        }

    })();
</script>
