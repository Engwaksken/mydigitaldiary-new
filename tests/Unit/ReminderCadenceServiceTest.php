<?php

namespace Tests\Unit;

use App\Services\ReminderCadenceService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ReminderCadenceServiceTest extends TestCase
{
    public function test_it_produces_three_staggered_nudges_for_a_future_time(): void
    {
        $service = new ReminderCadenceService();
        $now = Carbon::parse('2026-10-10 08:00:00', 'Africa/Kampala');
        $due = Carbon::parse('2026-10-12 09:00:00', 'Africa/Kampala');

        $times = $service->nudgeTimes($due, $now);

        $this->assertCount(3, $times);

        $this->assertEquals(
            '2026-10-11 09:00:00',
            $times[0]->toDateTimeString(),
            'first nudge is the day before'
        );
        $this->assertEquals(
            '2026-10-12 07:00:00',
            $times[1]->toDateTimeString(),
            'second nudge is two hours before'
        );
        $this->assertEquals(
            '2026-10-12 09:00:00',
            $times[2]->toDateTimeString(),
            'third nudge is at the due time'
        );
    }

    public function test_past_nudges_are_skipped(): void
    {
        $service = new ReminderCadenceService();
        $now = Carbon::parse('2026-10-12 08:00:00', 'Africa/Kampala');
        $due = Carbon::parse('2026-10-12 09:00:00', 'Africa/Kampala');

        $times = $service->nudgeTimes($due, $now);

        // "Day before" (11th 09:00) and "two hours before" (07:00) are past;
        // only the due-time nudge remains.
        $this->assertCount(1, $times);
        $this->assertEquals('2026-10-12 09:00:00', $times[2]->toDateTimeString());
    }

    public function test_already_due_item_still_gets_one_immediate_nudge(): void
    {
        $service = new ReminderCadenceService();
        $now = Carbon::parse('2026-10-12 09:30:00', 'Africa/Kampala');
        $due = Carbon::parse('2026-10-12 09:00:00', 'Africa/Kampala');

        $times = $service->nudgeTimes($due, $now);

        $this->assertCount(1, $times);
        $this->assertTrue($times[0]->greaterThan($now), 'the fallback nudge is in the future');
    }

    public function test_signature_is_stable_per_nudge_index(): void
    {
        $this->assertSame('42:nudge:0', ReminderCadenceService::signature(42, 0));
        $this->assertSame('42:nudge:2', ReminderCadenceService::signature(42, 2));
    }
}
