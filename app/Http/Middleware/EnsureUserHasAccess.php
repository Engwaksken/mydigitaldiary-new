<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the app behind an active trial or paid subscription. A user
 * whose trial/subscription has expired can still log in and reach a
 * small set of essential pages (dashboard, their own profile, billing,
 * privacy, help) — everything else, including every tracking module
 * (Finance, Health & Wellness, Work & Projects, Personal Life,
 * Productivity & AI), is blocked entirely and redirected straight to
 * the subscription page with a clear message, rather than a confusing
 * bounce back to wherever they came from.
 */
class EnsureUserHasAccess
{
    /**
     * Reachable regardless of subscription state — otherwise an
     * expired user could get stuck unable to even view their own
     * profile or actually pay to renew.
     */
    private const ALWAYS_ALLOWED_ROUTES = [
        'dashboard',
        'profile.edit', 'profile.update', 'profile.destroy', 'profile.password',
        'profile.avatar', 'profile.avatar.remove', 'profile.theme',
        'subscription.show', 'subscription.subscribe', 'subscription.cancel',
        'subscription.pay.card', 'subscription.pay.card.callback', 'subscription.pay.manual',
        'subscription.invoice', 'subscription.receipt', 'subscription.receipt.view',
        'privacy.show', 'privacy.destroy-account', 'privacy.export.request', 'privacy.export.download',
        'help.show',
        'organization.show', 'organization.invite',
        'organization.members.activate', 'organization.members.deactivate',
        'organization.members.replace', 'organization.members.remove',
        'enterprise.contact', 'enterprise.contact.submit',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->hasActiveAccess()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (in_array($routeName, self::ALWAYS_ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        return $this->deny($request);
    }

    private function deny(Request $request): Response
    {
        $message = 'Your trial or subscription has ended. Subscribe to continue using this feature.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 402);
        }

        return redirect()->route('subscription.show')->withErrors(['subscription' => $message]);
    }
}
