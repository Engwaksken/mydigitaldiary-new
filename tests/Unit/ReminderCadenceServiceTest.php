<?php

namespace Tests\Unit;

use App\Services\ReminderCadenceService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ReminderCadenceServiceTest extends TestCase
{
    public function test_it_produces_three_pre_due_and_three_overdue_nudges(): void
    {
        $service = new ReminderCadenceService();
        $now = Carbon::parse('2026-10-10 08:00:00', 'Africa/Kampala');
        $due = Carbon::parse('2026-10-10 10:00:00', 'Africa/Kampala');

        $times = $service->nudgeTimes($due, $now);

        $this->assertCount(6, $times);
        $this->assertSame('2026-10-10 09:30:00', $times[0]->toDateTimeString());
        $this->assertSame('2026-10-10 09:40:00', $times[1]->toDateTimeString());
        $this->assertSame('2026-10-10 09:50:00', $times[2]->toDateTimeString());
        $this->assertSame('2026-10-10 10:10:00', $times[3]->toDateTimeString());
        $this->assertSame('2026-10-10 10:20:00', $times[4]->toDateTimeString());
        $this->assertSame('2026-10-10 10:30:00', $times[5]->toDateTimeString());
    }

    public function test_past_nudges_are_skipped(): void
    {
        $service = new ReminderCadenceService();
        $now = Carbon::parse('2026-10-10 09:35:00', 'Africa/Kampala');
        $due = Carbon::parse('2026-10-10 10:00:00', 'Africa/Kampala');

        $times = $service->nudgeTimes($due, $now);

        // The first pre-due alert has passed; the other five remain scheduled.
        $this->assertCount(5, $times);
        $this->assertArrayNotHasKey(0, $times);
        $this->assertSame('2026-10-10 09:40:00', $times[1]->toDateTimeString());
    }

    public function test_already_due_item_gets_three_spaced_followups(): void
    {
        $service = new ReminderCadenceService();
        $now = Carbon::parse('2026-10-12 09:30:00', 'Africa/Kampala');
        $due = Carbon::parse('2026-10-12 09:00:00', 'Africa/Kampala');

        $times = $service->nudgeTimes($due, $now);

        $this->assertCount(3, $times);
        $this->assertSame('2026-10-12 09:31:00', $times[3]->toDateTimeString());
        $this->assertSame('2026-10-12 09:41:00', $times[4]->toDateTimeString());
        $this->assertSame('2026-10-12 09:51:00', $times[5]->toDateTimeString());
    }

    public function test_signature_is_stable_per_nudge_index(): void
    {
        $this->assertSame('42:nudge:0', ReminderCadenceService::signature(42, 0));
        $this->assertSame('42:nudge:2', ReminderCadenceService::signature(42, 2));
    }
}
