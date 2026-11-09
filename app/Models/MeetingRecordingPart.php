<?php

namespace App\Models;

use App\Services\Ai\AiTextFormatter;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * One fixed-length slice of a long browser recording, uploaded and
 * transcribed while the meeting is still running.
 */
class MeetingRecordingPart extends Model
{
    protected $fillable = [
        'meeting_recording_id',
        'part_number',
        'audio_path',
        'duration_seconds',
        'transcript',
        'transcript_segments',
        'transcription_status',
        'transcription_error',
    ];

    protected $casts = [
        'part_number' => 'integer',
        'duration_seconds' => 'integer',
        'transcript_segments' => 'array',
    ];

    public function recording()
    {
        return $this->belongsTo(MeetingRecording::class, 'meeting_recording_id');
    }

    protected function transcript(): Attribute
    {
        return Attribute::get(fn ($value) => $value === null ? null : AiTextFormatter::normalize((string) $value));
    }
}
