<?php

namespace App\Jobs;

use App\Models\MeetingRecordingPart;
use App\Services\MeetingRecordingPartAssembler;
use App\Services\MeetingRecordingPartTranscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Transcribe one uploaded part of a long browser recording in the
 * background. Retries and stuck parts are handled by the transcriber
 * (and by the progress poll), not by queue retries, so the same rules
 * apply whether or not a queue worker is running.
 */
class TranscribeMeetingRecordingPart implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $partId)
    {
    }

    public function handle(MeetingRecordingPartTranscriber $transcriber, MeetingRecordingPartAssembler $assembler): void
    {
        $part = MeetingRecordingPart::query()->with('recording')->find($this->partId);

        if (! $part || ! $part->recording) {
            return;
        }

        if ($part->transcription_status === 'completed') {
            $assembler->finalizeIfReady($part->recording);

            return;
        }

        $transcriber->process($part);
    }
}
