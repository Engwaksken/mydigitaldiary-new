<?php

declare(strict_types=1);

namespace App\Services\Ai;

/** Remove emoji and AI-style long dashes from generated and outgoing copy. */
final class AiTextFormatter
{
    public static function normalize(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = str_replace(['‐', '‑', '‒', '–', '—', '―', '−'], '-', $value);

            $value = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', $value) ?? $value;

            return preg_replace('/[ \t]{2,}/', ' ', $value) ?? $value;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::normalize($item);
            }
        }

        return $value;
    }

    public static function normalizeNotification(object $notification): void
    {
        $reflection = new \ReflectionObject($notification);

        do {
            foreach ($reflection->getProperties() as $property) {
                if ($property->isStatic() || $property->isReadOnly()) {
                    continue;
                }

                $property->setAccessible(true);
                if (! $property->isInitialized($notification)) {
                    continue;
                }

                $value = $property->getValue($notification);
                if (is_string($value) || is_array($value)) {
                    $property->setValue($notification, self::normalize($value));
                }
            }
            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);
    }
}
