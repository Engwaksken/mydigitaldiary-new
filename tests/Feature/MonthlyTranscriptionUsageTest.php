<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\TranscriptionMinuteUsage;
use App\Models\User;
use App\Services\MonthlyTranscriptionUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MonthlyTranscriptionUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_plan_minutes_are_reserved_and_counted_in_seconds(): void
    {
        $plan = SubscriptionPlan::query()->create([
            'key' => 'metered-plan', 'name' => 'Metered', 'category' => 'individual', 'individual_tier' => 'starter',
            'duration_months' => 1, 'discount_percent' => 0, 'flat_price' => 10,
            'monthly_transcription_minutes' => 5, 'transcription_capacity_mb' => 30,
            'color' => '#475569', 'is_enabled' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'user', 'subscription_status' => 'active', 'subscription_plan_id' => $plan->id,
            'subscription_started_at' => now()->subDays(2), 'subscription_expires_at' => now()->addDays(28),
        ]);
        $service = app(MonthlyTranscriptionUsageService::class);

        $reservation = $service->reserve($user, 240);
        $service->complete($reservation);

        $this->assertSame(240, $service->usedPlanSeconds($user->fresh()));
        $this->assertDatabaseHas('transcription_minute_usages', [
            'id' => $reservation->id,
            'plan_seconds' => 240,
            'status' => 'consumed',
        ]);

        try {
            $service->reserve($user->fresh(), 61);
            $this->fail('Usage beyond the plan allowance should be rejected without a top-up.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('top up', strtolower($exception->getMessage()));
        }
    }

    public function test_failed_transcription_reservation_is_released_and_refunds_extra_minutes(): void
    {
        $user = User::factory()->create([
            'role' => 'user', 'subscription_status' => 'inactive',
            'extra_recording_quota_minutes' => 2,
        ]);
        $service = app(MonthlyTranscriptionUsageService::class);
        $reservation = $service->reserve($user, 61);

        $this->assertSame(0, $user->fresh()->extra_recording_quota_minutes);

        $service->release($reservation);

        $this->assertSame(2, $user->fresh()->extra_recording_quota_minutes);
        $this->assertDatabaseMissing('transcription_minute_usages', ['id' => $reservation->id]);
    }

    public function test_allowance_resets_at_each_subscription_month_anniversary(): void
    {
        $plan = SubscriptionPlan::query()->create([
            'key' => 'monthly-plan', 'name' => 'Monthly', 'category' => 'individual', 'individual_tier' => 'starter',
            'duration_months' => 12, 'discount_percent' => 0, 'flat_price' => 10,
            'monthly_transcription_minutes' => 1, 'transcription_capacity_mb' => 30,
            'color' => '#475569', 'is_enabled' => true,
        ]);
        $anchor = now()->subMonthNoOverflow()->subDays(2);
        $user = User::factory()->create([
            'role' => 'user', 'subscription_status' => 'active', 'subscription_plan_id' => $plan->id,
            'subscription_started_at' => $anchor, 'subscription_expires_at' => now()->addMonths(10),
        ]);
        $service = app(MonthlyTranscriptionUsageService::class);
        TranscriptionMinuteUsage::query()->create([
            'user_id' => $user->id,
            'duration_seconds' => 60,
            'plan_seconds' => 60,
            'extra_minutes' => 0,
            'period_start' => $anchor->toDateString(),
            'status' => 'consumed',
        ]);

        $this->assertSame(0, $service->usedPlanSeconds($user->fresh()));
        $currentPeriodReservation = $service->reserve($user->fresh(), 60);
        $service->complete($currentPeriodReservation);
        $this->assertSame(60, $service->usedPlanSeconds($user->fresh()));
    }
}
