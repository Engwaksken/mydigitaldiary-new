<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * A one-time, manually-run command - createDefaultHydrationReminder() (see
 * User model) only ever ran automatically for NEW self-registrations after
 * that feature was added. Anyone who registered before then never got one.
 * Run this once to backfill it for every existing user who doesn't already
 * have a reminder tagged module=health with "hydrat" in its title (a loose
 * match rather than an exact one, in case a user already renamed theirs).
 */
class SeedHydrationRemindersForExistingUsers extends Command
{
    protected $signature = 'reminders:seed-hydration {--dry-run : Show who would get one without actually creating it}';

    protected $description = 'Backfill the default hydration reminder for existing users who don\'t already have one';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $created = 0;

        User::whereDoesntHave('reminders', function ($query) {
            $query->where('module', 'health')->where('title', 'like', '%drink water%');
        })->chunkById(100, function ($users) use (&$created, $dryRun) {
            foreach ($users as $user) {
                if ($dryRun) {
                    $this->line("Would create hydration reminder for: {$user->email}");
                    continue;
                }

                $user->createDefaultHydrationReminder();
                $created++;
                $this->info("Created hydration reminder for: {$user->email}");
            }
        });

        if ($dryRun) {
            $this->info('Dry run complete - no reminders were actually created. Re-run without --dry-run to apply.');
        } else {
            $this->info("Done. Created {$created} hydration reminder(s).");
        }

        return self::SUCCESS;
    }
}
