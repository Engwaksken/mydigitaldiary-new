<?php

namespace Tests\Feature;

use App\Mail\WeeklyAppreciationMail;
use App\Models\AppreciationEmailLog;
use App\Models\User;
use App\Services\Ai\ActiveAiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class WeeklyAppreciationEmailTest extends TestCase
{
    use RefreshDatabase;

    private const AI_MESSAGE = 'Thank you for being part of My Digital Diary, Amina. Every plan, note and reminder you add is a small promise to yourself, and we are honoured to help you keep it. This week, try opening your diary for just a few quiet minutes and choosing one thing that matters most to you.'
        ."\n\n".'You do not need a perfect routine to make real progress. A single note or a short plan for tomorrow is a wonderful start, and your diary will be right here whenever you are ready. We are cheering you on.';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-19 09:00:00', 'Africa/Kampala'));
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fakeAi(?callable $response = null): void
    {
        $this->mock(ActiveAiClient::class, function ($mock) use ($response): void {
            $mock->shouldReceive('json')->andReturnUsing($response ?? fn () => [
                'subject' => 'Thank you for joining us, Amina',
                'message' => self::AI_MESSAGE,
            ]);
        });
    }

    public function test_generates_ai_message_and_queues_email_for_eligible_users(): void
    {
        $prompts = [];
        $this->fakeAi(function (string $system, string $prompt, $user) use (&$prompts) {
            $prompts[] = $prompt;
            $this->assertNull($user, 'the shared admin-configured AI key is used, not a personal one');

            return ['subject' => 'Thank you for joining us, Amina', 'message' => self::AI_MESSAGE.' <script>alert(1)</script>'];
        });
        Mail::fake();

        $user = User::factory()->create(['name' => 'Amina Nakato', 'created_at' => now()->subMonths(3)]);
        $user->reminders()->create(['title' => 'Private reminder text', 'frequency' => 'once', 'next_run_at' => now()->addDay(), 'channel' => 'database', 'is_active' => true]);
        $unverified = User::factory()->unverified()->create();
        $suspended = User::factory()->create(['suspended_at' => now()]);
        $deleting = User::factory()->create(['scheduled_deletion_at' => now()->addDays(10)]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->artisan('appreciation:send-weekly')->assertSuccessful();

        Mail::assertQueued(WeeklyAppreciationMail::class, 1);
        Mail::assertQueued(WeeklyAppreciationMail::class, function (WeeklyAppreciationMail $mail) use ($user) {
            $html = $mail->render();

            return $mail->hasTo($user->email)
                && $mail->subjectLine === 'Thank you for joining us, Amina'
                && str_contains($html, 'Every plan, note and reminder')
                && ! str_contains($html, '<script>')
                && str_contains($html, 'Open My Digital Diary')
                && str_contains($html, e(WeeklyAppreciationMail::unsubscribeUrl($user)));
        });
        foreach ([$unverified, $suspended, $deleting, $admin] as $skipped) {
            Mail::assertNotQueued(WeeklyAppreciationMail::class, fn ($mail) => $mail->hasTo($skipped->email));
        }

        $this->assertCount(1, $prompts);
        $this->assertStringContainsString('Amina', $prompts[0]);
        $this->assertStringContainsString('3 months', $prompts[0]);
        $this->assertStringContainsString('Reminders', $prompts[0]);
        $this->assertStringContainsString('2026-W43', $prompts[0]);
        $this->assertStringNotContainsString('Private reminder text', $prompts[0]);

        $log = AppreciationEmailLog::where('user_id', $user->id)->first();
        $this->assertSame('ai', $log->source);
        $this->assertSame('2026-W43', $log->week_key);
        $this->assertNotNull($log->sent_at);
    }

    public function test_falls_back_to_built_in_message_when_ai_fails(): void
    {
        $this->fakeAi(fn () => throw new RuntimeException('No default AI provider/API key is configured in Admin Settings.'));
        Mail::fake();

        $users = User::factory()->count(5)->create();

        $this->artisan('appreciation:send-weekly')->assertSuccessful();

        Mail::assertQueued(WeeklyAppreciationMail::class, 5);
        Mail::assertQueued(WeeklyAppreciationMail::class, fn (WeeklyAppreciationMail $mail) => $mail->paragraphs !== []
            && str_contains(implode(' ', $mail->paragraphs), \App\Services\AppreciationMessageService::firstName($mail->user)));
        $this->assertSame(5, AppreciationEmailLog::where('source', 'fallback')->count());
    }

    public function test_fallback_does_not_repeat_last_weeks_text(): void
    {
        $this->fakeAi(fn () => throw new RuntimeException('timeout'));
        Mail::fake();
        $user = User::factory()->create();

        $this->artisan('appreciation:send-weekly')->assertSuccessful();
        Carbon::setTestNow(now()->addWeek());
        $this->artisan('appreciation:send-weekly')->assertSuccessful();

        $messages = AppreciationEmailLog::where('user_id', $user->id)->orderBy('id')->pluck('message');
        $this->assertCount(2, $messages);
        $this->assertNotSame($messages[0], $messages[1]);
    }

    public function test_opted_out_users_are_skipped(): void
    {
        $this->fakeAi();
        Mail::fake();

        $optedOut = User::factory()->create(['engagement_notification_preferences' => ['weekly_appreciation' => false]]);
        $optedIn = User::factory()->create();

        $this->artisan('appreciation:send-weekly')->assertSuccessful();

        Mail::assertQueued(WeeklyAppreciationMail::class, 1);
        Mail::assertQueued(WeeklyAppreciationMail::class, fn ($mail) => $mail->hasTo($optedIn->email));
        $this->assertDatabaseMissing('appreciation_email_logs', ['user_id' => $optedOut->id]);
    }

    public function test_never_sends_twice_in_the_same_week(): void
    {
        $this->fakeAi();
        Mail::fake();
        $user = User::factory()->create();

        $this->artisan('appreciation:send-weekly')->assertSuccessful();
        Carbon::setTestNow(now()->addDays(3));
        $this->artisan('appreciation:send-weekly')->assertSuccessful();
        $this->artisan('appreciation:send-weekly', ['--user' => $user->id])->assertSuccessful();

        Mail::assertQueued(WeeklyAppreciationMail::class, 1);
        $this->assertSame(1, AppreciationEmailLog::where('user_id', $user->id)->count());

        Carbon::setTestNow(now()->addWeek());
        $this->artisan('appreciation:send-weekly')->assertSuccessful();
        Mail::assertQueued(WeeklyAppreciationMail::class, 2);
    }

    public function test_dry_run_and_user_option(): void
    {
        $this->fakeAi();
        Mail::fake();
        $target = User::factory()->create(['email' => 'target@example.com']);
        User::factory()->create();

        $this->artisan('appreciation:send-weekly', ['--dry-run' => true, '--user' => 'target@example.com'])
            ->expectsOutputToContain('target@example.com')
            ->assertSuccessful();
        Mail::assertNothingQueued();
        $this->assertDatabaseCount('appreciation_email_logs', 0);

        $this->artisan('appreciation:send-weekly', ['--user' => 'target@example.com'])->assertSuccessful();
        Mail::assertQueued(WeeklyAppreciationMail::class, 1);
        Mail::assertQueued(WeeklyAppreciationMail::class, fn ($mail) => $mail->hasTo($target->email));
    }

    public function test_signed_unsubscribe_link_opts_the_user_out(): void
    {
        $user = User::factory()->create();
        $url = WeeklyAppreciationMail::unsubscribeUrl($user);

        $this->get($url)->assertOk()->assertSee('Unsubscribe');
        $this->assertTrue($user->fresh()->engagementNotificationEnabled('weekly_appreciation'), 'GET only confirms');

        $this->post($url)->assertOk()->assertSee('You have been unsubscribed');
        $this->assertFalse($user->fresh()->engagementNotificationEnabled('weekly_appreciation'));

        $this->get(route('appreciation.unsubscribe', ['user' => $user->id]))->assertForbidden();
        $this->post(route('appreciation.unsubscribe.confirm', ['user' => $user->id]))->assertForbidden();

        $this->fakeAi();
        Mail::fake();
        $this->artisan('appreciation:send-weekly')->assertSuccessful();
        Mail::assertNothingQueued();
    }

    public function test_schedule_runs_weekly_on_monday_morning(): void
    {
        Artisan::call('schedule:list');
        $this->assertMatchesRegularExpression('/0\s+9\s+\*\s+\*\s+1\s+.*appreciation:send-weekly/', Artisan::output());
    }
}
