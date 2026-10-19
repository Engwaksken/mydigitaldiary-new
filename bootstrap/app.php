<?php

use App\Http\Middleware\EnsureApiEmailIsVerified;
use App\Http\Middleware\EnsureIdempotentMobileWrite;
use App\Http\Middleware\EnsureUserHasAccess;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsSupportStaff;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        /*
        |--------------------------------------------------------------------------
        | Middleware aliases
        |--------------------------------------------------------------------------
        */
        $middleware->alias([
            'subscribed' => EnsureUserHasAccess::class,
            'admin' => EnsureUserIsAdmin::class,
            'support.staff' => EnsureUserIsSupportStaff::class,
            'mobile.idempotent' => EnsureIdempotentMobileWrite::class,
            'verified.api' => EnsureApiEmailIsVerified::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Email verification
        |--------------------------------------------------------------------------
        |
        | Every authenticated web route group carries Laravel's 'verified'
        | middleware, and every authenticated API group carries 'verified.api'
        | (JSON 403, code=email_unverified). Unverified web users are sent to
        | the verification notice page.
        | Exemptions are listed next to each route in routes/auth.php,
        | routes/web.php and routes/api.php.
        |
        */

        /*
        |--------------------------------------------------------------------------
        | CSRF exclusions
        |--------------------------------------------------------------------------
        |
        | ioTec sends payment callbacks directly from its servers, so these
        | webhook endpoints must not require Laravel's browser CSRF token.
        |
        | IMPORTANT:
        | Only webhook endpoints are excluded.
        | Normal subscription payment forms remain CSRF protected.
        |
        */
        $middleware->validateCsrfTokens(except: [
            'webhooks/iotec',
            'webhooks/iotec/subscriptions',
            // Browsers post CSP reports with no session or token.
            'csp-report',
        ]);

        // Framing, referrer, device-permission and CSP headers on every
        // response (app/Http/Middleware/SecurityHeaders.php).
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
