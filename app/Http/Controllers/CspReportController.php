<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives Content-Security-Policy violation reports (see SecurityHeaders)
 * and logs them to the "security" channel. Browsers send these without a
 * session or CSRF token, so the route is CSRF-exempt and throttled.
 */
class CspReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $payload = json_decode(mb_substr((string) $request->getContent(), 0, 16384), true);

        // report-uri sends {"csp-report": {...}}; the Reporting API sends a
        // list of {"type": "csp-violation", "body": {...}}.
        $reports = match (true) {
            is_array($payload['csp-report'] ?? null) => [$payload['csp-report']],
            is_array($payload) && array_is_list($payload) => array_column($payload, 'body'),
            default => [],
        };

        foreach (array_slice($reports, 0, 10) as $report) {
            if (! is_array($report)) {
                continue;
            }

            Log::channel('security')->info('CSP violation', [
                'directive' => $this->field($report, 'effective-directive', 'effectiveDirective', 'violated-directive'),
                'blocked' => $this->withoutQuery($this->field($report, 'blocked-uri', 'blockedURL')),
                'page' => $this->withoutQuery($this->field($report, 'document-uri', 'documentURL')),
                'source' => $this->withoutQuery($this->field($report, 'source-file', 'sourceFile')),
                'line' => $this->field($report, 'line-number', 'lineNumber'),
                'disposition' => $this->field($report, 'disposition'),
            ]);
        }

        return response()->noContent();
    }

    private function field(array $report, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($report[$key]) && is_scalar($report[$key])) {
                return mb_substr((string) $report[$key], 0, 300);
            }
        }

        return null;
    }

    /**
     * Signed links (unsubscribe, shared documents) carry their signature in
     * the query string, so it is never written to the log.
     */
    private function withoutQuery(?string $url): ?string
    {
        return $url === null ? null : preg_split('/[?#]/', $url)[0];
    }
}
