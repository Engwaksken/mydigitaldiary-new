<?php

namespace App\Services;

use App\Models\TranscriptionMinuteUsage;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MonthlyTranscriptionUsageService
{
    /** Reserve allowance before calling the transcription provider. */
    public function reserve(User $user, int $durationSeconds): TranscriptionMinuteUsage
    {
        $durationSeconds = max(1, $durationSeconds);

        return DB::transaction(function () use ($user, $durationSeconds): TranscriptionMinuteUsage {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $lockedUser->load('subscriptionPlan');
            $periodStart = $this->periodStart($lockedUser);
            $active = $lockedUser->hasActiveAccess();
            $monthlySeconds = $active
                ? max(0, (int) ($lockedUser->subscriptionPlan?->monthly_transcription_minutes ?? 60)) * 60
                : 0;
            $usedPlanSeconds = (int) TranscriptionMinuteUsage::query()
                ->where('user_id', $lockedUser->getKey())
                ->whereDate('period_start', $periodStart->toDateString())
                ->whereIn('status', ['reserved', 'consumed'])
                ->sum('plan_seconds');
            $planSeconds = min($durationSeconds, max(0, $monthlySeconds - $usedPlanSeconds));
            $secondsToCoverWithExtra = $durationSeconds - $planSeconds;
            $extraMinutes = (int) ceil($secondsToCoverWithExtra / 60);

            if ($extraMinutes > 0) {
                if ($lockedUser->extra_quota_expires_at && $lockedUser->extra_quota_expires_at->isPast()) {
                    throw new RuntimeException('Your monthly transcription allowance is exhausted. Please top up your recording quota or try again after it resets.');
                }

                $availableMinutes = max(0, (int) $lockedUser->extra_recording_quota_minutes);
                if ($availableMinutes < $extraMinutes) {
                    throw new RuntimeException('Your monthly transcription allowance is exhausted. Please top up your recording quota or try again after it resets.');
                }

                $lockedUser->forceFill([
                    'extra_recording_quota_minutes' => $availableMinutes - $extraMinutes,
                ])->save();
            }

            return TranscriptionMinuteUsage::query()->create([
                'user_id' => $lockedUser->getKey(),
                'duration_seconds' => $durationSeconds,
                'plan_seconds' => $planSeconds,
                'extra_minutes' => $extraMinutes,
                'period_start' => $periodStart->toDateString(),
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

    public function usedPlanSeconds(User $user): int
    {
        $periodStart = $this->periodStart($user);

        return (int) TranscriptionMinuteUsage::query()
            ->where('user_id', $user->getKey())
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereIn('status', ['reserved', 'consumed'])
            ->sum('plan_seconds');
    }

    private function periodStart(User $user): Carbon
    {
        $anchor = $user->subscription_started_at
            ? Carbon::parse($user->subscription_started_at)->startOfDay()
            : Carbon::parse($user->created_at ?? now())->startOfDay();

        if ($anchor->isFuture()) {
            return $anchor;
        }

        $months = (int) $anchor->diffInMonths(now());
        $start = $anchor->copy()->addMonthsNoOverflow($months);

        return $start->greaterThan(now()) ? $start->subMonthNoOverflow() : $start;
    }
}
