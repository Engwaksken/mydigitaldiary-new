<?php

namespace Tests\Feature;

use App\Jobs\SendAppreciationToUsersJob;
use App\Mail\WeeklyAppreciationMail;
use App\Models\AppreciationEmailLog;
use App\Models\User;
use App\Services\Ai\ActiveAiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class AdminBulkAppreciationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-19 09:00:00', 'Africa/Kampala'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_bulk_action_opens_the_preview_instead_of_sending(): void
    {
        Bus::fake();
        $users = User::factory()->count(2)->create();
        $ids = $users->pluck('id')->implode(',');

        $this->actingAs($this->admin())
            ->post(route('admin.users.bulk'), ['ids' => $users->pluck('id')->all(), 'action' => 'appreciation'])
            ->assertRedirect(route('admin.users.appreciation.preview', ['ids' => $ids]));

        Bus::assertNotDispatched(SendAppreciationToUsersJob::class);
    }

    public function test_preview_lists_recipients_and_skipped_users_with_reasons(): void
    {
        $eligible = User::factory()->create(['name' => 'Amina Nakato']);
        $unverified = User::factory()->unverified()->create(['name' => 'Una Verified']);
        $optedOut = User::factory()->create(['name' => 'Otto Out', 'engagement_notification_preferences' => ['weekly_appreciation' => false]]);
        $alreadySent = User::factory()->create(['name' => 'Sam Sent']);
        AppreciationEmailLog::create(['user_id' => $alreadySent->id, 'week_key' => '2026-W43', 'source' => 'ai', 'sent_at' => now()]);

        $ids = collect([$eligible, $unverified, $optedOut, $alreadySent])->pluck('id')->implode(',');

        $this->actingAs($this->admin())
            ->get(route('admin.users.appreciation.preview', ['ids' => $ids]))
            ->assertOk()
            ->assertSee('Amina Nakato')
            ->assertSee(route('admin.users.appreciation.preview-email', $eligible), false)
            ->assertSeeInOrder(['Skipped', 'Otto Out', 'Opted out', 'Sam Sent', 'Already sent this week', 'Una Verified', 'Email not verified'])
            ->assertSee('Send to 1 user')
            ->assertSee('name="ids[]" value="'.$eligible->id.'"', false)
            ->assertDontSee('name="ids[]" value="'.$unverified->id.'"', false);
    }

    public function test_previewed_message_is_exactly_the_one_sent(): void
    {
        $calls = 0;
        $this->mock(ActiveAiClient::class, function ($mock) use (&$calls) {
            $mock->shouldReceive('json')->andReturnUsing(function () use (&$calls) {
                $calls++;

                return ['subject' => "Thanks for being here (draft {$calls})", 'message' => str_repeat('A short, warm note for you this week. ', 8)."Draft {$calls}."];
            });
        });
        Mail::fake();
        $user = User::factory()->create(['name' => 'Amina Nakato']);
        $admin = $this->admin();

        $preview = $this->actingAs($admin)->getJson(route('admin.users.appreciation.preview-email', $user))
            ->assertOk()
            ->assertJsonPath('subject', 'Thanks for being here (draft 1)')
            ->assertJsonPath('source', 'ai');
        $this->assertStringContainsString('Draft 1.', $preview->json('html'));

        // Opening it again shows the same draft; regenerating writes a new one.
        $this->getJson(route('admin.users.appreciation.preview-email', $user))->assertJsonPath('subject', 'Thanks for being here (draft 1)');
        $this->getJson(route('admin.users.appreciation.preview-email', [$user, 'regenerate' => 1]))->assertJsonPath('subject', 'Thanks for being here (draft 2)');

        $this->post(route('admin.users.appreciation.send'), ['ids' => [$user->id]])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success', 'Appreciation email is being sent to 1 user.');

        Mail::assertQueued(WeeklyAppreciationMail::class, fn ($mail) => $mail->hasTo($user->email)
            && $mail->subjectLine === 'Thanks for being here (draft 2)');
        $this->assertSame(2, $calls, 'sending reuses the previewed draft instead of writing a third');
    }

    public function test_preview_email_is_refused_for_ineligible_users(): void
    {
        $unverified = User::factory()->unverified()->create();

        $this->actingAs($this->admin())
            ->getJson(route('admin.users.appreciation.preview-email', $unverified))
            ->assertStatus(422);
    }

    public function test_send_reports_skipped_users_and_queues_only_eligible_ones(): void
    {
        Bus::fake();
        $eligible = User::factory()->count(2)->create();
        $unverified = User::factory()->unverified()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.users.appreciation.send'), ['ids' => [...$eligible->pluck('id'), $unverified->id]])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success', 'Appreciation email is being sent to 2 users. 1 skipped (unverified, admin, suspended, opted out or already sent this week).');

        Bus::assertDispatched(SendAppreciationToUsersJob::class, fn ($job) => collect($job->userIds)->sort()->values()->all() === $eligible->pluck('id')->sort()->values()->all());
    }

    public function test_send_reports_when_nobody_is_eligible(): void
    {
        Bus::fake();
        $unverified = User::factory()->unverified()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.users.appreciation.send'), ['ids' => [$unverified->id]])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors('bulk');

        Bus::assertNotDispatched(SendAppreciationToUsersJob::class);
    }

    public function test_job_sends_one_email_per_user_and_respects_the_weekly_limit(): void
    {
        $this->mock(ActiveAiClient::class, fn ($mock) => $mock->shouldReceive('json')->andThrow(new RuntimeException('no AI')));
        Mail::fake();
        $users = User::factory()->count(2)->create();

        (new SendAppreciationToUsersJob($users->pluck('id')->all(), '2026-W43'))->handle(app(\App\Services\AppreciationSender::class));
        (new SendAppreciationToUsersJob($users->pluck('id')->all(), '2026-W43'))->handle(app(\App\Services\AppreciationSender::class));

        Mail::assertQueued(WeeklyAppreciationMail::class, 2);
        $this->assertSame(2, AppreciationEmailLog::where('week_key', '2026-W43')->count());
    }

    public function test_users_page_offers_the_appreciation_bulk_action(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('<option value="appreciation">Send Appreciation Email</option>', false)
            ->assertSee('admin-bulk-appreciation-fields', false);
    }
}
