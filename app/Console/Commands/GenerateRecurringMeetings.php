<?php

namespace App\Console\Commands;

use App\Models\Meeting;
use App\Services\RecurringMeetingService;
use Illuminate\Console\Command;

/**
 * Runs daily (see routes/console.php) - tops up each recurring
 * series' rolling window of future instances (see
 * RecurringMeetingService for why this is a rolling window rather
 * than generating everything up front).
 */
class GenerateRecurringMeetings extends Command
{
    protected $signature = 'meetings:generate-recurring';

    protected $description = "Top up each recurring meeting series' rolling window of upcoming instances";

    public function handle(RecurringMeetingService $service): int
    {
        // Only ever a PARENT (recurrence_parent_id null, but a
        // recurrence_frequency set) carries the rule - generated
        // instances themselves have no rule of their own to expand.
        $parents = Meeting::whereNotNull('recurrence_frequency')
            ->whereNull('recurrence_parent_id')
            ->get();

        $totalCreated = 0;
        foreach ($parents as $parent) {
            $totalCreated += $service->generateUpcoming($parent);
        }

        $this->info("Generated {$totalCreated} recurring meeting instance(s) across {$parents->count()} series.");

        return self::SUCCESS;
    }
}
