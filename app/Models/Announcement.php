<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class Announcement extends Model
{
    public const TYPE_GENERAL = 'general';
    public const TYPE_NEW_FEATURE = 'new_feature';

    public const TYPES = [
        self::TYPE_GENERAL => 'General update',
        self::TYPE_NEW_FEATURE => 'New feature',
    ];

    public const DEFAULT_FEATURE_CTA = 'Explore now';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SCHEDULED => 'Scheduled',
        self::STATUS_SENDING => 'Sending',
        self::STATUS_SENT => 'Sent',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_FAILED => 'Failed',
    ];

    protected $fillable = [
        'created_by', 'title', 'body', 'type', 'feature_key', 'link_url', 'cta_label', 'send_push', 'send_email', 'recipient_ids',
        'status', 'scheduled_for', 'sent_at', 'skip_already_told', 'skipped_count',
    ];

    protected $attributes = [
        'type' => self::TYPE_GENERAL,
        'send_push' => true,
        'send_email' => false,
        'status' => self::STATUS_SENDING,
        'skip_already_told' => false,
        'skipped_count' => 0,
    ];

    protected $casts = [
        'send_push' => 'boolean',
        'send_email' => 'boolean',
        'recipient_ids' => 'array',
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
        'skip_already_told' => 'boolean',
        'skipped_count' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries()
    {
        return $this->hasMany(AnnouncementDelivery::class);
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Whether this send leaves out people already told about the same feature. */
    public function skipsAlreadyTold(): bool
    {
        return $this->isNewFeature() && $this->skip_already_told && filled($this->feature_key);
    }

    /** "Mon 20 Oct, 09:00" in the app timezone (Africa/Kampala). */
    public static function formatTime(?\DateTimeInterface $at): ?string
    {
        return \App\Services\SendTime::format($at);
    }

    /** @return array<string, array{label:string,route:string,icon:string}> */
    public static function featureCatalogue(): array
    {
        return (array) config('features', []);
    }

    public function isNewFeature(): bool
    {
        return $this->type === self::TYPE_NEW_FEATURE;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? self::TYPES[self::TYPE_GENERAL];
    }

    /** @return array{label:string,route:string,icon:string}|null */
    public function feature(): ?array
    {
        return $this->feature_key ? (self::featureCatalogue()[$this->feature_key] ?? null) : null;
    }

    /**
     * Where the call to action points on the web: an explicit link wins
     * (kept as entered, so a relative "/meetings" stays relative), otherwise
     * the catalogue feature's route, otherwise nothing.
     */
    public function resolvedLinkUrl(): ?string
    {
        $link = trim((string) $this->link_url);
        if ($link !== '') {
            return $link;
        }

        $route = $this->feature()['route'] ?? null;

        return $route && Route::has($route) ? route($route) : null;
    }

    /** Absolute version of the link for email and web push; the dashboard when there is none. */
    public function absoluteLinkUrl(): string
    {
        $link = $this->resolvedLinkUrl();

        if ($link === null) {
            return route('dashboard');
        }

        return Str::startsWith($link, ['http://', 'https://']) ? $link : url($link);
    }

    public function resolvedCtaLabel(): ?string
    {
        $label = trim((string) $this->cta_label);
        if ($label !== '') {
            return $label;
        }

        return $this->isNewFeature() ? self::DEFAULT_FEATURE_CTA : null;
    }

    public function pushTitle(): string
    {
        return $this->isNewFeature() ? 'New: '.$this->title : $this->title;
    }

    public function pushBody(): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', (string) $this->body)), 120);
    }

    /** Users picked on the admin Users page, or null when sent to everyone. */
    public function recipientIds(): ?array
    {
        return $this->recipient_ids === null ? null : array_map('intval', $this->recipient_ids);
    }

    public function audienceLabel(): string
    {
        $ids = $this->recipientIds();

        return $ids === null ? 'Everyone' : count($ids).' selected '.str('user')->plural(count($ids));
    }

    /** @return list<string> */
    public function channels(): array
    {
        return array_values(array_filter([
            'In-app',
            $this->send_push ? 'Push' : null,
            $this->send_email ? 'Email' : null,
        ]));
    }
}
