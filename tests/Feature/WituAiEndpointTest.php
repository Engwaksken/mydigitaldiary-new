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
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('wituUrls')]
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
            ->assertSessionHasErrors('default_ai_api_key');

        $message = session('errors')->first('default_ai_api_key');
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
            ->assertSessionHasErrors('default_ai_api_key');

        $this->assertStringContainsString('not a complete WITU AI key', session('errors')->first('default_ai_api_key'));
    }

    public function test_witu_key_with_chatgpt_selected_uses_the_witu_provider(): void
    {
        // The reported case: Default Provider = ChatGPT, key = WITU key,
        // which used to send the key to api.openai.com and get a 401.
        $this->configureWitu('https://ai.witulibrary.org/gw/v1');
        SiteSetting::current()->forceFill(['default_ai_provider' => 'openai'])->save();
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"status":"ok"}']]]])]);

        app(ActiveAiClient::class)->testConnection();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://ai.witulibrary.org/gw/v1/chat/completions'
            && $request['model'] === 'qwen2.5:1.5b');
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'api.openai.com'));
    }

    public function test_witu_key_without_a_witu_provider_flags_the_default_provider(): void
    {
        SiteSetting::current()->forceFill([
            'default_ai_provider' => 'openai',
            'default_ai_api_key' => self::KEY,
        ])->save();
        Http::fake();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.settings.ai.test-connection'))
            ->assertSessionHasErrors('default_ai_provider');

        $this->assertStringContainsString('Use WITU AI preset', session('errors')->first('default_ai_provider'));
        Http::assertNothingSent();
    }

    private function saveAiSettings(array $overrides): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->from(route('admin.settings.edit', ['tab' => 'ai']))
            ->post(route('admin.settings.update'), array_merge([
                'settings_section' => 'ai',
                'site_name' => 'My Digital Diary',
                'monthly_price' => 5,
                'default_currency_code' => 'USD',
                'default_currency_symbol' => '$',
                'default_currency_decimals' => 2,
                'trial_days' => 14,
                'default_ai_free_limit_per_month' => 5,
            ], $overrides));
    }

    public function test_saving_a_witu_key_with_chatgpt_is_rejected_on_the_provider_field(): void
    {
        $this->configureWitu('https://ai.witulibrary.org/gw/v1');

        $this->saveAiSettings(['default_ai_provider' => 'openai', 'default_ai_api_key' => self::KEY])
            ->assertSessionHasErrors('default_ai_provider');

        $this->assertStringContainsString('must be WITU AI', session('errors')->first('default_ai_provider'));
        $this->assertSame('witu', SiteSetting::current()->fresh()->default_ai_provider);
    }

    public function test_saving_an_openai_key_for_witu_is_rejected_on_the_key_field(): void
    {
        $this->configureWitu('https://ai.witulibrary.org/gw/v1');

        $this->saveAiSettings(['default_ai_provider' => 'witu', 'default_ai_api_key' => 'sk-not-a-witu-key'])
            ->assertSessionHasErrors('default_ai_api_key');
    }

    public function test_saving_a_matching_witu_configuration_succeeds(): void
    {
        $this->configureWitu('https://ai.witulibrary.org/gw/v1');

        $this->saveAiSettings(['default_ai_provider' => 'witu', 'default_ai_api_key' => self::KEY])
            ->assertSessionHasNoErrors();
    }

    public function test_ai_tab_highlights_the_field_and_renders_structured_modals(): void
    {
        $this->configureWitu('https://ai.witulibrary.org/gw/v1');
        $errors = (new \Illuminate\Support\ViewErrorBag())->put('default', new \Illuminate\Support\MessageBag([
            'default_ai_provider' => 'Pick WITU AI.',
        ]));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->withSession(['errors' => $errors])
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('aria-describedby="default_ai_provider-error"', false)
            ->assertSee('Pick WITU AI.')
            ->assertSee('class="pm-modal-header"', false)
            ->assertSee('class="pm-modal-body space-y-4"', false)
            ->assertSee('Use WITU AI preset');
    }

    public function test_provider_validation_errors_reopen_the_add_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.settings.edit'))
            ->post(route('admin.ai-providers.store'), ['modal_id' => 'ai-provider-create-modal', 'name' => 'WITU AI'])
            ->assertSessionHasErrors(['key', 'api_base_url', 'default_model']);

        $this->get(route('admin.settings.edit'))
            ->assertSee('data-initial-show="true"', false);
    }

    public function test_adding_a_provider_returns_to_the_ai_tab(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.ai-providers.store'), [
                'name' => 'WITU AI',
                'key' => 'witu',
                'api_base_url' => 'https://ai.witulibrary.org/gw/v1',
                'default_model' => 'qwen2.5:1.5b',
                'is_enabled' => 1,
            ])
            ->assertRedirect(route('admin.settings.edit', ['tab' => 'ai']));
    }
}
