<?php

namespace App\Jobs;

use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
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
 * Each user's announcement_deliveries row is claimed before anything is
 * sent and records the time each channel succeeded, so a user who already
 * has a row is skipped: retries and repeated dispatches never send twice.
 * A new_feature announcement with skip_already_told also leaves out people
 * who had an earlier sent announcement of the same feature, checked here at
 * send time.
 *
 * Users are processed in chunks of 100 and every delivery is isolated, so
 * one bad device token or mailbox never stops the rest of the run.
 */
class SendAnnouncementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Safe to retry: users who already have a delivery row are skipped.
    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 3600;

    public function __construct(public Announcement $announcement)
    {
    }

    public function handle(FcmService $fcm, AnnouncementAudience $audience): void
    {
        $announcement = $this->announcement->fresh() ?? $this->announcement;
        $this->announcement = $announcement;

        // Cancelled after it was queued, or already finished by another run.
        if (in_array($announcement->status, [Announcement::STATUS_CANCELLED, Announcement::STATUS_SENT], true)) {
            return;
        }

        if ($announcement->status !== Announcement::STATUS_SENDING) {
            $announcement->forceFill(['status' => Announcement::STATUS_SENDING])->save();
        }

        $stats = ['in_app' => 0, 'push' => 0, 'email' => 0, 'failed' => 0];
        $pushData = self::pushData($announcement);
        $skipFeature = $announcement->skipsAlreadyTold() ? $announcement->feature_key : null;

        if ($skipFeature !== null) {
            $told = $audience->alreadyTold($announcement->recipientIds(), $skipFeature, $announcement->id);
            $announcement->forceFill(['skipped_count' => $told['count']])->save();
        }

        $audience->inApp($announcement->recipientIds(), $skipFeature, $announcement->id)
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')
                ->from('announcement_deliveries')
                ->whereColumn('announcement_deliveries.user_id', 'users.id')
                ->where('announcement_deliveries.announcement_id', $announcement->id))
            ->with('deviceTokens')
            ->chunkById(100, function ($users) use ($announcement, $fcm, $audience, $pushData, &$stats): void {
                foreach ($users as $user) {
                    $this->deliverTo($user, $announcement, $fcm, $audience, $pushData, $stats);
                }
            });

        $announcement->forceFill([
            'status' => Announcement::STATUS_SENT,
            'sent_at' => $announcement->sent_at ?? now(),
        ])->save();

        Log::info('Announcement delivered.', ['announcement_id' => $announcement->id] + $stats);
    }

    /** Marks the announcement failed once every try is used up. */
    public function failed(?Throwable $exception): void
    {
        Announcement::query()->whereKey($this->announcement->id)
            ->whereIn('status', [Announcement::STATUS_SENDING, Announcement::STATUS_SCHEDULED])
            ->update(['status' => Announcement::STATUS_FAILED, 'updated_at' => now()]);

        Log::error('Announcement could not be sent.', [
            'announcement_id' => $this->announcement->id,
            'error' => $exception?->getMessage(),
        ]);
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

    private function deliverTo(User $user, Announcement $announcement, FcmService $fcm, AnnouncementAudience $audience, array $pushData, array &$stats): void
    {
        // Claim this user first: the unique (announcement_id, user_id) pair
        // means a parallel or retried run skips anyone already claimed.
        $claimed = AnnouncementDelivery::query()->insertOrIgnore([
            'announcement_id' => $announcement->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($claimed < 1) {
            return;
        }

        $times = [];
        $failed = [];

        // In-app first, so the push carries an unread count that includes it.
        $this->attempt($user, AnnouncementDelivery::CHANNEL_IN_APP, $times, $failed,
            fn () => $user->notifyNow(new AnnouncementNotification($announcement)));

        if ($announcement->send_push && $user->deviceTokens->isNotEmpty()) {
            $this->attempt($user, AnnouncementDelivery::CHANNEL_PUSH, $times, $failed,
                fn () => $fcm->sendToUser($user, $announcement->pushTitle(), $announcement->pushBody(), $pushData));
        }

        if ($announcement->send_email && $audience->wantsEmail($user)) {
            $this->attempt($user, AnnouncementDelivery::CHANNEL_EMAIL, $times, $failed,
                fn () => Mail::to($user)->send(new AnnouncementMail($user, $announcement)));
        }

        foreach ($times as $column => $time) {
            $stats[substr($column, 0, -3)]++;
        }
        $stats['failed'] += count($failed);

        try {
            AnnouncementDelivery::query()
                ->where('announcement_id', $announcement->id)
                ->where('user_id', $user->id)
                ->update($times + [
                    'failed_channels' => $failed === [] ? null : json_encode($failed),
                    'updated_at' => now(),
                ]);
        } catch (Throwable $e) {
            Log::warning('Announcement delivery could not be recorded.', [
                'announcement_id' => $announcement->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Runs one channel, isolating its failure. Success stores the time in
     * $times["{channel}_at"]; failure adds the channel to $failed.
     */
    private function attempt(User $user, string $channel, array &$times, array &$failed, callable $send): void
    {
        try {
            $send();
            $times[$channel.'_at'] = now();
        } catch (Throwable $e) {
            $failed[] = $channel;
            Log::warning('Announcement delivery failed.', [
                'announcement_id' => $this->announcement->id,
                'user_id' => $user->id,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
