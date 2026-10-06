<?php

namespace Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Unit\Support\DailyPlanItemFactory;
use Tests\TestCase;

class DailyPlannerRecurrenceTest extends TestCase
{
    #[DataProvider('recurrenceDates')]
    public function test_calendar_recurrence_uses_whole_intervals(string $type, int $interval, string $start, string $date, bool $expected): void
    {
        $item = DailyPlanItemFactory::new()->make(['repeat_type' => $type,
            'repeat_interval' => $interval, 'repeat_starts_on' => $start]);
        $this->assertSame($expected, $item->occursOn(Carbon::parse($date)));
    }

    public static function recurrenceDates(): array
    {
        return [
            'daily matches' => ['daily', 2, '2026-01-31', '2026-02-02', true],
            'daily skips' => ['daily', 2, '2026-01-31', '2026-02-01', false],
            'weekly matches' => ['weekly', 2, '2026-01-31', '2026-02-14', true],
            'weekly skips' => ['weekly', 2, '2026-01-31', '2026-02-07', false],
            'short month clamps 31st' => ['monthly', 1, '2026-01-31', '2026-02-28', true],
            'leap month clamps 31st' => ['monthly', 1, '2028-01-31', '2028-02-29', true],
            'returns to 31st' => ['monthly', 1, '2026-01-31', '2026-03-31', true],
            'not the 30th' => ['monthly', 1, '2026-01-31', '2026-03-30', false],
            'interval crosses year' => ['monthly', 2, '2025-12-31', '2026-02-28', true],
            'interval skips month' => ['monthly', 2, '2025-12-31', '2026-01-31', false],
            'before start' => ['monthly', 1, '2026-01-31', '2025-12-31', false],
        ];
    }

    public function test_recurrence_end_date_is_inclusive(): void
    {
        $item = DailyPlanItemFactory::new()->make(['repeat_ends_on' => '2026-02-28']);
        $this->assertTrue($item->occursOn(Carbon::parse('2026-02-28 23:59:59')));
        $this->assertFalse($item->occursOn(Carbon::parse('2026-03-31')));
    }
}
