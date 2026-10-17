<?php

namespace Tests\Feature;

use App\Jobs\SendAppreciationToUsersJob;
use App\Mail\WeeklyAppreciationMail;
use App\Models\AppreciationCampaign;
use App\Models\AppreciationEmailLog;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Ai\ActiveAiClient;
use App\Services\WeeklyAppreciationSchedule;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class AppreciationCampaignTest extends TestCase
{
    use RefreshDatabase;

    private int $aiCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Monday 19 Oct 2026 (ISO week 2026-W43), 10:00 in Kampala.
        Carbon::setTestNow(Carbon::parse('2026-10-19 10:00:00', 'Africa/Kampala'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => 'Admin Person']);
    }

    private function fakeAi(): void
    {
        $this->mock(ActiveAiClient::class, function ($mock): void {
            $mock->shouldReceive('json')->andReturnUsing(function () {
                $this->aiCalls++;

                return ['subject' => "Thanks for being here (draft {$this->aiCalls})", 'message' => str_repeat('A short, warm note for you this week. ', 8)."Draft {$this->aiCalls}."];
            });
        });
    }

    public function test_history_page_lists_logs_newest_first_with_week_filter_and_search(): void
    {
        $admin = $this->admin();
        $amina = User::factory()->create(['name' => 'Amina Nakato']);
        $bob = User::factory()->create(['name' => 'Bob Byaru']);
        AppreciationEmailLog::create(['user_id' => $amina->id, 'week_key' => '2026-W42', 'source' => 'ai', 'subject' => 'Old note for Amina', 'sent_at' => now()->subWeek()]);
        AppreciationEmailLog::create(['user_id' => $bob->id, 'week_key' => '2026-W43', 'source' => 'fallback', 'subject' => 'Fresh note for Bob', 'sent_at' => now(), 'sent_by' => $admin->id, 'campaign_id' => 1]);

        $this->actingAs($admin)->get(route('admin.appreciation.index'))
            ->assertOk()
            ->assertSee('Appreciation emails')
            ->assertSee('Weekly run: Mondays at 09:00 (Africa/Kampala)')
            ->assertSee('Choose a week (optional)')
            ->assertSeeInOrder(['Fresh note for Bob', 'Built-in', 'Admin · Admin Person', 'Old note for Amina', 'AI', 'Weekly run']);

        $this->get(route('admin.appreciation.index', ['week' => '2026-W42']))
            ->assertSee('Old note for Amina')->assertDontSee('Fresh note for Bob');

        $this->get(route('admin.appreciation.index', ['search' => 'Bob']))
            ->assertSee('Fresh note for Bob')->assertDontSee('Old note for Amina');

        // Linked from the admin sidebar.
        $this->get(route('admin.users.index'))->assertSee(route('admin.appreciation.index'), false);
    }

    public function test_send_now_from_preview_creates_a_campaign_and_records_who_sent_it(): void
    {
        $this->fakeAi();
        Mail::fake();
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.appreciation.send'), ['ids' => [$user->id], 'when' => 'now'])
            ->assertRedirect(route('admin.users.index'));

        $campaign = AppreciationCampaign::sole();
        $this->assertSame(AppreciationCampaign::STATUS_SENT, $campaign->status);
        $this->assertSame($admin->id, $campaign->created_by);
        $this->assertSame([$user->id], $campaign->userIds());
        $this->assertSame(1, $campaign->sent_count);
        $this->assertNotNull($campaign->sent_at);

        $log = AppreciationEmailLog::sole();
        $this->assertSame($admin->id, $log->sent_by);
        $this->assertSame($campaign->id, $log->campaign_id);
        $this->assertSame('Admin · Admin Person', $log->originLabel());
    }

    public function test_scheduled_campaign_sends_exactly_the_previewed_text_after_the_cache_expires(): void
    {
        $this->fakeAi();
        Mail::fake();
        $admin = $this->admin();
        $previewed = User::factory()->create(['name' => 'Peter Previewed']);
        $notPreviewed = User::factory()->create(['name' => 'Nora Notopened']);

        $this->actingAs($admin)->getJson(route('admin.users.appreciation.preview-email', $previewed))
            ->assertJsonPath('subject', 'Thanks for being here (draft 1)');

        $this->post(route('admin.users.appreciation.send'), [
            'ids' => [$previewed->id, $notPreviewed->id], 'when' => 'later', 'scheduled_for' => '2026-10-19T18:30',
        ])->assertRedirect(route('admin.appreciation.index'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'scheduled for Mon 19 Oct, 18:30 (Africa/Kampala) for 2 users'));

        $campaign = AppreciationCampaign::sole();
        $this->assertSame(AppreciationCampaign::STATUS_SCHEDULED, $campaign->status);
        $this->assertSame('Thanks for being here (draft 1)', $campaign->draftFor($previewed->id)['subject']);
        $this->assertNull($campaign->draftFor($notPreviewed->id));
        Mail::assertNothingQueued();

        $this->get(route('admin.appreciation.index'))
            ->assertSee('Scheduled for Mon 19 Oct, 18:30')
            ->assertSee('1 previewed message kept')
            ->assertSee(route('admin.appreciation.campaigns.cancel', $campaign), false)
            ->assertSee(route('admin.appreciation.campaigns.send-now', $campaign), false);

        // The 6-hour draft cache is gone by the time it sends.
        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-10-19 18:30:00', 'Africa/Kampala'));
        $this->artisan('appreciation:dispatch-scheduled')->expectsOutputToContain('1 scheduled appreciation campaign(s) queued.')->assertSuccessful();

        Mail::assertQueued(WeeklyAppreciationMail::class, fn ($mail) => $mail->hasTo($previewed->email) && $mail->subjectLine === 'Thanks for being here (draft 1)');
        Mail::assertQueued(WeeklyAppreciationMail::class, fn ($mail) => $mail->hasTo($notPreviewed->email) && $mail->subjectLine === 'Thanks for being here (draft 2)');
        $this->assertSame(2, $this->aiCalls, 'the previewed person is not rewritten; the other is written at send time');

        $campaign->refresh();
        $this->assertSame(AppreciationCampaign::STATUS_SENT, $campaign->status);
        $this->assertSame(2, $campaign->sent_count);
        $this->assertSame('2026-W43', $campaign->week_key);

        // Never dispatched twice.
        $this->artisan('appreciation:dispatch-scheduled')->expectsOutputToContain('0 scheduled appreciation campaign(s) queued.');
        Mail::assertQueued(WeeklyAppreciationMail::class, 2);
    }

    public function test_weekly_rule_and_eligibility_use_the_week_of_the_actual_send(): void
    {
        $this->mock(ActiveAiClient::class, fn ($mock) => $mock->shouldReceive('json')->andThrow(new RuntimeException('no AI')));
        Mail::fake();
        $admin = $this->admin();
        $sentThisWeek = User::factory()->create();
        $sentNextWeek = User::factory()->create();
        $becomesUnverified = User::factory()->create();
        AppreciationEmailLog::create(['user_id' => $sentThisWeek->id, 'week_key' => '2026-W43', 'source' => 'ai', 'sent_at' => now()]);

        $this->actingAs($admin)->post(route('admin.users.appreciation.send'), [
            'ids' => [$sentThisWeek->id, $sentNextWeek->id, $becomesUnverified->id],
            'when' => 'later', 'scheduled_for' => '2026-10-27T09:00',
        ])->assertRedirect(route('admin.appreciation.index'));

        // Before it sends: one gets next week's email elsewhere, one stops being eligible.
        AppreciationEmailLog::create(['user_id' => $sentNextWeek->id, 'week_key' => '2026-W44', 'source' => 'ai', 'sent_at' => now()]);
        $becomesUnverified->forceFill(['email_verified_at' => null])->save();

        Carbon::setTestNow(Carbon::parse('2026-10-27 09:01:00', 'Africa/Kampala'));
        $this->artisan('appreciation:dispatch-scheduled')->assertSuccessful();

        // Had this week's (W43) email, but it sends in W44: they get it.
        Mail::assertQueued(WeeklyAppreciationMail::class, 1);
        Mail::assertQueued(WeeklyAppreciationMail::class, fn ($mail) => $mail->hasTo($sentThisWeek->email));
        $this->assertTrue(AppreciationEmailLog::where('user_id', $sentThisWeek->id)->where('week_key', '2026-W44')->exists());

        $campaign = AppreciationCampaign::sole();
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(2, $campaign->skipped_count);
        $this->assertSame('2026-W44', $campaign->week_key);
    }

    public function test_schedule_validation_cancel_and_send_now(): void
    {
        Bus::fake();
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.appreciation.send'), ['ids' => [$user->id], 'when' => 'later', 'scheduled_for' => '2026-10-19T09:59'])
            ->assertSessionHasErrors('scheduled_for');
        $this->post(route('admin.users.appreciation.send'), ['ids' => [$user->id], 'when' => 'later'])
            ->assertSessionHasErrors('scheduled_for');
        $this->assertSame(0, AppreciationCampaign::count());

        $this->post(route('admin.users.appreciation.send'), ['ids' => [$user->id], 'when' => 'later', 'scheduled_for' => '2026-10-21T09:00']);
        $this->post(route('admin.users.appreciation.send'), ['ids' => [$user->id], 'when' => 'later', 'scheduled_for' => '2026-10-22T09:00']);
        [$first, $second] = AppreciationCampaign::orderBy('id')->get()->all();

        $this->post(route('admin.appreciation.campaigns.cancel', $first))->assertSessionHas('success');
        $this->assertSame(AppreciationCampaign::STATUS_CANCELLED, $first->fresh()->status);
        $this->post(route('admin.appreciation.campaigns.send-now', $first))->assertSessionHas('error');

        $this->post(route('admin.appreciation.campaigns.send-now', $second))->assertSessionHas('success');
        $this->assertSame(AppreciationCampaign::STATUS_SENDING, $second->fresh()->status);
        $this->post(route('admin.appreciation.campaigns.send-now', $second))->assertSessionHas('error');
        Bus::assertDispatchedTimes(SendAppreciationToUsersJob::class, 1);
        Bus::assertDispatched(SendAppreciationToUsersJob::class, fn ($job) => $job->campaignId === $second->id && $job->weekKey === '2026-W43');

        // Cancelled campaigns are never picked up when due.
        Carbon::setTestNow(Carbon::parse('2026-10-23 09:00', 'Africa/Kampala'));
        $this->artisan('appreciation:dispatch-scheduled')->expectsOutputToContain('0 scheduled');
        Bus::assertDispatchedTimes(SendAppreciationToUsersJob::class, 1);
    }

    public function test_preview_page_shows_send_now_or_schedule_and_the_weekly_rule(): void
    {
        $user = User::factory()->create(['name' => 'Amina Nakato']);

        $this->actingAs($this->admin())->get(route('admin.users.appreciation.preview', ['ids' => $user->id]))
            ->assertOk()
            ->assertSee('Once per week')
            ->assertSee('at most one appreciation email per week')
            ->assertSee('Mondays at 09:00 (Africa/Kampala)')
            ->assertSee('When to send')
            ->assertSee('Schedule for later')
            ->assertSee('24h · Africa/Kampala')
            ->assertSee('Choose a person to preview their email');
    }

    public function test_weekly_settings_control_the_scheduler(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.settings.edit', ['tab' => 'weekly-email']))
            ->assertOk()
            ->assertSee('Weekly appreciation email')
            ->assertSee('Send the weekly appreciation email automatically')
            ->assertSee('Mondays at 09:00 (Africa/Kampala)');

        $this->post(route('admin.settings.weekly-appreciation.update'), [
            'appreciation_weekly_enabled' => '1', 'appreciation_weekly_day' => '3', 'appreciation_weekly_time' => '14:30',
        ])->assertRedirect(route('admin.settings.edit', ['tab' => 'weekly-email']));
        $this->post(route('admin.settings.weekly-appreciation.update'), [
            'appreciation_weekly_day' => '9', 'appreciation_weekly_time' => '25:00',
        ])->assertSessionHasErrors(['appreciation_weekly_day', 'appreciation_weekly_time']);

        $settings = WeeklyAppreciationSchedule::settings();
        $this->assertSame(['enabled' => true, 'day' => 3, 'time' => '14:30'], array_intersect_key($settings, array_flip(['enabled', 'day', 'time'])));

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'appreciation:send-weekly --scheduled'));
        $this->assertNotNull($event);

        $at = fn (string $time) => Carbon::setTestNow(Carbon::parse($time, 'Africa/Kampala'));

        $at('2026-10-19 09:00'); // Monday: no longer the day
        $this->assertFalse($event->filtersPass($this->app));
        $at('2026-10-21 14:29'); // Wednesday, a minute early
        $this->assertFalse($event->filtersPass($this->app));
        $at('2026-10-21 14:30');
        $this->assertTrue($event->filtersPass($this->app));
        $at('2026-10-21 16:00'); // a missed minute still runs later that day
        $this->assertTrue($event->filtersPass($this->app));

        // Runs once, then not again that week even if the day or time changes.
        $this->mock(ActiveAiClient::class, fn ($mock) => $mock->shouldReceive('json')->andThrow(new RuntimeException('no AI')));
        Mail::fake();
        $member = User::factory()->create();
        $this->artisan('appreciation:send-weekly --scheduled')->expectsOutputToContain('1 weekly appreciation email(s) sent for 2026-W43')->assertSuccessful();
        $this->assertFalse($event->filtersPass($this->app));

        SiteSetting::current()->forceFill(['appreciation_weekly_day' => 4, 'appreciation_weekly_time' => '08:00'])->save();
        $at('2026-10-22 08:00');
        $this->assertFalse($event->filtersPass($this->app), 'already ran this ISO week');
        $this->artisan('appreciation:send-weekly --scheduled')->expectsOutputToContain('has already happened')->assertSuccessful();
        Mail::assertQueued(WeeklyAppreciationMail::class, 1);

        // Next week it runs again on the new day.
        $at('2026-10-29 08:00');
        $this->assertTrue($event->filtersPass($this->app));

        // Switched off: never due, and the command refuses to run on schedule.
        SiteSetting::current()->forceFill(['appreciation_weekly_enabled' => false])->save();
        $this->assertFalse($event->filtersPass($this->app));
        $this->artisan('appreciation:send-weekly --scheduled')->expectsOutputToContain('switched off')->assertSuccessful();
        Mail::assertQueued(WeeklyAppreciationMail::class, 1);
        $this->assertSame(1, AppreciationEmailLog::where('user_id', $member->id)->count());
    }
}
