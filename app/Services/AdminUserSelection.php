<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A large set of user IDs picked on the admin Users page ("select all users
 * matching these filters"), kept server-side behind a short-lived token so
 * thousands of IDs never go in a URL: ?selection=<token>.
 */
class AdminUserSelection
{
    public const TTL_HOURS = 6;

    /**
     * @param  list<int>  $ids
     */
    public static function store(array $ids, string $description): string
    {
        $token = Str::random(40);

        Cache::put(self::key($token), [
            'ids' => array_values(array_map('intval', $ids)),
            'description' => $description,
        ], now()->addHours(self::TTL_HOURS));

        return $token;
    }

    /** @return array{ids: list<int>, description: string}|null */
    public static function get(?string $token): ?array
    {
        if (! is_string($token) || ! preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            return null;
        }

        $selection = Cache::get(self::key($token));

        return is_array($selection) && isset($selection['ids']) ? $selection : null;
    }

    private static function key(string $token): string
    {
        return 'admin-user-selection:'.$token;
    }
}
