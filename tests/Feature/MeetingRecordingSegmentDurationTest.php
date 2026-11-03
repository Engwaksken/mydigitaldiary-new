<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MeetingRecordingSegmentDurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_segment_end_beyond_stored_duration_is_rejected_without_source_duration(): void
    {
        [$user, $recording] = $this->recording(storedDuration: 0);

        $this->actingAs($user)
            ->postJson(route('meeting-recordings.segments.store', $recording), [
                'start_seconds' => 80,
                'end_seconds' => 165,
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'End time cannot exceed recording duration.']);
    }

    public function test_decoded_source_duration_raises_a_missing_stored_duration(): void
    {
        [$user, $recording] = $this->recording(storedDuration: 0);

        $response = $this->actingAs($user)
            ->postJson(route('meeting-recordings.segments.store', $recording), [
                'start_seconds' => 80,
                'end_seconds' => 165,
                'source_duration_seconds' => 600,
            ]);

        // Extraction itself needs FFmpeg; what matters is the range is no longer rejected.
        $this->assertNotSame('End time cannot exceed recording duration.', $response->json('message'));
        $this->assertSame(600, $recording->fresh()->duration_seconds);
    }

    public function test_source_duration_never_lowers_the_stored_duration(): void
    {
        [$user, $recording] = $this->recording(storedDuration: 900);

        $this->actingAs($user)
            ->postJson(route('meeting-recordings.segments.store', $recording), [
                'start_seconds' => 10,
                'end_seconds' => 20,
                'source_duration_seconds' => 30,
            ]);

        $this->assertSame(900, $recording->fresh()->duration_seconds);
    }

    /** @return array{0: User, 1: MeetingRecording} */
    private function recording(int $storedDuration): array
    {
        Storage::fake('public');
        Storage::disk('public')->put('meeting-recordings/upload.webm', str_repeat('a', 4096));

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
            'consent_given_at' => now(),
            'audio_path' => 'meeting-recordings/upload.webm',
            'duration_seconds' => $storedDuration,
        ]);

        return [$user, $recording];
    }
}
