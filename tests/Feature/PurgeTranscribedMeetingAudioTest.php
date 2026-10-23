<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeTranscribedMeetingAudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_only_audio_older_than_30_days_for_completed_transcripts(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['user_id' => $user->id]);

        $eligible = $this->recording($meeting, 'eligible.webm', now()->subDays(31), 'completed');
        $tooRecent = $this->recording($meeting, 'recent.webm', now()->subDays(29), 'completed');
        $failed = $this->recording($meeting, 'failed.webm', now()->subDays(40), 'failed');

        $this->artisan('meetings:purge-transcribed-audio')->assertExitCode(0);

        $this->assertNull($eligible->fresh()->audio_path);
        $this->assertSame('Transcript', $eligible->fresh()->transcript);
        $this->assertFalse(Storage::disk('public')->exists('meeting-recordings/eligible.webm'));
        $this->assertSame('meeting-recordings/recent.webm', $tooRecent->fresh()->audio_path);
        $this->assertSame('meeting-recordings/failed.webm', $failed->fresh()->audio_path);
    }

    private function recording(Meeting $meeting, string $filename, $createdAt, string $transcriptionStatus): MeetingRecording
    {
        Storage::disk('public')->put('meeting-recordings/'.$filename, 'audio');

        $recording = MeetingRecording::query()->create([
            'meeting_id' => $meeting->id,
            'recorded_by_user_id' => $meeting->user_id,
            'status' => 'completed',
            'audio_path' => 'meeting-recordings/'.$filename,
            'duration_seconds' => 60,
            'transcript' => 'Transcript',
            'transcription_status' => $transcriptionStatus,
        ]);
        $recording->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $recording;
    }
}
