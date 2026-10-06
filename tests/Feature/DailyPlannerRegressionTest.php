<?php

namespace Tests\Feature;

use App\Models\DailyPlanItem;
use App\Models\User;
use App\Services\DailyPlannerTaskReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Support\PersonalGoalFactory;
use Tests\TestCase;

class DailyPlannerRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function subscriber(): User
    {
        return User::factory()->create(['subscription_status' => 'active', 'subscription_expires_at' => now()->addMonth()]);
    }

    private function item(User $user, array $overrides = []): DailyPlanItem
    {
        $this->actingAs($user)->postJson('/api/daily-planner/items', [
            'plan_date' => '2026-10-06', 'title' => 'Morning walk', 'priority' => 'medium',
            'start_time' => '09:00:00', 'end_time' => '09:30:00', 'repeat_type' => 'weekly',
            'repeat_interval' => 2, 'repeat_starts_on' => '2026-10-06', 'repeat_ends_on' => '2027-01-06',
            ...$overrides,
        ])->assertCreated();
        return DailyPlanItem::query()->latest('id')->firstOrFail();
    }

    public static function transports(): array
    {
        return ['web' => [false], 'api' => [true]];
    }

    private function url(bool $api, DailyPlanItem $item): string
    {
        return $api ? '/api/daily-planner/items/'.$item->id : route('daily-planner.items.update', $item);
    }

    #[DataProvider('transports')]
    public function test_second_precision_times_are_accepted_on_create(bool $api): void
    {
        $response = $this->actingAs($this->subscriber())->postJson($api ? '/api/daily-planner/items' : route('daily-planner.items.store'), [
            'plan_date' => '2026-10-06', 'title' => 'Seconds', 'priority' => 'medium',
            'start_time' => '09:00:15', 'end_time' => '09:30:45',
        ]);
        $api ? $response->assertCreated() : $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('daily_plan_items', ['title' => 'Seconds', 'start_time' => '09:00:15', 'end_time' => '09:30:45']);
    }

    #[DataProvider('transports')]
    public function test_omitted_series_fields_survive_edit_with_second_precision_times(bool $api): void
    {
        $user = $this->subscriber();
        $item = $this->item($user);
        $series = $item->only(['repeat_type', 'repeat_days', 'repeat_interval', 'repeat_starts_on', 'repeat_ends_on', 'recurrence_group_id']);
        $response = $this->actingAs($user)->putJson($this->url($api, $item), [
            'title' => 'Edited walk', 'priority' => 'high', 'start_time' => '09:05:00', 'end_time' => '09:35:00',
        ]);
        $api ? $response->assertOk() : $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals($series, $item->fresh()->only(array_keys($series)));
    }

    #[DataProvider('transports')]
    public function test_explicit_once_clears_recurrence(bool $api): void
    {
        $user = $this->subscriber();
        $item = $this->item($user);
        $response = $this->actingAs($user)->putJson($this->url($api, $item), [
            'title' => 'One walk', 'priority' => 'medium', 'repeat_type' => 'once',
        ]);
        $api ? $response->assertOk() : $response->assertRedirect()->assertSessionHasNoErrors();
        $fresh = $item->fresh();
        $this->assertFalse($fresh->isRecurring());
        $this->assertNull($fresh->repeat_starts_on);
        $this->assertNull($fresh->repeat_ends_on);
        $this->assertNull($fresh->recurrence_group_id);
    }

    public function test_api_title_only_edit_preserves_schedule_and_reminder(): void
    {
        $user = $this->subscriber();
        $item = $this->item($user, ['reminder_enabled' => true, 'reminder_offset_minutes' => 15, 'reminder_channels' => ['email']]);
        $before = $item->only(['priority', 'start_time', 'end_time', 'repeat_type', 'repeat_interval', 'reminder_enabled', 'reminder_offset_minutes', 'reminder_channels', 'reminder_id']);
        $this->actingAs($user)->putJson($this->url(true, $item), ['title' => 'Renamed walk'])->assertOk();
        $this->assertEquals($before, $item->fresh()->only(array_keys($before)));
        $this->assertDatabaseHas('reminders', ['id' => $item->reminder_id, 'is_active' => true, 'title' => 'Task: Renamed walk']);
    }

    public function test_api_title_only_edit_preserves_omitted_task_fields(): void
    {
        $user = $this->subscriber();
        $item = $this->item($user, ['description' => 'Keep this description']);
        $before = $item->only(['description', 'priority', 'start_time', 'end_time', 'daily_plan_id',
            'repeat_type', 'repeat_interval', 'repeat_starts_on', 'repeat_ends_on', 'recurrence_group_id']);

        $this->actingAs($user)->putJson($this->url(true, $item), ['title' => 'Renamed'])->assertOk();

        $this->assertSame('Renamed', $item->fresh()->title);
        $this->assertEquals($before, $item->fresh()->only(array_keys($before)));
    }

    public function test_api_partial_reminder_edit_persists_custom_settings(): void
    {
        $user = $this->subscriber();
        $item = $this->item($user);
        // Isolate partial-request handling; the lifecycle test covers real reminders.
        $this->mock(DailyPlannerTaskReminderService::class, function ($mock) use ($item): void {
            $mock->shouldReceive('sync')->once()->withArgs(fn (DailyPlanItem $task, User $owner, Carbon $date) =>
                $task->id === $item->id && $task->reminder_enabled && $task->reminder_custom_at->format('Y-m-d H:i:s') === '2026-10-20 08:15:00'
            )->andReturnNull();
        });

        $this->actingAs($user)->putJson($this->url(true, $item), [
            'reminder_enabled' => true, 'reminder_offset_minutes' => 'custom',
            'reminder_custom_at' => '2026-10-20T08:15:00', 'reminder_channels' => ['email'],
        ])->assertOk();

        $fresh = $item->fresh();
        $this->assertTrue($fresh->reminder_enabled);
        $this->assertNull($fresh->reminder_offset_minutes);
        $this->assertSame(['email'], $fresh->reminder_channels);
        $this->assertSame('2026-10-20 08:15:00', $fresh->reminder_custom_at->format('Y-m-d H:i:s'));
    }

    public function test_api_partial_edit_can_disable_reminder(): void
    {
        $user = $this->subscriber();
        $item = $this->item($user, ['reminder_enabled' => true, 'reminder_offset_minutes' => 15]);
        $this->actingAs($user)->putJson($this->url(true, $item), ['reminder_enabled' => false])->assertOk();
        $this->assertFalse($item->fresh()->reminder_enabled);
        $this->assertDatabaseHas('reminders', ['id' => $item->reminder_id, 'is_active' => false]);
    }

    #[DataProvider('transports')]
    public function test_foreign_goal_is_rejected_without_changing_item(bool $api): void
    {
        $user = $this->subscriber();
        $item = $this->item($user);
        $goal = PersonalGoalFactory::new()->create();
        $this->actingAs($user)->putJson($this->url($api, $item), [
            'title' => 'Forbidden goal', 'priority' => 'medium', 'personal_goal_id' => $goal->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('personal_goal_id');
        $this->assertNull($item->fresh()->personal_goal_id);
        $this->assertSame('Morning walk', $item->fresh()->title);
    }

    #[DataProvider('transports')]
    public function test_other_user_cannot_edit_item(bool $api): void
    {
        $item = $this->item($this->subscriber());
        $this->actingAs($this->subscriber())->putJson($this->url($api, $item), ['title' => 'Stolen', 'priority' => 'medium'])->assertForbidden();
        $this->assertSame('Morning walk', $item->fresh()->title);
    }

    public function test_guest_cannot_create_api_item(): void
    {
        $this->postJson('/api/daily-planner/items', ['title' => 'Guest'])->assertUnauthorized();
    }

    public function test_planner_recovery_markers_and_full_input_are_flashed_after_validation_failure(): void
    {
        $user = $this->subscriber();
        $item = $this->item($user);
        $this->actingAs($user)->from(route('daily-planner.index'))->put($this->url(false, $item), [
            '_planner_form' => 'edit-task', '_editing_task_id' => (string) $item->id,
            'title' => '', 'priority' => 'high', 'description' => 'Unsaved notes',
            'repeat_type' => 'specific_days', 'repeat_days' => ['tuesday'],
        ])->assertRedirect(route('daily-planner.index'))->assertSessionHasErrors('title')
            ->assertSessionHasInput('_planner_form', 'edit-task')->assertSessionHasInput('_editing_task_id', (string) $item->id)
            ->assertSessionHasInput('description', 'Unsaved notes')->assertSessionHasInput('repeat_days', ['tuesday']);
        $this->assertSame('Morning walk', $item->fresh()->title);
    }

    public function test_completed_task_move_edit_rejection_retains_recovery_markers(): void
    {
        $user = $this->subscriber();
        $item = $this->item($user, ['repeat_type' => 'once']);
        $this->actingAs($user)->patchJson($this->url(true, $item).'/toggle')->assertOk();

        $this->actingAs($user)->from(route('daily-planner.index'))->put($this->url(false, $item), [
            '_planner_form' => 'edit-task', '_editing_task_id' => (string) $item->id,
            'title' => 'Unsaved move edit', 'priority' => 'medium', 'plan_date' => '2026-10-07', 'repeat_type' => 'once',
        ])->assertRedirect()->assertSessionHasErrors('task')
            ->assertSessionHasInput('_planner_form', 'edit-task')->assertSessionHasInput('_editing_task_id', (string) $item->id)
            ->assertSessionHasInput('title', 'Unsaved move edit')->assertSessionHasInput('plan_date', '2026-10-07');
        $this->assertSame('2026-10-06', $item->fresh()->plan->plan_date->toDateString());
        $this->assertSame('Morning walk', $item->fresh()->title);
    }

    public function test_successful_web_edit_does_not_persist_recovery_markers(): void
    {
        $user = $this->subscriber();
        $item = $this->item($user);
        $this->actingAs($user)->put($this->url(false, $item), [
            '_planner_form' => 'edit-task', '_editing_task_id' => (string) $item->id,
            'title' => 'Saved task', 'priority' => 'medium', 'repeat_type' => 'weekly',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Saved task', $item->fresh()->title);
        $this->assertArrayNotHasKey('_planner_form', $item->fresh()->getAttributes());
        $this->assertArrayNotHasKey('_editing_task_id', $item->fresh()->getAttributes());
    }

    public static function emptyGroups(): array
    {
        return ['web unchecked group' => [false, 'omitted'], 'API explicit empty array' => [true, 'empty'], 'API explicit null' => [true, 'null']];
    }

    #[DataProvider('emptyGroups')]
    public function test_intentionally_empty_channels_disable_existing_reminder(bool $api, string $selection): void
    {
        $user = $this->subscriber();
        $item = $this->item($user, ['reminder_enabled' => true, 'reminder_offset_minutes' => 15, 'reminder_channels' => ['email']]);
        $data = ['title' => 'Morning walk', 'priority' => 'medium', 'repeat_type' => 'weekly', 'reminder_enabled' => true];
        if ($selection !== 'omitted') {
            $data['reminder_channels'] = $selection === 'null' ? null : [];
        }
        $response = $api ? $this->actingAs($user)->putJson($this->url(true, $item), $data) : $this->actingAs($user)->put($this->url(false, $item), $data);
        $api ? $response->assertOk() : $response->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame([], $item->fresh()->reminder_channels);
        $this->assertFalse($item->fresh()->reminder_enabled);
        $this->assertDatabaseHas('reminders', ['id' => $item->reminder_id, 'is_active' => false]);
    }

    #[DataProvider('emptyGroups')]
    public function test_specific_days_rejects_intentionally_empty_day_selection(bool $api, string $selection): void
    {
        $user = $this->subscriber();
        $item = $this->item($user, ['repeat_type' => 'specific_days', 'repeat_days' => ['tuesday']]);
        $data = ['title' => 'Morning walk', 'priority' => 'medium', 'repeat_type' => 'specific_days'];
        if ($selection !== 'omitted') {
            $data['repeat_days'] = $selection === 'null' ? null : [];
        }
        if ($api) {
            $this->actingAs($user)->putJson($this->url(true, $item), $data)->assertUnprocessable()->assertJsonValidationErrors('repeat_days');
        } else {
            $this->actingAs($user)->put($this->url(false, $item), $data)->assertRedirect()->assertSessionHasErrors('repeat_days');
        }
        $this->assertSame(['tuesday'], $item->fresh()->repeat_days);
        $this->assertSame('specific_days', $item->fresh()->repeat_type);
    }

    public function test_api_omitted_checkbox_groups_preserve_days_and_reminder_channels(): void
    {
        $user = $this->subscriber();
        $item = $this->item($user, ['repeat_type' => 'specific_days', 'repeat_days' => ['tuesday'],
            'reminder_enabled' => true, 'reminder_offset_minutes' => 15, 'reminder_channels' => ['email']]);

        $this->actingAs($user)->putJson($this->url(true, $item), ['title' => 'Renamed task'])->assertOk();

        $this->assertSame(['tuesday'], $item->fresh()->repeat_days);
        $this->assertSame(['email'], $item->fresh()->reminder_channels);
        $this->assertTrue($item->fresh()->reminder_enabled);
        $this->assertDatabaseHas('reminders', ['id' => $item->reminder_id, 'is_active' => true]);
    }

    #[DataProvider('transports')]
    public function test_normalized_legacy_occurrence_reopens_existing_record_without_losing_overrides(bool $api): void
    {
        $user = $this->subscriber();
        $item = $this->item($user);
        $date = '2026-10-20';
        $url = $api ? $this->url(true, $item).'/toggle' : route('daily-planner.items.toggle', $item);
        $response = $this->actingAs($user)->patchJson($url, ['occurrence_date' => $date]);
        $api ? $response->assertOk() : $response->assertRedirect();
        $occurrence = $item->occurrences()->firstOrFail();
        $occurrence->update(['title_override' => 'Legacy edited walk', 'description_override' => 'Keep my notes',
            'priority_override' => 'high', 'start_time_override' => '09:05:00', 'end_time_override' => '09:35:00']);
        // Reproduce historical SQLite storage, deliberately bypassing the new mutator.
        DB::table('daily_plan_item_occurrences')->where('id', $occurrence->id)->update(['occurrence_date' => $date.' 00:00:00']);
        $before = (array) DB::table('daily_plan_item_occurrences')->where('id', $occurrence->id)->first();

        $migration = require database_path('migrations/2026_10_06_000003_normalize_sqlite_daily_plan_item_occurrence_dates.php');
        $migration->up();

        $after = (array) DB::table('daily_plan_item_occurrences')->where('id', $occurrence->id)->first();
        $this->assertSame(array_replace($before, ['occurrence_date' => $date]), $after);
        $this->assertSame($date, $occurrence->fresh()->occurrence_date->toDateString(), 'Carbon reads remain available.');
        $response = $this->actingAs($user)->patchJson($url, ['occurrence_date' => $date]);
        $api ? $response->assertOk() : $response->assertRedirect();

        $fresh = $occurrence->fresh();
        $this->assertFalse($fresh->is_completed);
        $this->assertNull($fresh->completed_at);
        $this->assertSame(1, $item->occurrences()->count());
        $this->assertSame($occurrence->id, $item->occurrences()->firstOrFail()->id);
        $this->assertSame('Legacy edited walk', $fresh->title_override);
        $this->assertSame('Keep my notes', $fresh->description_override);
        $this->assertSame('high', $fresh->priority_override);
        $this->assertSame('09:05:00', $fresh->start_time_override);
        $this->assertSame('09:35:00', $fresh->end_time_override);
    }

    #[DataProvider('transports')]
    public function test_recurring_toggle_passes_completion_state_to_side_effects(bool $api): void
    {
        $user = $this->subscriber();
        $goal = PersonalGoalFactory::new()->create(['user_id' => $user->id]);
        $item = $this->item($user, ['personal_goal_id' => $goal->id]);
        $date = '2026-10-20';
        // Verify the reminder boundary explicitly; wellbeing and occurrence writes
        // still execute against the real database.
        $this->mock(DailyPlannerTaskReminderService::class, function ($mock) use ($item, $user, $date): void {
            $mock->shouldReceive('cancel')->once()->withArgs(fn (DailyPlanItem $task, User $owner) => $task->id === $item->id && $owner->id === $user->id);
            $mock->shouldReceive('sync')->once()->withArgs(fn (DailyPlanItem $task, User $owner, Carbon $on) => $task->id === $item->id && $owner->id === $user->id && $on->toDateString() === $date)->andReturnNull();
        });
        $url = $api ? $this->url(true, $item).'/toggle' : route('daily-planner.items.toggle', $item);
        $key = "daily-plan:{$item->id}:{$date}:exercise";

        $response = $this->actingAs($user)->patchJson($url, ['occurrence_date' => $date]);
        $api ? $response->assertOk() : $response->assertRedirect();
        $this->assertFalse($item->fresh()->is_completed);
        $this->assertTrue($item->occurrences()->whereDate('occurrence_date', $date)->firstOrFail()->is_completed);
        $this->assertDatabaseHas('exercise_logs', ['daily_plan_source_key' => $key, 'duration_minutes' => 30]);

        $response = $this->actingAs($user)->patchJson($url, ['occurrence_date' => $date]);
        $api ? $response->assertOk() : $response->assertRedirect();
        $this->assertFalse($item->occurrences()->whereDate('occurrence_date', $date)->firstOrFail()->is_completed);
        $this->assertDatabaseMissing('exercise_logs', ['daily_plan_source_key' => $key]);
    }

    #[DataProvider('transports')]
    public function test_recurring_toggle_uses_occurrence_state_for_reminder_and_wellbeing(bool $api): void
    {
        $user = $this->subscriber();
        $goal = PersonalGoalFactory::new()->create(['user_id' => $user->id]);
        $item = $this->item($user, ['personal_goal_id' => $goal->id, 'reminder_enabled' => true, 'reminder_offset_minutes' => 15]);
        $url = $api ? $this->url(true, $item).'/toggle' : route('daily-planner.items.toggle', $item);
        $date = '2026-10-20';
        $key = "daily-plan:{$item->id}:{$date}:exercise";
        $response = $this->actingAs($user)->patchJson($url, ['occurrence_date' => $date]);
        $api ? $response->assertOk() : $response->assertRedirect();
        $this->assertFalse($item->fresh()->is_completed, 'Completing an occurrence must not complete the series.');
        $this->assertDatabaseHas('daily_plan_item_occurrences', ['daily_plan_item_id' => $item->id, 'occurrence_date' => $date, 'is_completed' => true]);
        $this->assertDatabaseHas('reminders', ['id' => $item->reminder_id, 'is_active' => false]);
        $this->assertDatabaseHas('exercise_logs', ['user_id' => $user->id, 'daily_plan_source_key' => $key, 'duration_minutes' => 30]);
        $response = $this->actingAs($user)->patchJson($url, ['occurrence_date' => $date]);
        $api ? $response->assertOk() : $response->assertRedirect();
        $this->assertDatabaseHas('daily_plan_item_occurrences', ['daily_plan_item_id' => $item->id, 'is_completed' => false]);
        $this->assertDatabaseHas('reminders', ['id' => $item->reminder_id, 'is_active' => true, 'next_run_at' => $date.' 08:45:00']);
        $this->assertDatabaseMissing('exercise_logs', ['daily_plan_source_key' => $key]);
        $this->assertSame(1, $item->occurrences()->whereDate('occurrence_date', $date)->count());
    }
}
