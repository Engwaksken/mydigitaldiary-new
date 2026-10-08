<?php

namespace Tests\Feature\Api;

use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TodayHubApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 10:00 in Kampala on Thursday 8 October 2026.
        $this->travelTo(Carbon::parse('2026-10-08 07:00:00', 'UTC'));
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['timezone' => 'Africa/Kampala'])->save();

        return $user->fresh();
    }

    private function task(User $user, string $title, string $priority = 'medium', bool $done = false): DailyPlanItem
    {
        $plan = DailyPlan::where('user_id', $user->id)->first()
            ?? DailyPlan::create(['user_id' => $user->id, 'plan_date' => '2026-10-08', 'title' => 'My Daily Plan']);

        return DailyPlanItem::create([
            'daily_plan_id' => $plan->id,
            'title' => $title,
            'priority' => $priority,
            'is_completed' => $done,
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/dashboard/today-hub')->assertUnauthorized();
    }

    public function test_returns_greeting_task_progress_and_top_open_tasks(): void
    {
        $user = $this->user();
        $this->task($user, 'Low thing', 'low');
        $high = $this->task($user, 'Call the bank', 'high');
        $this->task($user, 'Already done', 'high', true);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard/today-hub')
            ->assertOk()
            ->assertJsonPath('data.date', '2026-10-08')
            ->assertJsonPath('data.greeting', 'Good morning')
            ->assertJsonPath('data.date_label', 'Thursday, 8 October')
            ->assertJsonPath('data.tasks.total', 3)
            ->assertJsonPath('data.tasks.completed', 1)
            ->assertJsonPath('data.tasks.percent', 33)
            ->assertJsonPath('data.tasks.items.0.id', $high->id)
            ->assertJsonPath('data.tasks.items.0.title', 'Call the bank')
            ->assertJsonPath('data.tasks.items.0.occurrence_date', '2026-10-08')
            ->assertJsonPath('data.tasks.items.0.time_label', 'Anytime')
            ->assertJsonCount(2, 'data.tasks.items')
            ->assertJsonCount(7, 'data.streak.week')
            ->assertJsonPath('data.streak.week.6.today', true)
            ->assertJsonPath('data.streak.week.6.label', 'T')
            ->assertJsonCount(4, 'data.rings')
            ->assertJsonStructure(['data' => [
                'nudge', 'routine' => ['start_done', 'close_done'],
                'get_started' => ['steps', 'done', 'total', 'complete'],
                'coming_up' => ['items', 'more'],
                'money' => ['spent', 'income', 'budget'],
                'on_this_day' => ['items', 'comparison'],
                'daily_reminders' => ['morning_enabled', 'morning_time', 'evening_enabled', 'evening_time', 'device_registered', 'on'],
            ]]);

        $this->assertSame(
            ['plan', 'goal', 'money', 'daily-reminders'],
            array_column($response->json('data.get_started.steps'), 'key')
        );
        $this->assertTrue($response->json('data.get_started.steps.0.done'));
    }

    public function test_streak_dots_and_nudge_follow_recent_activity(): void
    {
        if (! Schema::hasTable('engagement_events') || ! Schema::hasTable('engagement_streaks')) {
            $this->markTestSkipped('Engagement tables are not available.');
        }

        $user = $this->user();
        foreach (['2026-10-06', '2026-10-07'] as $day) {
            DB::table('engagement_events')->insert([
                'user_id' => $user->id,
                'event_type' => 'task_completed',
                'event_date' => $day,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('engagement_streaks')->insert([
            'user_id' => $user->id,
            'current_streak' => 2,
            'best_streak' => 5,
            'last_meaningful_day' => '2026-10-07',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/today-hub')
            ->assertOk()
            ->assertJsonPath('data.streak.current', 2)
            ->assertJsonPath('data.streak.best', 5)
            ->assertJsonPath('data.streak.active_today', false)
            ->assertJsonPath('data.streak.week.4.active', true)
            ->assertJsonPath('data.streak.week.5.active', true)
            ->assertJsonPath('data.streak.week.6.active', false)
            ->assertJsonPath('data.nudge', 'Tick off one task to keep your streak.');
    }

    public function test_daily_reminders_on_only_with_a_registered_device(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/today-hub')
            ->assertJsonPath('data.daily_reminders.morning_time', '07:00')
            ->assertJsonPath('data.daily_reminders.evening_time', '20:00')
            ->assertJsonPath('data.daily_reminders.on', false);

        $this->postJson('/api/device-tokens', [
            'device_id' => 'device-1',
            'fcm_token' => 'token-1',
            'platform' => 'android',
        ])->assertSuccessful();

        $this->getJson('/api/dashboard/today-hub')
            ->assertJsonPath('data.daily_reminders.device_registered', true)
            ->assertJsonPath('data.daily_reminders.on', true);
    }

    public function test_on_this_day_memories_are_the_users_own(): void
    {
        $user = $this->user();
        $other = $this->user();

        $mine = Note::create(['user_id' => $user->id, 'title' => 'First day at the new job']);
        $mine->forceFill(['created_at' => Carbon::parse('2025-10-08 15:00:00', 'Africa/Kampala')->setTimezone(config('app.timezone'))])->save();
        $theirs = Note::create(['user_id' => $other->id, 'title' => 'Someone else memory']);
        $theirs->forceFill(['created_at' => Carbon::parse('2025-10-08 15:00:00', 'Africa/Kampala')->setTimezone(config('app.timezone'))])->save();

        Sanctum::actingAs($user);

        $items = $this->getJson('/api/dashboard/today-hub')->assertOk()->json('data.on_this_day.items');

        $texts = array_column($items, 'text');
        $this->assertNotEmpty(array_filter($texts, fn ($text) => str_contains($text, 'First day at the new job')));
        $this->assertEmpty(array_filter($texts, fn ($text) => str_contains($text, 'Someone else memory')));
        $this->assertSame('A year ago', $items[0]['when']);
    }

    public function test_existing_dashboard_payload_is_unchanged(): void
    {
        Sanctum::actingAs($this->user());

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonStructure(['monthly_income', 'monthly_expenses', 'top_tasks', 'finance_summary', 'today_insight', 'server_context']);
    }
}
