<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\MeetingTranscriptionLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PerMeetingTranscriptionLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_minutes_apply_to_each_meeting_instead_of_a_monthly_pool(): void
    {
        $plan = SubscriptionPlan::query()->create([
            'key' => 'metered-plan', 'name' => 'Metered', 'category' => 'individual', 'individual_tier' => 'starter',
            'duration_months' => 1, 'discount_percent' => 0, 'flat_price' => 10,
            'max_transcription_minutes_per_meeting' => 5, 'transcription_capacity_mb' => 30,
            'color' => '#475569', 'is_enabled' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'user', 'subscription_status' => 'active', 'subscription_plan_id' => $plan->id,
            'subscription_started_at' => now()->subDays(2), 'subscription_expires_at' => now()->addDays(28),
        ]);
        $service = app(MeetingTranscriptionLimitService::class);

        $reservation = $service->reserve($user, 240);
        $service->complete($reservation);

        $this->assertDatabaseHas('transcription_minute_usages', [
            'id' => $reservation->id,
            'plan_seconds' => 240,
            'status' => 'consumed',
        ]);

        $secondMeeting = $service->reserve($user->fresh(), 240);
        $service->complete($secondMeeting);
        $this->assertDatabaseHas('transcription_minute_usages', [
            'id' => $secondMeeting->id,
            'plan_seconds' => 240,
            'status' => 'consumed',
        ]);

        try {
            $service->reserve($user->fresh(), 301);
            $this->fail('A meeting longer than the configured per-meeting limit should need a top-up.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('per-meeting transcription limit', strtolower($exception->getMessage()));
            $this->assertStringContainsString('top up', strtolower($exception->getMessage()));
        }
    }

    public function test_failed_transcription_reservation_is_released_and_refunds_extra_minutes(): void
    {
        $user = User::factory()->create([
            'role' => 'user', 'subscription_status' => 'inactive',
            'extra_recording_quota_minutes' => 2,
        ]);
        $service = app(MeetingTranscriptionLimitService::class);
        $reservation = $service->reserve($user, 61);

        $this->assertSame(0, $user->fresh()->extra_recording_quota_minutes);

        $service->release($reservation);

        $this->assertSame(2, $user->fresh()->extra_recording_quota_minutes);
        $this->assertDatabaseMissing('transcription_minute_usages', ['id' => $reservation->id]);
    }

    public function test_purchased_extra_minutes_extend_a_single_meeting_and_are_consumed(): void
    {
        $plan = SubscriptionPlan::query()->create([
            'key' => 'per-meeting-plan', 'name' => 'Per meeting', 'category' => 'individual', 'individual_tier' => 'starter',
            'duration_months' => 12, 'discount_percent' => 0, 'flat_price' => 10,
            'max_transcription_minutes_per_meeting' => 1, 'transcription_capacity_mb' => 30,
            'color' => '#475569', 'is_enabled' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'user', 'subscription_status' => 'active', 'subscription_plan_id' => $plan->id,
            'subscription_started_at' => now()->subDays(2), 'subscription_expires_at' => now()->addMonths(10),
            'extra_recording_quota_minutes' => 2,
        ]);
        $service = app(MeetingTranscriptionLimitService::class);
        $reservation = $service->reserve($user->fresh(), 121);

        $this->assertSame(2, $reservation->extra_minutes);
        $this->assertSame(0, $user->fresh()->extra_recording_quota_minutes);
    }
}
