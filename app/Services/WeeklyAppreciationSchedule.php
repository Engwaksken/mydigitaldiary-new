<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * When the automatic weekly appreciation email runs, from Admin Settings >
 * Weekly Email (defaults: on, Monday, 09:00 Africa/Kampala).
 *
 * The scheduler checks isDue() every minute: it is due from the chosen time
 * on the chosen day until the run has been claimed for that ISO week, so a
 * minute missed by the server still runs later that day. claimWeek() is an
 * atomic update, so however the day or time is changed, the automatic run
 * happens at most once per week (and appreciation_email_logs still allows
 * only one email per person per week).
 */
class WeeklyAppreciationSchedule
{
    public const DEFAULT_DAY = 1;

    public const DEFAULT_TIME = '09:00';

    public const DAYS = [
        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
        5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
    ];

    /** @return array{enabled:bool, day:int, time:string, last_run_week:?string} */
    public static function settings(): array
    {
        $defaults = ['enabled' => true, 'day' => self::DEFAULT_DAY, 'time' => self::DEFAULT_TIME, 'last_run_week' => null];

        try {
            if (! Schema::hasTable('site_settings') || ! Schema::hasColumn('site_settings', 'appreciation_weekly_enabled')) {
                return $defaults;
            }

            $row = SiteSetting::query()->find(1);
        } catch (Throwable) {
            return $defaults;
        }

        if (! $row) {
            return $defaults;
        }

        $day = (int) ($row->appreciation_weekly_day ?? self::DEFAULT_DAY);
        $time = (string) ($row->appreciation_weekly_time ?? self::DEFAULT_TIME);

        return [
            'enabled' => $row->appreciation_weekly_enabled === null ? true : (bool) $row->appreciation_weekly_enabled,
            'day' => isset(self::DAYS[$day]) ? $day : self::DEFAULT_DAY,
            'time' => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) ? $time : self::DEFAULT_TIME,
            'last_run_week' => $row->appreciation_weekly_last_run_week,
        ];
    }

    public static function isDue(?Carbon $now = null): bool
    {
        $settings = self::settings();
        $now = ($now ?? now())->copy()->setTimezone(self::timezone());

        return $settings['enabled']
            && $now->isoWeekday() === $settings['day']
            && $now->format('H:i') >= $settings['time']
            && $settings['last_run_week'] !== AppreciationMessageService::weekKey($now);
    }

    /** Claims this ISO week's automatic run; false when it already ran this week. */
    public static function claimWeek(string $weekKey): bool
    {
        SiteSetting::current();

        return SiteSetting::query()->whereKey(1)
            ->where(fn ($q) => $q->whereNull('appreciation_weekly_last_run_week')
                ->orWhere('appreciation_weekly_last_run_week', '!=', $weekKey))
            ->update(['appreciation_weekly_last_run_week' => $weekKey]) === 1;
    }

    /** "Mondays at 09:00 (Africa/Kampala)" or "Off". */
    public static function describe(): string
    {
        $settings = self::settings();

        return $settings['enabled']
            ? self::DAYS[$settings['day']].'s at '.$settings['time'].' ('.self::timezone().')'
            : 'Off';
    }

    public static function timezone(): string
    {
        return (string) config('app.timezone', 'Africa/Kampala');
    }
}
