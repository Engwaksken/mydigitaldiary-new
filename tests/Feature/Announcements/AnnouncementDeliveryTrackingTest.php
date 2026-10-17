<?php

namespace Tests\Feature\Announcements;

use App\Jobs\SendAnnouncementJob;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\Services\AnnouncementAudience;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class AnnouncementDeliveryTrackingTest extends TestCase
{
    use RefreshDatabase;

    public int $pushes = 0;

    /** User ids whose push throws. */
    public array $failFor = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-10-19 10:00:00', 'Africa/Kampala'));

        $test = $this;
        $this->app->instance(FcmService::class, new class($test) extends FcmService
        {
            public function __construct(private readonly AnnouncementDeliveryTrackingTest $test)
            {
            }

            public function sendToUser(User $user, string $title, string $body, array $data = []): void
            {
                if (in_array($user->id, $this->test->failFor, true)) {
                    throw new RuntimeException('bad token');
                }
                $this->test->pushes++;
            }
        });
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

    private function withDevice(User $user): User
    {
        DeviceToken::forceCreate([
            'user_id' => $user->id,
            'device_id' => 'device-'.$user->id,
            'fcm_token' => 'token-'.$user->id,
            'platform' => 'android',
        ]);

        return $user;
    }

    private function announcement(array $attributes = []): Announcement
    {
        return Announcement::create($attributes + [
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
            'title' => 'Budgets just got smarter',
            'body' => 'Set limits per category.',
            'type' => Announcement::TYPE_NEW_FEATURE,
            'feature_key' => 'budgets',
            'send_push' => true,
            'send_email' => true,
        ]);
    }

    private function deliver(Announcement $announcement): void
    {
        (new SendAnnouncementJob($announcement))->handle(app(FcmService::class), app(AnnouncementAudience::class));
    }

    public function test_job_records_each_users_delivery_per_channel_and_marks_the_announcement_sent(): void
    {
        Mail::fake();
        $withDevice = $this->withDevice(User::factory()->create());
        $noEmail = User::factory()->create(['engagement_notification_preferences' => ['product_updates' => false]]);
        $failingPush = $this->withDevice(User::factory()->create());
        $this->failFor = [$failingPush->id];
        $suspended = User::factory()->create(['suspended_at' => now()]);

        $announcement = $this->announcement(['recipient_ids' => [$withDevice->id, $noEmail->id, $failingPush->id, $suspended->id]]);
        $this->deliver($announcement);

        $this->assertSame(3, AnnouncementDelivery::count());
        $this->assertFalse(AnnouncementDelivery::where('user_id', $suspended->id)->exists());

        $delivery = AnnouncementDelivery::where('user_id', $withDevice->id)->sole();
        $this->assertNotNull($delivery->in_app_at);
        $this->assertNotNull($delivery->push_at);
        $this->assertNotNull($delivery->email_at);
        $this->assertNull($delivery->failed_channels);

        $delivery = AnnouncementDelivery::where('user_id', $noEmail->id)->sole();
        $this->assertNotNull($delivery->in_app_at);
        $this->assertNull($delivery->push_at, 'no device registered');
        $this->assertNull($delivery->email_at, 'opted out of product updates');

        $delivery = AnnouncementDelivery::where('user_id', $failingPush->id)->sole();
        $this->assertNull($delivery->push_at);
        $this->assertSame(['push'], $delivery->failed_channels);
        $this->assertNotNull($delivery->email_at, 'one failed channel does not stop the others');

        $announcement->refresh();
        $this->assertSame(Announcement::STATUS_SENT, $announcement->status);
        $this->assertTrue($announcement->sent_at->equalTo(now()));
    }

    public function test_job_is_idempotent_so_a_retry_never_sends_twice(): void
    {
        Mail::fake();
        $users = collect([$this->withDevice(User::factory()->create()), User::factory()->create()]);
        $announcement = $this->announcement(['recipient_ids' => $users->pluck('id')->all()]);

        $this->deliver($announcement);
        // A retry, or the same announcement dispatched again.
        $announcement->forceFill(['status' => Announcement::STATUS_SENDING])->save();
        $this->deliver($announcement);

        foreach ($users as $user) {
            $this->assertSame(1, $user->notifications()->where('type', AnnouncementNotification::class)->count());
        }
        $this->assertSame(1, $this->pushes);
        Mail::assertQueued(AnnouncementMail::class, 2);
        $this->assertSame(2, AnnouncementDelivery::where('announcement_id', $announcement->id)->count());

        // A user who already has a delivery row (e.g. claimed by a run that
        // died mid-way) is skipped too.
        $late = User::factory()->create();
        AnnouncementDelivery::create(['announcement_id' => $announcement->id, 'user_id' => $late->id]);
        $announcement->forceFill(['status' => Announcement::STATUS_SENDING, 'recipient_ids' => [...$users->pluck('id'), $late->id]])->save();
        $this->deliver($announcement);
        $this->assertSame(0, $late->notifications()->count());
    }

    public function test_cancelled_announcement_is_not_sent_by_a_queued_job(): void
    {
        $user = User::factory()->create();
        $announcement = $this->announcement(['recipient_ids' => [$user->id], 'status' => Announcement::STATUS_CANCELLED]);

        $this->deliver($announcement);

        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(Announcement::STATUS_CANCELLED, $announcement->fresh()->status);
    }

    public function test_skips_users_already_told_about_the_same_feature_and_counts_reflect_it(): void
    {
        Mail::fake();
        $told = User::factory()->create(['name' => 'Told Before']);
        $fresh = User::factory()->create(['name' => 'Never Told']);
        $ids = [$told->id, $fresh->id];

        $first = $this->announcement(['recipient_ids' => [$told->id], 'skip_already_told' => true]);
        $this->deliver($first);
        $first->refresh();

        // An earlier announcement of another feature, or a scheduled one, does not count.
        $this->deliver($this->announcement(['feature_key' => 'notes', 'recipient_ids' => [$fresh->id]]));
        $this->announcement(['recipient_ids' => [$fresh->id], 'status' => Announcement::STATUS_SCHEDULED, 'scheduled_for' => now()->addDay()]);

        $audience = app(AnnouncementAudience::class);
        $this->assertSame(1, $audience->alreadyTold($ids, 'budgets')['count']);
        $this->assertSame(['in_app' => 1, 'push' => 0, 'email' => 1], $audience->counts($ids, 'budgets'));
        $this->assertSame(['in_app' => 2, 'push' => 0, 'email' => 2], $audience->counts($ids));

        $admin = $this->admin();
        $date = $first->sent_at->format('j M Y');

        // The Delivery tab's live counts and notice.
        $this->actingAs($admin)
            ->getJson(route('admin.announcements.audience', ['type' => 'new_feature', 'feature_key' => 'budgets', 'skip_already_told' => 1, 'recipients' => implode(',', $ids)]))
            ->assertOk()
            ->assertJson(['in_app' => 1, 'email' => 1, 'already_told' => 1, 'skipping' => true, 'notice' => "Already announced to 1 of these users on {$date}."]);
        $this->getJson(route('admin.announcements.audience', ['type' => 'new_feature', 'feature_key' => 'budgets', 'skip_already_told' => 0, 'recipients' => implode(',', $ids)]))
            ->assertJson(['in_app' => 2, 'already_told' => 1, 'skipping' => false]);
        $this->getJson(route('admin.announcements.audience', ['type' => 'general', 'feature_key' => 'budgets', 'recipients' => implode(',', $ids)]))
            ->assertJson(['in_app' => 2, 'already_told' => 0, 'notice' => null]);

        // The form shows the option, on by default.
        $this->get(route('admin.announcements.create', ['recipients' => implode(',', $ids)]))
            ->assertOk()
            ->assertSee('Skip users who were already told about this feature')
            ->assertSee('name="skip_already_told" value="1" checked', false);

        // Sending with the option on reaches only the new person.
        Queue::fake();
        $this->post(route('admin.announcements.store'), [
            'title' => 'Budgets, again', 'body' => 'Now with rollover.', 'type' => 'new_feature', 'feature_key' => 'budgets',
            'recipient_ids' => $ids, 'send_push' => '0', 'send_email' => '0', 'skip_already_told' => '1',
        ])->assertRedirect(route('admin.announcements.index'));
        $second = Announcement::latest('id')->firstOrFail();
        $this->assertTrue($second->skip_already_told);

        $this->deliver($second);
        $this->assertSame(1, $told->notifications()->count());
        $this->assertSame(1, $fresh->notifications()->where('data->announcement_id', $second->id)->count());
        $this->assertSame(1, $second->fresh()->skipped_count);

        // With the option off, everyone is told again.
        $third = $this->announcement(['recipient_ids' => $ids, 'skip_already_told' => false]);
        $this->deliver($third);
        $this->assertSame(2, AnnouncementDelivery::where('announcement_id', $third->id)->count());
    }

    public function test_command_dry_run_shows_the_skip_and_include_previous_turns_it_off(): void
    {
        Queue::fake();
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $told = User::factory()->create();
        User::factory()->create();

        $first = $this->announcement(['created_by' => $admin->id, 'recipient_ids' => [$told->id], 'feature_key' => 'meetings']);
        $this->deliver($first);
        $date = $first->fresh()->sent_at->format('j M Y');
        $everyone = User::query()->whereNull('suspended_at')->count();

        $this->artisan('announcements:send-feature', ['feature_key' => 'meetings', '--title' => 'Meetings', '--message' => 'Transcripts.', '--dry-run' => true])
            ->expectsTable(['Channel', 'Recipients'], [['In-app', $everyone - 1], ['Push', 0], ['Email', '0 (add --email)']])
            ->expectsOutputToContain("Already announced to 1 of these users on {$date}. They will be skipped")
            ->assertSuccessful();

        $this->artisan('announcements:send-feature', ['feature_key' => 'meetings', '--title' => 'Meetings', '--message' => 'Transcripts.', '--dry-run' => true, '--include-previous' => true])
            ->expectsTable(['Channel', 'Recipients'], [['In-app', $everyone], ['Push', 0], ['Email', '0 (add --email)']])
            ->expectsOutputToContain('They will be told again')
            ->assertSuccessful();

        $this->artisan('announcements:send-feature', ['feature_key' => 'meetings', '--title' => 'Meetings', '--message' => 'Transcripts.'])->assertSuccessful();
        $this->assertTrue(Announcement::latest('id')->firstOrFail()->skip_already_told);

        $this->artisan('announcements:send-feature', ['feature_key' => 'meetings', '--title' => 'Meetings', '--message' => 'Transcripts.', '--include-previous' => true])->assertSuccessful();
        $this->assertFalse(Announcement::latest('id')->firstOrFail()->skip_already_told);
    }

    public function test_details_page_shows_counts_audience_schedule_and_a_searchable_recipient_list(): void
    {
        Mail::fake();
        $amina = $this->withDevice(User::factory()->create(['name' => 'Amina Nakato', 'email' => 'amina@example.test']));
        $bob = User::factory()->create(['name' => 'Bob Byaru']);
        $failing = $this->withDevice(User::factory()->create(['name' => 'Fiona Fail']));
        $this->failFor = [$failing->id];

        $announcement = $this->announcement(['recipient_ids' => [$amina->id, $bob->id, $failing->id]]);
        $this->deliver($announcement);

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.announcements.index'))
            ->assertOk()
            ->assertSee(route('admin.announcements.show', $announcement), false)
            ->assertSee('3 reached');

        $this->get(route('admin.announcements.show', $announcement))
            ->assertOk()
            ->assertSee('Budgets just got smarter')
            ->assertSee('New feature')
            ->assertSee('Budgets')
            ->assertSee('3 selected users')
            ->assertSee('In-app, Push, Email')
            ->assertSee('Africa/Kampala')
            ->assertSeeInOrder(['Reached', '3', 'In-app', '3', 'Push', '1', 'Email', '3', 'Failed', '1'])
            ->assertSee('Amina Nakato')
            ->assertSee('Bob Byaru')
            ->assertSee('Push failed')
            ->assertSee('In-app · 19 Oct, 10:00');

        $this->get(route('admin.announcements.show', [$announcement, 'search' => 'amina@']))
            ->assertOk()
            ->assertSee('Amina Nakato')
            ->assertDontSee('Bob Byaru');
    }

    public function test_user_details_page_lists_recent_messages(): void
    {
        $user = User::factory()->create();
        $announcement = $this->announcement(['recipient_ids' => [$user->id], 'send_email' => false]);
        $this->deliver($announcement);
        \App\Models\AppreciationEmailLog::create([
            'user_id' => $user->id, 'week_key' => '2026-W43', 'source' => 'fallback',
            'subject' => 'Thanks for being here', 'sent_at' => now(),
        ]);

        $this->actingAs($this->admin())->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSee('Messages')
            ->assertSee('Budgets just got smarter')
            ->assertSee(route('admin.announcements.show', $announcement), false)
            ->assertSee('Thanks for being here')
            ->assertSee('2026-W43 · Built-in · Weekly run');
    }
}
