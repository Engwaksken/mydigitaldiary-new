<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\AppreciationMessageService;
use App\Services\AppreciationSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Admin "Send appreciation email" bulk action on the Users page. Runs in the
 * queue because each message may wait on the AI provider.
 */
class SendAppreciationToUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** One try: the weekly log already prevents duplicates, a retry would only skip. */
    public int $tries = 1;

    public int $timeout = 1800;

    /** @param  list<int>  $userIds */
    public function __construct(public array $userIds, public string $weekKey)
    {
    }

    public function handle(AppreciationSender $sender): void
    {
        $counts = [AppreciationSender::SENT => 0, AppreciationSender::SKIPPED => 0, AppreciationSender::FAILED => 0];

        AppreciationSender::recipients($this->weekKey)
            ->whereIn('id', $this->userIds)
            ->chunkById(50, function ($users) use ($sender, &$counts): void {
                foreach ($users as $user) {
                    /** @var User $user */
                    $counts[$sender->send($user, $this->weekKey)]++;
                }
            });

        Log::info('Admin appreciation emails processed.', $counts + ['week' => $this->weekKey]);
    }
}
