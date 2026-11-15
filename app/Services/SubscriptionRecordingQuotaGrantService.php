<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRecordingExtraGrant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SubscriptionRecordingQuotaGrantService
{
    public function grantIncludedMinutes(
        User $user,
        ?SubscriptionPlan $plan,
        string $activationEventKey,
        ?Carbon $expiresAt = null,
        ?Payment $payment = null,
        ?string $eventType = null
    ): ?SubscriptionRecordingExtraGrant {
        if (! $plan || $plan->includedExtraRecordingMinutes() <= 0) {
            return null;
        }

        if (
            ! Schema::hasTable('subscription_recording_extra_grants')
            || ! Schema::hasColumn('users', 'extra_recording_quota_minutes')
        ) {
            return null;
        }

        $key = $this->normaliseEventKey($activationEventKey);

        return DB::transaction(function () use ($user, $plan, $key, $expiresAt, $payment, $eventType) {
            $existing = SubscriptionRecordingExtraGrant::query()
                ->where('activation_event_key', $key)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            /** @var User $lockedUser */
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $minutes = $plan->includedExtraRecordingMinutes();

            $updates = [
                'extra_recording_quota_minutes' => max(0, (int) $lockedUser->extra_recording_quota_minutes) + $minutes,
            ];

            if (Schema::hasColumn('users', 'extra_quota_expires_at')) {
                $updates['extra_quota_expires_at'] = $this->laterExpiry(
                    $lockedUser->extra_quota_expires_at ? Carbon::parse($lockedUser->extra_quota_expires_at) : null,
                    $expiresAt
                );
            }

            $lockedUser->forceFill($updates)->save();

            return SubscriptionRecordingExtraGrant::create([
                'user_id' => $lockedUser->getKey(),
                'subscription_plan_id' => $plan->getKey(),
                'payment_id' => $payment?->getKey(),
                'activation_event_key' => $key,
                'activation_event_type' => $eventType,
                'included_minutes' => $minutes,
                'granted_at' => now(),
                'expires_at' => $expiresAt,
            ]);
        });
    }

    public function key(string $type, mixed ...$parts): string
    {
        return $type.':'.implode(':', array_map(
            fn ($part) => $part instanceof \DateTimeInterface
                ? Carbon::instance($part)->toISOString()
                : (string) ($part ?? 'null'),
            $parts
        ));
    }

    private function normaliseEventKey(string $activationEventKey): string
    {
        $activationEventKey = trim($activationEventKey);

        return strlen($activationEventKey) <= 255
            ? $activationEventKey
            : 'sha1:'.sha1($activationEventKey);
    }

    /**
     * Return the later of the user's current extra-quota expiry and the
     * new grant's expiry.
     *
     * Null-candidate (lifetime grant) rules:
     *  - current is null → return null (already a lifetime state).
     *  - current is a FUTURE date → return current so that a previously
     *    purchased (expiring) quota is never silently converted into
     *    permanent quota by a subsequent lifetime plan grant.
     *  - current is a PAST date → return null to clear the stale date,
     *    otherwise the lifetime minutes would be unusable because
     *    availableExtraRecordingQuotaMinutes() returns 0 when the
     *    expiry is in the past.
     *
     * When the candidate is set (non-lifetime grant) the existing
     * behaviour applies: return whichever date is later.
     */
    private function laterExpiry(?Carbon $current, ?Carbon $candidate): ?Carbon
    {
        // Lifetime grant (candidate is null).
        if (! $candidate) {
            // Preserve a future expiry - don't silently upgrade expiring
            // purchased quota into permanent quota.
            if ($current && $current->isFuture()) {
                return $current;
            }

            // Null current (already lifetime) or past current (stale):
            // return null so the lifetime minutes are usable.
            return null;
        }

        // Non-lifetime grant - return the later of the two dates.
        if (! $current || $candidate->gt($current)) {
            return $candidate;
        }

        return $current;
    }
}
