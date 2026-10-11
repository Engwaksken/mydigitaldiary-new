<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Computes pre-due and overdue nudge times for a task/meeting reminder.
 *
 * Each reminder stays a plain "once" reminder in the reminders table; the
 * multi-nudge behaviour comes from creating one row per nudge time, each
 * keyed by a stable source_signature so the sync services can upsert and
 * cancel them as a set.
 */
class ReminderCadenceService
{
    /**
     * Signed minute offsets from the due time, in delivery order: 30, 20,
     * and 10 minutes before due; then 10, 20, and 30 minutes overdue. Past
     * nudges are skipped. Source status is checked again at delivery time, so
     * follow-ups are automatically suppressed after the item is completed.
     */
    public const OFFSETS = [30, 20, 10, -10, -20, -30];

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

        // An item created after its due time still gets three spaced follow-ups
        // rather than a burst of stale pre-due alerts.
        if ($future === []) {
            return [
                '3' => $now->copy()->addMinutes(1),
                '4' => $now->copy()->addMinutes(11),
                '5' => $now->copy()->addMinutes(21),
            ];
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
