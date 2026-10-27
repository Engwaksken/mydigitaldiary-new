<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\User;
use App\Services\TranscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class MeetingRecordingStopTranscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_stop_transcribes_saved_audio_and_persists_transcript(): void
    {
        [$user, $recording] = $this->setupRecording();
        Storage::fake('public');
        $this->mock(TranscriptionService::class)->shouldReceive('transcribe')->once()
            ->withArgs(fn ($u, $path, $language, $duration) => $u->is($user) && Storage::disk('public')->exists($path) && $language === 'auto' && $duration === 15)
            ->andReturn(['transcript' => 'Meeting transcript', 'segments' => []]);

        $this->actingAs($user)->postJson(route('meeting-recordings.stop', $recording), [
            'recording' => UploadedFile::fake()->createWithContent('meeting.webm', str_repeat('a', 2048)),
            'duration_seconds' => 15,
        ])->assertOk()->assertJson(['ok' => true, 'transcription_status' => 'completed']);

        $this->assertSame('completed', $recording->fresh()->status);
        $this->assertSame('Meeting transcript', $recording->fresh()->transcript);
        $this->assertTrue(Storage::disk('public')->exists($recording->fresh()->audio_path));
    }

    public function test_provider_failure_keeps_completed_recording_audio_and_returns_non_server_error(): void
    {
        [$user, $recording] = $this->setupRecording();
        Storage::fake('public');
        $this->mock(TranscriptionService::class)->shouldReceive('transcribe')->once()
            ->andThrow(new RuntimeException('Provider unavailable'));

        $this->actingAs($user)->postJson(route('meeting-recordings.stop', $recording), [
            'recording' => UploadedFile::fake()->createWithContent('meeting.webm', str_repeat('a', 2048)),
            'duration_seconds' => 15,
        ])->assertSuccessful()->assertJson(['ok' => true, 'transcription_status' => 'failed']);

        $recording->refresh();
        $this->assertSame('completed', $recording->status);
        $this->assertTrue(Storage::disk('public')->exists($recording->audio_path));
    }

    public function test_retry_after_completed_stop_does_not_transcribe_or_replace_audio(): void
    {
        [$user, $recording] = $this->setupRecording();
        Storage::fake('public');
        $this->mock(TranscriptionService::class)->shouldReceive('transcribe')->once()
            ->andReturn(['transcript' => 'Transcript', 'segments' => []]);
        $first = ['recording' => UploadedFile::fake()->createWithContent('first.webm', str_repeat('a', 2048)), 'duration_seconds' => 15];
        $this->actingAs($user)->postJson(route('meeting-recordings.stop', $recording), $first)->assertSuccessful()->assertJson(['ok' => true, 'transcription_status' => 'completed']);
        $path = $recording->fresh()->audio_path;

        $this->actingAs($user)->postJson(route('meeting-recordings.stop', $recording), [
            'recording' => UploadedFile::fake()->createWithContent('retry.webm', str_repeat('b', 2048)),
            'duration_seconds' => 30,
        ])->assertOk()->assertJson(['ok' => true, 'transcription_status' => 'completed']);

        $this->assertSame($path, $recording->fresh()->audio_path);
        $this->assertSame(15, $recording->fresh()->duration_seconds);
    }

    private function setupRecording(): array
    {
        $user = User::factory()->create(['subscription_status' => 'active', 'subscription_expires_at' => null]);
        $meeting = Meeting::factory()->create(['user_id' => $user->id]);
        $recording = MeetingRecording::query()->create([
            'meeting_id' => $meeting->id,
            'recorded_by_user_id' => $user->id,
            'status' => 'recording',
        ]);

        return [$user, $recording];
    }
}
