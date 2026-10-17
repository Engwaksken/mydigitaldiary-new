<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * The in-app (database) copy of an admin Announcement, sent to every
 * non-suspended user by SendAnnouncementJob. Push and email for the same
 * announcement are sent by that job too, so this stays database-only.
 *
 * The data shape is a contract with the mobile app (GET /api/notifications):
 * {announcement_id, title, body, type, feature_key, link_url, cta_label}.
 */
class AnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Announcement $announcement)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return self::payload($this->announcement);
    }

    /** @return array{announcement_id:int,title:string,body:string,type:string,feature_key:?string,link_url:?string,cta_label:?string} */
    public static function payload(Announcement $announcement): array
    {
        return [
            'announcement_id' => $announcement->id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'type' => $announcement->type ?: Announcement::TYPE_GENERAL,
            'feature_key' => $announcement->feature_key ?: null,
            'link_url' => $announcement->resolvedLinkUrl(),
            'cta_label' => $announcement->resolvedCtaLabel(),
        ];
    }

    /** Fills the newer keys for announcements stored before they existed. */
    public static function normalise(array $data): array
    {
        return $data + [
            'type' => Announcement::TYPE_GENERAL,
            'feature_key' => null,
            'link_url' => null,
            'cta_label' => null,
        ];
    }
}
