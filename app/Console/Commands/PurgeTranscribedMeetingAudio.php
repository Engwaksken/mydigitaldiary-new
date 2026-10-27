<?php

namespace App\Console\Commands;

use App\Models\MeetingRecording;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgeTranscribedMeetingAudio extends Command
{
    protected $signature = 'meetings:purge-transcribed-audio';

    protected $description = 'Archive meeting audio after transcription or 14 days, retaining transcripts and activity logs';

    public function handle(): int
    {
        $deleted = 0;
        $disk = Storage::disk('public');

        MeetingRecording::query()
            ->where('status', 'completed')
            ->whereNotNull('audio_path')
            ->where(function ($query): void {
                $query->where(function ($completed): void {
                    $completed->where('transcription_status', 'completed')
                        ->whereNotNull('transcript')->where('transcript', '!=', '');
                })->orWhere('created_at', '<=', now()->subDays(14));
            })
            ->chunkById(100, function ($recordings) use ($disk, &$deleted): void {
                foreach ($recordings as $recording) {
                    try {
                        if ($disk->exists($recording->audio_path) && ! $disk->delete($recording->audio_path)) {
                            $this->warn("Could not delete audio for recording {$recording->id}; it will be retried.");
                            continue;
                        }

                        // Keep transcript, transcript segments, summary and audit row.
                        $recording->update(['audio_path' => null]);
                        $deleted++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->warn("Could not purge audio for recording {$recording->id}; it will be retried.");
                    }
                }
            });

        $this->info("Purged audio for {$deleted} transcribed meeting recording(s).");

        return self::SUCCESS;
    }
}
