<?php

namespace App\Services;

use App\Models\MeetingAuditLog;
use App\Models\MeetingRecording;
use App\Models\MeetingRecordingPart;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Combines the background-transcribed parts of a split browser recording
 * into the single recording users see: one transcript (with timestamps
 * shifted by each part's start time) and one audio file.
 */
class MeetingRecordingPartAssembler
{
    /**
     * Finish the recording when it has stopped and no part is still waiting.
     * Safe to call from every part job: only the call that finds every part
     * settled does the work, and a lock keeps two finishers from overlapping.
     */
    public function finalizeIfReady(MeetingRecording $recording): void
    {
        // block() waits rather than skipping, so a part finishing while another
        // finisher holds the lock still gets to re-check once it is released.
        Cache::lock('meeting-recording-finalize:' . $recording->getKey(), 600)
            ->block(120, function () use ($recording): void {
                $this->finalize($recording->fresh());
            });
    }

    private function finalize(?MeetingRecording $recording): void
    {
        if (! $recording || $recording->status !== 'completed') {
            // Still recording: parts keep transcribing; Stop triggers this again.
            return;
        }

        /** @var Collection<int, MeetingRecordingPart> $parts */
        $parts = $recording->parts()->get();

        if ($parts->isEmpty()) {
            return;
        }

        if ($parts->contains(fn (MeetingRecordingPart $part) => in_array($part->transcription_status, ['pending', 'processing'], true))) {
            return;
        }

        $durationSeconds = (int) $parts->sum('duration_seconds');
        $combinedAudio = $this->combineAudio($recording, $parts);

        $updates = ['duration_seconds' => max($durationSeconds, 0)];

        if ($combinedAudio !== null) {
            $updates['audio_path'] = $combinedAudio;
        }

        $failed = $parts->where('transcription_status', 'failed');

        if ($failed->isNotEmpty()) {
            $recording->update($updates + [
                'transcription_status' => 'failed',
                'transcription_error' => $this->failureMessage($parts, $failed),
            ]);

            return;
        }

        [$transcript, $segments] = $this->combineTranscripts($parts);

        $recording->update($updates + [
            'transcript' => $transcript,
            'transcript_segments' => $segments,
            'transcription_status' => 'completed',
            'transcription_error' => null,
        ]);

        MeetingAuditLog::record(
            $recording->meeting_id,
            $recording->recorded_by_user_id,
            'transcribed_recording',
            'Combined from ' . $parts->count() . ' parts'
        );

        // Part audio is only kept for retrying failed parts; once the full
        // audio exists and every part is transcribed it is redundant.
        if ($combinedAudio !== null) {
            $this->deletePartAudio($parts, $combinedAudio);
        }
    }

    /**
     * @param  Collection<int, MeetingRecordingPart>  $parts
     * @return array{0:string, 1:array<int, array<string, mixed>>}
     */
    public function combineTranscripts(Collection $parts): array
    {
        $texts = [];
        $segments = [];
        $offset = 0;

        foreach ($parts as $part) {
            $text = trim((string) $part->transcript);

            if ($text !== '') {
                $texts[] = $text;
            }

            foreach ((array) ($part->transcript_segments ?? []) as $segment) {
                if (! is_array($segment)) {
                    continue;
                }

                $segment['start_seconds'] = (int) ($segment['start_seconds'] ?? 0) + $offset;
                $segment['end_seconds'] = (int) ($segment['end_seconds'] ?? 0) + $offset;
                $segments[] = $segment;
            }

            $offset += (int) $part->duration_seconds;
        }

        return [implode("\n\n", $texts), $segments];
    }

    /**
     * Join the part files into one MP3 on the public disk.
     *
     * @param  Collection<int, MeetingRecordingPart>  $parts
     */
    private function combineAudio(MeetingRecording $recording, Collection $parts): ?string
    {
        $disk = Storage::disk('public');
        $paths = $parts->pluck('audio_path')->filter(fn ($path) => $path && $disk->exists($path))->values();

        if ($paths->count() !== $parts->count()) {
            // A part's audio was already removed after an earlier successful
            // combine; keep whatever full audio the recording already has.
            return $recording->audio_path && $disk->exists($recording->audio_path)
                ? $recording->audio_path
                : null;
        }

        if ($paths->count() === 1) {
            return $paths->first();
        }

        $temporaryDirectory = storage_path('app/meeting-recording-temp');
        if (! is_dir($temporaryDirectory) && ! @mkdir($temporaryDirectory, 0755, true) && ! is_dir($temporaryDirectory)) {
            Log::warning('Could not create a temporary directory to combine recording parts.', ['recording_id' => $recording->id]);

            return null;
        }

        $listFile = $temporaryDirectory . DIRECTORY_SEPARATOR . Str::uuid() . '.txt';
        $outputFile = $temporaryDirectory . DIRECTORY_SEPARATOR . Str::uuid() . '.mp3';

        try {
            file_put_contents($listFile, $paths
                ->map(fn ($path) => "file '" . str_replace("'", "'\\''", str_replace('\\', '/', $disk->path($path))) . "'")
                ->implode("\n"));

            $allMp3 = $paths->every(fn ($path) => str_ends_with(strtolower($path), '.mp3'));

            // Parts are normally MP3s with identical settings (see
            // MeetingRecordingAudioCompressor), so a stream copy is enough and
            // fast; anything else is re-encoded with the same settings.
            $succeeded = ($allMp3 && $this->runConcat($listFile, $outputFile, '-c copy'))
                || $this->runConcat($listFile, $outputFile, '-vn -ac 1 -ar 16000 -codec:a libmp3lame -b:a 32k');

            if (! $succeeded) {
                Log::warning('Could not combine meeting recording parts; part audio is kept.', ['recording_id' => $recording->id]);

                return null;
            }

            $stored = $disk->putFileAs(
                'meeting-recordings',
                new \Illuminate\Http\File($outputFile),
                Str::uuid() . '.mp3'
            );

            if (! $stored) {
                return null;
            }

            // Replace a full audio file from an earlier combine (e.g. after a
            // part was retried) so it is not orphaned.
            $previous = $recording->audio_path;
            if ($previous && $previous !== $stored && ! $paths->contains($previous) && $disk->exists($previous)) {
                $disk->delete($previous);
            }

            return $stored;
        } catch (Throwable $e) {
            report($e);

            return null;
        } finally {
            @unlink($listFile);
            @unlink($outputFile);
        }
    }

    private function runConcat(string $listFile, string $outputFile, string $codecArguments): bool
    {
        @unlink($outputFile);

        $output = [];
        $exitCode = 1;

        @exec(
            'ffmpeg -hide_banner -loglevel error -nostdin -y -f concat -safe 0 -i '
            . escapeshellarg($listFile) . ' ' . $codecArguments . ' '
            . escapeshellarg($outputFile) . ' 2>&1',
            $output,
            $exitCode
        );

        return $exitCode === 0 && is_file($outputFile) && filesize($outputFile) >= 1024;
    }

    /**
     * @param  Collection<int, MeetingRecordingPart>  $parts
     */
    private function deletePartAudio(Collection $parts, string $keepPath): void
    {
        $disk = Storage::disk('public');

        foreach ($parts as $part) {
            if (! $part->audio_path || $part->audio_path === $keepPath) {
                continue;
            }

            try {
                $disk->delete($part->audio_path);
                $part->update(['audio_path' => null]);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * @param  Collection<int, MeetingRecordingPart>  $parts
     * @param  Collection<int, MeetingRecordingPart>  $failed
     */
    private function failureMessage(Collection $parts, Collection $failed): string
    {
        $offset = 0;
        $ranges = [];

        foreach ($parts as $part) {
            $end = $offset + (int) $part->duration_seconds;

            if ($part->transcription_status === 'failed') {
                $ranges[] = 'part ' . $part->part_number . ' (' . $this->clock($offset) . '–' . $this->clock($end) . ')';
            }

            $offset = $end;
        }

        $reason = (string) ($failed->first()->transcription_error ?: 'The transcription service did not respond.');

        return 'Could not transcribe ' . implode(', ', $ranges) . '. ' . $reason
            . ' Click Transcribe to retry only the missing part' . ($failed->count() > 1 ? 's' : '') . '.';
    }

    private function clock(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
