<?php

namespace Tests\Feature;

use App\Models\HealthProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DailyStepsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_flutter_step_tracking_routes_load_start_sync_and_stop_today_steps(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/wellbeing/steps')
            ->assertOk()
            ->assertJsonPath('data.steps', 0)
            ->assertJsonPath('data.daily_goal', 5000)
            ->assertJsonPath('data.is_tracking', true)
            ->assertJsonPath('data.distance_km', 0)
            ->assertJsonPath('data.distance_m', 0);

        $this->postJson('/api/wellbeing/steps/start')
            ->assertOk()
            ->assertJsonPath('data.is_tracking', true);

        $this->postJson('/api/wellbeing/steps/sync', ['steps' => 125])
            ->assertOk()
            ->assertJsonPath('data.steps', 125)
            ->assertJsonPath('data.distance_km', 0.1)
            ->assertJsonPath('data.distance_m', 95)
            ->assertJsonPath('data.distance_source', 'estimated');

        $this->postJson('/api/wellbeing/steps/stop')
            ->assertOk()
            ->assertJsonPath('data.is_tracking', false)
            ->assertJsonPath('data.steps', 125);

        $this->postJson('/api/wellbeing/steps/start')
            ->assertOk()
            ->assertJsonPath('data.is_tracking', true)
            ->assertJsonPath('data.steps', 125);
    }

    public function test_step_tracking_auto_starts_and_stop_persists_for_the_day(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Auto-started on first touch — no explicit "start" call needed.
        $this->getJson('/api/wellbeing/steps')
            ->assertOk()
            ->assertJsonPath('data.is_tracking', true)
            ->assertJsonPath('data.tracking_started_at', fn ($value) => $value !== null);

        // Pausing persists: a later read must not auto-restart the session.
        $this->postJson('/api/wellbeing/steps/stop')
            ->assertOk()
            ->assertJsonPath('data.is_tracking', false);

        $this->getJson('/api/wellbeing/steps')
            ->assertJsonPath('data.is_tracking', false);
    }

    public function test_step_payload_uses_height_based_stride_for_distance(): void
    {
        $user = User::factory()->create();
        HealthProfile::create([
            'user_id' => $user->id,
            'height_cm' => 170,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/wellbeing/steps/sync', ['steps' => 1000])
            ->assertOk()
            ->assertJsonPath('data.distance_m', 706)
            ->assertJsonPath('data.distance_km', 0.7)
            ->assertJsonPath('data.stride_m', 0.706)
            ->assertJsonPath('data.distance_source', 'estimated');
    }

    public function test_sync_accepts_device_measured_distance_in_metres(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/wellbeing/steps/sync', [
                'steps' => 1000,
                'distance_m' => 850,
            ])
            ->assertOk()
            ->assertJsonPath('data.steps', 1000)
            ->assertJsonPath('data.distance_m', 850)
            ->assertJsonPath('data.distance_km', 0.9)
            ->assertJsonPath('data.distance_source', 'device');

        $this->getJson('/api/wellbeing/steps/history?days=7')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.steps', 1000)
            ->assertJsonPath('data.0.distance_m', 850)
            ->assertJsonPath('data.0.distance_km', 0.9)
            ->assertJsonPath('data.0.distance_source', 'device');
    }

    public function test_step_history_includes_distance(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/wellbeing/steps/sync', ['steps' => 2000])
            ->assertOk();

        $this->getJson('/api/wellbeing/steps/history?days=7')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.steps', 2000)
            ->assertJsonPath('data.0.distance_m', 1524)
            ->assertJsonPath('data.0.distance_km', 1.5)
            ->assertJsonPath('data.0.distance_source', 'estimated');
    }
}
