<?php

namespace Tests\Feature\Announcements;

use App\Jobs\SendAnnouncementJob;
use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnnouncementSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Saturday 17 Oct 2026, 15:00 in Kampala.
        Carbon::setTestNow(Carbon::parse('2026-10-17 15:00:00', 'Africa/Kampala'));
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

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Meetings got transcripts',
            'body' => 'Every meeting can now be transcribed.',
            'type' => 'new_feature',
            'feature_key' => 'meetings',
            'send_push' => '0',
            'send_email' => '0',
        ];
    }

    private function scheduled(array $attributes = []): Announcement
    {
        return Announcement::create($attributes + [
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
            'title' => 'Scheduled news',
            'body' => 'Coming soon.',
            'type' => Announcement::TYPE_GENERAL,
            'send_push' => false,
            'status' => Announcement::STATUS_SCHEDULED,
            'scheduled_for' => now()->addHour(),
        ]);
    }

    public function test_migrated_rows_default_to_sent(): void
    {
        $id = \Illuminate\Support\Facades\DB::table('announcements')->insertGetId([
            'created_by' => $this->admin()->id, 'title' => 'Old', 'body' => 'Old one',
            'created_at' => now()->subWeek(), 'updated_at' => now()->subWeek(),
        ]);

        $this->assertSame(Announcement::STATUS_SENT, Announcement::findOrFail($id)->status);
    }

    public function test_form_offers_send_now_or_schedule_in_the_app_timezone(): void
    {
        $this->actingAs($this->admin())->get(route('admin.announcements.create'))
            ->assertOk()
            ->assertSee('When to send')
            ->assertSee('Send now')
            ->assertSee('Schedule for later')
            ->assertSee('name="when" value="now" checked', false)
            ->assertSee('type="datetime-local"', false)
            ->assertSee('24h · Africa/Kampala');
    }

    public function test_schedule_must_be_a_valid_future_time(): void
    {
        Queue::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.announcements.store'), $this->payload(['when' => 'later']))
            ->assertSessionHasErrors('scheduled_for');
        $this->post(route('admin.announcements.store'), $this->payload(['when' => 'later', 'scheduled_for' => '2026-10-17T14:59']))
            ->assertSessionHasErrors('scheduled_for');
        $this->post(route('admin.announcements.store'), $this->payload(['when' => 'later', 'scheduled_for' => 'next tuesday']))
            ->assertSessionHasErrors('scheduled_for');
        $this->post(route('admin.announcements.store'), $this->payload(['when' => 'sometime']))
            ->assertSessionHasErrors('when');

        $this->assertSame(0, Announcement::count());
        Queue::assertNothingPushed();
    }

    public function test_scheduling_stores_the_time_and_does_not_send_until_due(): void
    {
        Queue::fake();
        $member = User::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.announcements.store'), $this->payload(['when' => 'later', 'scheduled_for' => '2026-10-19T09:00']))
            ->assertRedirect(route('admin.announcements.index'))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'scheduled for Mon 19 Oct, 09:00 (Africa/Kampala)'));

        $announcement = Announcement::sole();
        $this->assertSame(Announcement::STATUS_SCHEDULED, $announcement->status);
        $this->assertSame('2026-10-19 09:00', $announcement->scheduled_for->timezone('Africa/Kampala')->format('Y-m-d H:i'));
        $this->assertTrue($announcement->skip_already_told);
        Queue::assertNothingPushed();

        $this->get(route('admin.announcements.index'))
            ->assertOk()
            ->assertSee('Scheduled for Mon 19 Oct, 09:00')
            ->assertSee(route('admin.announcements.cancel', $announcement), false)
            ->assertSee(route('admin.announcements.send-now', $announcement), false);

        // Not yet due.
        Carbon::setTestNow(Carbon::parse('2026-10-19 08:59:00', 'Africa/Kampala'));
        $this->artisan('announcements:dispatch-scheduled')->assertSuccessful();
        Queue::assertNothingPushed();
        $this->assertSame(0, $member->notifications()->count());
    }

    public function test_due_announcements_are_dispatched_once_and_marked_sent(): void
    {
        $member = User::factory()->create();
        $announcement = $this->scheduled(['scheduled_for' => Carbon::parse('2026-10-19 09:00', 'Africa/Kampala')]);

        Carbon::setTestNow(Carbon::parse('2026-10-19 09:00:30', 'Africa/Kampala'));
        $this->artisan('announcements:dispatch-scheduled')->expectsOutputToContain('1 scheduled announcement(s) queued.')->assertSuccessful();

        $announcement->refresh();
        $this->assertSame(Announcement::STATUS_SENT, $announcement->status);
        $this->assertNotNull($announcement->sent_at);
        $this->assertSame(1, $member->notifications()->count());

        // A second (or overlapping) run finds nothing to do.
        $this->artisan('announcements:dispatch-scheduled')->expectsOutputToContain('0 scheduled announcement(s) queued.')->assertSuccessful();
        $this->assertSame(1, $member->notifications()->count());
    }

    public function test_claim_is_atomic_so_two_dispatchers_cannot_both_send(): void
    {
        Queue::fake();
        $announcement = $this->scheduled(['scheduled_for' => now()->subMinute()]);

        $this->assertTrue(\App\Console\Commands\DispatchScheduledAnnouncements::claim($announcement->id));
        $this->assertFalse(\App\Console\Commands\DispatchScheduledAnnouncements::claim($announcement->id));
        $this->assertSame(Announcement::STATUS_SENDING, $announcement->fresh()->status);

        $this->artisan('announcements:dispatch-scheduled')->assertSuccessful();
        Queue::assertNothingPushed();
    }

    public function test_cancel_stops_a_scheduled_announcement(): void
    {
        Queue::fake();
        $announcement = $this->scheduled(['scheduled_for' => now()->subMinute()]);

        $this->actingAs($this->admin())
            ->post(route('admin.announcements.cancel', $announcement))
            ->assertSessionHas('success');
        $this->assertSame(Announcement::STATUS_CANCELLED, $announcement->fresh()->status);

        $this->artisan('announcements:dispatch-scheduled')->assertSuccessful();
        Queue::assertNothingPushed();

        // Only scheduled ones can be cancelled.
        $this->post(route('admin.announcements.cancel', $announcement))->assertSessionHas('error');
        $this->post(route('admin.announcements.send-now', $announcement))->assertSessionHas('error');
        Queue::assertNothingPushed();
    }

    public function test_send_now_dispatches_a_scheduled_announcement_immediately(): void
    {
        $member = User::factory()->create();
        $announcement = $this->scheduled(['scheduled_for' => now()->addDays(3)]);

        $this->actingAs($this->admin())
            ->post(route('admin.announcements.send-now', $announcement))
            ->assertSessionHas('success', 'Announcement is being sent now.');

        $this->assertSame(Announcement::STATUS_SENT, $announcement->fresh()->status);
        $this->assertSame(1, $member->notifications()->count());

        // Already sent: a second click does nothing.
        Queue::fake();
        $this->post(route('admin.announcements.send-now', $announcement))->assertSessionHas('error');
        Queue::assertNotPushed(SendAnnouncementJob::class);
    }

    public function test_skip_already_told_is_checked_at_send_time(): void
    {
        $user = User::factory()->create();
        $later = $this->scheduled([
            'type' => Announcement::TYPE_NEW_FEATURE, 'feature_key' => 'notes', 'skip_already_told' => true,
            'recipient_ids' => [$user->id], 'scheduled_for' => now()->addDay(),
        ]);

        // Someone else announces the same feature before the scheduled one sends.
        $earlier = Announcement::create([
            'created_by' => $later->created_by, 'title' => 'Notes', 'body' => 'Now.', 'type' => Announcement::TYPE_NEW_FEATURE,
            'feature_key' => 'notes', 'send_push' => false, 'recipient_ids' => [$user->id],
        ]);
        SendAnnouncementJob::dispatchSync($earlier);
        $this->assertSame(1, $user->notifications()->count());

        Carbon::setTestNow(now()->addDays(2));
        $this->artisan('announcements:dispatch-scheduled')->assertSuccessful();

        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame(0, AnnouncementDelivery::where('announcement_id', $later->id)->count());
        $this->assertSame(1, $later->fresh()->skipped_count);
        $this->assertSame(Announcement::STATUS_SENT, $later->fresh()->status);
    }

    public function test_failed_job_marks_the_announcement_failed(): void
    {
        $announcement = $this->scheduled(['status' => Announcement::STATUS_SENDING]);

        (new SendAnnouncementJob($announcement))->failed(new \RuntimeException('queue down'));

        $this->assertSame(Announcement::STATUS_FAILED, $announcement->fresh()->status);
    }
}
