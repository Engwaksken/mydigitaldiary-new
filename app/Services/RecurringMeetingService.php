<?php

namespace App\Services;

use App\Models\Meeting;
use Illuminate\Support\Carbon;

/**
 * Generates upcoming instances of a recurring meeting, keeping a
 * rolling window of future occurrences (see the $targetCount default
 * below) rather than creating every instance up to recurrence_ends_at
 * (or forever, if unset) all at once - that would either be
 * unbounded or waste a lot of rows for a series nobody ever revisits.
 * GenerateRecurringMeetings (the daily scheduled command) calls this
 * for every active recurring series to top the window back up; the
 * web/mobile "create meeting" flow also calls it once immediately
 * after a new recurring meeting is saved, so the person doesn't see
 * an empty list until tomorrow's run.
 */
class RecurringMeetingService
{
    /**
     * Ensures at least $targetCount future instances exist for this
     * recurring parent, generating more if fewer than that remain
     * (stopping early if recurrence_ends_at is reached first).
     */
    public function generateUpcoming(Meeting $parent, int $targetCount = 12): int
    {
        if (! $parent->isRecurring()) {
            return 0;
        }

        $existingFutureCount = $parent->recurrenceInstances()
            ->where('start_at', '>=', now())
            ->count();

        if ($existingFutureCount >= $targetCount) {
            return 0;
        }

        $needed = $targetCount - $existingFutureCount;

        // Start generating from whichever is later: the parent's own
        // start time, or its most recently generated instance - so
        // topping up an already-partially-generated series continues
        // from where it left off instead of restarting from the
        // parent's original date every time.
        $lastGenerated = $parent->recurrenceInstances()->orderByDesc('start_at')->first();
        $cursor = $lastGenerated ? $lastGenerated->start_at->copy() : $parent->start_at->copy();
        $duration = $parent->start_at->diffInMinutes($parent->end_at ?? $parent->start_at);

        $created = 0;
        $safetyLimit = 400; // generous upper bound so a malformed rule can never loop forever

        while ($created < $needed && $safetyLimit-- > 0) {
            $cursor = $this->nextOccurrence($parent, $cursor);

            if ($cursor === null) {
                break; // no more valid days for this rule (shouldn't normally happen, but guards against it)
            }

            if ($parent->recurrence_ends_at && $cursor->startOfDay()->gt($parent->recurrence_ends_at->copy()->endOfDay())) {
                break; // reached the recurrence's own end date
            }

            Meeting::create([
                'user_id' => $parent->user_id,
                'title' => $parent->title,
                'start_at' => $cursor,
                'end_at' => $duration > 0 ? $cursor->copy()->addMinutes($duration) : null,
                'location' => $parent->location,
                'attendees' => $parent->attendees,
                'status' => 'scheduled',
                'notes' => $parent->notes,
                'recurrence_parent_id' => $parent->id,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Computes the next date/time after $after matching $parent's
     * rule. Weekly respects recurrence_days_of_week (falling through
     * day by day until a matching weekday is found); daily/monthly
     * are straightforward interval addition.
     */
    private function nextOccurrence(Meeting $parent, Carbon $after): ?Carbon
    {
        return match ($parent->recurrence_frequency) {
            'daily' => $after->copy()->addDay(),
            'monthly' => $after->copy()->addMonthNoOverflow(),
            'weekly' => $this->nextWeeklyOccurrence($parent, $after),
            default => null,
        };
    }

    private function nextWeeklyOccurrence(Meeting $parent, Carbon $after): ?Carbon
    {
        $days = $parent->recurrence_days_of_week;
        if (empty($days)) {
            // No specific days chosen - treat as "same weekday as the
            // original meeting, every week."
            return $after->copy()->addWeek();
        }

        $candidate = $after->copy()->addDay();
        for ($i = 0; $i < 8; $i++) {
            if (in_array($candidate->dayOfWeekIso, $days, true)) {
                return $candidate;
            }
            $candidate->addDay();
        }

        return null; // shouldn't be reachable - every ISO weekday (0-6) is checked within 7 days
    }
}
