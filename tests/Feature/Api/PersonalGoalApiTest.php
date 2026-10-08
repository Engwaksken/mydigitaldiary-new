<?php

namespace Tests\Feature\Api;

use App\Models\PersonalGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonalGoalApiTest extends TestCase
{
    use RefreshDatabase;

    private function goal(User $user, array $attributes = []): PersonalGoal
    {
        return PersonalGoal::create(array_merge([
            'user_id' => $user->id,
            'module' => 'personal',
            'title' => 'Read 12 books',
            'progress_percent' => 25,
            'status' => 'in_progress',
            'priority' => 'medium',
            'target_date' => now()->addMonth()->toDateString(),
        ], $attributes));
    }

    public function test_lists_the_users_goals_with_the_query_the_app_sends(): void
    {
        $user = User::factory()->create();
        $mine = $this->goal($user);
        $this->goal(User::factory()->create(), ['title' => 'Someone else']);
        Sanctum::actingAs($user);

        $this->getJson('/api/personal-goals?archived=0&page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.title', 'Read 12 books')
            ->assertJsonStructure(['data', 'current_page', 'last_page', 'total']);
    }

    public function test_filters_by_module_period_and_archived(): void
    {
        $user = User::factory()->create();
        $this->goal($user, ['module' => 'finance', 'title' => 'Save']);
        $this->goal($user, ['module' => 'health', 'title' => 'Run']);
        $this->goal($user, ['title' => 'Old', 'is_archived' => true]);
        Sanctum::actingAs($user);

        $this->getJson('/api/personal-goals?module=finance')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Save');
        $this->getJson('/api/personal-goals?archived=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Old');
        $this->getJson('/api/personal-goals?period=month')->assertOk();
        $this->getJson('/api/personal-goals?period=bogus')->assertStatus(422);
    }

    public function test_stats_and_non_numeric_ids_do_not_error(): void
    {
        $user = User::factory()->create();
        $this->goal($user);
        Sanctum::actingAs($user);

        $this->getJson('/api/personal-goals/stats')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('by_status.in_progress', 1);
        $this->getJson('/api/reminders/overview?scope=today')->assertNotFound();
        $this->getJson('/api/budgets/stats')->assertNotFound();
    }
}
