{{--
    Floating accessibility widget included in both layouts/app.blade.php
    and components/guest-layout.blade.php, so it's available whether or
    not you're logged in. Entirely client-side (no server round-trip,
    no new DB column): every preference is stored in localStorage and
    re-applied on each page load by a script placed as early as possible
    in <body> to minimize any flash of unstyled content.

    Deliberately built in-house rather than embedding a third-party widget
    script this app has no dependency on an external accessibility
    vendor's uptime, pricing, or data collection.
--}}
<div id="pm-a11y-widget">
    <style>

        /*
         * The children themselves are position:fixed, so the wrapper does
         * not need zero-size containment. Keeping the wrapper ordinary and
         * unclipped avoids Safari/Android browsers suppressing its fixed
         * descendants.
         */
        #pm-a11y-widget {
            display: block !important;
            position: static !important;
            width: auto !important;
            height: auto !important;
            min-width: 0 !important;
            min-height: 0 !important;
            max-width: none !important;
            max-height: none !important;
            overflow: visible !important;
            visibility: visible !important;
            opacity: 1 !important;
            pointer-events: auto !important;
        }

        /* Plain CSS rather than Tailwind's bg-[var(--brand-1)] bracket
           syntax the same reliability fix already applied to primary
           buttons elsewhere, since this toggle is a fixed, always-present
           element that must never fail to render. Also handles iOS's
           safe-area-inset so the button sits clear of the home-indicator
           gesture strip on notched phones, and sizes at a proper 44x44px
           minimum touch target on small screens (WCAG's minimum,
           slightly larger than this button used at 56x56 before, which
           was already fine, but tightened up further below on very
           narrow screens). */
        .pm-a11y-toggle {
            position: fixed !important;
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            right: max(1.25rem, env(safe-area-inset-right));
            bottom: max(1.25rem, env(safe-area-inset-bottom));
            z-index: 50;
            width: 56px;
            height: 56px;
            border-radius: 9999px;
            background-color: var(--brand-1);
            color: #ffffff;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.15s ease;
        }
        .pm-a11y-toggle:hover {
            background-color: var(--brand-2);
        }

        /* The panel used to anchor purely off "bottom-24 right-5" with no
           height cap on a short viewport (phones in landscape, or a
           small phone with the on-screen keyboard open) or a narrow one,
           it could overflow off the top or sides of the screen entirely,
           becoming impossible to fully see or scroll. Capping max-height
           with its own overflow-y:auto, and widening more generously on
           tablets, fixes both. */
        .pm-a11y-panel[hidden] { display: none !important; }

        .pm-a11y-panel {
            position: fixed !important;
            visibility: visible !important;
            opacity: 1 !important;
            right: max(1.25rem, env(safe-area-inset-right));
            bottom: calc(56px + 1.25rem + 12px + env(safe-area-inset-bottom));
            z-index: 50;
            width: min(92vw, 22rem);
            max-height: min(75vh, 32rem);
            overflow-y: auto;
            background-color: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid #e2e8f0;
            padding: 1.25rem;
        }

        @media (max-width: 767.98px) {
            .pm-a11y-toggle {
                position: fixed !important;
                right: max(12px, env(safe-area-inset-right)) !important;
                bottom: max(12px, env(safe-area-inset-bottom)) !important;
                width: 48px !important;
                height: 48px !important;
                min-width: 48px !important;
                min-height: 48px !important;
                max-width: 48px !important;
                max-height: 48px !important;
                margin: 0 !important;
                z-index: 9999 !important;
            }

            .pm-a11y-panel {
                position: fixed !important;
                top: auto !important;
                right: 12px !important;
                bottom: calc(72px + env(safe-area-inset-bottom)) !important;
                left: 12px !important;
                width: auto !important;
                min-width: 0 !important;
                max-width: none !important;
                height: auto !important;
                max-height: min(72dvh, 34rem) !important;
                margin: 0 !important;
                overflow-x: hidden !important;
                overflow-y: auto !important;
                overscroll-behavior: contain !important;
                z-index: 9999 !important;
                box-sizing: border-box !important;
            }
        }

        @media (max-width: 380px) {
            /* Very narrow phones: let the panel use nearly the full width
               and sit flush with both edges instead of only the right,
               so its own internal padding doesn't leave awkwardly
               asymmetric margins. */
            .pm-a11y-panel {
                right: 0.75rem;
                left: 0.75rem;
                width: auto;
            }
        }
    </style>

    <button type="button"
            id="pm-a11y-toggle"
            onclick="pmToggleA11yPanel()"
            aria-expanded="false"
            aria-controls="pm-a11y-panel"
            class="pm-a11y-toggle"
            aria-label="Accessibility options">
        <x-icon name="universal-access" class="text-xl" />
    </button>

    <div id="pm-a11y-panel"
         role="dialog"
         aria-label="Accessibility options"
         aria-modal="false"
         hidden
         class="pm-a11y-panel">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold text-slate-800 flex items-center gap-2">
                <x-icon name="universal-access" class="text-[var(--brand-1)]" />
                Accessibility
            </h2>
            <button type="button" onclick="pmToggleA11yPanel()"
                    class="w-11 h-11 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors shrink-0"
                    aria-label="Close accessibility panel">
                <x-icon name="xmark" />
            </button>
        </div>

        <div class="space-y-4 text-sm">
            <div>
                <span class="block font-medium text-slate-700 mb-2">Text size</span>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="pmA11yAdjustFontScale(-1)"
                            class="w-11 h-11 rounded-lg border border-slate-300 hover:bg-slate-50 flex items-center justify-center transition-colors shrink-0"
                            aria-label="Decrease text size">
                        <x-icon name="minus" />
                    </button>
                    <span id="pm-a11y-font-scale-label" class="w-14 text-center text-slate-600" aria-live="polite">100%</span>
                    <button type="button" onclick="pmA11yAdjustFontScale(1)"
                            class="w-11 h-11 rounded-lg border border-slate-300 hover:bg-slate-50 flex items-center justify-center transition-colors shrink-0"
                            aria-label="Increase text size">
                        <x-icon name="plus" />
                    </button>
                </div>
            </div>

            <label class="flex items-center justify-between cursor-pointer gap-3 py-1">
                <span class="text-slate-700">High contrast</span>
                <input type="checkbox" id="pm-a11y-contrast" onchange="pmA11yToggle('contrast', this.checked)"
                       class="w-5 h-5 rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)] shrink-0">
            </label>

            <label class="flex items-center justify-between cursor-pointer gap-3 py-1">
                <span class="text-slate-700">Underline all links</span>
                <input type="checkbox" id="pm-a11y-underline-links" onchange="pmA11yToggle('underline-links', this.checked)"
                       class="w-5 h-5 rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)] shrink-0">
            </label>

            <label class="flex items-center justify-between cursor-pointer gap-3 py-1">
                <span class="text-slate-700">Reduce motion</span>
                <input type="checkbox" id="pm-a11y-reduce-motion" onchange="pmA11yToggle('reduce-motion', this.checked)"
                       class="w-5 h-5 rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)] shrink-0">
            </label>

            <label class="flex items-center justify-between cursor-pointer gap-3 py-1">
                <span class="text-slate-700">Dyslexia-friendly font</span>
                <input type="checkbox" id="pm-a11y-readable-font" onchange="pmA11yToggle('readable-font', this.checked)"
                       class="w-5 h-5 rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)] shrink-0">
            </label>

            <label class="flex items-center justify-between cursor-pointer gap-3 py-1">
                <span class="text-slate-700">Grayscale (colorblind-friendly)</span>
                <input type="checkbox" id="pm-a11y-grayscale" onchange="pmA11yToggle('grayscale', this.checked)"
                       class="w-5 h-5 rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)] shrink-0">
            </label>

            <div class="border-t border-slate-100 pt-3">
                <span class="block font-medium text-slate-700 mb-2">Voice</span>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="pmA11yReadPage()"
                            class="flex-1 inline-flex items-center justify-center gap-2 border border-slate-300 rounded-lg px-3 py-2 text-sm hover:bg-slate-50 transition-colors">
                        <x-icon name="volume-high" /> Read page aloud
                    </button>
                    <button type="button" onclick="pmA11yStopReading()"
                            class="w-10 h-10 rounded-lg border border-slate-300 hover:bg-slate-50 flex items-center justify-center transition-colors" aria-label="Stop reading">
                        <x-icon name="stop" />
                    </button>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-3">
                <button type="button" onclick="pmA11yToggleShortcuts()"
                        class="w-full flex items-center justify-between text-slate-700 hover:text-slate-900">
                    <span class="font-medium">Keyboard navigation</span>
                    <x-icon name="chevron-down" class="text-xs transition-transform" id="pm-a11y-shortcuts-chevron" />
                </button>
                <ul id="pm-a11y-shortcuts-list" hidden class="mt-2 space-y-1.5 text-xs text-slate-500">
                    <li><kbd class="px-1.5 py-0.5 bg-slate-100 rounded border border-slate-200 font-mono">Tab</kbd> move to the next control</li>
                    <li><kbd class="px-1.5 py-0.5 bg-slate-100 rounded border border-slate-200 font-mono">← →</kbd> switch tabs, once one is focused</li>
                    <li><kbd class="px-1.5 py-0.5 bg-slate-100 rounded border border-slate-200 font-mono">Home / End</kbd> jump to the first/last tab</li>
                    <li><kbd class="px-1.5 py-0.5 bg-slate-100 rounded border border-slate-200 font-mono">Esc</kbd> close any open dialog/modal</li>
                    <li><kbd class="px-1.5 py-0.5 bg-slate-100 rounded border border-slate-200 font-mono">Enter / Space</kbd> activate a focused button</li>
                </ul>
            </div>

            <button type="button" onclick="pmA11yReset()"
                    class="w-full text-center text-slate-500 hover:text-slate-700 hover:underline pt-2 border-t border-slate-100 py-2">
                Reset to defaults
            </button>
        </div>
    </div>
</div>

<style>
    /* Applied via classes toggled on <html> by the script below. Kept in
       one place so every preference's actual visual effect is easy to
       audit and adjust. */
    html.pm-a11y-grayscale {
        filter: grayscale(1);
    }
    html.pm-a11y-contrast {
        filter: contrast(1.35) saturate(1.1);
    }
    html.pm-a11y-underline-links a {
        text-decoration: underline !important;
    }
    html.pm-a11y-reduce-motion,
    html.pm-a11y-reduce-motion * {
        animation-duration: 0.001ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.001ms !important;
        scroll-behavior: auto !important;
    }
    html.pm-a11y-readable-font body {
        font-family: 'Comic Sans MS', 'Comic Sans', Verdana, sans-serif !important;
        letter-spacing: 0.02em;
        line-height: 1.6;
    }
</style>

<script>
    (function () {
        var STORAGE_KEY = 'pm_a11y_prefs';

        function getPrefs() {
            try {
                return Object.assign(
                    { contrast: false, 'underline-links': false, 'reduce-motion': false, 'readable-font': false, fontScale: 100 },
                    JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}')
                );
            } catch (e) {
                return { contrast: false, 'underline-links': false, 'reduce-motion': false, 'readable-font': false, fontScale: 100 };
            }
        }

        function savePrefs(prefs) {
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs)); } catch (e) { /* storage unavailable degrade silently */ }
        }

        function applyPrefs(prefs) {
            ['contrast', 'underline-links', 'reduce-motion', 'readable-font', 'grayscale'].forEach(function (key) {
                document.documentElement.classList.toggle('pm-a11y-' + key, !!prefs[key]);
            });
            document.documentElement.style.fontSize = prefs.fontScale + '%';

            // Let dashboard/page runtimes react immediately. This is especially
            // important for dashboard components that use fixed pixel sizes.
            window.dispatchEvent(new CustomEvent('pm:a11y-change', {
                detail: { preferences: Object.assign({}, prefs) }
            }));
        }

        // Runs immediately (this script tag executes as soon as it's
        // parsed, near the top of <body>) rather than waiting for
        // DOMContentLoaded, specifically to avoid a flash of un-adjusted
        // text size/contrast on every page load.
        applyPrefs(getPrefs());

        window.pmToggleA11yPanel = function () {
            var panel = document.getElementById('pm-a11y-panel');
            var toggle = document.getElementById('pm-a11y-toggle');
            var isHidden = panel.hasAttribute('hidden');
            if (isHidden) {
                const supportPanel = document.getElementById('pm-support-panel');
                const supportToggle = document.getElementById('pm-support-toggle');

                if (supportPanel && !supportPanel.hidden) {
                    supportPanel.hidden = true;
                    supportToggle?.setAttribute('aria-expanded', 'false');
                }

                panel.removeAttribute('hidden');
                toggle.setAttribute('aria-expanded', 'true');
            } else {
                panel.setAttribute('hidden', '');
                toggle.setAttribute('aria-expanded', 'false');
            }
        };

        window.pmA11yToggle = function (key, value) {
            var prefs = getPrefs();
            prefs[key] = value;
            savePrefs(prefs);
            applyPrefs(prefs);
        };

        window.pmA11yAdjustFontScale = function (direction) {
            var prefs = getPrefs();
            var next = prefs.fontScale + direction * 10;
            next = Math.max(80, Math.min(160, next));
            prefs.fontScale = next;
            savePrefs(prefs);
            applyPrefs(prefs);
            document.getElementById('pm-a11y-font-scale-label').textContent = next + '%';
        };

        window.pmA11yReset = function () {
            var prefs = { contrast: false, 'underline-links': false, 'reduce-motion': false, 'readable-font': false, grayscale: false, fontScale: 100 };
            savePrefs(prefs);
            applyPrefs(prefs);
            syncControls(prefs);
        };

        function syncControls(prefs) {
            var contrastEl = document.getElementById('pm-a11y-contrast');
            var underlineEl = document.getElementById('pm-a11y-underline-links');
            var motionEl = document.getElementById('pm-a11y-reduce-motion');
            var fontEl = document.getElementById('pm-a11y-readable-font');
            var grayscaleEl = document.getElementById('pm-a11y-grayscale');
            var labelEl = document.getElementById('pm-a11y-font-scale-label');
            if (contrastEl) { contrastEl.checked = !!prefs.contrast; }
            if (underlineEl) { underlineEl.checked = !!prefs['underline-links']; }
            if (motionEl) { motionEl.checked = !!prefs['reduce-motion']; }
            if (fontEl) { fontEl.checked = !!prefs['readable-font']; }
            if (grayscaleEl) { grayscaleEl.checked = !!prefs.grayscale; }
            if (labelEl) { labelEl.textContent = prefs.fontScale + '%'; }
        }

        // Reads the page's main content aloud using the browser's own
        // built-in SpeechSynthesis API no external service, no
        // per-request cost, works offline once the page has loaded.
        // Targets <main> if the layout has one, falling back to the
        // whole <body> otherwise, so it reads the actual page content
        // rather than sidebar/navigation chrome repeated on every page.
        window.pmA11yReadPage = function () {
            if (!('speechSynthesis' in window)) {
                alert('Text-to-speech is not supported in this browser.');
                return;
            }
            window.speechSynthesis.cancel();
            var target = document.querySelector('main') || document.body;
            var utterance = new SpeechSynthesisUtterance(target.innerText);
            utterance.lang = document.documentElement.lang || 'en-US';
            window.speechSynthesis.speak(utterance);
        };

        window.pmA11yStopReading = function () {
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
            }
        };

        window.pmA11yToggleShortcuts = function () {
            var list = document.getElementById('pm-a11y-shortcuts-list');
            var chevron = document.getElementById('pm-a11y-shortcuts-chevron');
            list.hidden = !list.hidden;
            chevron.style.transform = list.hidden ? '' : 'rotate(180deg)';
        };

        document.addEventListener('DOMContentLoaded', function () {
            syncControls(getPrefs());
        });
    })();
</script>
