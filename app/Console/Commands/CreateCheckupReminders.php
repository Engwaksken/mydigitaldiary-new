<?php

namespace App\Console\Commands;

use App\Models\HealthCheckup;
use App\Models\Reminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Scans health_checkups.next_due_date and creates a Reminder a configurable
 * number of days beforehand, so a checkup you scheduled six months ago
 * doesn't get forgotten. Safe to run daily/repeatedly - each checkup+due-date
 * combination only ever gets one reminder (see reminders.source_signature).
 */
class CreateCheckupReminders extends Command
{
    protected $signature = 'checkups:create-reminders {--days=7 : How many days before the due date to send the reminder}';

    protected $description = 'Auto-create a Reminder for each upcoming health checkup that doesn\'t already have one';

    public function handle(): int
    {
        $leadDays = max(0, (int) $this->option('days'));
        $created = 0;

        HealthCheckup::query()
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '>=', Carbon::today())
            ->chunkById(100, function ($checkups) use ($leadDays, &$created) {
                foreach ($checkups as $checkup) {
                    if ($this->createReminderFor($checkup, $leadDays)) {
                        $created++;
                    }
                }
            });

        $this->info("Created {$created} checkup reminder(s).");

        return self::SUCCESS;
    }

    private function createReminderFor(HealthCheckup $checkup, int $leadDays): bool
    {
        // Fingerprint = which checkup + which due date/time. If the
        // checkup's next_due_date is later edited (including just the
        // time), the signature changes and a fresh reminder is generated
        // rather than silently reusing a stale one.
        $signature = $checkup->id . ':' . $checkup->next_due_date->format('Y-m-d H:i');

        $alreadyExists = Reminder::where('source_type', HealthCheckup::class)
            ->where('source_id', $checkup->id)
            ->where('source_signature', $signature)
            ->exists();

        if ($alreadyExists) {
            return false;
        }

        $sendAt = $checkup->next_due_date->copy()->subDays($leadDays)->setTime(8, 0);

        // If the lead time already passed (e.g. due date is in 3 days but
        // lead time is 7), send it almost immediately instead of in the past.
        if ($sendAt->isPast()) {
            $sendAt = Carbon::now()->addMinutes(5);
        }

        Reminder::create([
            'user_id' => $checkup->user_id,
            'title' => 'Upcoming checkup: ' . $checkup->checkup_type,
            'module' => 'health',
            'message' => sprintf(
                "Your %s checkup is due on %s. Book an appointment if you haven't already.",
                $checkup->checkup_type,
                $checkup->next_due_date->format('Y-m-d \a\t g:i A')
            ),
            'frequency' => 'once',
            'next_run_at' => $sendAt,
            'channel' => 'mail',
            'is_active' => true,
            'source_type' => HealthCheckup::class,
            'source_id' => $checkup->id,
            'source_signature' => $signature,
        ]);

        return true;
    }
}
