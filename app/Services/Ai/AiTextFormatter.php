<?php

declare(strict_types=1);

namespace App\Services\Ai;

/** Normalize long dash characters in generated text to the platform's hyphen style. */
final class AiTextFormatter
{
    public static function normalize(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(['—', '–'], '-', $value);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::normalize($item);
            }
        }

        return $value;
    }
}
