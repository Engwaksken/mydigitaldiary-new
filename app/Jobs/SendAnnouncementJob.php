<?php

namespace App\Jobs;

use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\Services\AnnouncementAudience;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Delivers one Announcement: in-app to every non-suspended user (or only the
 * users picked on the admin Users page), push to
 * those with a registered device (when send_push), and email to verified
 * users who have not switched off "product_updates" (when send_email).
 *
 * Users are processed in chunks of 100 and every delivery is isolated, so
 * one bad device token or mailbox never stops the rest of the run.
 */
class SendAnnouncementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // A retry would send the in-app copy a second time to people who already have it.
    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public Announcement $announcement)
    {
    }

    public function handle(FcmService $fcm, AnnouncementAudience $audience): void
    {
        $announcement = $this->announcement;
        $stats = ['in_app' => 0, 'push' => 0, 'email' => 0, 'failed' => 0];
        $pushData = self::pushData($announcement);

        $audience->inApp($announcement->recipientIds())->with('deviceTokens')->chunkById(100, function ($users) use ($announcement, $fcm, $audience, $pushData, &$stats): void {
            foreach ($users as $user) {
                // In-app first, so the push carries an unread count that includes it.
                $this->attempt($user, 'in-app', $stats, function () use ($user, $announcement, &$stats): void {
                    $user->notifyNow(new AnnouncementNotification($announcement));
                    $stats['in_app']++;
                });

                if ($announcement->send_push && $user->deviceTokens->isNotEmpty()) {
                    $this->attempt($user, 'push', $stats, function () use ($user, $announcement, $fcm, $pushData, &$stats): void {
                        $fcm->sendToUser($user, $announcement->pushTitle(), $announcement->pushBody(), $pushData);
                        $stats['push']++;
                    });
                }

                if ($announcement->send_email && $audience->wantsEmail($user)) {
                    $this->attempt($user, 'email', $stats, function () use ($user, $announcement, &$stats): void {
                        Mail::to($user)->send(new AnnouncementMail($user, $announcement));
                        $stats['email']++;
                    });
                }
            }
        });

        Log::info('Announcement delivered.', ['announcement_id' => $announcement->id] + $stats);
    }

    /**
     * FCM data payload (all strings). "link" is the absolute URL the web
     * push service worker opens on click; the mobile app uses
     * feature_key / link_url.
     *
     * @return array<string,string>
     */
    public static function pushData(Announcement $announcement): array
    {
        return [
            'type' => 'announcement',
            'announcement_id' => (string) $announcement->id,
            'announcement_type' => (string) ($announcement->type ?: Announcement::TYPE_GENERAL),
            'feature_key' => (string) $announcement->feature_key,
            'link_url' => (string) $announcement->resolvedLinkUrl(),
            'link' => $announcement->absoluteLinkUrl(),
        ];
    }

    private function attempt(User $user, string $channel, array &$stats, callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            $stats['failed']++;
            Log::warning('Announcement delivery failed.', [
                'announcement_id' => $this->announcement->id,
                'user_id' => $user->id,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
