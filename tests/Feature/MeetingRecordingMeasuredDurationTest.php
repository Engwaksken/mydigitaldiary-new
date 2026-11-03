<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\User;
use App\Services\AudioDurationProbe;
use App\Services\TranscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Saved recording lengths come from the audio itself, so uploads (which send
 * no duration) and under-counted browser timers are charged correctly.
 */
class MeetingRecordingMeasuredDurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_without_duration_saves_the_measured_length(): void
    {
        Storage::fake('public');
        $this->fakeProbe(4500);
        [$user, $meeting] = $this->meeting();

        $this->actingAs($user)->post(route('meetings.recordings.upload', $meeting), [
            'audio' => UploadedFile::fake()->createWithContent('meeting.webm', str_repeat('a', 4096)),
        ])->assertRedirect();

        $this->assertSame(4500, $meeting->recordings()->firstOrFail()->duration_seconds);
    }

    public function test_browser_stop_uses_measured_length_when_timer_under_counts(): void
    {
        Storage::fake('public');
        $this->fakeProbe(3600);
        [$user, $meeting] = $this->meeting();
        $recording = $this->recording($user, $meeting);

        $this->mock(TranscriptionService::class)->shouldReceive('transcribe')->once()
            ->withArgs(fn ($u, $path, $language, $duration) => $duration === 3600)
            ->andReturn(['transcript' => 'Meeting transcript', 'segments' => []]);

        $this->actingAs($user)->postJson(route('meeting-recordings.stop', $recording), [
            'recording' => UploadedFile::fake()->createWithContent('meeting.webm', str_repeat('a', 2048)),
            'duration_seconds' => 900,
        ])->assertOk();

        $this->assertSame(3600, $recording->fresh()->duration_seconds);
    }

    public function test_reported_length_is_kept_when_nothing_can_be_measured(): void
    {
        Storage::fake('public');
        $this->fakeProbe(null);
        [$user, $meeting] = $this->meeting();
        $recording = $this->recording($user, $meeting);

        $this->mock(TranscriptionService::class)->shouldReceive('transcribe')->once()
            ->andReturn(['transcript' => 'Meeting transcript', 'segments' => []]);

        $this->actingAs($user)->postJson(route('meeting-recordings.stop', $recording), [
            'recording' => UploadedFile::fake()->createWithContent('meeting.webm', str_repeat('a', 2048)),
            'duration_seconds' => 900,
        ])->assertOk();

        $this->assertSame(900, $recording->fresh()->duration_seconds);
    }

    public function test_api_stop_saves_the_measured_length(): void
    {
        Storage::fake('public');
        $this->fakeProbe(2400);
        [$user, $meeting] = $this->meeting();
        $recording = $this->recording($user, $meeting);

        $this->mock(TranscriptionService::class)->shouldReceive('transcribe')
            ->andReturn(['transcript' => 'Meeting transcript', 'segments' => []]);

        $this->actingAs($user, 'sanctum')->postJson("/api/meeting-recordings/{$recording->id}/stop", [
            'audio' => UploadedFile::fake()->createWithContent('meeting.webm', str_repeat('a', 2048)),
            'duration_seconds' => 60,
        ])->assertSuccessful();

        $this->assertSame(2400, $recording->fresh()->duration_seconds);
    }

    private function fakeProbe(?int $measuredSeconds): void
    {
        $this->app->instance(AudioDurationProbe::class, new class($measuredSeconds) extends AudioDurationProbe {
            public function __construct(private readonly ?int $measured) {}

            public function seconds(string $absolutePath): ?int
            {
                return $this->measured;
            }
        });
    }

    private function meeting(): array
    {
        $user = User::factory()->create(['subscription_status' => 'active', 'subscription_expires_at' => null]);

        return [$user, Meeting::factory()->create(['user_id' => $user->id])];
    }

    private function recording(User $user, Meeting $meeting): MeetingRecording
    {
        return MeetingRecording::query()->create([
            'meeting_id' => $meeting->id,
            'recorded_by_user_id' => $user->id,
            'status' => 'recording',
        ]);
    }
}
