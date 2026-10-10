<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Computes the staggered nudge times for a task/meeting reminder so a person
 * is reminded at least three times before an item is due, instead of relying
 * on a single one-off reminder that is easy to miss.
 *
 * Each reminder stays a plain "once" reminder in the reminders table; the
 * multi-nudge behaviour comes from creating one row per nudge time, each
 * keyed by a stable source_signature so the sync services can upsert and
 * cancel them as a set.
 */
class ReminderCadenceService
{
    /**
     * Minutes before the due time to nudge, earliest first. Together with the
     * due time itself these give three distinct reminders:
     *
     *   1. the day before (1440 minutes),
     *   2. a couple of hours before (120 minutes),
     *   3. at the due/start time (0 minutes).
     *
     * Reminders whose computed time is already in the past are skipped, so a
     * task created shortly before it is due simply gets fewer (but still
     * timely) nudges rather than a burst of expired ones.
     */
    public const OFFSETS = [1440, 120, 0];

    /**
     * @param  CarbonInterface  $dueAt  the item's due/start time
     * @return array<int, CarbonInterface>  nudge index (0..n) => time, ascending
     */
    public function nudgeTimes(CarbonInterface $dueAt, ?CarbonInterface $now = null): array
    {
        $dueAt = Carbon::instance($dueAt);
        $now = $now ? Carbon::instance($now) : Carbon::now();

        $times = [];
        foreach (self::OFFSETS as $index => $offset) {
            $times[$index] = $dueAt->copy()->subMinutes($offset);
        }

        $future = array_filter(
            $times,
            static fn (CarbonInterface $time) => $time->greaterThan($now)
        );

        // An item that is already due still deserves a nudge; fire once
        // shortly so the "never forget a task" guarantee holds even for
        // items added at the last minute.
        if ($future === []) {
            return ['0' => $now->copy()->addMinute()];
        }

        return $future;
    }

    /**
     * Stable identifier for one nudge of a source row, used as the reminder's
     * source_signature so sync() can upsert each nudge independently.
     */
    public static function signature(int $sourceId, int|string $nudgeIndex): string
    {
        return "{$sourceId}:nudge:{$nudgeIndex}";
    }
}
