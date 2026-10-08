<?php

namespace Tests\Feature;

use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Models\Note;
use App\Models\User;
use App\Services\OnThisDayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OnThisDayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 10:00 in Kampala on 8 October 2026.
        $this->travelTo(Carbon::parse('2026-10-08 07:00:00', 'UTC'));
    }

    private function subscriber(): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addYear(),
            'timezone' => 'Africa/Kampala',
        ])->save();

        return $user->fresh();
    }

    public function test_dashboard_hides_the_card_when_nothing_qualifies(): void
    {
        $user = $this->subscriber();

        // Two days ago is not an anchor day.
        $note = Note::create(['user_id' => $user->id, 'title' => 'Not a memory yet']);
        $note->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('id="td-memory-title"', false)
            ->assertDontSee('Not a memory yet');
    }

    public function test_dashboard_shows_memories_from_a_year_and_a_month_ago(): void
    {
        $user = $this->subscriber();
        $other = $this->subscriber();

        $note = Note::create(['user_id' => $user->id, 'title' => 'First day at the new job']);
        $note->forceFill(['created_at' => Carbon::parse('2025-10-08 15:00:00', 'Africa/Kampala')->setTimezone(config('app.timezone'))])->save();

        $plan = DailyPlan::create(['user_id' => $user->id, 'plan_date' => '2026-09-08', 'title' => 'My Daily Plan']);
        DailyPlanItem::create(['daily_plan_id' => $plan->id, 'title' => 'Ran 5km', 'priority' => 'high', 'is_completed' => true, 'completed_at' => now()->subMonth()]);
        DailyPlanItem::create(['daily_plan_id' => $plan->id, 'title' => 'Never finished', 'priority' => 'low', 'is_completed' => false]);

        // Someone else's memory must never leak.
        $foreign = Note::create(['user_id' => $other->id, 'title' => 'Someone else entirely']);
        $foreign->forceFill(['created_at' => now()->subYear()])->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('On this day')
            ->assertSee('A year ago')
            ->assertSee('First day at the new job', false)
            ->assertSee('A month ago')
            ->assertSee('Ran 5km', false)
            ->assertSee(route('notes.show', $note->id), false)
            ->assertSee('See more')
            ->assertDontSee('Never finished')
            ->assertDontSee('Someone else entirely');

        // The tiny comparison line: same week last month.
        $response->assertSee('Same week last month you completed 1 task.');
    }

    public function test_memories_include_savings_and_gratitude_and_feed_the_morning_push(): void
    {
        $user = $this->subscriber();

        $goalId = DB::table('savings_goals')->insertGetId([
            'user_id' => $user->id, 'name' => 'Emergency fund', 'target_amount' => 1000000,
            'status' => 'in_progress', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('savings_contributions')->insert([
            'user_id' => $user->id, 'savings_goal_id' => $goalId, 'amount' => 50000,
            'contributed_at' => '2026-10-01', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('daily_checkins')->insert([
            'user_id' => $user->id, 'checkin_date' => '2026-07-08', 'type' => 'close_day',
            'gratitude' => 'Dinner with my sister', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $memories = app(OnThisDayService::class)->forUser($user);
        $texts = collect($memories['items'])->pluck('text')->implode(' | ');

        $this->assertCount(2, $memories['items']);
        $this->assertSame('quarter', $memories['items'][0]['anchor']); // oldest first
        $this->assertStringContainsString('grateful for “Dinner with my sister”', $texts);
        $this->assertStringContainsString('toward “Emergency fund”', $texts);

        $line = app(OnThisDayService::class)->pushLine($user);
        $this->assertStringStartsWith('3 months ago: you were grateful for', $line);
    }
}
