<?php

namespace App\Console\Commands;

use App\Jobs\SendAnnouncementJob;
use App\Models\Announcement;
use Illuminate\Console\Command;

/**
 * Runs every minute. Moves each due scheduled announcement to "sending"
 * with a conditional update (only one run can win that change, so nothing
 * is dispatched twice) and queues SendAnnouncementJob, which marks it sent
 * or failed.
 */
class DispatchScheduledAnnouncements extends Command
{
    protected $signature = 'announcements:dispatch-scheduled';

    protected $description = 'Queue scheduled announcements that are due.';

    public function handle(): int
    {
        $dispatched = 0;

        Announcement::query()
            ->where('status', Announcement::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->pluck('id')
            ->each(function ($id) use (&$dispatched): void {
                $id = (int) $id;
                if (self::claim($id)) {
                    SendAnnouncementJob::dispatch(Announcement::query()->findOrFail($id));
                    $dispatched++;
                }
            });

        $this->info("{$dispatched} scheduled announcement(s) queued.");

        return self::SUCCESS;
    }

    /** Atomically moves one announcement from scheduled to sending; false if another run (or a cancel) got there first. */
    public static function claim(int $id): bool
    {
        return Announcement::query()
            ->whereKey($id)
            ->where('status', Announcement::STATUS_SCHEDULED)
            ->update(['status' => Announcement::STATUS_SENDING, 'updated_at' => now()]) === 1;
    }
}
