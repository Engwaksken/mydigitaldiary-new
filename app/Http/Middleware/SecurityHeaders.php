<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser security headers on every response.
 *
 * Origin restrictions that matter as browsers start acting for people (AI
 * agents, extensions): another site cannot frame these pages to trick a user
 * or agent into clicking, cannot learn full URLs through the Referer header,
 * and cannot use device features the app never asks for.
 *
 * The full Content-Security-Policy runs in report-only mode first. The app
 * still relies on inline scripts and several CDNs, so enforcing it today
 * would break pages; violations are posted to /csp-report and logged to
 * storage/logs/security-*.log, so the policy can be tightened from real data
 * and then moved to enforcement. The small enforced policy below has no
 * such risk: nothing in the app frames cross-origin, sets <base> or embeds
 * plugins.
 */
class SecurityHeaders
{
    private const ENFORCED_CSP = "frame-ancestors 'self'; base-uri 'self'; object-src 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        // Popups the app opens (OAuth, payments) keep working; other sites
        // that open this app lose their handle on its window.
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups', false);
        // Meeting recording needs the microphone, camera and screen capture.
        $headers->set('Permissions-Policy', implode(', ', [
            'microphone=(self)',
            'camera=(self)',
            'display-capture=(self)',
            'geolocation=()',
            'usb=()',
            'serial=()',
            'hid=()',
            'bluetooth=()',
            'accelerometer=()',
            'gyroscope=()',
            'magnetometer=()',
            'browsing-topics=()',
        ]), false);

        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', self::ENFORCED_CSP);
        }

        if (! $headers->has('Content-Security-Policy-Report-Only')) {
            $headers->set('Content-Security-Policy-Report-Only', $this->reportOnlyPolicy());
        }

        // HTTPS only, and never from local development, where a cached HSTS
        // entry would break plain-http testing on that hostname for a year.
        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000', false);
        }

        return $response;
    }

    private function reportOnlyPolicy(): string
    {
        $cdns = 'https://cdnjs.cloudflare.com https://cdn.jsdelivr.net';

        return implode('; ', [
            "default-src 'self'",
            // Alpine.js evaluates its directives, so 'unsafe-eval' stays until
            // the app moves to Alpine's CSP build.
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com {$cdns} https://www.gstatic.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net {$cdns}",
            "font-src 'self' data: https://fonts.gstatic.com https://fonts.bunny.net {$cdns}",
            "img-src 'self' data: blob: https:",
            "media-src 'self' blob:",
            // Firebase web push registers through *.googleapis.com.
            "connect-src 'self' https://*.googleapis.com https://www.gstatic.com {$cdns}",
            "frame-src 'self' blob: data:",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self' https:",
            "frame-ancestors 'self'",
            'report-uri '.route('csp.report', [], false),
        ]);
    }
}
