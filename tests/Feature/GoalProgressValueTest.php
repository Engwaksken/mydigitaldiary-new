<?php

namespace Tests\Feature;

use App\Models\PersonalGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalProgressValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_is_auto_calculated_from_target_and_current_value(): void
    {
        $user = User::factory()->create();

        $goal = PersonalGoal::create([
            'user_id' => $user->id,
            'module' => 'finance',
            'title' => 'Save 1,000,000',
            'target_value' => 1_000_000,
            'current_value' => 250_000,
            'progress_percent' => 0,
            'status' => 'not_started',
            'priority' => 'medium',
        ]);

        $this->assertSame(25, (int) $goal->fresh()->progress_percent);
        $this->assertSame('in_progress', $goal->fresh()->status);
    }

    public function test_target_current_ratio_overrides_a_manually_set_progress(): void
    {
        $user = User::factory()->create();

        $goal = PersonalGoal::create([
            'user_id' => $user->id,
            'module' => 'personal',
            'title' => 'Read pages',
            'target_value' => 100,
            'current_value' => 40,
            'progress_percent' => 80,
            'status' => 'in_progress',
            'priority' => 'low',
        ]);

        $this->assertSame(40, (int) $goal->fresh()->progress_percent);
    }

    public function test_progress_is_capped_at_100_and_completes_the_goal(): void
    {
        $user = User::factory()->create();

        $goal = PersonalGoal::create([
            'user_id' => $user->id,
            'module' => 'personal',
            'title' => 'Over the target',
            'target_value' => 10,
            'current_value' => 15,
            'progress_percent' => 0,
            'status' => 'not_started',
            'priority' => 'medium',
        ]);

        $this->assertSame(100, (int) $goal->fresh()->progress_percent);
        $this->assertSame('completed', $goal->fresh()->status);
    }
}
