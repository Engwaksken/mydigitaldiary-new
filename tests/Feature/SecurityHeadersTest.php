<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_send_origin_and_permission_headers(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

        $this->assertSame("frame-ancestors 'self'; base-uri 'self'; object-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('report-uri /csp-report', $response->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertStringContainsString('microphone=(self)', $response->headers->get('Permissions-Policy'));
        $this->assertStringContainsString('geolocation=()', $response->headers->get('Permissions-Policy'));
    }

    public function test_hsts_is_not_sent_outside_production(): void
    {
        $this->get('https://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_csp_reports_are_accepted_without_csrf_and_logged_without_query_strings(): void
    {
        $logger = Mockery::spy();
        Log::shouldReceive('channel')->with('security')->andReturn($logger);

        $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode([
            'csp-report' => [
                'document-uri' => 'https://my-digital-diary.com/appreciation/unsubscribe/5?signature=secret',
                'violated-directive' => 'script-src-elem',
                'blocked-uri' => 'https://evil.example/x.js?token=abc',
            ],
        ]))->assertNoContent();

        $logger->shouldHaveReceived('info')->withArgs(fn (string $message, array $context) => $message === 'CSP violation'
            && $context['page'] === 'https://my-digital-diary.com/appreciation/unsubscribe/5'
            && $context['blocked'] === 'https://evil.example/x.js'
            && $context['directive'] === 'script-src-elem');
    }
}
