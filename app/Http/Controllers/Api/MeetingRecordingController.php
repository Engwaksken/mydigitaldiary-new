<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingAuditLog;
use App\Models\MeetingRecording;
use App\Models\MeetingRecordingSegment;
use App\Models\User;
use App\Services\MeetingSummaryService;
use App\Services\TranscriptionService;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Mobile equivalent of MeetingRecordingController — same
 * start/pause/resume/stop/transcribe/summarize flow, JSON responses
 * instead of redirects. The Flutter side captures audio with the
 * `record` package and uploads the finished file to stop(), the same
 * shape as the web app's MediaRecorder-captured blob.
 */
class MeetingRecordingController extends Controller
{
    private const TRANSCRIPTION_FAILURE_MESSAGE =
        'We could not transcribe this recording. Please try again with a supported audio file within your transcription capacity.';

    private const TRANSCRIPTION_TOO_LARGE_MESSAGE =
        'This recording is too large to transcribe. Please top up your extra recording quota or record a shorter meeting.';

    private const TRANSCRIPTION_QUOTA_REQUIRED_MESSAGE =
        'Transcription needs an active subscription or extra recording minutes. Please top up your extra recording quota to continue.';

    private function authorizeMeeting(Request $request, Meeting $meeting): void
    {
        abort_unless($meeting->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
    }

    private function authorizeRecording(Request $request, MeetingRecording $recording): void
    {
        $this->authorizeMeeting($request, $recording->meeting);
    }

    public function index(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeMeeting($request, $meeting);

        $recordings = $meeting->recordings()->orderByDesc('id')->get();

        return response()->json(['data' => $recordings->map(fn ($r) => $this->transform($r, $request->user()))]);
    }

    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeMeeting($request, $meeting);
        $request->validate(['consent' => ['required', 'accepted']]);

        $recording = $meeting->recordings()->create([
            'recorded_by_user_id' => $request->user()->id,
            'status' => 'recording',
            'consent_given_at' => now(),
        ]);

        MeetingAuditLog::record($meeting->id, $request->user()->id, 'started_recording');

        return response()->json(['data' => $this->transform($recording, $request->user())]);
    }

    public function updateStatus(Request $request, MeetingRecording $recording): JsonResponse
    {
        $this->authorizeRecording($request, $recording);

        $data = $request->validate([
            'status' => ['required', 'in:recording,paused'],
            'duration_seconds' => ['required', 'integer', 'min:0'],
        ]);

        $recording->update($data);
        MeetingAuditLog::record($recording->meeting_id, $request->user()->id, $data['status'] === 'paused' ? 'paused_recording' : 'resumed_recording');

        return response()->json(['data' => $this->transform($recording, $request->user())]);
    }

    public function stop(Request $request, MeetingRecording $recording): JsonResponse
    {
        $this->authorizeRecording($request, $recording);

        $data = $request->validate([
            'audio' => ['required', 'file', 'max:204800'],
            'duration_seconds' => ['required', 'integer', 'min:0'],
        ]);

        $audioFile = $request->file('audio');
        $this->validateRecordingFile(
            $audioFile,
            $request->user()->effectiveTranscriptionCapacityBytes(),
            $request->user()->availableExtraRecordingQuotaMinutes() > 0
        );
        $audioPath = $this->storeRecordingFile($audioFile);

        $recording->update([
            'status' => 'completed',
            'audio_path' => $audioPath,
            'duration_seconds' => $data['duration_seconds'],
        ]);

        MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'stopped_recording', "Duration: {$recording->formattedDuration()}");

        return response()->json(['data' => $this->transform($recording, $request->user())]);
    }

    public function transcribe(Request $request, MeetingRecording $recording): JsonResponse
    {
        $this->authorizeRecording($request, $recording);

        if (! $recording->audio_path) {
            return response()->json(['message' => 'No audio to transcribe yet.'], 422);
        }

        $data = $request->validate([
            'transcription_language' => ['nullable', 'in:auto,en-GB,lg,sw'],
        ]);
        $language = $data['transcription_language'] ?? 'auto';

        $recording->update(['transcription_status' => 'processing', 'transcription_error' => null]);

        try {
            $result = app(TranscriptionService::class)->transcribe($request->user(), $recording->audio_path, $language);

            $recording->update([
                'transcript' => $result['transcript'],
                'transcript_segments' => $result['segments'],
                'transcription_status' => 'completed',
            ]);

            MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'transcribed_recording');

            return response()->json(['data' => $this->transform($recording->fresh(), $request->user())]);
        } catch (\Throwable $e) {
            report($e);

            $raw = strtolower((string) $e->getMessage());
            $tooLarge = str_contains($raw, 'too large');
            $quotaRequired = str_contains($raw, 'requires an active subscription or valid extra recording quota');
            $unsupportedAudio = str_contains($raw, 'clipboard')
                || str_contains($raw, 'image input')
                || str_contains($raw, 'could not transcribe')
                || str_contains($raw, 'unsupported audio')
                || str_contains($raw, 'not supported')
                || str_contains($raw, 'invalid audio')
                || str_contains($raw, 'file format')
                || str_contains($raw, 'audio encoding')
                || str_contains($raw, 'whisper');

            if ($tooLarge) {
                $message = self::TRANSCRIPTION_TOO_LARGE_MESSAGE;
                $errorCode = 'recording_too_large';
            } elseif ($quotaRequired) {
                $message = self::TRANSCRIPTION_QUOTA_REQUIRED_MESSAGE;
                $errorCode = 'recording_quota_required';
            } elseif ($unsupportedAudio) {
                $message = self::TRANSCRIPTION_FAILURE_MESSAGE;
                $errorCode = 'unsupported_audio';
            } else {
                $message = self::TRANSCRIPTION_FAILURE_MESSAGE;
                $errorCode = 'transcription_failed';
            }

            $recording->update([
                'transcription_status' => 'failed',
                'transcription_error' => $message,
            ]);

            return response()->json([
                'message' => $message,
                'error_code' => $errorCode,
            ], 422);
        }
    }

    public function updateTranscript(Request $request, MeetingRecording $recording): JsonResponse
    {
        $this->authorizeRecording($request, $recording);

        $data = $request->validate(['transcript' => ['required', 'string']]);
        $recording->update(['transcript' => $data['transcript']]);

        MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'edited_transcript');

        return response()->json(['data' => $this->transform($recording, $request->user())]);
    }

    public function generateSummary(Request $request, MeetingRecording $recording): JsonResponse
    {
        $this->authorizeRecording($request, $recording);

        if (! $recording->transcript) {
            return response()->json(['message' => 'Transcribe the recording first.'], 422);
        }

        $recording->update(['summary_status' => 'processing', 'summary_error' => null]);

        try {
            $summary = app(MeetingSummaryService::class)->generate($request->user(), $recording->transcript);
            $recording->update(['summary' => $summary, 'summary_status' => 'completed']);

            MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'generated_summary');

            return response()->json(['data' => $this->transform($recording->fresh(), $request->user())]);
        } catch (\Throwable $e) {
            $recording->update(['summary_status' => 'failed', 'summary_error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, MeetingRecording $recording): JsonResponse
    {
        $this->authorizeRecording($request, $recording);

        if ($recording->audio_path) {
            Storage::disk('public')->delete($recording->audio_path);
        }

        MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'deleted_recording');
        $recording->delete();

        return response()->json(['message' => 'Recording removed.']);
    }

    public function listSegments(Request $request, MeetingRecording $recording): JsonResponse
    {
        $this->authorizeRecording($request, $recording);

        $segments = $recording->segments()->orderBy('start_seconds')->get();

        return response()->json(['data' => $segments->map(fn ($s) => $this->transformSegment($s))]);
    }

    public function createSegment(Request $request, MeetingRecording $recording): JsonResponse
    {
        $this->authorizeRecording($request, $recording);

        if (! $recording->audio_path) {
            return response()->json(['message' => 'No audio file available to create segment.'], 422);
        }

        $data = $request->validate([
            'start_seconds' => ['required', 'integer', 'min:0'],
            'end_seconds' => ['required', 'integer', 'min:1'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($data['end_seconds'] <= $data['start_seconds']) {
            return response()->json(['message' => 'End time must be greater than start time.'], 422);
        }

        if ($data['end_seconds'] > $recording->duration_seconds) {
            return response()->json(['message' => 'End time cannot exceed recording duration.'], 422);
        }

        $duration = $data['end_seconds'] - $data['start_seconds'];

        if ($duration < 1) {
            return response()->json(['message' => 'Segment must be at least 1 second long.'], 422);
        }

        try {
            $segmentPath = $this->extractAudioSegment(
                $recording->audio_path,
                $data['start_seconds'],
                $duration
            );

            $segment = $recording->segments()->create([
                'created_by_user_id' => $request->user()->id,
                'audio_path' => $segmentPath,
                'start_seconds' => $data['start_seconds'],
                'end_seconds' => $data['end_seconds'],
                'duration_seconds' => $duration,
                'title' => $data['title'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transcription_status' => 'pending',
                'summary_status' => 'pending',
            ]);

            MeetingAuditLog::record(
                $recording->meeting_id,
                $request->user()->id,
                'created_recording_segment',
                "Segment: {$segment->formattedStartTime()} - {$segment->formattedEndTime()}"
            );

            return response()->json(['data' => $this->transformSegment($segment->fresh())]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Failed to create audio segment: ' . $e->getMessage()], 500);
        }
    }

    public function transcribeSegment(Request $request, MeetingRecordingSegment $segment): JsonResponse
    {
        $segment->loadMissing('recording');
        $this->authorizeRecording($request, $segment->recording);

        if (! $segment->audio_path) {
            return response()->json(['message' => 'No audio to transcribe.'], 422);
        }

        $data = $request->validate([
            'transcription_language' => ['nullable', 'in:auto,en-GB,lg,sw'],
        ]);
        $language = $data['transcription_language'] ?? 'auto';

        $segment->update(['transcription_status' => 'processing', 'transcription_error' => null]);

        try {
            $result = app(TranscriptionService::class)->transcribe($request->user(), $segment->audio_path, $language);

            $segment->update([
                'transcript' => $result['transcript'],
                'transcript_segments' => $result['segments'],
                'transcription_status' => 'completed',
            ]);

            MeetingAuditLog::record(
                $segment->recording->meeting_id,
                $request->user()->id,
                'transcribed_recording_segment',
                "Segment #{$segment->id}"
            );

            return response()->json(['data' => $this->transformSegment($segment->fresh())]);
        } catch (\Throwable $e) {
            report($e);

            $errorMessage = self::TRANSCRIPTION_FAILURE_MESSAGE;

            if (str_contains(strtolower((string) $e->getMessage()), 'top up')) {
                $errorMessage = 'The recording is too large. Please top up your recording quota to complete full transcription.';
            }

            $segment->update([
                'transcription_status' => 'failed',
                'transcription_error' => $errorMessage,
            ]);

            return response()->json([
                'message' => $errorMessage,
                'error_code' => 'transcription_failed',
            ], 422);
        }
    }

    public function generateSegmentSummary(Request $request, MeetingRecordingSegment $segment): JsonResponse
    {
        $segment->loadMissing('recording');
        $this->authorizeRecording($request, $segment->recording);

        if (! $segment->transcript) {
            return response()->json(['message' => 'Transcribe the segment first.'], 422);
        }

        $segment->update(['summary_status' => 'processing', 'summary_error' => null]);

        try {
            $summary = app(MeetingSummaryService::class)->generate($request->user(), $segment->transcript);
            $segment->update(['summary' => $summary, 'summary_status' => 'completed']);

            MeetingAuditLog::record(
                $segment->recording->meeting_id,
                $request->user()->id,
                'generated_segment_summary',
                "Segment #{$segment->id}"
            );

            return response()->json(['data' => $this->transformSegment($segment->fresh())]);
        } catch (\Throwable $e) {
            report($e);

            $segment->update(['summary_status' => 'failed', 'summary_error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroySegment(Request $request, MeetingRecordingSegment $segment): JsonResponse
    {
        $segment->loadMissing('recording');
        $this->authorizeRecording($request, $segment->recording);

        if ($segment->audio_path) {
            Storage::disk('public')->delete($segment->audio_path);
        }

        MeetingAuditLog::record(
            $segment->recording->meeting_id,
            $request->user()->id,
            'deleted_recording_segment',
            "Segment #{$segment->id}"
        );

        $segment->delete();

        return response()->json(['message' => 'Segment removed.']);
    }

    private function extractAudioSegment(
        string $sourcePath,
        int $startSeconds,
        int $durationSeconds
    ): string {
        $disk = Storage::disk('public');
        $absoluteSourcePath = $disk->path($sourcePath);

        if (! is_file($absoluteSourcePath)) {
            throw new \RuntimeException('Source recording file not found.');
        }

        $temporaryDirectory = storage_path('app/transcription-temp');

        if (! is_dir($temporaryDirectory)) {
            if (! mkdir($temporaryDirectory, 0755, true) && ! is_dir($temporaryDirectory)) {
                throw new \RuntimeException('Unable to create temporary directory.');
            }
        }

        $destinationPath = $temporaryDirectory . DIRECTORY_SEPARATOR . \Illuminate\Support\Str::uuid() . '.mp3';

        try {
            $ffmpeg = FFMpeg::create();
            $video = $ffmpeg->open($absoluteSourcePath);
            $format = new Mp3();
            $format->setAudioKiloBitrate(64);
            $format->setAdditionalParameters([
                '-ss' => (string) $startSeconds,
                '-t' => (string) $durationSeconds,
                '-vn' => null,
                '-ac' => '1',
                '-ar' => '16000',
            ]);
            $video->save($format, $destinationPath);
        } catch (\Throwable $e) {
            @unlink($destinationPath);
            \Illuminate\Support\Facades\Log::warning('Audio segment extraction failed.', [
                'source' => $sourcePath,
                'start' => $startSeconds,
                'duration' => $durationSeconds,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to extract audio segment: ' . $e->getMessage());
        }

        if (! is_file($destinationPath) || filesize($destinationPath) < 1024) {
            @unlink($destinationPath);
            throw new \RuntimeException('Failed to extract audio segment. The source audio may be incomplete or corrupted.');
        }

        $storedPath = $disk->putFileAs(
            'meeting-recording-segments',
            new \Illuminate\Http\File($destinationPath),
            basename($destinationPath),
            'public'
        );

        @unlink($destinationPath);

        return $storedPath;
    }

    private function transformSegment(MeetingRecordingSegment $segment): array
    {
        return [
            'id' => $segment->id,
            'meeting_recording_id' => $segment->meeting_recording_id,
            'audio_url' => $segment->audioUrl(),
            'start_seconds' => $segment->start_seconds,
            'end_seconds' => $segment->end_seconds,
            'duration_seconds' => $segment->duration_seconds,
            'formatted_duration' => $segment->formattedDuration(),
            'formatted_start' => $segment->formattedStartTime(),
            'formatted_end' => $segment->formattedEndTime(),
            'title' => $segment->title,
            'notes' => $segment->notes,
            'transcript' => $segment->transcript,
            'transcript_segments' => $segment->transcript_segments,
            'transcription_status' => $segment->transcription_status,
            'transcription_error' => $segment->transcription_error,
            'summary' => $segment->summary,
            'summary_status' => $segment->summary_status,
            'summary_error' => $segment->summary_error,
            'created_at' => $segment->created_at->toIso8601String(),
        ];
    }

    /** Store uploaded media with a real extension. Laravel's default store()
     * can use .bin when Android sends application/octet-stream, and OpenAI
     * rejects an otherwise valid recording because the multipart filename has
     * an unsupported extension. */
    private function storeRecordingFile(\Illuminate\Http\UploadedFile $file): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension === '') {
            $extension = strtolower((string) $file->extension());
        }
        if ($extension === '') {
            $extension = 'webm';
        }

        $safeExtension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: 'webm';
        $name = (string) \Illuminate\Support\Str::uuid() . '.' . $safeExtension;

        return $file->storeAs('meeting-recordings', $name, 'public');
    }

    private function validateRecordingFile(\Illuminate\Http\UploadedFile $file, int $capacityBytes, bool $hasTopUp = false): void
    {
        if (! $hasTopUp && ($file->getSize() ?? 0) > $capacityBytes) {
            abort(422, 'The recording exceeds your transcription capacity.');
        }
        if (($file->getSize() ?? 0) < 1024) {
            abort(422, 'The recording is empty or too short. Record at least a few seconds and try again.');
        }

        $mime = strtolower((string) $file->getMimeType());
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $recordingExtensions = [
            'mp3','wav','wave','m4a','mp4','m4v','webm','weba','ogg','oga','opus',
            'aac','flac','wma','amr','awb','3gp','3gpp','3g2','mov','mkv','mka','avi',
            'mpeg','mpg','mpga','aiff','aif','aifc','caf','ac3','eac3','wmv','ts','mts',
            'm2ts','au','snd','ra','ram','rm'
        ];

        if (! str_starts_with($mime, 'audio/')
            && ! str_starts_with($mime, 'video/')
            && ! in_array($extension, $recordingExtensions, true)) {
            abort(422, 'The selected file is not a recognised audio or video recording.');
        }
    }

    /** Size in bytes of the stored audio, 0 when there is no upload yet. */
    private function recordingFileSizeBytes(MeetingRecording $recording): int
    {
        if (! $recording->audio_path) {
            return 0;
        }

        $path = Storage::disk('public')->path($recording->audio_path);
        $size = is_file($path) ? filesize($path) : false;

        return $size === false ? 0 : (int) $size;
    }

    private function transform(MeetingRecording $recording, User $user): array
    {
        $fileSizeBytes = $this->recordingFileSizeBytes($recording);
        $transcriptionLimitMb = $user->effectiveTranscriptionCapacityMb();
        $hasActiveAccess = (bool) $user->hasActiveAccess();
        $extraMinutes = $user->availableExtraRecordingQuotaMinutes();
        $canTranscribe = $hasActiveAccess || $extraMinutes > 0;

        return [
            'id' => $recording->id,
            'meeting_id' => $recording->meeting_id,
            'status' => $recording->status,
            'duration_seconds' => $recording->duration_seconds,
            'formatted_duration' => $recording->formattedDuration(),
            'audio_url' => $recording->audioUrl(),
            'file_size_bytes' => $fileSizeBytes,
            'file_size_mb' => round($fileSizeBytes / (1024 * 1024), 1),
            'transcription_limit_mb' => $transcriptionLimitMb,
            'is_over_upload_limit' => $fileSizeBytes > ($transcriptionLimitMb * 1024 * 1024),
            'transcript' => $recording->transcript,
            'transcript_segments' => $recording->transcript_segments,
            'transcription_status' => $recording->transcription_status,
            'transcription_error' => $recording->transcription_status === 'failed'
                ? self::TRANSCRIPTION_FAILURE_MESSAGE
                : $recording->transcription_error,
            'summary' => $recording->summary,
            'summary_status' => $recording->summary_status,
            'summary_error' => $recording->summary_error,
            'has_active_access' => $hasActiveAccess,
            'extra_recording_minutes_remaining' => $extraMinutes,
            'extra_quota_expires_at' => $user->extra_quota_expires_at?->toIso8601String(),
            'can_transcribe' => $canTranscribe,
            'created_at' => $recording->created_at->toIso8601String(),
        ];
    }
}
