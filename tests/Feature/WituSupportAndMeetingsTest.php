<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\SiteSetting;
use App\Models\SupportConversation;
use App\Models\User;
use App\Services\MeetingSummaryService;
use App\Services\SupportAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WituSupportAndMeetingsTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'uhk_live_abcde12345_ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopq';

    private const GATEWAY = 'https://ai.witulibrary.org/gw/v1/chat/completions';

    private function configureWitu(string $defaultProvider = 'witu'): void
    {
        AiProvider::query()->updateOrCreate(['key' => 'witu'], [
            'name' => 'WITU AI',
            'api_base_url' => 'https://ai.witulibrary.org/gw/v1',
            'default_model' => 'qwen2.5:1.5b',
            'is_enabled' => true,
        ]);

        SiteSetting::current()->forceFill([
            'default_ai_provider' => $defaultProvider,
            'default_ai_api_key' => self::KEY,
        ])->save();
    }

    private function conversation(): SupportConversation
    {
        $user = User::factory()->create();
        $conversation = SupportConversation::create(['user_id' => $user->id, 'status' => 'open', 'subject' => 'Help']);
        $conversation->messages()->create(['user_id' => $user->id, 'sender_type' => 'user', 'message' => 'How do I add a budget?']);

        return $conversation;
    }

    private function fakeReply(string $content): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => $content]]]])]);
    }

    public function test_support_reply_uses_witu(): void
    {
        $this->configureWitu();
        $this->fakeReply('**Open** Budgets, then tap Add.');

        $reply = app(SupportAiService::class)->reply($this->conversation(), 'How do I add a budget?');

        $this->assertSame('Open Budgets, then tap Add.', $reply);
        Http::assertSent(fn (Request $request) => $request->url() === self::GATEWAY
            && $request['model'] === 'qwen2.5:1.5b'
            && $request['messages'][0]['role'] === 'system'
            && end($request->data()['messages'])['content'] === 'How do I add a budget?'
            && $request->hasHeader('Authorization', 'Bearer ' . self::KEY));
    }

    public function test_support_reply_with_chatgpt_selected_still_reaches_witu(): void
    {
        $this->configureWitu('openai');
        $this->fakeReply('Hello');

        app(SupportAiService::class)->reply($this->conversation(), 'Hi');

        Http::assertSent(fn (Request $request) => $request->url() === self::GATEWAY);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'api.openai.com'));
    }

    public function test_support_reply_falls_back_when_the_provider_fails(): void
    {
        $this->configureWitu();
        Http::fake(['*' => Http::response(['error' => ['message' => 'down']], 503)]);

        $reply = app(SupportAiService::class)->reply($this->conversation(), 'Hi');

        $this->assertStringContainsString('saved for support follow-up', $reply);
    }

    public function test_support_reply_still_uses_openai_with_an_openai_key(): void
    {
        SiteSetting::current()->forceFill(['default_ai_provider' => 'openai', 'default_ai_api_key' => 'sk-shared'])->save();
        $this->fakeReply('Hello');

        app(SupportAiService::class)->reply($this->conversation(), 'Hi');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request['model'] === 'gpt-4o-mini');
    }

    public function test_meeting_summary_with_chatgpt_selected_uses_witu(): void
    {
        $this->configureWitu('openai');
        $this->fakeReply('{"main_points":["Budget agreed"],"decisions":[],"action_items":[],"questions_for_followup":[]}');

        $summary = app(MeetingSummaryService::class)->generate(User::factory()->create(), 'We agreed the budget.');

        $this->assertSame(['Budget agreed'], $summary['main_points']);
        Http::assertSent(fn (Request $request) => $request->url() === self::GATEWAY
            && $request['model'] === 'qwen2.5:1.5b');
    }
}
