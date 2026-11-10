<?php

namespace App\Services;

use App\Models\MeetingRecording;
use App\Models\MeetingRecordingPart;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Transcribes the parts of a split browser recording.
 *
 * Works the same from a queue worker and from a plain web request (the
 * production `sync` queue): each part is claimed with an atomic update, so
 * two callers never transcribe the same part, and a part whose process was
 * killed mid-way is reclaimed once it has been "processing" for too long.
 */
class MeetingRecordingPartTranscriber
{
    public const MAX_ATTEMPTS = 3;

    /** Longer than one part can take (three 180 s model calls plus audio prep). */
    public const STALE_AFTER_SECONDS = 900;

    public function __construct(
        private TranscriptionService $transcription,
        private MeetingRecordingPartAssembler $assembler
    ) {
    }

    /**
     * Transcribe the next part of the recording that is waiting (or stuck).
     * Returns true when a part was worked on.
     */
    public function processNext(MeetingRecording $recording): bool
    {
        $candidates = $recording->parts()
            ->where(function ($query): void {
                $query->where('transcription_status', 'pending')
                    ->orWhere(function ($query): void {
                        $query->where('transcription_status', 'processing')
                            ->where(function ($query): void {
                                $stale = now()->subSeconds(self::STALE_AFTER_SECONDS);
                                $query->where('processing_started_at', '<', $stale)
                                    ->orWhere(fn ($q) => $q->whereNull('processing_started_at')->where('updated_at', '<', $stale));
                            });
                    });
            })
            ->orderBy('part_number')
            ->get();

        foreach ($candidates as $part) {
            if ($this->process($part)) {
                return true;
            }
        }

        // Nothing left to transcribe: make sure a stopped recording gets combined.
        $this->finalize($recording);

        return false;
    }

    /** Claim and transcribe one part. Returns false if someone else holds it. */
    public function process(MeetingRecordingPart $part): bool
    {
        if (! $this->claim($part)) {
            return false;
        }

        // Keep going if the browser stops waiting or the default time limit hits.
        @ignore_user_abort(true);
        @set_time_limit(0);

        $part->refresh()->load('recording.meeting');
        $recording = $part->recording;
        $user = $recording?->recordedBy ?? $recording?->meeting?->user;

        if (! $recording || ! $user || ! $part->audio_path) {
            $this->markFailed($part, MeetingTranscriptionErrorMessage::GENERIC);
            $recording && $this->finalize($recording);

            return true;
        }

        try {
            $result = $this->transcription->transcribe(
                $user,
                $part->audio_path,
                'auto',
                (int) $part->duration_seconds,
                (int) $recording->getKey()
            );
        } catch (Throwable $e) {
            report($e);

            if (MeetingTranscriptionErrorMessage::isPermanent($e) || $part->transcription_attempts >= self::MAX_ATTEMPTS) {
                $this->markFailed($part, MeetingTranscriptionErrorMessage::from($e));
            } else {
                // Transient (network, rate limit): the next poll or job retries it.
                $part->update(['transcription_status' => 'pending', 'processing_started_at' => null]);
            }

            $this->finalize($recording);

            return true;
        }

        $part->update([
            'transcript' => $result['transcript'],
            'transcript_segments' => $result['segments'],
            'transcription_status' => 'completed',
            'transcription_error' => null,
            'processing_started_at' => null,
        ]);

        $this->finalize($recording);

        return true;
    }

    /** Put failed parts back in line, with a fresh set of attempts. */
    public function requeueFailed(MeetingRecordingPart $part): void
    {
        $part->update([
            'transcription_status' => 'pending',
            'transcription_error' => null,
            'transcription_attempts' => 0,
            'processing_started_at' => null,
        ]);
    }

    private function claim(MeetingRecordingPart $part): bool
    {
        $stale = now()->subSeconds(self::STALE_AFTER_SECONDS);

        // A part reclaimed after too many killed attempts is given up on.
        $exhausted = MeetingRecordingPart::query()
            ->whereKey($part->getKey())
            ->where('transcription_status', 'processing')
            ->where('transcription_attempts', '>=', self::MAX_ATTEMPTS)
            ->where(fn ($q) => $q->where('processing_started_at', '<', $stale)
                ->orWhere(fn ($q) => $q->whereNull('processing_started_at')->where('updated_at', '<', $stale)))
            ->update([
                'transcription_status' => 'failed',
                'transcription_error' => 'Transcribing this part took too long. Please retry.',
                'processing_started_at' => null,
                'updated_at' => now(),
            ]);

        if ($exhausted > 0) {
            $part->recording && $this->finalize($part->recording);

            return false;
        }

        return MeetingRecordingPart::query()
            ->whereKey($part->getKey())
            ->whereNotNull('audio_path')
            ->where(function ($query) use ($stale): void {
                $query->where('transcription_status', 'pending')
                    ->orWhere(fn ($q) => $q->where('transcription_status', 'processing')
                        ->where(fn ($q) => $q->where('processing_started_at', '<', $stale)
                            ->orWhere(fn ($q) => $q->whereNull('processing_started_at')->where('updated_at', '<', $stale))));
            })
            ->update([
                'transcription_status' => 'processing',
                'transcription_error' => null,
                'transcription_attempts' => DB::raw('transcription_attempts + 1'),
                'processing_started_at' => now(),
                'updated_at' => now(),
            ]) === 1;
    }

    private function finalize(MeetingRecording $recording): void
    {
        try {
            $this->assembler->finalizeIfReady($recording);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function markFailed(MeetingRecordingPart $part, string $message): void
    {
        $part->update([
            'transcription_status' => 'failed',
            'transcription_error' => $message,
            'processing_started_at' => null,
        ]);
    }
}
