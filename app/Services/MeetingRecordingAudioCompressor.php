<?php

namespace App\Services;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Compress uploaded meeting media before retaining it on the public disk. */
class MeetingRecordingAudioCompressor
{
    public function store(UploadedFile $upload, int $capacityBytes, bool $hasTopUp): string
    {
        $temporaryDirectory = storage_path('app/meeting-recording-temp');
        if (! is_dir($temporaryDirectory) && ! mkdir($temporaryDirectory, 0755, true) && ! is_dir($temporaryDirectory)) {
            throw new RuntimeException('Unable to create the temporary recording directory.');
        }

        $compressedPath = $temporaryDirectory . DIRECTORY_SEPARATOR . Str::uuid() . '.mp3';
        $compressed = false;

        try {
            if ($this->ffmpegAvailable() && $upload->getRealPath()) {
                $command = 'ffmpeg -hide_banner -loglevel error -y -i '
                    . escapeshellarg($upload->getRealPath())
                    . ' -vn -ac 1 -ar 16000 -codec:a libmp3lame -b:a 32k '
                    . escapeshellarg($compressedPath) . ' 2>&1';
                $output = [];
                $exitCode = 1;
                @exec($command, $output, $exitCode);

                $compressed = $exitCode === 0
                    && is_file($compressedPath)
                    && filesize($compressedPath) >= 1024
                    && filesize($compressedPath) < ($upload->getSize() ?: PHP_INT_MAX);

                if (! $compressed && $exitCode !== 0) {
                    Log::warning('Meeting recording compression failed; retaining original upload.', [
                        'error' => trim(implode("\n", $output)),
                    ]);
                }
            }

            $storedSize = $compressed ? (int) filesize($compressedPath) : (int) ($upload->getSize() ?: 0);
            if (! $hasTopUp && $storedSize > $capacityBytes) {
                throw new RuntimeException('The recording exceeds your transcription capacity, even after compression.');
            }

            $disk = Storage::disk('public');
            if ($compressed) {
                $storedPath = $disk->putFileAs(
                    'meeting-recordings',
                    new File($compressedPath),
                    Str::uuid() . '.mp3'
                );
            } else {
                $extension = strtolower((string) $upload->getClientOriginalExtension());
                if ($extension === '') {
                    $extension = strtolower((string) $upload->extension()) ?: 'webm';
                }
                if ($extension === 'bin') {
                    $mime = strtolower((string) $upload->getMimeType());
                    $extension = match (true) {
                        str_contains($mime, 'webm') => 'webm',
                        str_contains($mime, 'ogg') => 'ogg',
                        str_contains($mime, 'mpeg') => 'mp3',
                        str_contains($mime, 'wav') => 'wav',
                        str_contains($mime, 'mp4') => 'm4a',
                        default => 'webm',
                    };
                }
                $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: 'webm';
                $storedPath = $upload->storeAs('meeting-recordings', Str::uuid() . '.' . $extension, 'public');
            }

            if (! $storedPath) {
                throw new RuntimeException('Unable to store the meeting recording.');
            }

            return $storedPath;
        } finally {
            if (is_file($compressedPath)) {
                @unlink($compressedPath);
            }
        }
    }

    private function ffmpegAvailable(): bool
    {
        $output = [];
        $exitCode = 1;
        @exec('ffmpeg -version 2>&1', $output, $exitCode);

        return $exitCode === 0;
    }
}
