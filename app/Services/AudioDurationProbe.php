<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Measure the real length of an audio file on the server.
 *
 * Client-reported durations cannot be trusted for transcription limits:
 * uploads send none (stored as 0) and the browser recorder's timer slows
 * down in background tabs. Returns null when FFmpeg tools are unavailable
 * or the length cannot be read, so callers keep their existing value.
 */
class AudioDurationProbe
{
    public function seconds(string $absolutePath): ?int
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        return $this->fromContainer($absolutePath)
            ?? $this->fromDecoding($absolutePath);
    }

    /**
     * Length to save for a file on the public disk: the measured length, or
     * the client-reported one when it is longer or nothing could be measured.
     */
    public function storedSeconds(string $publicDiskPath, int $reportedSeconds): int
    {
        $measured = $this->seconds(Storage::disk('public')->path($publicDiskPath));

        return max(0, $reportedSeconds, (int) $measured);
    }

    /** Fast path: the duration recorded in the file header (MP3, M4A, WAV...). */
    private function fromContainer(string $path): ?int
    {
        $output = [];
        $exitCode = 1;

        @exec(
            'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '
            . escapeshellarg($path) . ' 2>&1',
            $output,
            $exitCode
        );

        $value = trim((string) ($output[0] ?? ''));

        return $exitCode === 0 && is_numeric($value) && (float) $value > 0
            ? (int) ceil((float) $value)
            : null;
    }

    /**
     * Fallback for browser MediaRecorder WebM, whose header has no duration:
     * decode to the null muxer and read the last reported timestamp.
     */
    private function fromDecoding(string $path): ?int
    {
        $output = [];
        $exitCode = 1;

        @exec(
            'ffmpeg -hide_banner -nostdin -vn -i ' . escapeshellarg($path) . ' -f null - 2>&1',
            $output,
            $exitCode
        );

        if ($exitCode !== 0) {
            return null;
        }

        $seconds = null;

        foreach ($output as $line) {
            if (preg_match_all('/time=(\d+):(\d{2}):(\d{2}(?:\.\d+)?)/', $line, $matches, PREG_SET_ORDER)) {
                $last = end($matches);
                $seconds = (int) $last[1] * 3600 + (int) $last[2] * 60 + (float) $last[3];
            }
        }

        return $seconds !== null && $seconds > 0 ? (int) ceil($seconds) : null;
    }
}
