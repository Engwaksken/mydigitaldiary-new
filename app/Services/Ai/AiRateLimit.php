<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Per-user limit for AI calls that ride on an ordinary endpoint (a flag on
 * expenses.store or social-media store), where a route-level throttle would
 * also limit normal saves. Dedicated AI routes use throttle:N,1 instead.
 */
class AiRateLimit
{
    /**
     * Counts one attempt and reports whether the user is over the limit.
     */
    public static function exceeded(string $feature, int|string $userId, int $perMinute): bool
    {
        $key = 'ai:'.$feature.':'.$userId;

        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            return true;
        }

        RateLimiter::hit($key, 60);

        return false;
    }
}
