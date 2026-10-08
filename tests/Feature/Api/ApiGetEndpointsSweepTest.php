<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Smoke-tests every parameterless, authenticated GET API route the mobile
 * app can hit: none of them may answer with a 5xx for a fresh user.
 */
class ApiGetEndpointsSweepTest extends TestCase
{
    use RefreshDatabase;

    /** Routes that stream files or call third parties; covered elsewhere. */
    private const SKIP = [
        '#/report/pdf$#',
        '#/pdf$#',
        '#export#',
        '#download#',
    ];

    public function test_no_parameterless_get_endpoint_returns_a_server_error(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $failures = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/') || ! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (str_contains($uri, '{')) {
                continue;
            }
            foreach (self::SKIP as $pattern) {
                if (preg_match($pattern, $uri)) {
                    continue 2;
                }
            }

            foreach (['', '?archived=0&page=1', '?archived=1&page=1'] as $query) {
                $response = $this->getJson('/'.$uri.$query);
                if ($response->getStatusCode() >= 500) {
                    $failures[] = $uri.$query.' -> '.$response->getStatusCode().' '.mb_substr((string) $response->getContent(), 0, 200);
                }
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }
    /** Real query strings the mobile app sends (see the Flutter services). */
    public function test_mobile_app_parameterised_get_urls_do_not_return_server_errors(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $month = now()->format('Y-m');
        $date = now()->toDateString();

        $urls = [
            'personal-goals?archived=0&page=1',
            'personal-goals?per_page=100',
            'personal-goals?page=1&per_page=100',
            'personal-goals?archived=0&page=1&period=month',
            'personal-goals?archived=0&page=1&q=run',
            'annual-plans?year='.now()->year.'&per_page=100',
            "budgets?month={$month}",
            "daily-planner?date={$date}",
            'daily-planner/history?period=all&per_page=50',
            'daily-steps?days=7',
            'engagement/today',
            'engagement/review/week',
            "monthly-review?month={$month}",
            'reminders?scope=today',
            'reminders?scope=upcoming',
            'reminders/overview?scope=today',
            'reminders/items-for-module?module=personal-goals',
            'search?q=test',
            'social-media-planner/ready-to-share',
            'wellbeing/steps',
            'wellbeing/steps/history?days=7',
            'expenses/budget-options',
            'expenses/summary',
            'incomes/stats',
            'dashboard/today-hub',
            'goal-intelligence',
            'organization/access-context',
            'team-chat/conversations',
        ];

        $failures = [];
        foreach ($urls as $url) {
            $response = $this->getJson('/api/'.$url);
            if ($response->getStatusCode() >= 500) {
                $failures[] = $url.' -> '.$response->getStatusCode();
            }
        }

        $this->assertSame([], $failures, implode("
", $failures));
    }
}
