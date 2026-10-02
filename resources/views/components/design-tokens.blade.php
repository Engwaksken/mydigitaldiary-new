{{--
    Design tokens — shared CSS custom properties for the "My Digital Diary"
    UI component library.

    Maps to the existing brand palette (--brand-1 / --brand-2 defined in the
    app layout) and the Apple-inspired 2026 visual language. Include this
    component once near the top of a page (or in the layout) so every
    component below can rely on these tokens.

    Usage:
        <x-design-tokens />
--}}
<style>
    :root {
        /* Brand / semantic colors */
        --color-primary: var(--brand-1, #00897B);
        --color-secondary: var(--brand-2, #73BEB6);
        /* Contrast on white, measured (WCAG 1.4.3 AA for text needs 4.5:1):
             success #15803d = 4.83:1  (was #16a34a = 3.30:1 — FAILED)
             warning #b45309 = 4.53:1  (was #d97706 = 3.19:1 — FAILED)
             danger  #b91c1c = 6.30:1  (was #dc2626 = 4.46:1 on
                                       --color-background #f4f6f8 — FAILED by 0.04)
           These darker steps are already used elsewhere in this codebase. Please
           do not "brighten" them back: the previous values are the green/amber/
           red-500 family and they are not AA-compliant as text. */
        --color-success: #15803d;
        --color-warning: #b45309;
        --color-danger: #b91c1c;
        --color-info: #2563eb;

        /* Neutrals */
        --color-border: #dce2e8;
        --color-background: #f4f6f8;
        --color-text: #111827;
        --color-muted: #667085;

        /* Radii */
        --radius-sm: 8px;
        --radius-md: 12px;
        --radius-lg: 20px;

        /* Shadows */
        --shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.05);
        --shadow-md: 0 8px 26px rgba(15, 23, 42, 0.055);
        --shadow-lg: 0 14px 38px rgba(15, 23, 42, 0.08);

        /* Spacing scale */
        --spacing-1: 0.25rem;
        --spacing-2: 0.5rem;
        --spacing-3: 0.75rem;
        --spacing-4: 1rem;
        --spacing-5: 1.25rem;
        --spacing-6: 1.5rem;
        --spacing-7: 1.75rem;
        --spacing-8: 2rem;
    }
</style>
