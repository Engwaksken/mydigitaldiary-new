<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Meeting extends Model
{
    use HasFactory;

    private const EXTERNAL_SOURCE_ATTRIBUTES = [
        'external_platform', 'external_id', 'calendar_provider',
        'external_calendar_id', 'external_event_id', 'external_series_id',
    ];

    private const COPIED_FROM_ANCESTORS =
        'copiedFrom.copiedFrom.copiedFrom.copiedFrom.copiedFrom.copiedFrom';

    protected $fillable = [
        'user_id', 'copied_from_meeting_id', 'title', 'start_at', 'end_at', 'location', 'attendees', 'status', 'notes',
        'external_platform', 'external_id', 'meeting_status', 'is_archived',
        'recurrence_frequency', 'recurrence_days_of_week', 'recurrence_ends_at', 'recurrence_parent_id',
        'calendar_provider', 'external_calendar_id', 'external_event_id', 'external_series_id',
        'calendar_synced_at', 'calendar_sync_from_date', 'calendar_sync_to_date',
    ];
    protected $appends = ['diary_join_url'];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'recurrence_days_of_week' => 'array',
        'recurrence_ends_at' => 'date',
        'calendar_synced_at' => 'datetime',
        'calendar_sync_from_date' => 'date',
        'calendar_sync_to_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $meeting): void {
            // copied_from_meeting_id is nullOnDelete. Persist the external
            // source markers on existing copies before that FK is cleared so
            // they cannot later be mistaken for diary-created meetings.
            if ($meeting->hasExternalSourceMarkers()) {
                static::query()
                    ->where('copied_from_meeting_id', $meeting->id)
                    ->update($meeting->externalSourceMarkerValues());
            }
        });
    }

    /**
     * Database cascades bypass Meeting's deleting event. Before a user is
     * deleted, preserve an external source's markers on copies owned by other
     * users; copied_from_meeting_id will then be nullified safely by the FK.
     */
    public static function preserveExternalClassificationForUser(User $user): void
    {
        static::query()
            ->where('user_id', $user->id)
            ->with(self::COPIED_FROM_ANCESTORS)
            ->each(function (self $meeting) use ($user): void {
                $source = $meeting;

                while ($source && ! $source->hasExternalSourceMarkers()) {
                    $source = $source->relationLoaded('copiedFrom')
                        ? $source->getRelation('copiedFrom')
                        : null;
                }

                if ($source) {
                    static::query()
                        ->where('copied_from_meeting_id', $meeting->id)
                        ->where('user_id', '!=', $user->id)
                        ->update($source->externalSourceMarkerValues());
                }
            });
    }

    public function isRecurring(): bool
    {
        return ! empty($this->recurrence_frequency);
    }

    public function recurrenceParent()
    {
        return $this->belongsTo(Meeting::class, 'recurrence_parent_id');
    }

    public function recurrenceInstances()
    {
        return $this->hasMany(Meeting::class, 'recurrence_parent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function copiedFrom()
    {
        return $this->belongsTo(Meeting::class, 'copied_from_meeting_id');
    }

    public function recordings()
    {
        return $this->hasMany(MeetingRecording::class);
    }

    /**
     * Calendar imports retain one of these source markers. A diary-created
     * meeting has no such marker. A calendar copy inherits its source type
     * through copied_from_meeting_id, even though its own source columns are
     * intentionally empty.
     */
    public function isInternallyCreated(): bool
    {
        if ($this->hasExternalSourceMarkers()) {
            return false;
        }

        if ($this->copied_from_meeting_id) {
            $source = $this->relationLoaded('copiedFrom')
                ? $this->getRelation('copiedFrom')
                : $this->copiedFrom()->first();

            if ($source && ! $source->isInternallyCreated()) {
                return false;
            }
        }

        return true;
    }

    private function hasExternalSourceMarkers(): bool
    {
        return collect(self::EXTERNAL_SOURCE_ATTRIBUTES)
            ->contains(fn (string $attribute) => filled($this->getAttribute($attribute)));
    }

    private function externalSourceMarkerValues(): array
    {
        return collect(self::EXTERNAL_SOURCE_ATTRIBUTES)
            ->mapWithKeys(fn (string $attribute) => [$attribute => $this->getAttribute($attribute)])
            ->all();
    }

    /**
     * The attendees field predates structured invitations, so match complete
     * valid email tokens rather than allowing a substring to grant access.
     */
    public function attendeeEmails()
    {
        return collect(preg_split('/[,;\s]+/', (string) $this->attendees))
            ->map(fn ($attendee) => strtolower(trim($attendee)))
            ->filter(fn ($attendee) => filter_var($attendee, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();
    }

    /**
     * LIKE narrows the legacy text field's candidate set only. Complete,
     * normalized email tokens remain authoritative for visibility.
     */
    public static function visibleTo(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        $email = strtolower(trim((string) $user->email));
        $candidates = static::query()
            ->with(self::COPIED_FROM_ANCESTORS)
            ->where(function ($query) use ($user, $email) {
                $query->where('user_id', $user->id);

                if ($email !== '') {
                    $query->orWhere('attendees', 'like', '%'.$email.'%');
                }
            })
            ->get();

        return $candidates->filter(fn (self $meeting) =>
            (int) $meeting->user_id === (int) $user->id
            || ($email !== '' && $meeting->attendeeEmails()->contains($email))
        )->values();
    }

    /** Only explicit HTTP(S) web links may be emitted in an href. */
    public function getSafeExternalUrlAttribute(): ?string
    {
        $url = trim((string) $this->location);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            && filter_var($url, FILTER_VALIDATE_URL)
            && filled(parse_url($url, PHP_URL_HOST))
            ? $url
            : null;
    }

    public function canBeJoinedBy(?User $user): bool
    {
        if (! $user || ! $this->isInternallyCreated()) {
            return false;
        }

        if ((int) $this->user_id === (int) $user->id) {
            return true;
        }

        $email = strtolower(trim((string) $user->email));
        if ($email === '') {
            return false;
        }

        return $this->attendeeEmails()->contains($email);
    }

    public function getDiaryJoinUrlAttribute(): ?string
    {
        return $this->isInternallyCreated()
            ? route('meetings.join', $this)
            : null;
    }

    /**
     * "Missed" isn't a status anyone sets manually — it's derived: a
     * meeting that was scheduled, never got marked completed or
     * cancelled, and whose start time has now passed. Computed on read
     * rather than needing a scheduled job to flip a stored value, since
     * "has start_at passed" doesn't need to be pre-computed to be cheap.
     */
    public function displayStatus(): string
    {
        if (in_array($this->meeting_status, ['completed', 'cancelled'], true)) {
            return $this->meeting_status;
        }

        if ($this->start_at && $this->start_at->isPast()) {
            return 'missed';
        }

        return 'scheduled';
    }

    public function getStartDateAttribute(): ?string
    {
        return $this->start_at?->format('Y-m-d');
    }

    public function getStartTimeAttribute(): ?string
    {
        return $this->start_at?->format('H:i');
    }

    public function getEndDateAttribute(): ?string
    {
        return $this->end_at?->format('Y-m-d');
    }

    public function getEndTimeAttribute(): ?string
    {
        return $this->end_at?->format('H:i');
    }

    public function getStartHourAttribute(): ?string
    {
        return $this->start_at?->format('g');
    }

    public function getStartMinuteAttribute(): ?string
    {
        return $this->start_at?->format('i');
    }

    public function getStartMeridiemAttribute(): ?string
    {
        return $this->start_at?->format('A');
    }

    public function getEndHourAttribute(): ?string
    {
        return $this->end_at?->format('g');
    }

    public function getEndMinuteAttribute(): ?string
    {
        return $this->end_at?->format('i');
    }

    public function getEndMeridiemAttribute(): ?string
    {
        return $this->end_at?->format('A');
    }
}
