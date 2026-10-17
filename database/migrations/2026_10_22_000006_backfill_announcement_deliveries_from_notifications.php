<?php

use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuilds announcement_deliveries for announcements sent before delivery
 * tracking existed, from the in-app notifications they created (each one's
 * data holds its announcement_id). Without this, "Skip users who were
 * already told about this feature" could not see earlier announcements.
 *
 * Only the in-app time is known for these: push and email were never
 * recorded, so they stay null. Re-runnable: existing rows are left alone,
 * and notifications whose announcement or user is gone are ignored.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications') || ! Schema::hasTable('announcement_deliveries')) {
            return;
        }

        $announcementIds = DB::table('announcements')->pluck('id')->flip();
        $userClass = (new User)->getMorphClass();

        DB::table('notifications')
            ->where('type', AnnouncementNotification::class)
            ->where('notifiable_type', $userClass)
            ->orderBy('created_at')
            ->orderBy('id')
            ->chunk(500, function ($notifications) use ($announcementIds): void {
                $rows = [];

                foreach ($notifications as $notification) {
                    $announcementId = (int) (json_decode((string) $notification->data, true)['announcement_id'] ?? 0);
                    if (! $announcementIds->has($announcementId)) {
                        continue;
                    }

                    $rows["{$announcementId}:{$notification->notifiable_id}"] = [
                        'announcement_id' => $announcementId,
                        'user_id' => (int) $notification->notifiable_id,
                        'in_app_at' => $notification->created_at,
                        'created_at' => $notification->created_at,
                        'updated_at' => $notification->created_at,
                    ];
                }

                if ($rows === []) {
                    return;
                }

                // Users deleted since are dropped so the foreign key holds.
                $existingUsers = DB::table('users')
                    ->whereIn('id', array_unique(array_column($rows, 'user_id')))
                    ->pluck('id')
                    ->flip();

                DB::table('announcement_deliveries')->insertOrIgnore(array_values(array_filter(
                    $rows,
                    fn (array $row) => $existingUsers->has($row['user_id'])
                )));
            });
    }

    public function down(): void
    {
        // Rows rebuilt here are indistinguishable from real deliveries; leave them.
    }
};
