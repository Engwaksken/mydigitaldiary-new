<?php

namespace App\Http\Controllers;

use App\Mail\MeetingSummaryMail;
use App\Models\Meeting;
use App\Models\MeetingAuditLog;
use App\Models\MeetingRecording;
use App\Services\Ai\AiCredentialResolver;
use App\Services\MeetingSummaryService;
use App\Services\TranscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use App\Models\MeetingRecordingSegment;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class MeetingRecordingController extends Controller
{
    private const TRANSCRIPTION_FAILURE_MESSAGE =
        'We could not transcribe this recording. Please try again with a supported audio file under 30 MB.';

    private function authorizeMeeting(Request $request, Meeting $meeting): void
    {
        abort_unless(
            (int) $meeting->user_id === (int) $request->user()->id
            || $request->user()->isAdmin(),
            403
        );
    }

    private function authorizeRecording(
        Request $request,
        MeetingRecording $recording
    ): void {
        $recording->loadMissing('meeting');

        $this->authorizeMeeting(
            $request,
            $recording->meeting
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Start recording session
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request,
        Meeting $meeting
    ): JsonResponse {
        $this->authorizeMeeting(
            $request,
            $meeting
        );

        $request->validate([
            'consent' => [
                'required',
                'accepted',
            ],
        ]);

        $recording =
            $meeting
                ->recordings()
                ->create([
                    'recorded_by_user_id' =>
                        $request->user()->id,

                    'status' =>
                        'recording',

                    'consent_given_at' =>
                        now(),
                ]);

        MeetingAuditLog::record(
            $meeting->id,
            $request->user()->id,
            'started_recording'
        );

        return response()->json([
            'ok' => true,
            'recording_id' => $recording->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Upload existing recording
    |--------------------------------------------------------------------------
    */

    public function upload(
        Request $request,
        Meeting $meeting
    ): RedirectResponse {
        $this->authorizeMeeting(
            $request,
            $meeting
        );

        $data =
            $request->validate([
                'audio' => [
                    'required',
                    'file',
                    'max:204800',
                ],

                'duration_seconds' => [
                    'nullable',
                    'integer',
                    'min:0',
                ],
            ]);

        /** @var UploadedFile $audioFile */
        $audioFile =
            $request->file('audio');

        $this->validateRecordingFile(
            $audioFile
        );

        $audioPath =
            $this->storeRecordingFile(
                $audioFile
            );

        $recording =
            $meeting
                ->recordings()
                ->create([
                    'recorded_by_user_id' =>
                        $request->user()->id,

                    'status' =>
                        'completed',

                    'consent_given_at' =>
                        now(),

                    'audio_path' =>
                        $audioPath,

                    'duration_seconds' =>
                        (int) (
                            $data['duration_seconds']
                            ?? 0
                        ),
                ]);

        MeetingAuditLog::record(
            $meeting->id,
            $request->user()->id,
            'uploaded_recording',
            $audioFile->getClientOriginalName()
        );

        return back()->with(
            'success',
            'Recording uploaded successfully. You can now transcribe it and generate an AI summary.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Pause / resume
    |--------------------------------------------------------------------------
    */

    public function updateStatus(
        Request $request,
        MeetingRecording $recording
    ): JsonResponse {
        $this->authorizeRecording(
            $request,
            $recording
        );

        $data =
            $request->validate([
                'status' => [
                    'required',
                    'in:recording,paused',
                ],

                'duration_seconds' => [
                    'required',
                    'integer',
                    'min:0',
                ],
            ]);

        $recording->update(
            $data
        );

        MeetingAuditLog::record(
            $recording->meeting_id,
            $request->user()->id,
            $data['status'] === 'paused'
                ? 'paused_recording'
                : 'resumed_recording'
        );

        return response()->json([
            'ok' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Stop browser recording
    |--------------------------------------------------------------------------
    */

    public function stop(
        Request $request,
        MeetingRecording $recording
    ): JsonResponse {
        $this->authorizeRecording(
            $request,
            $recording
        );

        $request->validate([
            'recording' => [
                'nullable',
                'file',
                'max:204800',
            ],

            'audio' => [
                'nullable',
                'file',
                'max:204800',
            ],

            'duration_seconds' => [
                'required',
                'integer',
                'min:0',
            ],

            'capture_type' => [
                'nullable',
                'in:microphone,mixed',
            ],

            'meeting_audio_captured' => [
                'nullable',
                'boolean',
            ],

            'microphone_audio_captured' => [
                'nullable',
                'boolean',
            ],
        ]);

        /** @var UploadedFile|null $audioFile */
        $audioFile =
            $request->file('recording')
            ?: $request->file('audio');

        if (! $audioFile) {
            return response()->json([
                'message' =>
                    'No recording file was received.',
            ], 422);
        }

        if (
            $request->input('capture_type')
            === 'mixed'
        ) {
            if (
                ! $request->boolean(
                    'meeting_audio_captured'
                )
            ) {
                return response()->json([
                    'message' =>
                        'Meeting audio was not captured. Select the meeting tab and enable Share tab audio.',
                ], 422);
            }

            if (
                ! $request->boolean(
                    'microphone_audio_captured'
                )
            ) {
                return response()->json([
                    'message' =>
                        'Microphone audio was not captured.',
                ], 422);
            }
        }

        $this->validateRecordingFile(
            $audioFile
        );

        $audioPath =
            $this->storeRecordingFile(
                $audioFile
            );

        if (
            $recording->audio_path
            && Storage::disk('public')
                ->exists(
                    $recording->audio_path
                )
        ) {
            Storage::disk('public')
                ->delete(
                    $recording->audio_path
                );
        }

        $recording->update([
            'status' =>
                'completed',

            'audio_path' =>
                $audioPath,

            'duration_seconds' =>
                (int) $request->input(
                    'duration_seconds',
                    0
                ),
        ]);

        MeetingAuditLog::record(
            $recording->meeting_id,
            $request->user()->id,
            'stopped_recording',
            'Duration: '
            . $recording->formattedDuration()
        );

        return response()->json([
            'ok' => true,
            'recording_id' => $recording->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Transcription Capacity Check
    |--------------------------------------------------------------------------
    */

    public function checkTranscriptionCapacity(
        Request $request,
        MeetingRecording $recording
    ): JsonResponse {
        $this->authorizeRecording($request, $recording);

        if (! $recording->audio_path) {
            return response()->json([
                'can_transcribe' => false,
                'reason' => 'no_audio',
                'message' => 'No audio file to transcribe.',
            ]);
        }

        $user = $request->user();
        $hasActive = $user->hasActiveAccess();
        $extraMinutes = (int) ($user->extra_recording_quota_minutes ?? 0);
        $extraExpires = $user->extra_quota_expires_at;
        $hasExtraQuota = $extraMinutes > 0 && (is_null($extraExpires) || $extraExpires->isFuture());
        $canTranscribe = $hasActive || $hasExtraQuota;

        $disk = Storage::disk('public');
        $fileSizeBytes = 0;
        if ($recording->audio_path && $disk->exists($recording->audio_path)) {
            $fileSizeBytes = $disk->size($recording->audio_path) ?? 0;
        }
        $fileSizeMb = round($fileSizeBytes / (1024 * 1024), 1);

        $resolvedKey = app(AiCredentialResolver::class)->resolveForUser($user, 'openai');
        $apiKey = is_array($resolvedKey) ? (string) ($resolvedKey['api_key'] ?? '') : '';
        $openAiConfigured = $apiKey !== '';

        if (! $openAiConfigured) {
            return response()->json([
                'can_transcribe' => false,
                'reason' => 'openai_not_configured',
                'message' => 'OpenAI transcription is not available. Please ask the administrator to configure OpenAI under AI Settings.',
                'has_active_access' => $hasActive,
                'extra_minutes_remaining' => $extraMinutes,
                'file_size_mb' => $fileSizeMb,
            ]);
        }

        if (! $canTranscribe) {
            return response()->json([
                'can_transcribe' => false,
                'reason' => 'quota_exceeded',
                'message' => 'Transcription requires an active subscription or extra recording quota minutes.',
                'has_active_access' => $hasActive,
                'extra_minutes_remaining' => $extraMinutes,
                'file_size_mb' => $fileSizeMb,
            ]);
        }

        if ($fileSizeBytes > 30 * 1024 * 1024 && $extraMinutes === 0) {
            return response()->json([
                'can_transcribe' => false,
                'reason' => 'file_too_large',
                'message' => "The recording is {$fileSizeMb} MB, which exceeds the 30 MB limit. Please top up your recording quota to transcribe larger files.",
                'has_active_access' => $hasActive,
                'extra_minutes_remaining' => $extraMinutes,
                'file_size_mb' => $fileSizeMb,
            ]);
        }

        return response()->json([
            'can_transcribe' => true,
            'has_active_access' => $hasActive,
            'extra_minutes_remaining' => $extraMinutes,
            'extra_quota_expires_at' => $extraExpires?->toIso8601String(),
            'file_size_mb' => $fileSizeMb,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Transcription
    |--------------------------------------------------------------------------
    */

    public function transcribe(
        Request $request,
        MeetingRecording $recording
    ): RedirectResponse {
        $this->authorizeRecording(
            $request,
            $recording
        );

        if (! $recording->audio_path) {
            return back()->withErrors([
                'transcription' =>
                    'No audio to transcribe yet.',
            ]);
        }

        $data =
            $request->validate([
                'transcription_language' => [
                    'nullable',
                    'in:auto,en-GB,lg,sw',
                ],
            ]);

        $language =
            $data['transcription_language']
            ?? 'auto';

        $recording->update([
            'transcription_status' =>
                'processing',

            'transcription_error' =>
                null,
        ]);

        try {
            $result =
                app(
                    TranscriptionService::class
                )->transcribe(
                    $request->user(),
                    $recording->audio_path,
                    $language
                );

            $recording->update([
                'transcript' =>
                    $result['transcript'],

                'transcript_segments' =>
                    $result['segments'],

                'transcription_status' =>
                    'completed',
            ]);

            MeetingAuditLog::record(
                $recording->meeting_id,
                $request->user()->id,
                'transcribed_recording'
            );

            return back()->with(
                'success',
                'Transcript ready using '
                . $this->transcriptionLanguageLabel(
                    $language
                )
                . '.'
            );
        } catch (\Throwable $e) {
            report($e);

            $errorMessage = self::TRANSCRIPTION_FAILURE_MESSAGE;

            if (
                str_contains(
                    strtolower(
                        (string) $e->getMessage()
                    ),
                    'top up'
                )
            ) {
                $errorMessage =
                    'The recording is too large. Please top up your recording quota to complete full transcription.';
            }

            $recording->update([
                'transcription_status' =>
                    'failed',

                'transcription_error' =>
                    $errorMessage,
            ]);

            return back()->withErrors([
                'transcription' =>
                    $errorMessage,
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update transcript
    |--------------------------------------------------------------------------
    */

    public function updateTranscript(
        Request $request,
        MeetingRecording $recording
    ): RedirectResponse {
        $this->authorizeRecording(
            $request,
            $recording
        );

        $data =
            $request->validate([
                'transcript' => [
                    'required',
                    'string',
                ],
            ]);

        $recording->update([
            'transcript' =>
                $data['transcript'],
        ]);

        MeetingAuditLog::record(
            $recording->meeting_id,
            $request->user()->id,
            'edited_transcript'
        );

        return back()->with(
            'success',
            'Transcript updated.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Generate AI summary
    |--------------------------------------------------------------------------
    */

    public function generateSummary(
        Request $request,
        MeetingRecording $recording
    ): RedirectResponse {
        $this->authorizeRecording(
            $request,
            $recording
        );

        if (! $recording->transcript) {
            return back()->withErrors([
                'summary' =>
                    'Transcribe the recording first.',
            ]);
        }

        $recording->update([
            'summary_status' =>
                'processing',

            'summary_error' =>
                null,
        ]);

        try {
            $summary =
                app(
                    MeetingSummaryService::class
                )->generate(
                    $request->user(),
                    $recording->transcript
                );

            $recording->update([
                'summary' =>
                    $summary,

                'summary_status' =>
                    'completed',
            ]);

            MeetingAuditLog::record(
                $recording->meeting_id,
                $request->user()->id,
                'generated_summary'
            );

            return back()->with(
                'success',
                'AI summary ready.'
            );
        } catch (\Throwable $e) {
            report($e);

            $recording->update([
                'summary_status' =>
                    'failed',

                'summary_error' =>
                    $e->getMessage(),
            ]);

            return back()->withErrors([
                'summary' =>
                    $e->getMessage(),
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Transcribe and summarise
    |--------------------------------------------------------------------------
    */

    public function transcribeAndSummarize(
        Request $request,
        MeetingRecording $recording
    ): RedirectResponse {
        $this->authorizeRecording(
            $request,
            $recording
        );

        if (
            ! $recording->audio_path
            && ! $recording->transcript
        ) {
            return back()->withErrors([
                'processing' =>
                    'Upload or record meeting audio first.',
            ]);
        }

        $data =
            $request->validate([
                'transcription_language' => [
                    'nullable',
                    'in:auto,en-GB,lg,sw',
                ],
            ]);

        $language =
            $data['transcription_language']
            ?? 'auto';

        try {
            if (! $recording->transcript) {
                $recording->update([
                    'transcription_status' =>
                        'processing',

                    'transcription_error' =>
                        null,
                ]);

                $result =
                    app(
                        TranscriptionService::class
                    )->transcribe(
                        $request->user(),
                        $recording->audio_path,
                        $language
                    );

                $recording->update([
                    'transcript' =>
                        $result['transcript'],

                    'transcript_segments' =>
                        $result['segments'],

                    'transcription_status' =>
                        'completed',
                ]);

                MeetingAuditLog::record(
                    $recording->meeting_id,
                    $request->user()->id,
                    'transcribed_recording'
                );
            }

            $recording->update([
                'summary_status' =>
                    'processing',

                'summary_error' =>
                    null,
            ]);

            $summary =
                app(
                    MeetingSummaryService::class
                )->generate(
                    $request->user(),
                    $recording
                        ->fresh()
                        ->transcript
                );

            $recording->update([
                'summary' =>
                    $summary,

                'summary_status' =>
                    'completed',
            ]);

            MeetingAuditLog::record(
                $recording->meeting_id,
                $request->user()->id,
                'generated_summary'
            );

            return back()->with(
                'success',
                'Transcript and AI summary are ready.'
            );
        } catch (\Throwable $e) {
            report($e);

            if (
                $recording->transcription_status
                === 'processing'
            ) {
                $recording->update([
                    'transcription_status' =>
                        'failed',

                    'transcription_error' =>
                        self::TRANSCRIPTION_FAILURE_MESSAGE,
                ]);
                $message = self::TRANSCRIPTION_FAILURE_MESSAGE;
            } else {
                $recording->update([
                    'summary_status' =>
                        'failed',

                    'summary_error' =>
                        $e->getMessage(),
                ]);
                $message = $e->getMessage();
            }

            return back()->withErrors([
                'processing' =>
                    $message,
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Stream audio
    |--------------------------------------------------------------------------
    */

    public function streamAudio(
        Request $request,
        MeetingRecording $recording
    ) {
        $this->authorizeRecording(
            $request,
            $recording
        );

        abort_unless(
            $recording->audio_path,
            404
        );

        $disk =
            Storage::disk('public');

        abort_unless(
            $disk->exists(
                $recording->audio_path
            ),
            404,
            'Recording file not found.'
        );

        $absolutePath =
            $disk->path(
                $recording->audio_path
            );

        $extension =
            strtolower(
                pathinfo(
                    $recording->audio_path,
                    PATHINFO_EXTENSION
                )
            );

        try {
            $mime =
                $disk->mimeType(
                    $recording->audio_path
                );
        } catch (\Throwable $e) {
            $mime =
                null;
        }

        if (
            ! is_string($mime)
            || $mime === ''
            || $mime === 'application/octet-stream'
        ) {
            $mime =
                match ($extension) {
                    'mp3', 'mpga' =>
                        'audio/mpeg',

                    'wav', 'wave' =>
                        'audio/wav',

                    'm4a' =>
                        'audio/mp4',

                    'mp4', 'm4v' =>
                        'video/mp4',

                    'webm', 'weba' =>
                        'audio/webm',

                    'ogg', 'oga' =>
                        'audio/ogg',

                    'opus' =>
                        'audio/ogg; codecs=opus',

                    'aac' =>
                        'audio/aac',

                    'flac' =>
                        'audio/flac',

                    'mov' =>
                        'video/quicktime',

                    'mpeg', 'mpg' =>
                        'video/mpeg',

                    default =>
                        'application/octet-stream',
                };
        }

        return response()->file(
            $absolutePath,
            [
                'Content-Type' =>
                    $mime,

                'Content-Disposition' =>
                    'inline; filename="meeting-recording-'
                    . $recording->id
                    . '.'
                    . (
                        $extension
                        ?: 'audio'
                    )
                    . '"',

                'Accept-Ranges' =>
                    'bytes',

                'Cache-Control' =>
                    'private, no-store, max-age=0',

                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Download audio
    |--------------------------------------------------------------------------
    */

    public function downloadAudio(
        Request $request,
        MeetingRecording $recording
    ) {
        $this->authorizeRecording(
            $request,
            $recording
        );

        abort_unless(
            $recording->audio_path,
            404
        );

        MeetingAuditLog::record(
            $recording->meeting_id,
            $request->user()->id,
            'downloaded_audio'
        );

        $extension =
            pathinfo(
                $recording->audio_path,
                PATHINFO_EXTENSION
            ) ?: 'bin';

        return Storage::disk('public')
            ->download(
                $recording->audio_path,
                'meeting-recording-'
                . $recording->id
                . '.'
                . $extension
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Download transcript
    |--------------------------------------------------------------------------
    */

    public function downloadTranscript(
        Request $request,
        MeetingRecording $recording
    ) {
        $this->authorizeRecording(
            $request,
            $recording
        );

        abort_unless(
            $recording->transcript,
            404
        );

        MeetingAuditLog::record(
            $recording->meeting_id,
            $request->user()->id,
            'downloaded_transcript'
        );

        $segments =
            collect(
                $recording->transcript_segments
                ?? []
            );

        $lines =
            $segments->map(
                function ($segment) {
                    $seconds =
                        (int) (
                            $segment['start_seconds']
                            ?? 0
                        );

                    $timestamp =
                        sprintf(
                            '[%02d:%02d]',
                            intdiv(
                                $seconds,
                                60
                            ),
                            $seconds % 60
                        );

                    $speaker =
                        ! empty(
                            $segment['speaker']
                        )
                            ? $segment['speaker']
                                . ': '
                            : '';

                    return $timestamp
                        . ' '
                        . $speaker
                        . (
                            $segment['text']
                            ?? ''
                        );
                }
            );

        $content =
            $lines->isNotEmpty()
                ? $lines->implode("\n")
                : $recording->transcript;

        return response(
            $content,
            200,
            [
                'Content-Type' =>
                    'text/plain; charset=UTF-8',

                'Content-Disposition' =>
                    'attachment; filename="meeting-transcript-'
                    . $recording->id
                    . '.txt"',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Download summary
    |--------------------------------------------------------------------------
    */

    public function downloadSummary(
        Request $request,
        MeetingRecording $recording
    ) {
        $this->authorizeRecording(
            $request,
            $recording
        );

        abort_unless(
            $recording->summary,
            404
        );

        MeetingAuditLog::record(
            $recording->meeting_id,
            $request->user()->id,
            'downloaded_summary'
        );

        return response(
            $this->formatSummaryAsText(
                $recording
            ),
            200,
            [
                'Content-Type' =>
                    'text/plain; charset=UTF-8',

                'Content-Disposition' =>
                    'attachment; filename="meeting-summary-'
                    . $recording->id
                    . '.txt"',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Email notes / transcript / summary
    |--------------------------------------------------------------------------
    */

    public function emailSummary(
        Request $request,
        MeetingRecording $recording
    ): RedirectResponse {
        $this->authorizeRecording(
            $request,
            $recording
        );

        $recording->loadMissing(
            'meeting'
        );

        $data =
            $request->validate([
                'emails' => [
                    'required',
                    'string',
                    'max:5000',
                ],

                'include_notes' => [
                    'nullable',
                    'boolean',
                ],

                'include_transcript' => [
                    'nullable',
                    'boolean',
                ],

                'include_summary' => [
                    'nullable',
                    'boolean',
                ],

                'message' => [
                    'nullable',
                    'string',
                    'max:3000',
                ],
            ]);

        $addresses =
            collect(
                preg_split(
                    '/[,;\n]+/',
                    $data['emails']
                )
            )
                ->map(
                    fn ($email) =>
                        trim(
                            (string) $email
                        )
                )
                ->filter()
                ->unique()
                ->values();

        $invalidAddresses =
            $addresses->filter(
                fn ($email) =>
                    ! filter_var(
                        $email,
                        FILTER_VALIDATE_EMAIL
                    )
            );

        if (
            $invalidAddresses
                ->isNotEmpty()
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'emails' =>
                        'Invalid email address: '
                        . $invalidAddresses
                            ->implode(', '),
                ]);
        }

        if (
            $addresses
                ->isEmpty()
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'emails' =>
                        'Enter at least one valid recipient email address.',
                ]);
        }

        $includeNotes =
            $request->boolean(
                'include_notes'
            );

        $includeTranscript =
            $request->boolean(
                'include_transcript'
            );

        $includeSummary =
            $request->boolean(
                'include_summary'
            );

        if (
            ! $includeNotes
            && ! $includeTranscript
            && ! $includeSummary
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'share' =>
                        'Select at least one item to share.',
                ]);
        }

        if (
            $includeTranscript
            && ! filled(
                $recording->transcript
            )
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'share' =>
                        'A transcript has not been generated for this recording yet.',
                ]);
        }

        if (
            $includeSummary
            && empty(
                $recording->summary
            )
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'share' =>
                        'An AI summary has not been generated for this recording yet.',
                ]);
        }

        foreach (
            $addresses as $address
        ) {
            Mail::to(
                $address
            )->send(
                new MeetingSummaryMail(
                    recording:
                        $recording,

                    senderName:
                        $request->user()
                            ->name
                        ?? 'Meeting organiser',

                    includeNotes:
                        $includeNotes,

                    includeTranscript:
                        $includeTranscript,

                    includeSummary:
                        $includeSummary,

                    personalMessage:
                        $data['message']
                        ?? null
                )
            );
        }

        MeetingAuditLog::record(
            $recording->meeting_id,
            $request->user()->id,
            'shared_meeting_information',
            json_encode([
                'recording_id' =>
                    $recording->id,

                'recipients' =>
                    $addresses->all(),

                'included' => [
                    'notes' =>
                        $includeNotes,

                    'transcript' =>
                        $includeTranscript,

                    'summary' =>
                        $includeSummary,
                ],
            ])
        );

        return back()->with(
            'success',
            'Meeting information emailed successfully to '
            . $addresses->count()
            . (
                $addresses->count() === 1
                    ? ' recipient.'
                    : ' recipients.'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        MeetingRecording $recording
    ): RedirectResponse {
        $this->authorizeRecording(
            $request,
            $recording
        );

        if (
            $recording->audio_path
        ) {
            Storage::disk('public')
                ->delete(
                    $recording->audio_path
                );
        }

        MeetingAuditLog::record(
            $recording->meeting_id,
            $request->user()->id,
            'deleted_recording'
        );

        $recording->delete();

        return back()->with(
            'success',
            'Recording removed.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Audio Segments (Cut/Trim)
    |--------------------------------------------------------------------------
    */

    public function createSegment(
        Request $request,
        MeetingRecording $recording
    ): JsonResponse {
        $this->authorizeRecording(
            $request,
            $recording
        );

        if (! $recording->audio_path) {
            return response()->json([
                'message' => 'No audio file available to create segment.',
            ], 422);
        }

        $data = $request->validate([
            'start_seconds' => ['required', 'integer', 'min:0'],
            'end_seconds' => ['required', 'integer', 'min:1'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($data['end_seconds'] <= $data['start_seconds']) {
            return response()->json([
                'message' => 'End time must be greater than start time.',
            ], 422);
        }

        if ($data['end_seconds'] > $recording->duration_seconds) {
            return response()->json([
                'message' => 'End time cannot exceed recording duration.',
            ], 422);
        }

        $duration = $data['end_seconds'] - $data['start_seconds'];

        if ($duration < 1) {
            return response()->json([
                'message' => 'Segment must be at least 1 second long.',
            ], 422);
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

            return response()->json([
                'ok' => true,
                'segment' => [
                    'id' => $segment->id,
                    'audio_path' => $segment->audio_path,
                    'audio_url' => $segment->audioUrl(),
                    'start_seconds' => $segment->start_seconds,
                    'end_seconds' => $segment->end_seconds,
                    'duration_seconds' => $segment->duration_seconds,
                    'formatted_duration' => $segment->formattedDuration(),
                    'formatted_start' => $segment->formattedStartTime(),
                    'formatted_end' => $segment->formattedEndTime(),
                    'title' => $segment->title,
                    'notes' => $segment->notes,
                    'transcription_status' => $segment->transcription_status,
                    'summary_status' => $segment->summary_status,
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Failed to create audio segment: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function listSegments(
        Request $request,
        MeetingRecording $recording
    ): JsonResponse {
        $this->authorizeRecording(
            $request,
            $recording
        );

        $segments = $recording->segments()
            ->orderBy('start_seconds')
            ->get()
            ->map(function ($segment) {
                return [
                    'id' => $segment->id,
                    'audio_path' => $segment->audio_path,
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
                    'transcription_status' => $segment->transcription_status,
                    'transcription_error' => $segment->transcription_error,
                    'summary' => $segment->summary,
                    'summary_status' => $segment->summary_status,
                    'summary_error' => $segment->summary_error,
                ];
            });

        return response()->json([
            'ok' => true,
            'segments' => $segments,
        ]);
    }

    public function transcribeSegment(
        Request $request,
        MeetingRecordingSegment $segment
    ): RedirectResponse {
        $segment->loadMissing('recording');
        $this->authorizeRecording($request, $segment->recording);

        if (! $segment->audio_path) {
            return back()->withErrors([
                'transcription' => 'No audio to transcribe.',
            ]);
        }

        $data = $request->validate([
            'transcription_language' => ['nullable', 'in:auto,en-GB,lg,sw'],
        ]);

        $language = $data['transcription_language'] ?? 'auto';

        $segment->update([
            'transcription_status' => 'processing',
            'transcription_error' => null,
        ]);

        try {
            $result = app(TranscriptionService::class)->transcribe(
                $request->user(),
                $segment->audio_path,
                $language
            );

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

            return back()->with(
                'success',
                'Segment transcript ready using ' . $this->transcriptionLanguageLabel($language) . '.'
            );
        } catch (\Throwable $e) {
            report($e);

            $errorMessage = self::TRANSCRIPTION_FAILURE_MESSAGE;

            if (
                str_contains(
                    strtolower((string) $e->getMessage()),
                    'top up'
                )
            ) {
                $errorMessage = 'The recording is too large. Please top up your recording quota to complete full transcription.';
            }

            $segment->update([
                'transcription_status' => 'failed',
                'transcription_error' => $errorMessage,
            ]);

            return back()->withErrors([
                'transcription' => $errorMessage,
            ]);
        }
    }

    public function generateSegmentSummary(
        Request $request,
        MeetingRecordingSegment $segment
    ): RedirectResponse {
        $segment->loadMissing('recording');
        $this->authorizeRecording($request, $segment->recording);

        if (! $segment->transcript) {
            return back()->withErrors([
                'summary' => 'Transcribe the segment first.',
            ]);
        }

        $segment->update([
            'summary_status' => 'processing',
            'summary_error' => null,
        ]);

        try {
            $summary = app(MeetingSummaryService::class)->generate(
                $request->user(),
                $segment->transcript
            );

            $segment->update([
                'summary' => $summary,
                'summary_status' => 'completed',
            ]);

            MeetingAuditLog::record(
                $segment->recording->meeting_id,
                $request->user()->id,
                'generated_segment_summary',
                "Segment #{$segment->id}"
            );

            return back()->with('success', 'AI summary ready for segment.');
        } catch (\Throwable $e) {
            report($e);

            $segment->update([
                'summary_status' => 'failed',
                'summary_error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'summary' => $e->getMessage(),
            ]);
        }
    }

    public function updateSegment(
        Request $request,
        MeetingRecordingSegment $segment
    ): RedirectResponse {
        $segment->loadMissing('recording');
        $this->authorizeRecording($request, $segment->recording);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $segment->update($data);

        MeetingAuditLog::record(
            $segment->recording->meeting_id,
            $request->user()->id,
            'updated_recording_segment',
            "Segment #{$segment->id}"
        );

        return back()->with('success', 'Segment updated.');
    }

    public function destroySegment(
        Request $request,
        MeetingRecordingSegment $segment
    ): RedirectResponse {
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

        return back()->with('success', 'Segment removed.');
    }

    public function streamSegmentAudio(
        Request $request,
        MeetingRecordingSegment $segment
    ) {
        $segment->loadMissing('recording');
        $this->authorizeRecording($request, $segment->recording);

        abort_unless(
            $segment->audio_path,
            404
        );

        $disk = Storage::disk('public');

        abort_unless(
            $disk->exists($segment->audio_path),
            404,
            'Segment audio file not found.'
        );

        $absolutePath = $disk->path($segment->audio_path);

        $extension = strtolower(pathinfo($segment->audio_path, PATHINFO_EXTENSION));

        try {
            $mime = $disk->mimeType($segment->audio_path);
        } catch (\Throwable $e) {
            $mime = null;
        }

        if (! is_string($mime) || $mime === '' || $mime === 'application/octet-stream') {
            $mime = match ($extension) {
                'mp3', 'mpga' => 'audio/mpeg',
                'wav', 'wave' => 'audio/wav',
                'm4a' => 'audio/mp4',
                'mp4', 'm4v' => 'video/mp4',
                'webm', 'weba' => 'audio/webm',
                'ogg', 'oga' => 'audio/ogg',
                'opus' => 'audio/ogg; codecs=opus',
                'aac' => 'audio/aac',
                'flac' => 'audio/flac',
                'mov' => 'video/quicktime',
                'mpeg', 'mpg' => 'video/mpeg',
                default => 'application/octet-stream',
            };
        }

        return response()->file(
            $absolutePath,
            [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="meeting-segment-' . $segment->id . '.' . ($extension ?: 'audio') . '"',
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function downloadSegmentAudio(
        Request $request,
        MeetingRecordingSegment $segment
    ) {
        $segment->loadMissing('recording');
        $this->authorizeRecording($request, $segment->recording);

        abort_unless($segment->audio_path, 404);

        MeetingAuditLog::record(
            $segment->recording->meeting_id,
            $request->user()->id,
            'downloaded_segment_audio'
        );

        $extension = pathinfo($segment->audio_path, PATHINFO_EXTENSION) ?: 'bin';

        return Storage::disk('public')->download(
            $segment->audio_path,
            'meeting-segment-' . $segment->id . '.' . $extension
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function transcriptionLanguageLabel(
        string $language
    ): string {
        return match ($language) {
            'en-GB' =>
                'UK English',

            'lg' =>
                'Luganda',

            'sw' =>
                'Kiswahili',

            default =>
                'automatic language detection',
        };
    }

    private function formatSummaryAsText(
        MeetingRecording $recording
    ): string {
        $summary =
            (array) (
                $recording->summary
                ?? []
            );

        $lines = [
            'MEETING SUMMARY',
            '',
        ];

        $lines[] =
            'Main Discussion Points:';

        foreach (
            $summary['main_points']
            ?? [] as $point
        ) {
            $lines[] =
                '- ' . $point;
        }

        $lines[] = '';

        $lines[] =
            'Decisions Made:';

        foreach (
            $summary['decisions']
            ?? [] as $decision
        ) {
            $lines[] =
                '- ' . $decision;
        }

        $lines[] = '';

        $lines[] =
            'Action Items:';

        foreach (
            $summary['action_items']
            ?? [] as $item
        ) {
            if (
                is_string($item)
            ) {
                $lines[] =
                    '- ' . $item;

                continue;
            }

            $line =
                '- '
                . (
                    $item['task']
                    ?? ''
                );

            if (
                ! empty(
                    $item['assigned_to']
                )
            ) {
                $line .=
                    ' | Assigned to: '
                    . $item['assigned_to'];
            }

            if (
                ! empty(
                    $item['deadline']
                )
            ) {
                $line .=
                    ' | Deadline: '
                    . $item['deadline'];
            }

            $lines[] =
                $line;
        }

        $lines[] = '';

        $lines[] =
            'Questions Requiring Follow-up:';

        foreach (
            $summary[
                'questions_for_followup'
            ] ?? [] as $question
        ) {
            $lines[] =
                '- ' . $question;
        }

        return implode(
            "\n",
            $lines
        );
    }

    private function storeRecordingFile(
        UploadedFile $file
    ): string {
        $extension =
            strtolower(
                (string)
                $file
                    ->getClientOriginalExtension()
            );

        if (
            $extension === ''
        ) {
            $extension =
                strtolower(
                    (string)
                    $file->extension()
                );
        }

        if (
            $extension === ''
            || $extension === 'bin'
        ) {
            $mime =
                strtolower(
                    (string)
                    $file->getMimeType()
                );

            $extension =
                match (true) {
                    str_contains(
                        $mime,
                        'webm'
                    ) =>
                        'webm',

                    str_contains(
                        $mime,
                        'ogg'
                    ) =>
                        'ogg',

                    str_contains(
                        $mime,
                        'mpeg'
                    ) =>
                        'mp3',

                    str_contains(
                        $mime,
                        'wav'
                    ) =>
                        'wav',

                    str_contains(
                        $mime,
                        'mp4'
                    ) =>
                        'm4a',

                    default =>
                        'webm',
                };
        }

        $safeExtension =
            preg_replace(
                '/[^a-z0-9]+/',
                '',
                $extension
            ) ?: 'webm';

        $name =
            (string) Str::uuid()
            . '.'
            . $safeExtension;

        return $file->storeAs(
            'meeting-recordings',
            $name,
            'public'
        );
    }

    private function validateRecordingFile(
        UploadedFile $file
    ): void {
        if (
            ($file->getSize() ?? 0)
            < 1024
        ) {
            abort(
                422,
                'The recording is empty or too short. Record at least a few seconds and try again.'
            );
        }

        $mime =
            strtolower(
                (string)
                $file->getMimeType()
            );

        $extension =
            strtolower(
                (string)
                $file
                    ->getClientOriginalExtension()
            );

        $recordingExtensions = [
            'mp3',
            'wav',
            'wave',
            'm4a',
            'mp4',
            'm4v',
            'webm',
            'weba',
            'ogg',
            'oga',
            'opus',
            'aac',
            'flac',
            'wma',
            'amr',
            'awb',
            '3gp',
            '3gpp',
            '3g2',
            'mov',
            'mkv',
            'mka',
            'avi',
            'mpeg',
            'mpg',
            'mpga',
            'aiff',
            'aif',
            'aifc',
            'caf',
            'ac3',
            'eac3',
            'wmv',
            'ts',
            'mts',
            'm2ts',
            'au',
            'snd',
            'ra',
            'ram',
            'rm',
        ];

        if (
            ! str_starts_with(
                $mime,
                'audio/'
            )
            && ! str_starts_with(
                $mime,
                'video/'
            )
            && $mime
                !== 'application/octet-stream'
            && ! in_array(
                $extension,
                $recordingExtensions,
                true
            )
        ) {
            abort(
                422,
                'The selected file is not a recognised audio or video recording.'
            );
        }
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

        $destinationPath = $temporaryDirectory . DIRECTORY_SEPARATOR . Str::uuid() . '.mp3';

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
            Log::warning('Audio segment extraction failed.', [
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
}
