{{--
    Shared styles for every auth page included (not linked) so the CSS
    is guaranteed present the instant the page renders, same as every
    other style block in this app. An external stylesheet loads async
    and can land after Tailwind's CDN script injects its own reset,
    which is what was actually breaking the input fields this fixes
    that while still keeping the CSS in one file instead of six copies.
--}}
<style>
    :root {
        --brand-1: #00897B;
        --brand-2: #73BEB6;
        --brand-1-dark: #092b21;
        --brand-2-dark: #5c7d61;
        --brand-1-tint-10: rgba(12, 59, 46, 0.1);
        --brand-1-tint-20: rgba(12, 59, 46, 0.2);
        --brand-2-tint-25: rgba(109, 151, 115, 0.25);
    }

    body {
        font-family: 'Lato', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    }

    a:focus-visible, button:focus-visible, input:focus-visible,
    select:focus-visible, textarea:focus-visible {
        outline: 3px solid #FFBA00;
        outline-offset: 2px;
    }

    .pm-input {
        display: block !important;
        width: 100% !important;
        border: 1px solid #94a3b8 !important;
        border-radius: 0.5rem !important;
        padding: 0.625rem 0.875rem;
        background-color: #ffffff !important;
        color: #1e293b !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    /* Room for a leading icon (see x-auth-field's optional icon prop) -
       the icon itself is absolutely positioned inside .auth-field-icon-wrap.
       Not !important: the password-toggle script sets its own inline
       padding-right for the eye icon, and this must not fight it. */
    .pm-input.has-icon {
        padding-left: 2.5rem;
    }

    .auth-field-icon-wrap {
        position: relative;
    }

    .auth-field-icon {
        position: absolute;
        top: 0;
        bottom: 0;
        left: 0.875rem;
        display: flex;
        align-items: center;
        color: #94a3b8;
        pointer-events: none;
        font-size: 0.875rem;
    }

    .pm-input::placeholder {
        color: #94a3b8;
    }

    .pm-input:focus {
        outline: none !important;
        border-color: var(--brand-2) !important;
        box-shadow: 0 0 0 3px var(--brand-2-tint-25) !important;
    }

    .pm-input[aria-invalid="true"] {
        border-color: #e11d48 !important;
    }

    /* The auth card itself a clearly visible lift off the page
       background, not just a flat white rectangle. */
    .auth-card {
        box-shadow: 0 20px 40px -15px rgba(12, 59, 46, 0.18), 0 4px 10px -4px rgba(12, 59, 46, 0.08);
    }

    .btn-primary {
        background-color: var(--brand-1);
    }

    .btn-primary:hover {
        background-color: var(--brand-2);
    }

    .auth-link {
        color: var(--brand-1);
        text-decoration: none;
        transition: color 0.15s ease;
    }

    .auth-link:hover {
        color: var(--brand-2);
    }

    .auth-link-muted {
        color: #64748b;
        text-decoration: none;
        transition: color 0.15s ease;
    }

    .auth-link-muted:hover {
        color: #1e293b;
    }

    .auth-hint {
        font-size: 0.8125rem;
        color: #94a3b8;
        margin-top: 0.375rem;
    }

    .auth-side {
        background-color: var(--brand-1);
    }

    .auth-logo {
        height: 5.5rem;
        width: auto;
        max-width: 85%;
    }

    @media (min-width: 640px) {
        .auth-logo {
            height: 6.5rem;
        }
    }
</style>
