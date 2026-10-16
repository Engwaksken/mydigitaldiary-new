<?php

namespace Tests\Feature\Api;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionPlansApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_payload_includes_tier_and_transcription_limits_for_flutter(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        SubscriptionPlan::query()->create([
            'key' => 'flutter-gold-plan',
            'name' => 'Gold',
            'category' => 'individual',
            'individual_tier' => 'gold',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 10000,
            'max_transcription_minutes_per_meeting' => 240,
            'transcription_capacity_mb' => 80,
            'color' => '#475569',
            'is_enabled' => true,
        ]);

        $this->getJson('/api/subscription/plans')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Gold',
                'individual_tier_label' => 'Gold',
                'max_transcription_minutes_per_meeting' => 240,
                'transcription_capacity_mb' => 80,
            ]);
    }
}
