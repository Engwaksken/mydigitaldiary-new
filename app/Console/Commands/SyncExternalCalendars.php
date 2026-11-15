<?php

namespace App\Console\Commands;

use App\Models\UserMeetingConnection;
use App\Services\ExternalCalendarSyncService;
use Illuminate\Console\Command;

class SyncExternalCalendars extends Command
{
    protected $signature = 'meetings:sync-external-calendars {--user= : Sync only one user ID}';
    protected $description = 'Sync connected Google, Outlook, Zoom and Webex calendars into My Digital Diary meetings';

    public function handle(ExternalCalendarSyncService $syncService): int
    {
        $userIds = $this->option('user')
            ? collect([(int) $this->option('user')])
            : UserMeetingConnection::query()->distinct()->pluck('user_id');

        if ($userIds->isEmpty()) {
            $this->info('No external calendar connections found.');
            return self::SUCCESS;
        }

        $totalImported = 0;
        $totalUpdated = 0;

        foreach ($userIds as $userId) {
            $result = $syncService->syncUser((int) $userId);
            $totalImported += $result['imported'];
            $totalUpdated += $result['updated'];

            $this->line(sprintf(
                'User %d: %d imported, %d updated%s',
                $userId,
                $result['imported'],
                $result['updated'],
                $result['errors'] ? ' - ' . implode('; ', $result['errors']) : ''
            ));
        }

        $this->info("External calendar sync complete: {$totalImported} imported, {$totalUpdated} updated.");
        return self::SUCCESS;
    }
}
