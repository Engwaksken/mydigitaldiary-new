<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API counterpart of Laravel's 'verified' middleware, registered as the
 * 'verified.api' alias. Laravel's own EnsureEmailIsVerified only answers
 * with JSON when the client sends "Accept: application/json"; otherwise it
 * redirects to the web verification.notice page, which is useless to the
 * Flutter app. This always answers with a stable, machine-readable 403 so
 * the app can show its "verify your email" screen.
 *
 * Deliberately NOT applied to: me, logout, email/verification-notification
 * and email/verification-status (see routes/api.php).
 */
final class EnsureApiEmailIsVerified
{
    public const CODE = 'email_unverified';

    public const MESSAGE = 'Please verify your email address to continue.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => self::MESSAGE,
                'code' => self::CODE,
            ], 403);
        }

        return $next($request);
    }
}
