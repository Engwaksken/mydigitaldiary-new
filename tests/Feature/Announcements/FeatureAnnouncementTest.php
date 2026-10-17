<?php

namespace Tests\Feature\Announcements;

use App\Jobs\SendAnnouncementJob;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class FeatureAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{user:int,title:string,body:string,data:array}> */
    public array $sent = [];

    /** User ids whose push should throw, to prove one failure does not stop the run. */
    public array $failFor = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $test = $this;
        $this->app->instance(FcmService::class, new class($test) extends FcmService
        {
            public function __construct(private readonly FeatureAnnouncementTest $test)
            {
            }

            public function sendToUser(User $user, string $title, string $body, array $data = []): void
            {
                if (in_array($user->id, $this->test->failFor, true)) {
                    throw new RuntimeException('bad token');
                }
                $this->test->sent[] = ['user' => $user->id, 'title' => $title, 'body' => $body, 'data' => $data];
            }
        });
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
            'title' => 'Team Chat is here',
            'body' => 'Chat with your team in real time, share files and keep every conversation next to your projects.',
            'type' => Announcement::TYPE_NEW_FEATURE,
            'feature_key' => 'team_chat',
            'send_push' => true,
            'send_email' => false,
        ]);
    }

    public function test_feature_catalogue_routes_all_exist(): void
    {
        $this->assertNotEmpty(config('features'));
        foreach (config('features') as $key => $feature) {
            $this->assertMatchesRegularExpression('/^[a-z_]+$/', $key);
            $this->assertTrue(Route::has($feature['route']), "Route {$feature['route']} for {$key} is missing");
            $this->assertNotEmpty($feature['label']);
            $this->assertStringStartsWith('fa-', $feature['icon']);
        }
    }

    public function test_notification_data_shape_and_link_resolution(): void
    {
        $announcement = $this->announcement();

        $this->assertSame([
            'announcement_id' => $announcement->id,
            'title' => 'Team Chat is here',
            'body' => $announcement->body,
            'type' => 'new_feature',
            'feature_key' => 'team_chat',
            'link_url' => route('team-chat.index'),
            'cta_label' => 'Explore now',
        ], (new AnnouncementNotification($announcement))->toArray(new User()));

        // An explicit link wins over the feature route.
        $announcement->update(['link_url' => '/meetings', 'cta_label' => 'Try it']);
        $data = AnnouncementNotification::payload($announcement->fresh());
        $this->assertSame('/meetings', $data['link_url']);
        $this->assertSame('Try it', $data['cta_label']);

        // General announcements with no link have no link and no button.
        $general = $this->announcement(['type' => 'general', 'feature_key' => null]);
        $data = AnnouncementNotification::payload($general);
        $this->assertSame('general', $data['type']);
        $this->assertNull($data['feature_key']);
        $this->assertNull($data['link_url']);
        $this->assertNull($data['cta_label']);
    }

    public function test_job_sends_in_app_and_push_with_the_contract_payload_to_non_suspended_users(): void
    {
        Mail::fake();
        $withDevice = $this->withDevice(User::factory()->create());
        $broken = $this->withDevice(User::factory()->create());
        $noDevice = User::factory()->create();
        $suspended = $this->withDevice(User::factory()->create(['suspended_at' => now()]));
        $this->failFor = [$broken->id];

        $announcement = $this->announcement([
            'body' => str_repeat('Plan meetings, share agendas and keep notes together. ', 5),
        ]);
        (new SendAnnouncementJob($announcement))->handle(app(FcmService::class), app(\App\Services\AnnouncementAudience::class));

        foreach ([$withDevice, $broken, $noDevice] as $user) {
            $this->assertSame(1, $user->notifications()->where('type', AnnouncementNotification::class)->count());
        }
        $this->assertSame(0, $suspended->notifications()->count());

        $this->assertCount(1, $this->sent, 'the broken token did not stop the run');
        $push = $this->sent[0];
        $this->assertSame($withDevice->id, $push['user']);
        $this->assertSame('New: Team Chat is here', $push['title']);
        $this->assertLessThanOrEqual(123, mb_strlen($push['body']));
        $this->assertStringEndsWith('...', $push['body']);
        $this->assertSame([
            'type' => 'announcement',
            'announcement_id' => (string) $announcement->id,
            'announcement_type' => 'new_feature',
            'feature_key' => 'team_chat',
            'link_url' => route('team-chat.index'),
            'link' => route('team-chat.index'),
        ], $push['data']);
        foreach ($push['data'] as $value) {
            $this->assertIsString($value);
        }

        Mail::assertNothingQueued();
    }

    public function test_no_push_when_send_push_is_off(): void
    {
        $this->withDevice(User::factory()->create());
        $announcement = $this->announcement(['send_push' => false]);

        SendAnnouncementJob::dispatchSync($announcement);

        $this->assertSame([], $this->sent);
    }

    public function test_email_goes_only_to_verified_opted_in_users(): void
    {
        Mail::fake();
        $optedIn = User::factory()->create(['name' => 'Amina Nakato']);
        $unverified = User::factory()->unverified()->create();
        $suspended = User::factory()->create(['suspended_at' => now()]);
        $optedOut = User::factory()->create(['engagement_notification_preferences' => ['product_updates' => false]]);

        $announcement = $this->announcement(['send_email' => true, 'send_push' => false]);
        SendAnnouncementJob::dispatchSync($announcement);

        // The opted-in member plus the (verified) admin who created it.
        Mail::assertQueued(AnnouncementMail::class, 2);
        Mail::assertQueued(AnnouncementMail::class, fn ($mail) => $mail->hasTo($announcement->creator->email));
        Mail::assertQueued(AnnouncementMail::class, function (AnnouncementMail $mail) use ($optedIn) {
            $html = $mail->render();

            return $mail->hasTo($optedIn->email)
                && str_contains($mail->subject, 'Team Chat is here')
                && str_contains($html, 'New feature')
                && str_contains($html, 'Explore now')
                && str_contains($html, 'Hi Amina')
                && str_contains($html, e(route('team-chat.index')))
                && str_contains($html, e(AnnouncementMail::unsubscribeUrl($optedIn)));
        });
        foreach ([$unverified, $suspended, $optedOut] as $skipped) {
            Mail::assertNotQueued(AnnouncementMail::class, fn ($mail) => $mail->hasTo($skipped->email));
        }
        // Opting out of email does not remove the in-app copy.
        $this->assertSame(1, $optedOut->notifications()->count());
        $this->assertSame(1, $unverified->notifications()->count());
    }

    public function test_signed_unsubscribe_link_turns_off_new_feature_emails(): void
    {
        $user = User::factory()->create();
        $url = AnnouncementMail::unsubscribeUrl($user);

        $this->get($url)->assertOk()->assertSee('Unsubscribe');
        $this->assertTrue($user->fresh()->engagementNotificationEnabled('product_updates'), 'GET only confirms');

        $this->post($url)->assertOk()->assertSee('You have been unsubscribed');
        $this->assertFalse($user->fresh()->engagementNotificationEnabled('product_updates'));
        $this->assertTrue($user->fresh()->engagementNotificationEnabled('weekly_appreciation'), 'other preferences untouched');

        $this->get(route('product-updates.unsubscribe', ['user' => $user->id]))->assertForbidden();
        $this->post(route('product-updates.unsubscribe.confirm', ['user' => $user->id]))->assertForbidden();

        Mail::fake();
        SendAnnouncementJob::dispatchSync($this->announcement(['send_email' => true]));
        Mail::assertNotQueued(AnnouncementMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_product_updates_preference_via_api_and_profile_toggle(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/personalisation', ['engagement_notification_preferences' => ['product_updates' => false]])
            ->assertOk();
        $this->assertFalse($user->fresh()->engagementNotificationEnabled('product_updates'));

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('New feature emails');
    }

    public function test_admin_form_shows_tabs_preview_and_stores_then_dispatches_the_job(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.announcements.create'))
            ->assertOk()
            ->assertSee('Choose a feature (optional)')
            ->assertSee('Send push notification')
            ->assertSee('Also send email')
            ->assertSee('Feature &amp; link', false)
            ->assertSee('Preview')
            ->assertDontSee('Plain text');

        Queue::fake();

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Budgets just got smarter',
            'body' => 'Set limits per category.',
            'type' => 'new_feature',
            'feature_key' => 'budgets',
            'link_url' => '',
            'cta_label' => '',
            'send_push' => '1',
            'send_email' => '1',
        ])->assertRedirect(route('admin.announcements.index'));

        $announcement = Announcement::sole();
        $this->assertSame('new_feature', $announcement->type);
        $this->assertSame('budgets', $announcement->feature_key);
        $this->assertNull($announcement->link_url);
        $this->assertTrue($announcement->send_push);
        $this->assertTrue($announcement->send_email);
        Queue::assertPushed(SendAnnouncementJob::class, fn ($job) => $job->announcement->is($announcement));

        $this->actingAs($admin)->get(route('admin.announcements.index'))
            ->assertOk()->assertSee('New feature')->assertSee('In-app, Push, Email');
    }

    public function test_admin_form_rejects_unsafe_links_and_unknown_features(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['javascript:alert(1)', '//evil.test', 'http://plain.test'] as $link) {
            $this->actingAs($admin)->post(route('admin.announcements.store'), [
                'title' => 'x', 'body' => 'y', 'link_url' => $link,
            ])->assertSessionHasErrors('link_url');
        }
        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'x', 'body' => 'y', 'feature_key' => 'nope',
        ])->assertSessionHasErrors('feature_key');

        Queue::assertNothingPushed();
        $this->assertSame(0, Announcement::count());
    }

    public function test_admin_preview_renders_the_email_without_sending(): void
    {
        Mail::fake();
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.announcements.preview'), [
            'title' => 'Savings goals',
            'body' => 'Track every goal.',
            'type' => 'new_feature',
            'feature_key' => 'savings_goals',
        ])->assertOk()->assertSee('Savings goals')->assertSee('Explore now')->assertSee(route('savings-goals.index'), false);

        $this->assertSame(0, Announcement::count());
        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
    }

    public function test_web_notification_centre_shows_badge_icon_and_explore_button(): void
    {
        $user = User::factory()->create();
        SendAnnouncementJob::dispatchSync($this->announcement(['send_push' => false]));
        $notification = $user->notifications()->sole();

        $item = collect(app(\App\Services\NotificationCenterService::class)->forUser($user)['items'])->firstWhere('database_id', $notification->id);
        $this->assertSame('New feature', $item['badge']);
        $this->assertSame('fa-comments', $item['icon']);
        $this->assertSame('Explore now', $item['cta_label']);
        $this->assertSame(route('team-chat.index'), $item['url']);

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()->assertSee('New feature')->assertSee('Explore now')->assertSee('fa-comments');

        $this->actingAs($user)->post(route('notifications.read', $notification->id), ['redirect' => $item['url']])
            ->assertRedirect(route('team-chat.index'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_api_notifications_endpoint_exposes_the_new_fields(): void
    {
        $user = User::factory()->create();
        $announcement = $this->announcement(['send_push' => false]);
        SendAnnouncementJob::dispatchSync($announcement);

        // A legacy announcement stored before the new keys existed.
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => AnnouncementNotification::class,
            'data' => ['announcement_id' => 99, 'title' => 'Old', 'body' => 'Old body'],
            'created_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/notifications')->assertOk();

        $response->assertJsonPath('data.0.type', 'AnnouncementNotification')
            ->assertJsonPath('data.0.read', false)
            ->assertJsonPath('data.0.data', [
                'announcement_id' => $announcement->id,
                'title' => 'Team Chat is here',
                'body' => $announcement->body,
                'type' => 'new_feature',
                'feature_key' => 'team_chat',
                'link_url' => route('team-chat.index'),
                'cta_label' => 'Explore now',
            ]);
        $response->assertJsonPath('data.1.data.type', 'general')
            ->assertJsonPath('data.1.data.feature_key', null)
            ->assertJsonPath('data.1.data.link_url', null)
            ->assertJsonPath('data.1.data.cta_label', null);
    }

    public function test_artisan_command_dry_run_prints_counts_and_sends_nothing(): void
    {
        Queue::fake();
        User::factory()->create(['role' => 'admin']);
        $this->withDevice(User::factory()->create());
        User::factory()->create(['engagement_notification_preferences' => ['product_updates' => false]]);
        User::factory()->create(['suspended_at' => now()]);

        $this->artisan('announcements:send-feature', [
            'feature_key' => 'meetings', '--title' => 'Meetings', '--message' => 'Now with transcripts.', '--email' => true, '--dry-run' => true,
        ])
            ->expectsTable(['Channel', 'Recipients'], [['In-app', 3], ['Push', 1], ['Email', 2]])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame(0, Announcement::count());
        Queue::assertNothingPushed();
    }

    public function test_artisan_command_creates_and_sends_a_new_feature_announcement(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $member = $this->withDevice(User::factory()->create());

        $this->artisan('announcements:send-feature', [
            'feature_key' => 'notes', '--title' => 'Sticky notes', '--message' => 'Pin notes to your dashboard.',
            '--link' => '/notes', '--cta' => 'Open notes', '--email' => true,
        ])->assertSuccessful();

        $announcement = Announcement::sole();
        $this->assertSame($admin->id, $announcement->created_by);
        $this->assertSame('new_feature', $announcement->type);
        $this->assertSame('/notes', $announcement->link_url);
        $this->assertTrue($announcement->send_email);
        $this->assertSame('Open notes', $member->notifications()->sole()->data['cta_label']);
        $this->assertSame('/notes', $this->sent[0]['data']['link_url']);
        $this->assertSame(url('/notes'), $this->sent[0]['data']['link']);
        Mail::assertQueued(AnnouncementMail::class, fn ($mail) => $mail->hasTo($member->email));

        $this->artisan('announcements:send-feature', ['feature_key' => 'nope', '--title' => 'a', '--message' => 'b'])->assertFailed();
        $this->artisan('announcements:send-feature', ['feature_key' => 'notes', '--message' => 'b'])->assertFailed();
        $this->artisan('announcements:send-feature', ['feature_key' => 'notes', '--title' => 'a', '--message' => 'b', '--no-push' => true, '--link' => 'javascript:x'])->assertFailed();
        $this->assertSame(1, Announcement::count());
    }
}
