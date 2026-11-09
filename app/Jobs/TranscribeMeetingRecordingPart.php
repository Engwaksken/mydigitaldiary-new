<?php

namespace App\Jobs;

use App\Models\MeetingRecordingPart;
use App\Services\MeetingRecordingPartAssembler;
use App\Services\MeetingTranscriptionErrorMessage;
use App\Services\TranscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Transcribe one uploaded part of a long browser recording in the
 * background, then combine the recording if this was the last part.
 */
class TranscribeMeetingRecordingPart implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** Up to three model attempts of 180 s each, plus audio preparation. */
    public int $timeout = 720;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public int $partId)
    {
    }

    public function handle(TranscriptionService $transcription, MeetingRecordingPartAssembler $assembler): void
    {
        $part = MeetingRecordingPart::query()->with('recording.meeting')->find($this->partId);

        if (! $part || ! $part->recording) {
            return;
        }

        if ($part->transcription_status === 'completed') {
            $assembler->finalizeIfReady($part->recording);

            return;
        }

        // A duplicate dispatch (e.g. a retry clicked while this runs) must not
        // send the same audio to the provider twice.
        $lock = Cache::lock('meeting-recording-part:' . $part->getKey(), $this->timeout + 60);

        if (! $lock->get()) {
            return;
        }

        try {
            $part->update(['transcription_status' => 'processing', 'transcription_error' => null]);

            $recording = $part->recording;
            $user = $recording->recordedBy ?? $recording->meeting?->user;

            if (! $user || ! $part->audio_path) {
                $this->markFailed($part, MeetingTranscriptionErrorMessage::GENERIC);
                $assembler->finalizeIfReady($recording);

                return;
            }

            try {
                $result = $transcription->transcribe(
                    $user,
                    $part->audio_path,
                    'auto',
                    (int) $part->duration_seconds,
                    (int) $recording->getKey()
                );
            } catch (Throwable $e) {
                if (MeetingTranscriptionErrorMessage::isPermanent($e) || $this->attempts() >= $this->tries) {
                    report($e);
                    $this->markFailed($part, MeetingTranscriptionErrorMessage::from($e));
                    $assembler->finalizeIfReady($recording);

                    return;
                }

                // Leave the part pending so the next attempt can pick it up.
                $part->update(['transcription_status' => 'pending']);

                throw $e;
            }

            $part->update([
                'transcript' => $result['transcript'],
                'transcript_segments' => $result['segments'],
                'transcription_status' => 'completed',
                'transcription_error' => null,
            ]);

            $assembler->finalizeIfReady($recording);
        } finally {
            $lock->release();
        }
    }

    /** Runs when the worker gives up (timeout, crash), not only on exceptions. */
    public function failed(?Throwable $e): void
    {
        $part = MeetingRecordingPart::query()->with('recording')->find($this->partId);

        if (! $part || $part->transcription_status === 'completed') {
            return;
        }

        $this->markFailed($part, $e ? MeetingTranscriptionErrorMessage::from($e) : MeetingTranscriptionErrorMessage::GENERIC);

        if ($part->recording) {
            app(MeetingRecordingPartAssembler::class)->finalizeIfReady($part->recording);
        }
    }

    private function markFailed(MeetingRecordingPart $part, string $message): void
    {
        $part->update([
            'transcription_status' => 'failed',
            'transcription_error' => $message,
        ]);
    }
}
