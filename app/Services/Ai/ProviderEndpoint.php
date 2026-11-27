<?php

namespace App\Services\Ai;

/**
 * Turns the endpoint an admin typed into AI Providers into the URL that
 * OpenAI-compatible chat requests are sent to.
 *
 * WITU AI is a special case: its project keys (uhk_live_… / uhk_test_…) are
 * only accepted by the gateway at https://ai.witulibrary.org/gw/v1. The bare
 * host and /v1 on the same host belong to the older WITU gateway, which
 * answers every such key with HTTP 401. Those keys are therefore always sent
 * to /gw/v1/chat/completions, whatever path was saved.
 */
final class ProviderEndpoint
{
    public const WITU_HOST = 'ai.witulibrary.org';

    public const WITU_BASE_URL = 'https://ai.witulibrary.org/gw/v1';

    private const WITU_KEY_PATTERN = '/^uhk_(live|test)_[0-9a-z]{10}_[A-Za-z0-9_-]{43}$/';

    public static function chatCompletions(string $url, string $apiKey = ''): string
    {
        $url = rtrim(trim($url), '/');

        if ($url === '') {
            return $url;
        }

        if (self::isWitu($url) && self::isWituKey($apiKey)) {
            return self::WITU_BASE_URL . '/chat/completions';
        }

        // A complete endpoint is used exactly as configured.
        if (
            str_ends_with($url, '/chat/completions')
            || str_contains($url, ':generateContent')
            || str_ends_with($url, '/responses')
        ) {
            return $url;
        }

        // A versioned base URL such as …/v1 or …/gw/v1.
        if (preg_match('#/v\d+$#', $url) === 1) {
            return $url . '/chat/completions';
        }

        // A bare host: OpenAI-compatible servers serve /v1/chat/completions.
        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($path === '' || $path === '/') {
            return $url . '/v1/chat/completions';
        }

        // Any other path is kept as configured.
        return $url;
    }

    public static function isWitu(string $url): bool
    {
        return strtolower((string) parse_url(trim($url), PHP_URL_HOST)) === self::WITU_HOST;
    }

    public static function isWituKey(string $apiKey): bool
    {
        return preg_match(self::WITU_KEY_PATTERN, trim($apiKey)) === 1;
    }

    public static function looksLikeWituKey(string $apiKey): bool
    {
        return str_starts_with(trim($apiKey), 'uhk_');
    }
}
