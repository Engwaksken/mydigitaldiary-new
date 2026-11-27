<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Ai\ActiveAiClient;
use App\Services\Ai\ProviderEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WituAiEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'uhk_live_abcde12345_ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopq';

    private function configureWitu(string $baseUrl, string $key = self::KEY): void
    {
        AiProvider::query()->updateOrCreate(['key' => 'witu'], [
            'name' => 'WITU AI',
            'api_base_url' => $baseUrl,
            'default_model' => 'qwen2.5:1.5b',
            'is_enabled' => true,
        ]);

        SiteSetting::current()->forceFill([
            'default_ai_provider' => 'witu',
            'default_ai_api_key' => $key,
        ])->save();
    }

    public static function wituUrls(): array
    {
        return [
            'bare host' => ['https://ai.witulibrary.org'],
            'legacy /v1' => ['https://ai.witulibrary.org/v1'],
            'legacy full endpoint' => ['https://ai.witulibrary.org/v1/chat/completions'],
            'gw without version' => ['https://ai.witulibrary.org/gw/'],
            'correct base' => ['https://ai.witulibrary.org/gw/v1'],
        ];
    }

    /** @dataProvider wituUrls */
    public function test_witu_keys_always_go_to_the_gw_v1_gateway(string $baseUrl): void
    {
        $this->assertSame(
            'https://ai.witulibrary.org/gw/v1/chat/completions',
            ProviderEndpoint::chatCompletions($baseUrl, self::KEY)
        );
    }

    public function test_other_endpoints_are_normalised_without_guessing(): void
    {
        $this->assertSame('https://x.example/v1/chat/completions', ProviderEndpoint::chatCompletions('https://x.example/v1/', 'k'));
        $this->assertSame('https://x.example/v1/chat/completions', ProviderEndpoint::chatCompletions('https://x.example', 'k'));
        $this->assertSame('https://x.example/custom/path', ProviderEndpoint::chatCompletions('https://x.example/custom/path', 'k'));
        // A non-WITU key on the WITU host is left alone (legacy gateway keys).
        $this->assertSame('https://ai.witulibrary.org/v1/chat/completions', ProviderEndpoint::chatCompletions('https://ai.witulibrary.org/v1', 'legacy-key'));
    }

    public function test_active_client_calls_the_gateway_with_the_project_key(): void
    {
        $this->configureWitu('https://ai.witulibrary.org/v1');
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"status":"ok"}']]]])]);

        app(ActiveAiClient::class)->testConnection();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://ai.witulibrary.org/gw/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer ' . self::KEY));
    }

    public function test_a_401_names_the_endpoint_and_the_gateway_url(): void
    {
        $this->configureWitu('https://ai.witulibrary.org/gw/v1');
        Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid or missing credentials']], 401)]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.settings.ai.test-connection'))
            ->assertSessionHasErrors('ai_connection');

        $message = session('errors')->first('ai_connection');
        $this->assertStringContainsString('WITU AI key was rejected (HTTP 401) by https://ai.witulibrary.org/gw/v1/chat/completions', $message);
        $this->assertStringContainsString('issue a new key', $message);
        $this->assertStringNotContainsString(self::KEY, $message);
    }

    public function test_a_truncated_witu_key_is_called_out(): void
    {
        $this->configureWitu('https://ai.witulibrary.org/gw/v1', 'uhk_live_abcde12345_short');
        Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid or missing credentials']], 401)]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.settings.ai.test-connection'))
            ->assertSessionHasErrors('ai_connection');

        $this->assertStringContainsString('not a complete WITU AI key', session('errors')->first('ai_connection'));
    }
}
