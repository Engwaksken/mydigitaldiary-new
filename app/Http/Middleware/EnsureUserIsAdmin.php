<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the entire /admin area. Registered as the 'admin' route middleware
 * alias (see bootstrap/app.php additions in README) and applied ONLY to
 * routes/admin.php - never to any route that exposes a specific user's own
 * tracked data (plans, expenses, health, etc.). Admins manage ACCOUNTS
 * (subscription status, suspension, role, platform statistics) - by design
 * there is no admin route anywhere that reads another user's plans,
 * expenses, health records, diet logs, or any other personal module data.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            abort(403, 'You do not have access to the admin area.');
        }

        return $next($request);
    }
}
