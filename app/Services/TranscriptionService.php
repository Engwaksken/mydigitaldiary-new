<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Ai\AiTextFormatter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TranscriptionService
{
    /**
     * OpenAI transcription endpoint.
     */
    private const ENDPOINT =
        'https://api.openai.com/v1/audio/transcriptions';

    /**
     * Formats supported by OpenAI transcription.
     */
    private const SUPPORTED_EXTENSIONS = [
        'flac',
        'm4a',
        'mp3',
        'mp4',
        'mpeg',
        'mpga',
        'oga',
        'ogg',
        'wav',
        'webm',
    ];

    /**
     * Models attempted in priority order.
     */
    private const MODELS = [
        'gpt-4o-transcribe',
        'gpt-4o-mini-transcribe',
        'whisper-1',
    ];

    /**
     * Upload size supported for transcription: 30 MB.
     */
    private const MAX_FILE_SIZE_BYTES = 30 * 1024 * 1024;

    /**
     * Transcribe a stored meeting recording.
     *
     * @return array{
     *     transcript:string,
     *     segments:array<int,array<string,mixed>>
     * }
     */
    public function transcribe(
        User $user,
        string $audioPath,
        string $language = 'auto'
    ): array {
        $hasActive = $user->hasActiveAccess();
        $extraMinutes = (int) ($user->extra_recording_quota_minutes ?? 0);
        $extraExpires = $user->extra_quota_expires_at;
        $hasExtraQuota = $extraMinutes > 0 && (is_null($extraExpires) || $extraExpires->isFuture());

        if (! $hasActive && ! $hasExtraQuota) {
            throw new RuntimeException(
                'Transcription requires an active subscription or valid extra recording quota minutes.'
            );
        }

        $apiKey =
            $this->resolveOpenAiApiKey(
                $user
            );

        if ($apiKey === '') {
            throw new RuntimeException(
                'OpenAI transcription is not available. Please ask the administrator to configure OpenAI under AI Settings.'
            );
        }

        $disk =
            Storage::disk(
                'public'
            );

        if (
            ! $disk->exists(
                $audioPath
            )
        ) {
            throw new RuntimeException(
                'The meeting recording file could not be found.'
            );
        }

        $sourcePath =
            $disk->path(
                $audioPath
            );

        if (
            ! is_file(
                $sourcePath
            )
        ) {
            throw new RuntimeException(
                'The stored meeting recording is unavailable.'
            );
        }

        $sourceSize =
            filesize(
                $sourcePath
            );

        if (
            $sourceSize === false
            || $sourceSize < 1024
        ) {
            throw new RuntimeException(
                'The recording is empty or too short to transcribe.'
            );
        }

        if (
            $sourceSize > $user->effectiveTranscriptionCapacityBytes()
            && $user->availableExtraRecordingQuotaMinutes() === 0
        ) {
            throw new RuntimeException(
                'This recording exceeds your transcription capacity. Please top up recording quota or upload a shorter recording.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Prepare audio
        |--------------------------------------------------------------------------
        |
        | Browser MediaRecorder output can occasionally produce WebM/Opus files
        | that browsers can play but transcription providers reject.
        |
        | When FFmpeg is available, normalise every recording to MP3 first.
        |
        */

        $prepared =
            $this->prepareAudioFile(
                $sourcePath,
                $audioPath,
                $user
            );

        $preparedPath =
            $prepared['path'];

        $preparedName =
            $prepared['name'];

        $temporary =
            $prepared['temporary'];

        try {
            try {
                return $this->sendToOpenAi(
                    $apiKey,
                    $preparedPath,
                    $preparedName,
                    $language
                );
            } catch (RuntimeException $e) {
                /*
                 * A personal key that is out of credits (429) or rejected
                 * (401/403) must not block transcription. Fall back to the
                 * shared administrator key when it differs from the key that
                 * just failed.
                 */
                $sharedKey =
                    $this->resolveSharedOpenAiApiKey();

                if (
                    $sharedKey !== ''
                    && $sharedKey !== $apiKey
                    && $this->isAccountKeyRejection(
                        $e->getMessage()
                    )
                ) {
                    return $this->sendToOpenAi(
                        $sharedKey,
                        $preparedPath,
                        $preparedName,
                        $language
                    );
                }

                throw $e;
            }
        } finally {
            if (
                $temporary
                && is_file(
                    $preparedPath
                )
            ) {
                @unlink(
                    $preparedPath
                );
            }
        }
    }

    /**
     * Resolve the OpenAI API key.
     *
     * Priority:
     * 1. User-specific OpenAI credential.
     * 2. Shared administrator OpenAI key from SiteSetting.
     */
    private function resolveOpenAiApiKey(
        User $user
    ): string {
        /*
        |--------------------------------------------------------------------------
        | User-specific API credential
        |--------------------------------------------------------------------------
        */

        try {
            if (
                method_exists(
                    $user,
                    'activeApiCredential'
                )
            ) {
                $credential =
                    $user->activeApiCredential();

                if ($credential) {
                    $provider =
                        strtolower(
                            trim(
                                (string) (
                                    $credential->provider
                                    ?? ''
                                )
                            )
                        );

                    if (
                        in_array(
                            $provider,
                            [
                                'openai',
                                'chatgpt',
                            ],
                            true
                        )
                    ) {
                        /*
                         * Common encrypted/accessor field names supported
                         * without exposing the key.
                         */
                        foreach (
                            [
                                'api_key',
                                'key',
                            ] as $field
                        ) {
                            try {
                                $value =
                                    trim(
                                        (string) (
                                            $credential->{$field}
                                            ?? ''
                                        )
                                    );

                                if ($value !== '') {
                                    return $value;
                                }
                            } catch (Throwable $e) {
                                report($e);
                            }
                        }

                        foreach (
                            [
                                'getApiKey',
                                'apiKey',
                                'getDecryptedKey',
                                'decryptedKey',
                            ] as $method
                        ) {
                            if (
                                method_exists(
                                    $credential,
                                    $method
                                )
                            ) {
                                try {
                                    $value =
                                        trim(
                                            (string)
                                            $credential->{$method}()
                                        );

                                    if (
                                        $value !== ''
                                    ) {
                                        return $value;
                                    }
                                } catch (Throwable $e) {
                                    report($e);
                                }
                            }
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        /*
        |--------------------------------------------------------------------------
        | Shared system AI key
        |--------------------------------------------------------------------------
        |
        | SiteSetting casts default_ai_api_key as encrypted, so Laravel
        | automatically decrypts it when accessed.
        |
        */

        return $this->resolveSharedOpenAiApiKey();
    }

    /**
     * Resolve only the administrator's shared OpenAI key, ignoring any
     * user-specific credential.
     */
    private function resolveSharedOpenAiApiKey(): string
    {
        try {
            $settings =
                SiteSetting::current();

            if (
                ! $settings
                || ! $settings->hasDefaultAiKey()
            ) {
                return '';
            }

            $provider =
                strtolower(
                    trim(
                        (string)
                        $settings
                            ->default_ai_provider
                    )
                );

            if (
                ! in_array(
                    $provider,
                    [
                        'openai',
                        'chatgpt',
                    ],
                    true
                )
            ) {
                return '';
            }

            return trim(
                (string)
                $settings
                    ->default_ai_api_key
            );
        } catch (Throwable $e) {
            report($e);

            return '';
        }
    }

    /**
     * Whether a transcription failure was caused by the API key itself
     * (rejected or out of credits) rather than the recording content.
     */
    private function isAccountKeyRejection(
        string $message
    ): bool {
        return preg_match(
            '/API error \((401|403|429)\):?/i',
            $message
        ) === 1;
    }

    /**
     * Prepare a recording for OpenAI.
     *
     * @return array{
     *     path:string,
     *     name:string,
     *     temporary:bool
     * }
     */
    private function prepareAudioFile(
        string $sourcePath,
        string $storedPath,
        ?\App\Models\User $user = null
    ): array {
        $extension =
            strtolower(
                pathinfo(
                    $storedPath,
                    PATHINFO_EXTENSION
                )
            );

        /*
         * Prefer FFmpeg because it normalises browser recordings
         * into a reliable speech-friendly MP3.
         */
        if (
            $this->ffmpegAvailable()
        ) {
            return $this->convertToMp3(
                $sourcePath,
                $user
            );
        }

        /*
         * If FFmpeg is unavailable, only submit an original file
         * when its extension is supported.
         */
        if (
            ! in_array(
                $extension,
                self::SUPPORTED_EXTENSIONS,
                true
            )
        ) {
            throw new RuntimeException(
                'The recording format is not supported for transcription. Install FFmpeg on the server or upload MP3, WAV, M4A, OGG, FLAC or WebM audio.'
            );
        }

        $this->validateBasicContainer(
            $sourcePath,
            $extension
        );

        /*
         * Validate file size for OpenAI's limit.
         * If the file is too large, check if the user has extra recording quota minutes.
         * If they do, FFmpeg will attempt to compress it; otherwise, ask them to top up.
         */
        $fileSize = filesize($sourcePath);

        if ($fileSize > self::MAX_FILE_SIZE_BYTES) {
            if (
                $user
                && (
                    $fileSize <= $user->effectiveTranscriptionCapacityBytes()
                    || $user->availableExtraRecordingQuotaMinutes() > 0
                )
            ) {
                // The plan/override capacity or an active top-up authorizes
                // this upload; compress it below the transcription provider's limit.
                return $this->convertToMp3(
                    $sourcePath,
                    $user
                );
            }

            $sizeMb = round($fileSize / (1024 * 1024), 1);

            throw new RuntimeException(
                "The recording is too large ({$sizeMb} MB) for transcription. "
                . 'Please top up your recording quota to complete full transcription, '
                . 'or upload a shorter recording under 30 MB.'
            );
        }

        return [
            'path' =>
                $sourcePath,

            'name' =>
                'meeting-recording.'
                . $extension,

            'temporary' =>
                false,
        ];
    }

    /**
     * Convert recording to MP3.
     *
     * @return array{
     *     path:string,
     *     name:string,
     *     temporary:bool
     * }
     */
    private function convertToMp3(
        string $sourcePath,
        ?\App\Models\User $user = null
    ): array {
        $temporaryDirectory =
            storage_path(
                'app/transcription-temp'
            );

        if (
            ! is_dir(
                $temporaryDirectory
            )
        ) {
            if (
                ! mkdir(
                    $temporaryDirectory,
                    0755,
                    true
                )
                && ! is_dir(
                    $temporaryDirectory
                )
            ) {
                throw new RuntimeException(
                    'Unable to create the temporary transcription directory.'
                );
            }
        }

        $destinationPath =
            $temporaryDirectory
            . DIRECTORY_SEPARATOR
            . Str::uuid()
            . '.mp3';

        /*
         * -vn           Remove video track.
         * -ac 1         Mono audio.
         * -ar 16000     Speech-friendly sample rate.
         * -b:a 64k      Keeps transcription files relatively small.
         */
        $command =
            'ffmpeg'
            . ' -hide_banner'
            . ' -loglevel error'
            . ' -y'
            . ' -i '
            . escapeshellarg(
                $sourcePath
            )
            . ' -vn'
            . ' -ac 1'
            . ' -ar 16000'
            . ' -codec:a libmp3lame'
            . ' -b:a 64k '
            . escapeshellarg(
                $destinationPath
            )
            . ' 2>&1';

        $output = [];
        $exitCode = 1;

        @exec(
            $command,
            $output,
            $exitCode
        );

        if (
            $exitCode !== 0
            || ! is_file(
                $destinationPath
            )
            || filesize(
                $destinationPath
            ) < 1024
        ) {
            @unlink(
                $destinationPath
            );

            Log::warning(
                'Meeting recording FFmpeg conversion failed.',
                [
                    'source' =>
                        $sourcePath,

                    'exit_code' =>
                        $exitCode,

                    'output' =>
                        trim(
                            implode(
                                "\n",
                                $output
                            )
                        ),
                ]
            );

            throw new RuntimeException(
                'The meeting recording could not be prepared for transcription. The saved audio may be incomplete or corrupted.'
            );
        }

        $this->validateWithFfprobe(
            $destinationPath
        );

        /*
         * If the compressed MP3 still exceeds the 30 MB upload limit,
         * re-encode at an even lower bitrate to fit under the threshold.
         */
        if (filesize($destinationPath) > self::MAX_FILE_SIZE_BYTES) {
            $ultraPath =
                $temporaryDirectory
                . DIRECTORY_SEPARATOR
                . Str::uuid()
                . '-lowbitrate.mp3';

            $ultraCommand =
                'ffmpeg'
                . ' -hide_banner'
                . ' -loglevel error'
                . ' -y'
                . ' -i '
                . escapeshellarg(
                    $destinationPath
                )
                . ' -vn'
                . ' -ac 1'
                . ' -ar 16000'
                . ' -codec:a libmp3lame'
                . ' -b:a 32k '
                . escapeshellarg(
                    $ultraPath
                )
                . ' 2>&1';

            $ultraOutput = [];
            $ultraExit = 1;

            @exec(
                $ultraCommand,
                $ultraOutput,
                $ultraExit
            );

            if (
                $ultraExit === 0
                && is_file($ultraPath)
                && filesize($ultraPath) > 1024
                && filesize($ultraPath) <= self::MAX_FILE_SIZE_BYTES
            ) {
                @unlink($destinationPath);
                $destinationPath = $ultraPath;
            } else {
                @unlink($ultraPath);

                /*
                 * Last resort: if the low-bitrate pass still failed
                 * or produced a file that is somehow still too large,
                 * throw a clear error.
                 */
                if (filesize($destinationPath) > self::MAX_FILE_SIZE_BYTES) {
                    $sizeMb = round(filesize($destinationPath) / (1024 * 1024), 1);

                    throw new RuntimeException(
                        "The recording is too large ({$sizeMb} MB) even after compression. "
                        . 'Please upload a shorter recording under 30 MB.'
                    );
                }
            }
        }

        return [
            'path' =>
                $destinationPath,

            'name' =>
                'meeting-recording.mp3',

            'temporary' =>
                true,
        ];
    }

    /**
     * Send prepared audio to OpenAI.
     *
     * @return array{
     *     transcript:string,
     *     segments:array<int,array<string,mixed>>
     * }
     */
    private function sendToOpenAi(
        string $apiKey,
        string $filePath,
        string $filename,
        string $language
    ): array {
        $errors = [];

        foreach (
            self::MODELS as $model
        ) {
            try {
                $result =
                    $this->attemptModel(
                        $apiKey,
                        $model,
                        $filePath,
                        $filename,
                        $language
                    );

                if (
                    trim(
                        $result[
                            'transcript'
                        ] ?? ''
                    ) !== ''
                ) {
                    return AiTextFormatter::normalize($result);
                }

                $errors[] =
                    $model
                    . ': Empty transcript returned.';
            } catch (Throwable $e) {
                report($e);

                $errors[] =
                    $model
                    . ': '
                    . $e->getMessage();
            }
        }

        throw new RuntimeException(
            'OpenAI transcription failed. '
            . implode(
                ' | ',
                $errors
            )
        );
    }

    /**
     * Attempt transcription with one model.
     *
     * @return array{
     *     transcript:string,
     *     segments:array<int,array<string,mixed>>
     * }
     */
    private function attemptModel(
        string $apiKey,
        string $model,
        string $filePath,
        string $filename,
        string $language
    ): array {
        $fileHandle =
            fopen(
                $filePath,
                'rb'
            );

        if (
            $fileHandle === false
        ) {
            throw new RuntimeException(
                'Unable to open the prepared recording.'
            );
        }

        try {
            $request =
                Http::withToken(
                    $apiKey
                )
                    ->acceptJson()
                    ->timeout(
                        180
                    )
                    ->connectTimeout(
                        20
                    )
                    ->retry(
                        2,
                        1000,
                        throw: false
                    )
                    ->attach(
                        'file',
                        $fileHandle,
                        $filename
                    );

            $parameters = [
                'model' =>
                    $model,
            ];

            $mappedLanguage =
                $this->mapLanguage(
                    $language
                );

            if (
                $mappedLanguage
                !== null
            ) {
                $parameters[
                    'language'
                ] =
                    $mappedLanguage;
            }

            /*
             * whisper-1 can provide timestamped segments.
             */
            if (
                $model
                === 'whisper-1'
            ) {
                $parameters[
                    'response_format'
                ] =
                    'verbose_json';

                $parameters[
                    'timestamp_granularities[]'
                ] =
                    'segment';
            } else {
                $parameters[
                    'response_format'
                ] =
                    'json';
            }

            $response =
                $request->post(
                    self::ENDPOINT,
                    $parameters
                );
        } finally {
            if (
                is_resource(
                    $fileHandle
                )
            ) {
                fclose(
                    $fileHandle
                );
            }
        }

        if (
            ! $response->successful()
        ) {
            $message =
                data_get(
                    $response->json(),
                    'error.message'
                );

            if (
                ! is_string(
                    $message
                )
                || trim(
                    $message
                ) === ''
            ) {
                $message =
                    'HTTP '
                    . $response->status();
            }

            throw new RuntimeException(
                'OpenAI API error (' . $response->status() . '): ' . $message
            );
        }

        $payload =
            $response->json();

        if (
            ! is_array(
                $payload
            )
        ) {
            throw new RuntimeException(
                'OpenAI returned an invalid transcription response.'
            );
        }

        $transcript =
            trim(
                (string) (
                    $payload['text']
                    ?? ''
                )
            );

        if (
            $transcript === ''
        ) {
            throw new RuntimeException(
                'OpenAI returned an empty transcript.'
            );
        }

        return [
            'transcript' =>
                $transcript,

            'segments' =>
                $this->normaliseSegments(
                    $payload[
                        'segments'
                    ] ?? [],
                    $transcript
                ),
        ];
    }

    /**
     * Normalise transcription segments.
     *
     * @param mixed $segments
     *
     * @return array<int,array{
     *     start_seconds:int,
     *     end_seconds:int,
     *     speaker:?string,
     *     text:string
     * }>
     */
    private function normaliseSegments(
        mixed $segments,
        string $transcript
    ): array {
        if (
            ! is_array(
                $segments
            )
            || empty(
                $segments
            )
        ) {
            return [
                [
                    'start_seconds' =>
                        0,

                    'end_seconds' =>
                        0,

                    'speaker' =>
                        null,

                    'text' =>
                        $transcript,
                ],
            ];
        }

        return collect(
            $segments
        )
            ->map(
                function ($segment) {
                    if (
                        ! is_array(
                            $segment
                        )
                    ) {
                        return null;
                    }

                    $text =
                        trim(
                            (string) (
                                $segment['text']
                                ?? ''
                            )
                        );

                    if (
                        $text === ''
                    ) {
                        return null;
                    }

                    return [
                        'start_seconds' =>
                            (int) round(
                                (float) (
                                    $segment['start']
                                    ?? $segment[
                                        'start_seconds'
                                    ]
                                    ?? 0
                                )
                            ),

                        'end_seconds' =>
                            (int) round(
                                (float) (
                                    $segment['end']
                                    ?? $segment[
                                        'end_seconds'
                                    ]
                                    ?? 0
                                )
                            ),

                        'speaker' =>
                            filled(
                                $segment[
                                    'speaker'
                                ] ?? null
                            )
                                ? (string)
                                    $segment[
                                        'speaker'
                                    ]
                                : null,

                        'text' =>
                            $text,
                    ];
                }
            )
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Map My Digital Diary language options to OpenAI.
     */
    private function mapLanguage(
        string $language
    ): ?string {
        return match (
            strtolower(
                trim(
                    $language
                )
            )
        ) {
            'en-gb',
            'en' =>
                'en',

            'sw' =>
                'sw',

            /*
             * Leave Luganda on auto detection rather than forcing
             * a provider language value that may not be accepted.
             */
            'lg',
            'auto',
            '' =>
                null,

            default =>
                null,
        };
    }

    /**
     * Validate original recording when FFmpeg is unavailable.
     */
    private function validateBasicContainer(
        string $path,
        string $extension
    ): void {
        if (
            ! is_file(
                $path
            )
        ) {
            throw new RuntimeException(
                'The recording file could not be found.'
            );
        }

        $size =
            filesize(
                $path
            );

        if (
            $size === false
            || $size < 1024
        ) {
            throw new RuntimeException(
                'The recording file is empty or incomplete.'
            );
        }

        if (
            ! in_array(
                $extension,
                self::SUPPORTED_EXTENSIONS,
                true
            )
        ) {
            throw new RuntimeException(
                'Unsupported transcription audio format.'
            );
        }
    }

    /**
     * Validate converted output with ffprobe where available.
     */
    private function validateWithFfprobe(
        string $path
    ): void {
        if (
            ! $this->commandAvailable(
                'ffprobe'
            )
        ) {
            return;
        }

        $command =
            'ffprobe'
            . ' -v error'
            . ' -select_streams a:0'
            . ' -show_entries stream=codec_name'
            . ' -of default=noprint_wrappers=1:nokey=1 '
            . escapeshellarg(
                $path
            )
            . ' 2>&1';

        $output = [];
        $exitCode = 1;

        @exec(
            $command,
            $output,
            $exitCode
        );

        if (
            $exitCode !== 0
            || empty(
                array_filter(
                    $output
                )
            )
        ) {
            throw new RuntimeException(
                'The prepared recording does not contain a valid audio stream.'
            );
        }
    }

    /**
     * Determine whether FFmpeg exists.
     */
    private function ffmpegAvailable(): bool
    {
        return $this->commandAvailable(
            'ffmpeg'
        );
    }

    /**
     * Determine whether a command exists.
     */
    private function commandAvailable(
        string $command
    ): bool {
        $output = [];
        $exitCode = 1;

        if (
            DIRECTORY_SEPARATOR
            === '\\'
        ) {
            $checkCommand =
                'where '
                . escapeshellarg(
                    $command
                )
                . ' 2>NUL';
        } else {
            $checkCommand =
                'command -v '
                . escapeshellarg(
                    $command
                )
                . ' 2>/dev/null';
        }

        @exec(
            $checkCommand,
            $output,
            $exitCode
        );

        return (
            $exitCode === 0
            && ! empty(
                array_filter(
                    $output
                )
            )
        );
    }
}
