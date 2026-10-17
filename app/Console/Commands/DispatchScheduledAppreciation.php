<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendAppreciationToUsersJob;
use App\Models\AppreciationCampaign;
use App\Services\AppreciationMessageService;
use Illuminate\Console\Command;

/**
 * Runs every minute. Moves each due scheduled appreciation campaign to
 * "sending" with a conditional update (so it is never dispatched twice) and
 * queues SendAppreciationToUsersJob for the week of the actual send, which
 * is when eligibility and the once-per-week rule are checked.
 */
class DispatchScheduledAppreciation extends Command
{
    protected $signature = 'appreciation:dispatch-scheduled';

    protected $description = 'Queue scheduled appreciation email campaigns that are due.';

    public function handle(): int
    {
        $dispatched = 0;

        AppreciationCampaign::query()
            ->where('status', AppreciationCampaign::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->pluck('id')
            ->each(function ($id) use (&$dispatched): void {
                $id = (int) $id;
                if (self::start($id)) {
                    $dispatched++;
                }
            });

        $this->info("{$dispatched} scheduled appreciation campaign(s) queued.");

        return self::SUCCESS;
    }

    /** Claims a scheduled campaign and queues it; false when it was already claimed or cancelled. */
    public static function start(int $id): bool
    {
        $weekKey = AppreciationMessageService::weekKey();

        $claimed = AppreciationCampaign::query()
            ->whereKey($id)
            ->where('status', AppreciationCampaign::STATUS_SCHEDULED)
            ->update(['status' => AppreciationCampaign::STATUS_SENDING, 'week_key' => $weekKey, 'updated_at' => now()]) === 1;

        if ($claimed) {
            $campaign = AppreciationCampaign::query()->findOrFail($id);
            SendAppreciationToUsersJob::dispatch($campaign->userIds(), $weekKey, $campaign->id);
        }

        return $claimed;
    }
}
