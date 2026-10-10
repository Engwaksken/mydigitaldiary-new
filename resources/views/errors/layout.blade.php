<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Something went wrong') · {{ config('app.name', 'My Digital Diary') }}</title>

    <meta name="robots" content="noindex,nofollow">

    {{-- An offline navigation lands on the app's offline page, and an error
         page can be the first thing an installed app renders. Either way the
         manifest link has to be here for the install prompt to stay valid. --}}
    @include('partials.pwa-head')

    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <style>
        :root {
            --error-brand: #0f766e;
            --error-brand-dark: #115e59;
            --error-text: #0f172a;
            --error-muted: #64748b;
            --error-border: #e2e8f0;
            --error-surface: #ffffff;
            --error-bg: #f8fafc;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
            margin: 0;
        }

        body {
            min-height: 100dvh;
            display: grid;
            place-items: center;
            background:
                radial-gradient(
                    circle at top left,
                    rgba(15, 118, 110, .08),
                    transparent 34rem
                ),
                linear-gradient(180deg, #ffffff 0%, var(--error-bg) 100%);
            color: var(--error-text);
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            padding: 24px;
        }

        .error-shell {
            width: min(100%, 720px);
        }

        .error-card {
            overflow: hidden;
            border: 1px solid var(--error-border);
            border-radius: 24px;
            background: rgba(255, 255, 255, .96);
            box-shadow:
                0 24px 70px rgba(15, 23, 42, .10),
                0 4px 14px rgba(15, 23, 42, .05);
        }

        .error-accent {
            height: 5px;
            background:
                linear-gradient(
                    90deg,
                    var(--error-brand),
                    #14b8a6,
                    #5eead4
                );
        }

        .error-content {
            padding: 30px;
        }

        .error-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .error-logo {
            display: grid;
            place-items: center;
            width: 46px;
            height: 46px;
            flex: 0 0 46px;
            border-radius: 14px;
            background: rgba(15, 118, 110, .10);
            color: var(--error-brand);
            font-size: 22px;
            font-weight: 900;
        }

        .error-brand-copy {
            min-width: 0;
        }

        .error-brand-name {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
            color: var(--error-text);
        }

        .error-brand-subtitle {
            margin: 2px 0 0;
            font-size: 12px;
            color: var(--error-muted);
        }

        .error-icon {
            display: grid;
            place-items: center;
            width: 72px;
            height: 72px;
            margin-bottom: 18px;
            border-radius: 22px;
            background: rgba(15, 118, 110, .08);
            color: var(--error-brand);
            font-size: 32px;
            font-weight: 900;
        }

        .error-eyebrow {
            margin: 0 0 8px;
            color: var(--error-brand);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .error-title {
            margin: 0;
            max-width: 620px;
            font-size: clamp(1.65rem, 4vw, 2.35rem);
            line-height: 1.12;
            letter-spacing: -.025em;
            font-weight: 900;
        }

        .error-message {
            margin: 14px 0 0;
            max-width: 620px;
            color: var(--error-muted);
            font-size: 15px;
            line-height: 1.7;
        }

        .error-help {
            margin-top: 22px;
            padding: 14px 16px;
            border: 1px solid #ccfbf1;
            border-radius: 14px;
            background: #f0fdfa;
            color: #115e59;
            font-size: 13px;
            line-height: 1.6;
        }

        .error-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 24px;
        }

        .error-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            padding: 10px 16px;
            border-radius: 12px;
            border: 1px solid transparent;
            text-decoration: none;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
        }

        .error-btn-primary {
            background: var(--error-brand);
            color: #ffffff;
        }

        .error-btn-primary:hover {
            background: var(--error-brand-dark);
        }

        .error-btn-secondary {
            border-color: var(--error-border);
            background: #ffffff;
            color: #334155;
        }

        .error-footer {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 14px 30px 18px;
            border-top: 1px solid var(--error-border);
            color: #94a3b8;
            font-size: 11px;
        }

        .error-reference {
            font-variant-numeric: tabular-nums;
        }

        @media (max-width: 640px) {
            body {
                display: block;
                padding: 12px;
            }

            .error-shell {
                margin: 5vh auto 0;
            }

            .error-card {
                border-radius: 20px;
            }

            .error-content {
                padding: 22px 18px;
            }

            .error-footer {
                padding: 13px 18px 16px;
            }

            .error-actions {
                display: grid;
                grid-template-columns: 1fr;
            }

            .error-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
@php
    $reference = request()->headers->get('X-Request-ID')
        ?: substr(hash('sha256', request()->fullUrl().'|'.microtime(true)), 0, 10);

    $homeRoute = auth()->check() && \Illuminate\Support\Facades\Route::has('dashboard')
        ? route('dashboard')
        : url('/');

    $supportRoute = auth()->check() && \Illuminate\Support\Facades\Route::has('support.index')
        ? route('support.index')
        : null;

    $loginRoute = !auth()->check() && \Illuminate\Support\Facades\Route::has('login')
        ? route('login')
        : null;
@endphp

<main class="error-shell">
    <section class="error-card" aria-labelledby="error-page-heading">
        <div class="error-accent"></div>

        <div class="error-content">
            <div class="error-brand">
                <div class="error-logo" aria-hidden="true">M</div>

                <div class="error-brand-copy">
                    <p class="error-brand-name">
                        {{ config('app.name', 'My Digital Diary') }}
                    </p>

                    <p class="error-brand-subtitle">
                        Your private digital space
                    </p>
                </div>
            </div>

            <div class="error-icon" aria-hidden="true">
                <i class="fa-solid fa-@yield('icon', 'triangle-exclamation')"></i>
            </div>

            <p class="error-eyebrow">
                @yield('eyebrow', 'We could not complete that request')
            </p>

            {{-- The live region is scoped to the heading only. It used to wrap
                 the whole card, which made a screen reader read the brand block,
                 icon, message, help text and every button on load. Everything
                 outside it is ordinary static content. The wrapper is a plain
                 block box with no margins, and .error-title is margin: 0, so
                 the rendered layout is unchanged — and the <h1> keeps its
                 heading semantics instead of being overwritten by a role. --}}
            <div role="alert">
                <h1 class="error-title" id="error-page-heading">
                    @yield('heading', 'Something went wrong')
                </h1>
            </div>

            <p class="error-message">
                @yield(
                    'message',
                    'The request could not be completed. Please try again or return to a safe page.'
                )
            </p>

            @hasSection('help')
                <div class="error-help">
                    @yield('help')
                </div>
            @endif

            <div class="error-actions">
                @hasSection('primary_action')
                    @yield('primary_action')
                @else
                    <a href="{{ $homeRoute }}" class="error-btn error-btn-primary">
                        Go to {{ auth()->check() ? 'Dashboard' : 'Home' }}
                    </a>
                @endif

                <button
                    type="button"
                    class="error-btn error-btn-secondary"
                    onclick="if (history.length > 1) { history.back(); } else { window.location.href = @js($homeRoute); }"
                >
                    Go Back
                </button>

                @if ($supportRoute)
                    <a href="{{ $supportRoute }}" class="error-btn error-btn-secondary">
                        Contact Support
                    </a>
                @elseif ($loginRoute)
                    <a href="{{ $loginRoute }}" class="error-btn error-btn-secondary">
                        Sign In
                    </a>
                @endif
            </div>
        </div>

        <footer class="error-footer">
            <span>
                Technical details have been recorded securely.
            </span>

            <span class="error-reference">
                Reference: {{ strtoupper($reference) }}
            </span>
        </footer>
    </section>
</main>
</body>
</html>
