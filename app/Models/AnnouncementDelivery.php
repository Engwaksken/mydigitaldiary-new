<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Which channels of one Announcement reached one user, and when. Claimed
 * (inserted) before anything is sent, so the unique pair keeps
 * SendAnnouncementJob idempotent.
 */
class AnnouncementDelivery extends Model
{
    public const CHANNEL_IN_APP = 'in_app';
    public const CHANNEL_PUSH = 'push';
    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_LABELS = [
        self::CHANNEL_IN_APP => 'In-app',
        self::CHANNEL_PUSH => 'Push',
        self::CHANNEL_EMAIL => 'Email',
    ];

    protected $fillable = [
        'announcement_id', 'user_id', 'in_app_at', 'push_at', 'email_at', 'failed_channels',
    ];

    protected $casts = [
        'in_app_at' => 'datetime',
        'push_at' => 'datetime',
        'email_at' => 'datetime',
        'failed_channels' => 'array',
    ];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** @return list<string> labels of the channels that reached the user */
    public function reachedChannels(): array
    {
        return array_values(array_filter([
            $this->in_app_at ? 'In-app' : null,
            $this->push_at ? 'Push' : null,
            $this->email_at ? 'Email' : null,
        ]));
    }

    /** @return list<string> labels of the channels that failed */
    public function failedChannelLabels(): array
    {
        return array_map(fn (string $channel) => self::CHANNEL_LABELS[$channel] ?? $channel, (array) $this->failed_channels);
    }
}
