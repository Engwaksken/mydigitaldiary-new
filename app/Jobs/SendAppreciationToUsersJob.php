<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AppreciationCampaign;
use App\Models\User;
use App\Services\AppreciationSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Admin appreciation email send (the Users page bulk action, sent now or
 * from a scheduled AppreciationCampaign). Runs in the queue because each
 * message may wait on the AI provider.
 *
 * Eligibility and the once-per-ISO-week rule are checked here, for the week
 * of the actual send. A campaign's previewed drafts are sent exactly as
 * previewed; anyone not previewed gets a message written now. Users are
 * processed in chunks and each one's failure is isolated.
 */
class SendAppreciationToUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** One try: the weekly log already prevents duplicates, a retry would only skip. */
    public int $tries = 1;

    public int $timeout = 3600;

    /** @param  list<int>  $userIds */
    public function __construct(public array $userIds, public string $weekKey, public ?int $campaignId = null)
    {
    }

    public function handle(AppreciationSender $sender): void
    {
        $counts = [AppreciationSender::SENT => 0, AppreciationSender::SKIPPED => 0, AppreciationSender::FAILED => 0];
        $campaign = $this->campaignId ? AppreciationCampaign::query()->find($this->campaignId) : null;

        if ($this->campaignId && (! $campaign || $campaign->status === AppreciationCampaign::STATUS_CANCELLED)) {
            return;
        }

        $sentBy = $campaign?->created_by ? (int) $campaign->created_by : null;
        $processed = 0;

        foreach (array_chunk(array_values(array_unique(array_map('intval', $this->userIds))), 500) as $ids) {
            AppreciationSender::recipients($this->weekKey)
                ->whereIn('id', $ids)
                ->chunkById(50, function ($users) use ($sender, $campaign, $sentBy, &$counts, &$processed): void {
                    foreach ($users as $user) {
                        /** @var User $user */
                        $processed++;
                        try {
                            $result = $sender->send($user, $this->weekKey, $campaign?->draftFor((int) $user->id), $sentBy, $campaign?->id);
                        } catch (Throwable $exception) {
                            report($exception);
                            $result = AppreciationSender::FAILED;
                        }
                        $counts[$result]++;
                    }
                });
        }

        // People who stopped being eligible (or were already sent this week) count as skipped.
        $counts[AppreciationSender::SKIPPED] += max(0, count(array_unique($this->userIds)) - $processed);

        $campaign?->forceFill([
            'status' => AppreciationCampaign::STATUS_SENT,
            'week_key' => $this->weekKey,
            'sent_count' => $counts[AppreciationSender::SENT],
            'skipped_count' => $counts[AppreciationSender::SKIPPED],
            'failed_count' => $counts[AppreciationSender::FAILED],
            'sent_at' => now(),
        ])->save();

        Log::info('Admin appreciation emails processed.', $counts + ['week' => $this->weekKey, 'campaign_id' => $this->campaignId]);
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->campaignId) {
            AppreciationCampaign::query()->whereKey($this->campaignId)
                ->where('status', AppreciationCampaign::STATUS_SENDING)
                ->update(['status' => AppreciationCampaign::STATUS_FAILED, 'updated_at' => now()]);
        }

        Log::error('Admin appreciation emails could not be processed.', [
            'campaign_id' => $this->campaignId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
