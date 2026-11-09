<?php

namespace App\Services;

use Throwable;

/** Turn a transcription exception into a message that is safe to show users. */
class MeetingTranscriptionErrorMessage
{
    public const GENERIC =
        'We could not transcribe this recording. Please try again with a supported audio file within your transcription capacity.';

    public static function from(Throwable $e): string
    {
        $raw = strtolower((string) $e->getMessage());

        if (str_contains($raw, 'per-meeting transcription limit')) {
            return str_contains($raw, 'expired')
                ? 'This meeting exceeds the per-meeting transcription limit, and your extra recording minutes have expired.'
                : 'This meeting exceeds the per-meeting transcription limit. Top up your recording quota to transcribe longer meetings.';
        }

        if (str_contains($raw, 'maximum content size limit exceeded') || str_contains($raw, '413')) {
            return 'The recording is still too large for the transcription service after compression.';
        }

        if (str_contains($raw, 'top up')) {
            return 'The recording is too large. Please top up your recording quota to complete full transcription.';
        }

        if (str_contains($raw, 'requires an active subscription')) {
            return 'Transcription requires an active subscription or extra recording quota minutes.';
        }

        return self::GENERIC;
    }

    /**
     * Quota and plan problems will not fix themselves on a retry, so the
     * queue should stop instead of spending further attempts.
     */
    public static function isPermanent(Throwable $e): bool
    {
        $raw = strtolower((string) $e->getMessage());

        return str_contains($raw, 'per-meeting transcription limit')
            || str_contains($raw, 'top up')
            || str_contains($raw, 'requires an active subscription')
            || str_contains($raw, 'exceeds your transcription capacity')
            || str_contains($raw, 'could not be found')
            || str_contains($raw, 'empty or too short');
    }
}
