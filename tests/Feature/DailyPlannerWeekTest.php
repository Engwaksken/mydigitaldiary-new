<?php

namespace Tests\Feature;

use App\Models\DailyPlanItem;
use App\Models\User;
use App\Services\DailyPlannerRecurrenceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyPlannerWeekTest extends TestCase
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

    private function monday(): Carbon
    {
        return today()->startOfWeek(Carbon::MONDAY);
    }

    private function titlesOn(User $user, Carbon $date): array
    {
        return app(DailyPlannerRecurrenceService::class)
            ->itemsForDate($user->id, $date)
            ->pluck('title')
            ->all();
    }

    public function test_one_off_week_tasks_are_added_on_each_ticked_day(): void
    {
        $user = $this->subscriber();
        $monday = $this->monday();

        $this->actingAs($user)
            ->post(route('daily-planner.week.store'), [
                // Any day of the week resolves to that week's Monday.
                'week_start' => $monday->copy()->addDays(3)->toDateString(),
                'tasks' => [[
                    'title' => 'Gym',
                    'priority' => 'high',
                    'start_time' => '06:00',
                    'end_time' => '07:00',
                    'days' => ['friday', 'monday', 'wednesday'],
                ]],
            ])
            ->assertRedirect(route('daily-planner.index', ['date' => $monday->toDateString(), 'tab' => 'week']))
            ->assertSessionHas('success', '3 tasks added to your week.');

        $this->assertSame(3, DailyPlanItem::where('title', 'Gym')->where('repeat_type', 'once')->count());
        $this->assertSame(['Gym'], $this->titlesOn($user, $monday));
        $this->assertSame([], $this->titlesOn($user, $monday->copy()->addDay()));
        $this->assertSame(['Gym'], $this->titlesOn($user, $monday->copy()->addDays(2)));
        $this->assertSame(['Gym'], $this->titlesOn($user, $monday->copy()->addDays(4)));
        $this->assertSame([], $this->titlesOn($user, $monday->copy()->addWeek()));
    }

    public function test_repeat_every_week_stores_one_recurring_task(): void
    {
        $user = $this->subscriber();
        $monday = $this->monday();

        $this->actingAs($user)
            ->post(route('daily-planner.week.store'), [
                'week_start' => $monday->toDateString(),
                'tasks' => [
                    ['title' => 'Team sync', 'priority' => 'medium', 'days' => ['tuesday', 'thursday'], 'repeat_weekly' => '1'],
                    ['title' => 'Read', 'priority' => 'low', 'days' => DailyPlannerRecurrenceService::WEEK_DAYS, 'repeat_weekly' => '1'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $sync = DailyPlanItem::where('title', 'Team sync')->sole();
        $this->assertSame('specific_days', $sync->repeat_type);
        $this->assertSame(['tuesday', 'thursday'], $sync->repeat_days);
        $this->assertSame('daily', DailyPlanItem::where('title', 'Read')->sole()->repeat_type);

        // Next week's Thursday has both; next week's Wednesday only the daily one.
        $nextMonday = $monday->copy()->addWeek();
        $this->assertEqualsCanonicalizing(['Team sync', 'Read'], $this->titlesOn($user, $nextMonday->copy()->addDays(3)));
        $this->assertSame(['Read'], $this->titlesOn($user, $nextMonday->copy()->addDays(2)));
    }

    public function test_every_week_task_needs_a_day(): void
    {
        $this->actingAs($this->subscriber())
            ->post(route('daily-planner.week.store'), [
                'week_start' => $this->monday()->toDateString(),
                'tasks' => [['title' => 'No days', 'priority' => 'medium']],
            ])
            ->assertSessionHasErrors('tasks.0.days');

        $this->assertSame(0, DailyPlanItem::count());
    }

    public function test_week_tab_lists_the_weeks_tasks(): void
    {
        $user = $this->subscriber();
        $monday = $this->monday();

        $this->actingAs($user)->post(route('daily-planner.week.store'), [
            'week_start' => $monday->toDateString(),
            'tasks' => [['title' => 'Sunday prep', 'priority' => 'medium', 'days' => ['sunday']]],
        ]);

        $this->actingAs($user)
            ->get(route('daily-planner.index', ['date' => $monday->toDateString(), 'tab' => 'week']))
            ->assertOk()
            ->assertSee('id="dp-tab-week" class="dp-tab-panel is-active"', false)
            ->assertSee('Sunday prep')
            ->assertSee('id="planWeekModal"', false);
    }

    public function test_past_tasks_tab_panel_renders_past_plans(): void
    {
        $user = $this->subscriber();
        $lastWeek = $this->monday()->subWeek();

        $this->actingAs($user)->post(route('daily-planner.week.store'), [
            'week_start' => $lastWeek->toDateString(),
            'tasks' => [['title' => 'Old errand', 'priority' => 'low', 'days' => ['tuesday']]],
        ]);

        // The panel id must match what the tab script toggles (dp-tab-history).
        $this->actingAs($user)
            ->get(route('daily-planner.index', ['tab' => 'history']))
            ->assertOk()
            ->assertSee('id="dp-tab-history" class="dp-tab-panel is-active"', false)
            ->assertSee($lastWeek->copy()->addDay()->format('d M Y'));
    }

    public function test_toggling_from_the_week_view_returns_to_it(): void
    {
        $user = $this->subscriber();
        $monday = $this->monday();

        $this->actingAs($user)->post(route('daily-planner.week.store'), [
            'week_start' => $monday->toDateString(),
            'tasks' => [['title' => 'Laundry', 'priority' => 'low', 'days' => ['monday']]],
        ]);

        $item = DailyPlanItem::where('title', 'Laundry')->sole();

        $this->actingAs($user)
            ->patch(route('daily-planner.items.toggle', $item), [
                'occurrence_date' => $monday->toDateString(),
                'return_tab' => 'week',
            ])
            ->assertRedirect(route('daily-planner.index', ['date' => $monday->toDateString(), 'tab' => 'week']));

        $this->assertTrue((bool) $item->fresh()->is_completed);
    }
}
