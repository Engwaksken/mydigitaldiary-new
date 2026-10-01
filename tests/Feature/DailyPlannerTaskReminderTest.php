<?php

namespace Tests\Feature;

use App\Models\DailyPlanItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyPlannerTaskReminderTest extends TestCase
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

    private function addTask(User $user, array $overrides = []): DailyPlanItem
    {
        $this->actingAs($user)
            ->post(route('daily-planner.items.store'), [
                'plan_date' => today()->toDateString(),
                'title' => 'Call the bank',
                'priority' => 'medium',
                'start_time' => '09:00',
                'repeat_type' => 'once',
                ...$overrides,
            ])
            ->assertSessionHasNoErrors();

        return DailyPlanItem::query()->latest('id')->firstOrFail();
    }

    public function test_daily_planner_page_renders_add_and_edit_reminder_fields(): void
    {
        $this->actingAs($this->subscriber())
            ->get(route('daily-planner.index'))
            ->assertOk()
            ->assertSee('id="addTaskReminderFields"', false)
            ->assertSee('id="editTaskReminderFields"', false)
            ->assertSee('id="editReminderEnabled"', false)
            ->assertSee('id="addTask-panel-schedule"', false)
            ->assertSee('id="editTask-panel-reminder"', false);
    }

    public function test_adding_a_task_saves_its_reminder_settings(): void
    {
        $item = $this->addTask($this->subscriber(), [
            'reminder_enabled' => '1',
            'reminder_offset_minutes' => '30',
            'reminder_channels' => ['in_app', 'email'],
        ]);

        $this->assertTrue($item->reminder_enabled);
        $this->assertSame(30, $item->reminder_offset_minutes);
        $this->assertNull($item->reminder_custom_at);
        $this->assertSame(['in_app', 'email'], $item->reminder_channels);
    }

    public function test_custom_reminder_time_is_saved_without_an_offset(): void
    {
        $at = today()->addDay()->setTime(8, 30);

        $item = $this->addTask($this->subscriber(), [
            'reminder_enabled' => '1',
            'reminder_offset_minutes' => 'custom',
            'reminder_custom_at' => $at->format('Y-m-d\TH:i'),
        ]);

        $this->assertNull($item->reminder_offset_minutes);
        $this->assertTrue($item->reminder_custom_at->equalTo($at));
    }

    public function test_unticking_remind_me_on_edit_disables_the_reminder(): void
    {
        $user = $this->subscriber();
        $item = $this->addTask($user, [
            'reminder_enabled' => '1',
            'reminder_offset_minutes' => '15',
        ]);

        $this->actingAs($user)
            ->put(route('daily-planner.items.update', $item), [
                'plan_date' => today()->toDateString(),
                'title' => 'Call the bank',
                'priority' => 'medium',
                'start_time' => '09:00',
                'repeat_type' => 'once',
                'reminder_offset_minutes' => '15',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($item->fresh()->reminder_enabled);
    }
}
