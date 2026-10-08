<?php

namespace Tests\Feature;

use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Models\DeviceToken;
use App\Models\Reminder;
use App\Models\User;
use App\Services\DailyReminderService;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DailyRemindersTest extends TestCase
{
    use RefreshDatabase;

    private FcmService $fcm;

    /** @var list<array{user:int,title:string,body:string,data:array}> */
    public array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $test = $this;
        $this->fcm = new class($test) extends FcmService
        {
            public function __construct(private readonly DailyRemindersTest $test)
            {
            }

            public function sendToUser(User $user, string $title, string $body, array $data = []): void
            {
                $this->test->sent[] = ['user' => $user->id, 'title' => $title, 'body' => $body, 'data' => $data];
            }
        };

        $this->app->instance(FcmService::class, $this->fcm);
    }

    private function subscriber(string $timezone = 'Africa/Kampala', bool $withDevice = true): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addYear(),
            'timezone' => $timezone,
        ])->save();

        if ($withDevice) {
            DeviceToken::create([
                'user_id' => $user->id,
                'device_id' => 'phone-'.$user->id,
                'fcm_token' => 'token-'.$user->id,
                'platform' => 'android',
            ]);
        }

        return $user->fresh();
    }

    private function sentTo(User $user): array
    {
        return array_values(array_filter($this->sent, fn ($push) => $push['user'] === $user->id));
    }

    private function plannerDay(User $user, string $date, int $total, int $done): void
    {
        $plan = DailyPlan::create(['user_id' => $user->id, 'plan_date' => $date, 'title' => 'My Daily Plan']);

        for ($i = 1; $i <= $total; $i++) {
            DailyPlanItem::create([
                'daily_plan_id' => $plan->id,
                'title' => "Task {$i}",
                'priority' => $i === 1 ? 'high' : 'medium',
                'is_completed' => $i <= $done,
                'completed_at' => $i <= $done ? now() : null,
            ]);
        }
    }

    public function test_preferences_save_from_profile(): void
    {
        $user = $this->subscriber();

        $this->actingAs($user)
            ->put(route('profile.daily-reminders'), [
                'morning_enabled' => '1',
                'morning_time' => '06:30',
                'evening_enabled' => '0',
                'evening_time' => '21:15',
            ])
            ->assertRedirect()
            ->assertSessionHas('profile_status', 'daily-reminders-updated');

        $prefs = app(DailyReminderService::class)->preferences($user);

        $this->assertTrue($prefs['morning_enabled']);
        $this->assertSame('06:30', $prefs['morning_time']);
        $this->assertFalse($prefs['evening_enabled']);
        $this->assertSame('21:15', $prefs['evening_time']);
    }

    public function test_preferences_default_to_seven_and_eight_and_reject_bad_times(): void
    {
        $user = $this->subscriber();

        $prefs = app(DailyReminderService::class)->preferences($user);
        $this->assertSame(['07:00', '20:00'], [$prefs['morning_time'], $prefs['evening_time']]);
        $this->assertTrue($prefs['morning_enabled'] && $prefs['evening_enabled']);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.daily-reminders'), ['morning_time' => '25:00'])
            ->assertSessionHasErrors('morning_time');
    }

    public function test_profile_has_daily_reminders_tab(): void
    {
        $user = $this->subscriber();

        $this->actingAs($user)
            ->get(route('profile.edit', ['tab' => 'reminders']))
            ->assertOk()
            ->assertSee('id="panel-reminders"', false)
            ->assertSee('Save reminder times')
            ->assertSee('name="morning_time"', false)
            // Firebase web keys are not configured in tests: degrade quietly.
            ->assertSee('Phone reminders aren’t available yet.', false)
            ->assertDontSee('id="pm-reminders-enable"', false);
    }

    public function test_morning_reminder_goes_out_at_local_time_once(): void
    {
        $kampala = $this->subscriber('Africa/Kampala');      // UTC+3
        $london = $this->subscriber('Europe/London');         // UTC+1 in October
        $this->plannerDay($kampala, '2026-10-08', 3, 0);

        // 06:55 in Kampala: not yet.
        $this->travelTo(Carbon::parse('2026-10-08 03:55:00', 'UTC'));
        $this->artisan('digest:daily-top-tasks')->assertSuccessful();
        $this->assertSame([], $this->sent);

        // 07:05 in Kampala, 05:05 in London.
        $this->travelTo(Carbon::parse('2026-10-08 04:05:00', 'UTC'));
        $this->artisan('digest:daily-top-tasks')->assertSuccessful();

        $pushes = $this->sentTo($kampala);
        $this->assertCount(1, $pushes);
        $this->assertSame('Plan your day', $pushes[0]['title']);
        $this->assertStringContainsString('Good morning — 3 tasks today. Top: Task 1.', $pushes[0]['body']);
        $this->assertStringContainsString('routine=start', $pushes[0]['data']['link']);
        $this->assertSame([], $this->sentTo($london));

        // The next quarter-hour run must not send again.
        $this->travelTo(Carbon::parse('2026-10-08 04:20:00', 'UTC'));
        $this->artisan('digest:daily-top-tasks')->assertSuccessful();
        $this->assertCount(1, $this->sentTo($kampala));

        // 07:05 in London.
        $this->travelTo(Carbon::parse('2026-10-08 06:05:00', 'UTC'));
        $this->artisan('digest:daily-top-tasks')->assertSuccessful();
        $this->assertCount(1, $this->sentTo($london));
        $this->assertStringContainsString('plan your day in 1 minute', $this->sentTo($london)[0]['body']);

        // Next morning it goes out again.
        $this->travelTo(Carbon::parse('2026-10-09 04:05:00', 'UTC'));
        $this->artisan('digest:daily-top-tasks')->assertSuccessful();
        $this->assertCount(2, $this->sentTo($kampala));
    }

    public function test_morning_reminder_respects_custom_time_switch_and_started_day(): void
    {
        $custom = $this->subscriber();
        $off = $this->subscriber();
        $started = $this->subscriber();
        $noDevice = $this->subscriber('Africa/Kampala', false);

        $service = app(DailyReminderService::class);
        $service->savePreferences($custom, ['morning_time' => '09:30']);
        $service->savePreferences($off, ['morning_enabled' => false]);
        DB::table('daily_checkins')->insert([
            'user_id' => $started->id, 'checkin_date' => '2026-10-08', 'type' => 'start_day',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->travelTo(Carbon::parse('2026-10-08 04:10:00', 'UTC')); // 07:10 local
        $this->artisan('digest:daily-top-tasks')->assertSuccessful();

        $this->assertSame([], $this->sentTo($custom));
        $this->assertSame([], $this->sentTo($off));
        $this->assertSame([], $this->sentTo($started));
        $this->assertSame([], $this->sentTo($noDevice));

        $this->travelTo(Carbon::parse('2026-10-08 06:35:00', 'UTC')); // 09:35 local
        $this->artisan('digest:daily-top-tasks')->assertSuccessful();
        $this->assertCount(1, $this->sentTo($custom));
    }

    public function test_evening_reminder_is_personal_idempotent_and_skips_closed_days(): void
    {
        $user = $this->subscriber();
        $closed = $this->subscriber();
        $this->plannerDay($user, '2026-10-08', 3, 2);

        DB::table('engagement_streaks')->insert([
            'user_id' => $user->id, 'current_streak' => 5, 'best_streak' => 5,
            'last_meaningful_day' => '2026-10-08', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('engagement_events')->insert([
            'user_id' => $user->id, 'event_date' => '2026-10-08', 'event_type' => 'task_completed',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('daily_checkins')->insert([
            'user_id' => $closed->id, 'checkin_date' => '2026-10-08', 'type' => 'close_day',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->travelTo(Carbon::parse('2026-10-08 16:50:00', 'UTC')); // 19:50 local
        $this->artisan('digest:end-of-day')->assertSuccessful();
        $this->assertSame([], $this->sent);

        $this->travelTo(Carbon::parse('2026-10-08 17:05:00', 'UTC')); // 20:05 local
        $this->artisan('digest:end-of-day')->assertSuccessful();
        $this->travelTo(Carbon::parse('2026-10-08 17:20:00', 'UTC'));
        $this->artisan('digest:end-of-day')->assertSuccessful();

        $pushes = $this->sentTo($user);
        $this->assertCount(1, $pushes);
        $this->assertSame('Close your day', $pushes[0]['title']);
        $this->assertSame('You did 2 of 3 today · 5-day streak — close your day?', $pushes[0]['body']);
        $this->assertStringContainsString('routine=close', $pushes[0]['data']['link']);

        $this->assertSame([], $this->sentTo($closed));
    }

    public function test_due_items_push_defers_to_the_morning_reminder(): void
    {
        $morningOn = $this->subscriber();
        $morningOff = $this->subscriber();
        app(DailyReminderService::class)->savePreferences($morningOff, ['morning_enabled' => false]);

        foreach ([$morningOn, $morningOff] as $user) {
            Reminder::create([
                'user_id' => $user->id,
                'title' => 'Pay rent',
                'module' => 'finance',
                'frequency' => 'once',
                'next_run_at' => Carbon::parse('2026-10-08 12:00:00', 'Africa/Kampala'),
                'channel' => 'mail',
                'is_active' => true,
            ]);
        }

        Cache::flush();
        $this->travelTo(Carbon::parse('2026-10-08 05:05:00', 'UTC')); // 08:05 local
        $this->artisan('push:daily-due-items')->assertSuccessful();

        $this->assertSame([], $this->sentTo($morningOn));
        $this->assertCount(1, $this->sentTo($morningOff));
    }

    public function test_browser_can_register_and_remove_its_push_token(): void
    {
        $previous = $this->subscriber('Africa/Kampala', false);
        $user = $this->subscriber('Africa/Kampala', false);

        DeviceToken::create(['user_id' => $previous->id, 'device_id' => 'web-abc', 'fcm_token' => 'browser-token', 'platform' => 'web']);

        $this->actingAs($user)
            ->postJson(route('push.web-token.store'), ['device_id' => 'web-abc', 'fcm_token' => 'browser-token'])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', ['user_id' => $user->id, 'device_id' => 'web-abc', 'platform' => 'web']);
        // The previous account on this browser no longer receives pushes here.
        $this->assertDatabaseMissing('device_tokens', ['user_id' => $previous->id]);

        $this->actingAs($user)
            ->deleteJson(route('push.web-token.destroy'), ['device_id' => 'web-abc'])
            ->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['user_id' => $user->id]);
    }

    public function test_dashboard_offers_one_tap_reminders_only_when_web_push_is_configured(): void
    {
        $user = $this->subscriber('Africa/Kampala', false);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Turn on a reminder')
            ->assertDontSee('window.pmPushConfig', false);

        config([
            'services.firebase.project_id' => 'demo-project',
            'services.firebase.web' => [
                'api_key' => 'public-api-key',
                'app_id' => '1:123:web:abc',
                'messaging_sender_id' => '123',
                'vapid_key' => 'BPublicVapidKey',
            ],
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Turn on daily reminders')
            ->assertSee('id="td-step-daily-reminders"', false)
            ->assertSee('window.pmPushConfig', false)
            ->assertSee('js/push.js', false);
    }

    public function test_fcm_removes_only_dead_tokens(): void
    {
        $this->app->forgetInstance(FcmService::class);
        $user = $this->subscriber('Africa/Kampala', false);

        foreach (['dead-404', 'dead-400', 'bad-payload'] as $device) {
            DeviceToken::create(['user_id' => $user->id, 'device_id' => $device, 'fcm_token' => $device, 'platform' => 'web']);
        }

        config(['services.firebase.project_id' => 'demo-project']);
        Cache::put('fcm_access_token', 'test-access-token', now()->addMinutes(5));

        Http::fake(function ($request) {
            $token = $request['message']['token'] ?? '';

            return match ($token) {
                'dead-404' => Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404),
                'dead-400' => Http::response(['error' => ['status' => 'INVALID_ARGUMENT', 'message' => 'The registration token is not a valid FCM registration token']], 400),
                default => Http::response(['error' => ['status' => 'INVALID_ARGUMENT', 'message' => 'Invalid value at message.webpush.fcm_options.link']], 400),
            };
        });

        (new FcmService())->sendToUser($user, 'Plan your day', 'Good morning', ['type' => 'start_of_day', 'link' => 'https://example.test/dashboard']);

        $this->assertSame(['bad-payload'], DeviceToken::where('user_id', $user->id)->pluck('device_id')->all());

        Http::assertSent(fn ($request) => ($request['message']['webpush']['fcm_options']['link'] ?? null) === 'https://example.test/dashboard');
    }
}
