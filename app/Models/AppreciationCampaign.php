<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An admin send of the appreciation email: sent now (status sending, then
 * sent) or scheduled for later. drafts keeps the previewed copy per user.
 */
class AppreciationCampaign extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';

    public const STATUS_LABELS = [
        self::STATUS_SCHEDULED => 'Scheduled',
        self::STATUS_SENDING => 'Sending',
        self::STATUS_SENT => 'Sent',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_FAILED => 'Failed',
    ];

    protected $fillable = [
        'created_by', 'user_ids', 'scheduled_for', 'status', 'week_key', 'drafts',
        'sent_count', 'skipped_count', 'failed_count', 'sent_at',
    ];

    protected $casts = [
        'user_ids' => 'array',
        'drafts' => 'array',
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
        'sent_count' => 'integer',
        'skipped_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return list<int> */
    public function userIds(): array
    {
        return array_values(array_map('intval', (array) $this->user_ids));
    }

    /**
     * The copy the admin previewed for this user, or null when they did
     * not open it (a message is then written at send time).
     *
     * @return array{subject:string, paragraphs:list<string>, source:string, theme:string}|null
     */
    public function draftFor(int $userId): ?array
    {
        $draft = ((array) $this->drafts)[(string) $userId] ?? ((array) $this->drafts)[$userId] ?? null;

        if (! is_array($draft) || blank($draft['subject'] ?? null) || empty($draft['paragraphs'])) {
            return null;
        }

        return [
            'subject' => (string) $draft['subject'],
            'paragraphs' => array_values(array_map('strval', (array) $draft['paragraphs'])),
            'source' => (string) ($draft['source'] ?? 'ai'),
            'theme' => (string) ($draft['theme'] ?? ''),
        ];
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }
}
