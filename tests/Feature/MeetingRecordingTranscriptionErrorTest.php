<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\User;
use App\Services\TranscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MeetingRecordingTranscriptionErrorTest extends TestCase
{
    use RefreshDatabase;

    private const USER_MESSAGE =
        'We could not transcribe this recording. Please try again with a supported audio file within your transcription capacity.';

    private const PROVIDER_MESSAGE =
        'OpenAI transcription failed. gpt-4o-transcribe: Audio file might be corrupted or unsupported | whisper-1: 413: Maximum content size limit exceeded.';

    public function test_web_transcription_hides_provider_error_details(): void
    {
        [$user, $recording] = $this->recordingForUser();
        $this->failingTranscriptionService();

        $this->actingAs($user)
            ->post(route('meeting-recordings.transcribe', $recording))
            ->assertRedirect()
            ->assertSessionHasErrors(['transcription' => self::USER_MESSAGE]);

        $this->assertSame(self::USER_MESSAGE, $recording->fresh()->transcription_error);
    }

    public function test_api_transcription_hides_provider_error_details(): void
    {
        [$user, $recording] = $this->recordingForUser();
        $this->failingTranscriptionService();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/meeting-recordings/{$recording->id}/transcribe")
            ->assertUnprocessable()
            ->assertJson(['message' => self::USER_MESSAGE])
            ->assertJsonMissing(['message' => self::PROVIDER_MESSAGE]);

        $this->assertSame(self::USER_MESSAGE, $recording->fresh()->transcription_error);
    }

    /** @return array{User, MeetingRecording} */
    private function recordingForUser(): array
    {
        $user = User::factory()->create([
            'subscription_status' => 'active',
            'subscription_expires_at' => null,
        ]);
        $meeting = Meeting::create([
            'user_id' => $user->id,
            'title' => 'Diary meeting',
            'start_at' => now(),
            'status' => 'scheduled',
        ]);

        $recording = MeetingRecording::create([
            'meeting_id' => $meeting->id,
            'recorded_by_user_id' => $user->id,
            'status' => 'completed',
            'audio_path' => 'meeting-recordings/recording.webm',
        ]);

        return [$user, $recording];
    }

    private function failingTranscriptionService(): void
    {
        $this->mock(TranscriptionService::class)
            ->shouldReceive('transcribe')
            ->once()
            ->andThrow(new RuntimeException(self::PROVIDER_MESSAGE));
    }
}
