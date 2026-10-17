<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The "When to send" choice shared by the announcement form and the
 * appreciation preview: "now" (default) or "later" with a date and time
 * entered in the app timezone (Africa/Kampala) as Y-m-d\TH:i (24h).
 */
class SendTime
{
    public const NOW = 'now';

    public const LATER = 'later';

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'when' => ['nullable', 'in:'.self::NOW.','.self::LATER],
            'scheduled_for' => ['nullable', 'required_if:when,'.self::LATER, 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (request()->input('when') !== self::LATER) {
                    return;
                }

                $at = self::parse((string) $value);

                if ($at === null) {
                    $fail('Enter the date and time to send, for example 2026-10-20 09:00.');
                } elseif ($at->lessThanOrEqualTo(now())) {
                    $fail('Choose a date and time in the future ('.self::timezone().').');
                }
            }],
        ];
    }

    public static function parse(?string $value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'] as $format) {
            try {
                $at = Carbon::createFromFormat($format, $value, self::timezone());
                if ($at !== false && $at->format($format) === $value) {
                    return $at->second(0);
                }
            } catch (Throwable) {
                // try the next format
            }
        }

        return null;
    }

    /** The scheduled time when "later" was chosen, otherwise null (send now). */
    public static function fromRequest(\Illuminate\Http\Request $request): ?Carbon
    {
        return $request->input('when') === self::LATER ? self::parse($request->input('scheduled_for')) : null;
    }

    /** "Mon 20 Oct, 09:00" */
    public static function format(?\DateTimeInterface $at): ?string
    {
        return $at ? Carbon::instance($at)->timezone(self::timezone())->format('D j M, H:i') : null;
    }

    public static function timezone(): string
    {
        return (string) config('app.timezone', 'Africa/Kampala');
    }

    /** Earliest value for the datetime-local input. */
    public static function minInput(): string
    {
        return now()->timezone(self::timezone())->addMinute()->format('Y-m-d\TH:i');
    }
}
