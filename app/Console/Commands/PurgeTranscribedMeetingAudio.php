<?php

namespace App\Console\Commands;

use App\Models\MeetingRecording;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgeTranscribedMeetingAudio extends Command
{
    protected $signature = 'meetings:purge-transcribed-audio';

    protected $description = 'Delete audio for successfully transcribed meetings after 30 days, retaining transcripts and summaries';

    public function handle(): int
    {
        $deleted = 0;
        $disk = Storage::disk('public');

        MeetingRecording::query()
            ->where('status', 'completed')
            ->where('transcription_status', 'completed')
            ->whereNotNull('transcript')
            ->where('transcript', '!=', '')
            ->whereNotNull('audio_path')
            ->where('created_at', '<=', now()->subDays(30))
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
