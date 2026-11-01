<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SubscriptionExpiredWeeklyReminderNotification;
use App\Notifications\SubscriptionExtensionSummaryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubscriptionExtensionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_grant_extension_extends_trial_and_reactivates_expired_subscribers(): void
    {
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(12, 0));

        $admin = User::factory()->create(['role' => 'admin']);
        $trial = User::factory()->create([
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(5),
        ]);
        $expired = User::factory()->create([
            'subscription_status' => 'expired',
            'subscription_expires_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:grant-extension')->assertSuccessful();

        $this->assertTrue($trial->fresh()->trial_ends_at->greaterThanOrEqualTo(now()->addDays(29)));
        $this->assertSame('active', $expired->fresh()->subscription_status);
        $this->assertTrue($expired->fresh()->subscription_expires_at->greaterThanOrEqualTo(now()->addDays(13)));

        Notification::assertSentTo($admin, SubscriptionExtensionSummaryNotification::class, function ($notification): bool {
            return $notification->trialCount === 1
                && $notification->trialDays === 30
                && $notification->expiredCount === 1
                && $notification->expiredDays === 14;
        });
    }

    public function test_grant_extension_dry_run_makes_no_changes(): void
    {
        Notification::fake();
        $trial = User::factory()->create([
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(5),
        ]);
        $before = $trial->fresh()->getRawOriginal('trial_ends_at');

        $this->artisan('subscriptions:grant-extension', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame($before, $trial->fresh()->getRawOriginal('trial_ends_at'));
        Notification::assertNothingSent();
    }

    public function test_weekly_reminder_targets_expired_users_only(): void
    {
        Notification::fake();

        $expired = User::factory()->create([
            'subscription_status' => 'expired',
            'subscription_expires_at' => now()->subDay(),
        ]);
        $active = User::factory()->create([
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDay(),
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'subscription_status' => 'expired',
            'subscription_expires_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:send-expired-weekly-reminder')->assertSuccessful();

        Notification::assertSentTo($expired, SubscriptionExpiredWeeklyReminderNotification::class);
        Notification::assertNotSentTo($active, SubscriptionExpiredWeeklyReminderNotification::class);
        Notification::assertNotSentTo($admin, SubscriptionExpiredWeeklyReminderNotification::class);
    }
}
