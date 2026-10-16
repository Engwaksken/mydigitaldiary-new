<?php

namespace App\Services;

use App\Models\TranscriptionMinuteUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MeetingTranscriptionLimitService
{
    /** Reserve per-meeting plan minutes and any purchased overage before transcription. */
    public function reserve(User $user, int $durationSeconds): TranscriptionMinuteUsage
    {
        $durationSeconds = max(1, $durationSeconds);

        return DB::transaction(function () use ($user, $durationSeconds): TranscriptionMinuteUsage {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $lockedUser->load('subscriptionPlan');
            $includedSeconds = $lockedUser->hasActiveAccess()
                ? max(0, (int) ($lockedUser->subscriptionPlan?->max_transcription_minutes_per_meeting ?? 60)) * 60
                : 0;
            $includedSecondsUsed = min($durationSeconds, $includedSeconds);
            $overageMinutes = (int) ceil(max(0, $durationSeconds - $includedSeconds) / 60);

            if ($overageMinutes > 0) {
                if ($lockedUser->extra_quota_expires_at && $lockedUser->extra_quota_expires_at->isPast()) {
                    throw new RuntimeException('This meeting exceeds the per-meeting transcription limit, and your extra recording minutes have expired.');
                }

                $availableMinutes = max(0, (int) $lockedUser->extra_recording_quota_minutes);
                if ($availableMinutes < $overageMinutes) {
                    throw new RuntimeException('This meeting exceeds the per-meeting transcription limit. Top up your recording quota to transcribe longer meetings.');
                }

                $lockedUser->forceFill([
                    'extra_recording_quota_minutes' => $availableMinutes - $overageMinutes,
                ])->save();
            }

            return TranscriptionMinuteUsage::query()->create([
                'user_id' => $lockedUser->getKey(),
                'duration_seconds' => $durationSeconds,
                'plan_seconds' => $includedSecondsUsed,
                'extra_minutes' => $overageMinutes,
                // Kept as a required audit field; entitlement is no longer accumulated by month.
                'period_start' => today()->toDateString(),
                'status' => 'reserved',
            ]);
        });
    }

    public function complete(TranscriptionMinuteUsage $usage): void
    {
        $usage->forceFill(['status' => 'consumed'])->save();
    }

    /** Release a failed provider attempt and return its reserved top-up minutes. */
    public function release(TranscriptionMinuteUsage $usage): void
    {
        DB::transaction(function () use ($usage): void {
            $lockedUsage = TranscriptionMinuteUsage::query()->lockForUpdate()->find($usage->getKey());
            if (! $lockedUsage || $lockedUsage->status !== 'reserved') {
                return;
            }

            $lockedUser = User::query()->lockForUpdate()->find($lockedUsage->user_id);
            if ($lockedUser && $lockedUsage->extra_minutes > 0) {
                $lockedUser->forceFill([
                    'extra_recording_quota_minutes' => max(0, (int) $lockedUser->extra_recording_quota_minutes) + $lockedUsage->extra_minutes,
                ])->save();
            }

            $lockedUsage->delete();
        });
    }
}
