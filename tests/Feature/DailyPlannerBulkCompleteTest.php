<?php

namespace Tests\Feature;

use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Models\User;
use App\Services\DailyPlannerRecurrenceService;
use App\Services\DailyPlannerTaskReminderService;
use App\Services\DailyPlannerWellbeingSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailyPlannerBulkCompleteTest extends TestCase
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

    private function task(User $user, array $attributes = [], ?string $date = null): DailyPlanItem
    {
        $date ??= today()->toDateString();
        $plan = DailyPlan::where('user_id', $user->id)->whereDate('plan_date', $date)->first();
        if (! $plan) {
            $plan = DailyPlan::findOrFail(DB::table('daily_plans')->insertGetId([
                'user_id' => $user->id,
                'plan_date' => $date,
                'title' => 'My Daily Plan',
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        return $plan->items()->create([
            'title' => 'Task',
            'priority' => 'medium',
            'repeat_type' => 'once',
            ...$attributes,
        ]);
    }

    private function complete(User $user, array $ids)
    {
        return $this->actingAs($user)->patch(route('daily-planner.items.bulk-complete'), [
            'ids' => $ids,
            'occurrence_date' => today()->toDateString(),
        ]);
    }

    public function test_selected_tasks_are_completed_with_reminder_and_wellbeing_sync(): void
    {
        $user = $this->subscriber();
        $first = $this->task($user);
        $second = $this->task($user);
        $unselected = $this->task($user);

        $this->mock(DailyPlannerTaskReminderService::class, function ($mock) {
            $mock->shouldReceive('cancel')->twice();
        });
        $this->mock(DailyPlannerWellbeingSyncService::class, function ($mock) {
            $mock->shouldReceive('syncCompletion')->withArgs(
                fn ($user, $item, $date, $completed) => $completed && $date->isToday()
            )->twice()->andReturnNull();
        });

        $this->complete($user, [$first->id, $second->id, $first->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('daily-planner.index', ['date' => today()->toDateString()]))
            ->assertSessionHas('success', '2 tasks marked complete.');

        $this->assertTrue($first->fresh()->is_completed);
        $this->assertNotNull($first->fresh()->completed_at);
        $this->assertTrue($second->fresh()->is_completed);
        $this->assertFalse($unselected->fresh()->is_completed);
    }

    public function test_completing_again_does_not_reopen_tasks_or_change_completion_time(): void
    {
        $user = $this->subscriber();
        $item = $this->task($user);
        $this->complete($user, [$item->id])->assertSessionHasNoErrors();
        $completedAt = $item->fresh()->completed_at;
        $this->travel(5)->minutes();

        $this->complete($user, [$item->id])->assertSessionHasNoErrors();

        $this->assertTrue($item->fresh()->is_completed);
        $this->assertTrue($item->fresh()->completed_at->equalTo($completedAt));
    }

    public function test_recurring_completion_only_affects_the_viewed_date(): void
    {
        $user = $this->subscriber();
        $item = $this->task($user, [
            'repeat_type' => 'daily',
            'repeat_starts_on' => today()->subDay()->toDateString(),
            'repeat_interval' => 1,
        ], today()->subDay()->toDateString());

        $this->complete($user, [$item->id])->assertSessionHasNoErrors();
        $this->complete($user, [$item->id])->assertSessionHasNoErrors();

        $this->assertSame(1, $item->occurrences()->count());
        $occurrence = $item->occurrences()->sole();
        $this->assertTrue($occurrence->is_completed);
        $this->assertTrue($occurrence->occurrence_date->isToday());
        $this->assertFalse($item->fresh()->is_completed);
        $tomorrow = app(DailyPlannerRecurrenceService::class)->itemsForDate($user->id, today()->addDay());
        $this->assertFalse($tomorrow->sole()->is_completed);
    }

    public function test_other_users_off_date_and_skipped_tasks_are_not_completed(): void
    {
        $user = $this->subscriber();
        $foreign = $this->task($this->subscriber());
        $offDate = $this->task($user, [], today()->addDay()->toDateString());
        $notStarted = $this->task($user, [
            'repeat_type' => 'daily',
            'repeat_starts_on' => today()->addDay()->toDateString(),
        ]);
        $skipped = $this->task($user, [
            'repeat_type' => 'daily',
            'repeat_starts_on' => today()->toDateString(),
        ]);
        app(DailyPlannerRecurrenceService::class)->skipOccurrence($skipped, $user->id, today());

        $this->complete($user, [$foreign->id, $offDate->id, $notStarted->id, $skipped->id])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'No pending tasks were selected for this day.');

        $this->assertFalse($foreign->fresh()->is_completed);
        $this->assertFalse($offDate->fresh()->is_completed);
        $this->assertSame(0, $notStarted->occurrences()->count());
        $this->assertTrue($skipped->occurrences()->sole()->is_skipped);
        $this->assertFalse($skipped->occurrences()->sole()->is_completed);
    }

    public function test_bulk_completion_requires_selection_and_date_and_renders_the_action(): void
    {
        $user = $this->subscriber();
        $this->task($user);

        $this->actingAs($user)->patch(route('daily-planner.items.bulk-complete'), [])
            ->assertSessionHasErrors(['ids', 'occurrence_date']);

        $this->actingAs($user)->get(route('daily-planner.index'))
            ->assertOk()
            ->assertSee('Mark selected complete')
            ->assertSee('id="bulkCompleteTaskForm"', false)
            ->assertSee(route('daily-planner.items.bulk-complete'), false);
    }
}
