<?php

namespace Tests\Feature;

use App\Jobs\TranscribeMeetingRecordingPart;
use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\MeetingRecordingPart;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\MeetingTranscriptionLimitService;
use App\Services\TranscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class MeetingRecordingPartsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, int> */
    private array $ranJobs = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Behave like production with a worker: jobs are queued, not run
        // after the response (the test app is reused across requests, so
        // after-response callbacks would pile up and run repeatedly).
        config(['queue.default' => 'database']);
        Queue::fake();
    }

    /** Act as the queue worker: run each queued part job exactly once. */
    private function runQueuedJobs(): void
    {
        foreach (Queue::pushed(TranscribeMeetingRecordingPart::class) as $job) {
            if (in_array(spl_object_id($job), $this->ranJobs, true)) {
                continue;
            }

            $this->ranJobs[] = spl_object_id($job);
            app()->call([$job, 'handle']);
        }
    }

    public function test_uploaded_part_is_stored_and_queued_for_transcription(): void
    {
        Storage::fake('public');
        [$user, $recording] = $this->setupRecording();

        $this->actingAs($user)->postJson(route('meeting-recordings.parts.store', $recording), [
            'audio' => $this->audio('part-1.webm'),
            'part_number' => 1,
            'duration_seconds' => 1200,
        ])->assertOk()->assertJson(['ok' => true, 'part_number' => 1]);

        $part = $recording->parts()->firstOrFail();
        $this->assertSame(1200, $part->duration_seconds);
        $this->assertTrue(Storage::disk('public')->exists($part->audio_path));
        $this->assertSame('recording', $recording->fresh()->status);
        Queue::assertPushed(TranscribeMeetingRecordingPart::class, fn ($job) => $job->partId === $part->id);
    }

    public function test_retried_part_upload_is_not_stored_or_queued_twice(): void
    {
        Storage::fake('public');
        [$user, $recording] = $this->setupRecording();
        $payload = ['part_number' => 1, 'duration_seconds' => 1200];

        $this->actingAs($user)->postJson(route('meeting-recordings.parts.store', $recording), $payload + ['audio' => $this->audio('a.webm')])->assertOk();
        $path = $recording->parts()->firstOrFail()->audio_path;

        $this->actingAs($user)->postJson(route('meeting-recordings.parts.store', $recording), $payload + ['audio' => $this->audio('b.webm')])->assertOk();

        $this->assertSame(1, $recording->parts()->count());
        $this->assertSame($path, $recording->parts()->firstOrFail()->audio_path);
        Queue::assertPushed(TranscribeMeetingRecordingPart::class, 1);
    }

    public function test_finish_refuses_when_a_part_is_missing(): void
    {
        Storage::fake('public');
        [$user, $recording] = $this->setupRecording();

        $this->actingAs($user)->postJson(route('meeting-recordings.parts.store', $recording), [
            'audio' => $this->audio('part-1.webm'), 'part_number' => 1, 'duration_seconds' => 1200,
        ])->assertOk();

        $this->actingAs($user)->postJson(route('meeting-recordings.parts.finish', $recording), ['parts_count' => 3])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'missing_parts' => [2, 3]]);

        $this->assertSame('recording', $recording->fresh()->status);
    }

    public function test_parts_are_transcribed_and_combined_in_order_with_shifted_timestamps(): void
    {
        Storage::fake('public');
        [$user, $recording] = $this->setupRecording();

        $transcripts = [
            1 => ['transcript' => 'First part.', 'segments' => [['start_seconds' => 5, 'end_seconds' => 9, 'speaker' => null, 'text' => 'First part.']]],
            2 => ['transcript' => 'Second part.', 'segments' => [['start_seconds' => 3, 'end_seconds' => 7, 'speaker' => null, 'text' => 'Second part.']]],
        ];

        $this->mock(TranscriptionService::class)->shouldReceive('transcribe')->twice()
            ->withArgs(fn ($u, $path, $language, $duration, $recordingId) => $u->is($user) && $recordingId === $recording->id)
            ->andReturnUsing(function ($u, $path) use ($transcripts) {
                $part = MeetingRecordingPart::query()->where('audio_path', $path)->firstOrFail();

                return $transcripts[$part->part_number];
            });

        // Part 2 arrives and finishes before part 1: order still follows part numbers.
        $this->actingAs($user)->postJson(route('meeting-recordings.parts.store', $recording), [
            'audio' => $this->audio('part-2.webm'), 'part_number' => 2, 'duration_seconds' => 300,
        ])->assertOk();
        $this->actingAs($user)->postJson(route('meeting-recordings.parts.store', $recording), [
            'audio' => $this->audio('part-1.webm'), 'part_number' => 1, 'duration_seconds' => 1200,
        ])->assertOk();

        $this->runQueuedJobs();

        // Every part transcribed while still recording; nothing combined yet.
        $this->assertSame('', $recording->fresh()->transcript);
        $this->assertSame(2, $recording->parts()->where('transcription_status', 'completed')->count());

        $this->actingAs($user)->postJson(route('meeting-recordings.parts.finish', $recording), ['parts_count' => 2])
            ->assertOk()
            ->assertJson(['ok' => true, 'transcription_status' => 'completed']);

        $recording->refresh();
        $this->assertSame('completed', $recording->status);
        $this->assertSame(1500, $recording->duration_seconds);
        $this->assertSame("First part.\n\nSecond part.", $recording->transcript);
        $this->assertSame([5, 1203], array_column($recording->transcript_segments, 'start_seconds'));
        $this->assertSame([9, 1207], array_column($recording->transcript_segments, 'end_seconds'));
    }

    public function test_failed_part_marks_recording_failed_and_transcribe_retries_only_that_part(): void
    {
        Storage::fake('public');
        [$user, $recording] = $this->setupRecording();

        $calls = [];
        $this->mock(TranscriptionService::class)->shouldReceive('transcribe')
            ->andReturnUsing(function ($u, $path) use (&$calls) {
                $part = MeetingRecordingPart::query()->where('audio_path', $path)->firstOrFail();
                $calls[] = $part->part_number;

                // Part 2 fails on the first round, then succeeds on retry.
                if ($part->part_number === 2 && count(array_keys($calls, 2)) === 1) {
                    throw new RuntimeException('Per-meeting transcription limit exceeded.');
                }

                return ['transcript' => 'Part ' . $part->part_number, 'segments' => []];
            });

        foreach ([1 => 1200, 2 => 600] as $number => $duration) {
            $this->actingAs($user)->postJson(route('meeting-recordings.parts.store', $recording), [
                'audio' => $this->audio("part-{$number}.webm"), 'part_number' => $number, 'duration_seconds' => $duration,
            ])->assertOk();
        }

        $this->runQueuedJobs();

        $this->actingAs($user)->postJson(route('meeting-recordings.parts.finish', $recording), ['parts_count' => 2])->assertOk();

        $recording->refresh();
        $this->assertSame('failed', $recording->transcription_status);
        $this->assertStringContainsString('part 2 (20:00–30:00)', $recording->transcription_error);
        $this->assertSame('', $recording->transcript);

        $this->actingAs($user)->post(route('meeting-recordings.transcribe', $recording))->assertRedirect();
        $this->assertSame('processing', $recording->fresh()->transcription_status);
        $this->runQueuedJobs();

        $recording->refresh();
        $this->assertSame([1, 2, 2], $calls);
        $this->assertSame('completed', $recording->transcription_status);
        $this->assertSame("Part 1\n\nPart 2", $recording->transcript);
    }

    public function test_status_endpoint_reports_part_progress(): void
    {
        Storage::fake('public');
        [$user, $recording] = $this->setupRecording();
        $recording->update(['status' => 'completed', 'transcription_status' => 'processing']);
        MeetingRecordingPart::query()->create(['meeting_recording_id' => $recording->id, 'part_number' => 1, 'transcription_status' => 'completed']);
        MeetingRecordingPart::query()->create(['meeting_recording_id' => $recording->id, 'part_number' => 2, 'transcription_status' => 'processing']);

        $this->actingAs($user)->getJson(route('meeting-recordings.transcription-status', $recording))
            ->assertOk()
            ->assertJson(['transcription_status' => 'processing', 'parts_total' => 2, 'parts_completed' => 1]);
    }

    public function test_status_poll_transcribes_parts_without_a_queue_worker(): void
    {
        Storage::fake('public');
        [$user, $recording] = $this->setupRecording();
        $recording->update(['status' => 'completed', 'transcription_status' => 'processing']);

        // One part was never picked up; another was left "processing" by a
        // request that was killed half an hour ago.
        MeetingRecordingPart::query()->create([
            'meeting_recording_id' => $recording->id, 'part_number' => 1, 'audio_path' => 'p1.webm',
            'duration_seconds' => 1200, 'transcription_status' => 'processing', 'transcription_attempts' => 1,
            'processing_started_at' => now()->subMinutes(30),
        ]);
        MeetingRecordingPart::query()->create([
            'meeting_recording_id' => $recording->id, 'part_number' => 2, 'audio_path' => 'p2.webm',
            'duration_seconds' => 300, 'transcription_status' => 'pending',
        ]);

        $calls = 0;
        $this->mock(TranscriptionService::class)->shouldReceive('transcribe')
            ->andReturnUsing(function ($u, $path) use (&$calls) {
                $calls++;

                // The first try of part 2 hits a transient error.
                if ($path === 'p2.webm' && $calls === 2) {
                    throw new RuntimeException('cURL error 28: Operation timed out');
                }

                return ['transcript' => $path, 'segments' => []];
            });

        $status = fn () => $this->actingAs($user)->getJson(route('meeting-recordings.transcription-status', $recording))->assertOk();

        $status()->assertJson(['transcription_status' => 'processing', 'parts_completed' => 1]);
        $status()->assertJson(['transcription_status' => 'processing', 'parts_completed' => 1, 'parts_waiting' => 1]);
        $status()->assertJson(['transcription_status' => 'completed', 'parts_completed' => 2]);

        $this->assertSame(3, $calls);
        $this->assertSame("p1.webm

p2.webm", $recording->fresh()->transcript);
    }

    public function test_part_killed_too_many_times_is_marked_failed(): void
    {
        Storage::fake('public');
        [$user, $recording] = $this->setupRecording();
        $recording->update(['status' => 'completed', 'transcription_status' => 'processing']);
        MeetingRecordingPart::query()->create([
            'meeting_recording_id' => $recording->id, 'part_number' => 1, 'audio_path' => 'p1.webm',
            'transcription_status' => 'processing', 'transcription_attempts' => 3,
            'processing_started_at' => now()->subMinutes(30),
        ]);
        $this->mock(TranscriptionService::class)->shouldNotReceive('transcribe');

        $this->actingAs($user)->getJson(route('meeting-recordings.transcription-status', $recording))
            ->assertOk()
            ->assertJson(['transcription_status' => 'failed', 'parts_failed' => 1]);
    }

    public function test_status_poll_leaves_a_part_another_request_is_working_on(): void
    {
        Storage::fake('public');
        [$user, $recording] = $this->setupRecording();
        $recording->update(['status' => 'completed', 'transcription_status' => 'processing']);
        MeetingRecordingPart::query()->create([
            'meeting_recording_id' => $recording->id, 'part_number' => 1, 'audio_path' => 'p1.webm',
            'transcription_status' => 'processing', 'transcription_attempts' => 1,
            'processing_started_at' => now()->subMinute(),
        ]);
        $this->mock(TranscriptionService::class)->shouldNotReceive('transcribe');

        $this->actingAs($user)->getJson(route('meeting-recordings.transcription-status', $recording))
            ->assertOk()
            ->assertJson(['transcription_status' => 'processing', 'parts_completed' => 0]);
    }

    public function test_parts_of_one_recording_share_a_single_per_meeting_allowance(): void
    {
        $plan = SubscriptionPlan::query()->create([
            'key' => 'metered-plan', 'name' => 'Metered', 'category' => 'individual', 'individual_tier' => 'starter',
            'duration_months' => 1, 'discount_percent' => 0, 'flat_price' => 10,
            'max_transcription_minutes_per_meeting' => 5, 'transcription_capacity_mb' => 30,
            'color' => '#475569', 'is_enabled' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'user', 'subscription_status' => 'active', 'subscription_plan_id' => $plan->id,
            'subscription_started_at' => now()->subDays(2), 'subscription_expires_at' => now()->addDays(28),
            'extra_recording_quota_minutes' => 0,
        ]);
        $meeting = Meeting::factory()->create(['user_id' => $user->id]);
        $recording = MeetingRecording::query()->create(['meeting_id' => $meeting->id, 'recorded_by_user_id' => $user->id, 'status' => 'recording']);
        $service = app(MeetingTranscriptionLimitService::class);

        $service->complete($service->reserve($user, 240, $recording->id));

        try {
            // 4 + 4 minutes of one 5-minute meeting must need a top-up.
            $service->reserve($user->fresh(), 240, $recording->id);
            $this->fail('Splitting a meeting into parts must not grant each part a fresh allowance.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('per-meeting transcription limit', strtolower($exception->getMessage()));
        }

        // A different meeting still gets its own allowance.
        $service->complete($service->reserve($user->fresh(), 240));
        $this->assertDatabaseCount('transcription_minute_usages', 2);
    }

    public function test_deleting_a_recording_removes_part_audio(): void
    {
        Storage::fake('public');
        [$user, $recording] = $this->setupRecording();

        $this->actingAs($user)->postJson(route('meeting-recordings.parts.store', $recording), [
            'audio' => $this->audio('part-1.webm'), 'part_number' => 1, 'duration_seconds' => 60,
        ])->assertOk();
        $path = $recording->parts()->firstOrFail()->audio_path;

        $this->actingAs($user)->delete(route('meeting-recordings.destroy', $recording))->assertRedirect();

        Storage::disk('public')->assertMissing($path);
    }

    private function audio(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, str_repeat('a', 2048));
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
