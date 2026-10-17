<?php

namespace Tests\Feature\Announcements;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\Services\AnnouncementAudience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnnouncementDeliveryBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function runBackfill(): void
    {
        (require database_path('migrations/2026_10_22_000006_backfill_announcement_deliveries_from_notifications.php'))->up();
    }

    public function test_rebuilds_deliveries_from_pre_tracking_notifications(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $old = Announcement::create([
            'created_by' => $admin->id, 'title' => 'Budgets are here', 'body' => 'Try them.',
            'type' => 'new_feature', 'feature_key' => 'budgets', 'status' => 'sent',
        ]);
        $told = User::factory()->count(2)->create();
        $notTold = User::factory()->create();

        Carbon::setTestNow('2026-09-01 10:00:00');
        // How announcements were delivered before tracking: notifications only.
        foreach ($told as $user) {
            $user->notifyNow(new AnnouncementNotification($old));
        }
        Carbon::setTestNow();
        DB::table('announcement_deliveries')->delete();

        $this->runBackfill();
        $this->runBackfill(); // safe to run again

        $rows = DB::table('announcement_deliveries')->where('announcement_id', $old->id)->get();
        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing($told->pluck('id')->all(), $rows->pluck('user_id')->map(fn ($id) => (int) $id)->all());
        $this->assertTrue($rows->every(fn ($row) => str_starts_with((string) $row->in_app_at, '2026-09-01')
            && $row->push_at === null && $row->email_at === null));

        $ids = [...$told->pluck('id'), $notTold->id];
        $this->assertSame(2, app(AnnouncementAudience::class)->alreadyTold($ids, 'budgets')['count']);
    }

    public function test_ignores_notifications_for_deleted_announcements_or_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $gone = Announcement::create(['created_by' => $admin->id, 'title' => 'Old', 'body' => 'Old.', 'status' => 'sent']);
        $user = User::factory()->create();
        $user->notifyNow(new AnnouncementNotification($gone));
        DB::table('announcement_deliveries')->delete();
        $gone->delete();

        $this->runBackfill();

        $this->assertSame(0, DB::table('announcement_deliveries')->count());
    }
}
