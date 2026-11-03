<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserExtraRequest;
use App\Services\ApplyExtraRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtraRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_extra_recording_quota_request(): void
    {
        $user = User::factory()->create([
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($user)->post(route('extra-requests.store'), [
            'quota_amount' => 120,
            'amount' => 9000,
            'currency' => 'UGX',
            'description' => 'Extra meeting recording minutes.',
        ]);

        $request = UserExtraRequest::firstOrFail();

        $response->assertRedirect(route('extra-requests.show', $request));
        $this->assertDatabaseHas('user_extra_requests', [
            'user_id' => $user->id,
            'quota_amount' => 120,
            'amount' => 9000,
            'currency' => 'UGX',
            'status' => 'pending',
        ]);
    }

    public function test_applying_extra_request_adds_remaining_minutes_and_casts_expiry(): void
    {
        $user = User::factory()->create([
            'extra_recording_quota_minutes' => 30,
            'extra_quota_expires_at' => now()->addDays(5),
        ]);
        $extraRequest = UserExtraRequest::factory()->for($user)->create([
            'quota_amount' => 120,
            'quota_used' => 20,
            'expires_at' => now()->addDays(30),
        ]);

        app(ApplyExtraRequestService::class)->applyQuota($extraRequest);

        $user->refresh();
        $this->assertSame(130, $user->extra_recording_quota_minutes);
        $this->assertTrue($user->extra_quota_expires_at->isSameDay($extraRequest->expires_at));
        $this->assertSame('applied', $extraRequest->fresh()->status);
    }

    public function test_applying_an_old_request_gives_minutes_that_are_usable_now(): void
    {
        $user = User::factory()->create([
            'extra_recording_quota_minutes' => 0,
            'extra_quota_expires_at' => now()->subDays(10),
        ]);
        // Created 45 days ago: its original expires_at (created + 30 days) is already past.
        $extraRequest = UserExtraRequest::factory()->for($user)->create([
            'quota_amount' => 120,
            'quota_used' => 0,
            'expires_at' => now()->subDays(15),
        ]);

        app(ApplyExtraRequestService::class)->applyQuota($extraRequest);

        $user->refresh();
        $this->assertSame(120, $user->extra_recording_quota_minutes);
        $this->assertTrue($user->extra_quota_expires_at->isFuture());
        $this->assertTrue($user->extra_quota_expires_at->isSameDay(now()->addDays(30)));
        $this->assertSame(120, $user->availableExtraRecordingQuotaMinutes());

        // A meeting needing all 120 extra minutes beyond the plan allowance can now be reserved.
        $includedMinutes = $user->hasActiveAccess()
            ? (int) ($user->subscriptionPlan?->max_transcription_minutes_per_meeting ?? 60)
            : 0;
        $usage = app(\App\Services\MeetingTranscriptionLimitService::class)
            ->reserve($user, ($includedMinutes + 120) * 60);
        $this->assertSame(120, $usage->extra_minutes);
        $this->assertSame(0, $user->fresh()->extra_recording_quota_minutes);
    }

    public function test_applying_keeps_a_later_existing_expiry_and_a_never_expires_state(): void
    {
        $later = User::factory()->create([
            'extra_recording_quota_minutes' => 10,
            'extra_quota_expires_at' => now()->addDays(90),
        ]);
        $lifetime = User::factory()->create([
            'extra_recording_quota_minutes' => 10,
            'extra_quota_expires_at' => null,
        ]);

        foreach ([$later, $lifetime] as $user) {
            app(ApplyExtraRequestService::class)->applyQuota(
                UserExtraRequest::factory()->for($user)->create(['quota_amount' => 60, 'quota_used' => 0])
            );
        }

        $this->assertTrue($later->fresh()->extra_quota_expires_at->isSameDay(now()->addDays(90)));
        $this->assertNull($lifetime->fresh()->extra_quota_expires_at);
        $this->assertSame(70, $lifetime->fresh()->extra_recording_quota_minutes);
    }

    public function test_repair_migration_extends_expiry_for_recently_applied_requests(): void
    {
        $affected = User::factory()->create([
            'extra_recording_quota_minutes' => 120,
            'extra_quota_expires_at' => now()->subDays(5),
        ]);
        $untouched = User::factory()->create([
            'extra_recording_quota_minutes' => 50,
            'extra_quota_expires_at' => null,
        ]);

        foreach ([$affected, $untouched] as $user) {
            UserExtraRequest::factory()->for($user)->create([
                'status' => 'applied',
                'applied_at' => now()->subDays(2),
                'quota_amount' => 120,
            ]);
        }

        $migration = require database_path('migrations/2026_11_03_000001_repair_extra_quota_expiry_from_applied_requests.php');
        $migration->up();

        $this->assertTrue($affected->fresh()->extra_quota_expires_at->isSameDay(now()->addDays(28)));
        $this->assertSame(120, $affected->fresh()->availableExtraRecordingQuotaMinutes());
        $this->assertNull($untouched->fresh()->extra_quota_expires_at);
    }
}
