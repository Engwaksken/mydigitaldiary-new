<?php

namespace App\Services;

use Illuminate\Http\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cuts a stretch of a stored meeting recording into its own small MP3.
 *
 * Runs the ffmpeg command directly, like the rest of the recording code:
 * php-ffmpeg's audio formats have no way to pass -ss/-t.
 */
class MeetingRecordingSegmentExtractor
{
    public function extract(string $sourcePath, int $startSeconds, int $durationSeconds): string
    {
        $disk = Storage::disk('public');
        $absoluteSourcePath = $disk->path($sourcePath);

        if (! is_file($absoluteSourcePath)) {
            throw new RuntimeException('Source recording file not found.');
        }

        $temporaryDirectory = storage_path('app/transcription-temp');

        if (! is_dir($temporaryDirectory) && ! mkdir($temporaryDirectory, 0755, true) && ! is_dir($temporaryDirectory)) {
            throw new RuntimeException('Unable to create temporary directory.');
        }

        $destinationPath = $temporaryDirectory . DIRECTORY_SEPARATOR . Str::uuid() . '.mp3';

        // -ss before -i seeks straight to the start instead of decoding from
        // the beginning, which matters for hour-long recordings.
        $command = 'ffmpeg -hide_banner -loglevel error -nostdin -y'
            . ' -ss ' . escapeshellarg((string) max(0, $startSeconds))
            . ' -i ' . escapeshellarg($absoluteSourcePath)
            . ' -t ' . escapeshellarg((string) max(1, $durationSeconds))
            . ' -vn -ac 1 -ar 16000 -codec:a libmp3lame -b:a 64k '
            . escapeshellarg($destinationPath) . ' 2>&1';

        @set_time_limit(0);

        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);

        if ($exitCode !== 0 || ! is_file($destinationPath) || filesize($destinationPath) < 1024) {
            @unlink($destinationPath);

            $error = trim(implode("\n", $output));

            Log::warning('Audio segment extraction failed.', [
                'source' => $sourcePath,
                'start' => $startSeconds,
                'duration' => $durationSeconds,
                'exit_code' => $exitCode,
                'error' => $error,
            ]);

            throw new RuntimeException(
                $exitCode !== 0 && $error === ''
                    ? 'Failed to extract audio segment: ffmpeg is not available on the server.'
                    : 'Failed to extract audio segment. The source audio may be incomplete or corrupted.'
            );
        }

        try {
            $storedPath = $disk->putFileAs(
                'meeting-recording-segments',
                new File($destinationPath),
                basename($destinationPath),
                'public'
            );
        } finally {
            @unlink($destinationPath);
        }

        if (! $storedPath) {
            throw new RuntimeException('Unable to store the audio segment.');
        }

        return $storedPath;
    }
}
