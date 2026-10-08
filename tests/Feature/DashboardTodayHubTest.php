<?php

namespace Tests\Feature;

use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTodayHubTest extends TestCase
{
    use RefreshDatabase;

    private function subscriber(): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
        ])->save();

        return $user;
    }

    private function todayTask(User $user, string $title = 'Call the bank'): DailyPlanItem
    {
        $plan = DailyPlan::create([
            'user_id' => $user->id,
            'plan_date' => now($user->timezone ?: 'Africa/Kampala')->toDateString(),
            'title' => 'My Daily Plan',
        ]);

        return DailyPlanItem::create([
            'daily_plan_id' => $plan->id,
            'title' => $title,
            'priority' => 'high',
            'is_completed' => false,
        ]);
    }

    public function test_dashboard_renders_today_hub_with_checkable_tasks(): void
    {
        $user = $this->subscriber();
        $item = $this->todayTask($user);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Today’s tasks', false);
        $response->assertSee('Call the bank');
        $response->assertSee(route('daily-planner.items.toggle', $item->id), false);
        $response->assertSee('id="td-get-started"', false);
        $response->assertSee('id="pm-bottom-nav"', false);
        $response->assertSee(route('daily-planner.index', ['new' => 1]), false);
    }

    public function test_dashboard_shows_single_action_empty_state_without_tasks(): void
    {
        $user = $this->subscriber();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Add a task');
    }

    public function test_planner_toggle_returns_json_for_dashboard_check_off(): void
    {
        $user = $this->subscriber();
        $item = $this->todayTask($user);

        $this->actingAs($user)
            ->patchJson(route('daily-planner.items.toggle', $item->id), [
                'occurrence_date' => $item->plan->plan_date->toDateString(),
                'respond' => 'json',
            ])
            ->assertOk()
            ->assertJson(['id' => $item->id, 'is_completed' => true]);

        $this->assertTrue((bool) $item->fresh()->is_completed);
    }

    public function test_planner_toggle_still_redirects_for_regular_form_posts(): void
    {
        $user = $this->subscriber();
        $item = $this->todayTask($user);

        $this->actingAs($user)
            ->patch(route('daily-planner.items.toggle', $item->id))
            ->assertRedirect();

        // JSON-accepting callers without the explicit flag keep the redirect too.
        $this->actingAs($user)
            ->patchJson(route('daily-planner.items.toggle', $item->id))
            ->assertRedirect();
    }
}
