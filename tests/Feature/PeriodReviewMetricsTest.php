<?php

namespace Tests\Feature;

use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use App\Services\DailyPlannerRecurrenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PeriodReviewMetricsTest extends TestCase
{
    use RefreshDatabase;

    private function seedPlanner(User $user): void
    {
        $monthStart = Carbon::now('Africa/Kampala')->startOfMonth();

        $base = DailyPlan::create([
            'user_id' => $user->id,
            'plan_date' => $monthStart->copy()->subDays(10)->toDateString(),
            'title' => 'Series home',
        ]);

        $daily = DailyPlanItem::create([
            'daily_plan_id' => $base->id,
            'title' => 'Daily habit',
            'priority' => 'medium',
            'repeat_type' => 'daily',
            'repeat_starts_on' => $base->plan_date,
            'is_completed' => false,
        ]);

        // One completed and one skipped occurrence inside the month.
        $daily->occurrences()->create([
            'user_id' => $user->id,
            'occurrence_date' => $monthStart->copy()->addDays(1)->toDateString(),
            'is_completed' => true,
            'completed_at' => now(),
        ]);
        $daily->occurrences()->create([
            'user_id' => $user->id,
            'occurrence_date' => $monthStart->copy()->addDays(2)->toDateString(),
            'is_skipped' => true,
        ]);

        DailyPlanItem::create([
            'daily_plan_id' => $base->id,
            'title' => 'Weekly review',
            'priority' => 'low',
            'repeat_type' => 'weekly',
            'repeat_starts_on' => $base->plan_date,
            'is_completed' => false,
        ]);

        foreach ([0, 3, 9] as $offset) {
            $plan = DailyPlan::create([
                'user_id' => $user->id,
                'plan_date' => $monthStart->copy()->addDays($offset)->toDateString(),
                'title' => 'Plan',
            ]);
            DailyPlanItem::create([
                'daily_plan_id' => $plan->id,
                'title' => "Once $offset",
                'priority' => 'high',
                'is_completed' => $offset === 3,
            ]);
        }

        // Another user's tasks must never be counted.
        $other = User::factory()->create();
        $otherPlan = DailyPlan::create([
            'user_id' => $other->id,
            'plan_date' => $monthStart->toDateString(),
            'title' => 'Other',
        ]);
        DailyPlanItem::create([
            'daily_plan_id' => $otherPlan->id,
            'title' => 'Not mine',
            'repeat_type' => 'daily',
            'repeat_starts_on' => $monthStart->toDateString(),
            'is_completed' => false,
        ]);
    }

    public function test_range_statistics_match_per_day_resolution(): void
    {
        $user = User::factory()->create();
        $this->seedPlanner($user);

        $service = app(DailyPlannerRecurrenceService::class);
        $from = Carbon::now('Africa/Kampala')->startOfMonth();
        $to = Carbon::now('Africa/Kampala')->endOfMonth();

        $expectedTotal = 0;
        $expectedCompleted = 0;
        $expectedDays = 0;
        for ($date = Carbon::parse($from->toDateString()); $date->lte(Carbon::parse($to->toDateString())); $date->addDay()) {
            $stats = $service->statistics($service->itemsForDate($user->id, $date->copy()));
            $expectedTotal += $stats['total'];
            $expectedCompleted += $stats['completed'];
            if ($stats['total'] > 0) {
                $expectedDays++;
            }
        }

        $range = $service->statisticsForRange($user->id, $from, $to);

        $this->assertGreaterThan(0, $expectedTotal);
        $this->assertSame($expectedTotal, $range['total']);
        $this->assertSame($expectedCompleted, $range['completed']);
        $this->assertSame($expectedDays, $range['days']);
    }

    public function test_review_endpoints_return_recurrence_aware_totals_in_one_request(): void
    {
        $user = User::factory()->create(['timezone' => 'Africa/Kampala']);
        $this->seedPlanner($user);
        Sanctum::actingAs($user);

        $monthStart = Carbon::now('Africa/Kampala')->startOfMonth();
        Income::create([
            'user_id' => $user->id, 'source' => 'Salary', 'amount' => 1000,
            'frequency' => 'one_time', 'received_at' => $monthStart->toDateString(),
        ]);
        Income::create([
            'user_id' => $user->id, 'source' => 'Old', 'amount' => 999,
            'frequency' => 'one_time', 'received_at' => $monthStart->toDateString(),
            'is_archived' => true,
        ]);
        Expense::create([
            'user_id' => $user->id, 'category' => 'Food', 'amount' => 250,
            'spent_at' => $monthStart->toDateString(),
        ]);

        $month = $this->getJson('/api/engagement/review/month')->assertOk();

        $service = app(DailyPlannerRecurrenceService::class);
        $expected = $service->statisticsForRange(
            $user->id,
            $monthStart,
            Carbon::now('Africa/Kampala')->endOfMonth()
        );

        $month->assertJsonPath('data.tasks_source', 'daily_planner')
            ->assertJsonPath('data.tasks_total', $expected['total'])
            ->assertJsonPath('data.tasks_completed', $expected['completed'])
            ->assertJsonPath('data.planner_days', $expected['days'])
            ->assertJsonPath('data.period_start', $monthStart->toDateString())
            ->assertJsonStructure(['data' => [
                'tasks_total', 'tasks_completed', 'completion_percent',
                'meaningful_days', 'exercise_sessions', 'income', 'expenses',
                'streak' => ['current', 'best'], 'period_start', 'period_end',
            ]]);
        $this->assertEquals(1000, $month->json('data.income'));
        $this->assertEquals(250, $month->json('data.expenses'));

        $this->getJson('/api/engagement/review/week')
            ->assertOk()
            ->assertJsonPath('data.tasks_source', 'daily_planner');
    }
}
