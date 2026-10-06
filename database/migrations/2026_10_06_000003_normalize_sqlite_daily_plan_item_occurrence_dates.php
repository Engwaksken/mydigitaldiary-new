<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection($this->getConnection());

        // Real DATE engines already store date-only values; do not alter them.
        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $connection->transaction(function () use ($connection): void {
            // Only exact, valid Gregorian YYYY-MM-DD 00:00:00 text qualifies.
            // The +0 days modifier forces SQLite to normalize invalid dates
            // (e.g. February 30), which then fail the equality check.
            $recognizedMidnight = <<<'SQL'
                typeof(legacy.occurrence_date) = 'text'
                AND length(legacy.occurrence_date) = 19
                AND legacy.occurrence_date GLOB '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9] 00:00:00'
                AND substr(legacy.occurrence_date, 1, 4) BETWEEN '0001' AND '9999'
                AND date(substr(legacy.occurrence_date, 1, 10), '+0 days')
                    = substr(legacy.occurrence_date, 1, 10)
                SQL;

            // Preflight the actual unique key, not user_id. Never merge or
            // delete colliding records: either one could hold user edits.
            // Include identical legacy values in case the index is missing.
            $collision = $connection->selectOne(<<<SQL
                SELECT legacy.id AS legacy_id, existing.id AS existing_id
                FROM daily_plan_item_occurrences AS legacy
                JOIN daily_plan_item_occurrences AS existing
                    ON existing.daily_plan_item_id = legacy.daily_plan_item_id
                    AND existing.id <> legacy.id
                    AND (
                        existing.occurrence_date = substr(legacy.occurrence_date, 1, 10)
                        OR existing.occurrence_date = legacy.occurrence_date
                    )
                WHERE {$recognizedMidnight}
                LIMIT 1
                SQL);

            if ($collision !== null) {
                throw new \RuntimeException(
                    'SQLite occurrence date normalization refused: '
                    .'daily_plan_item_occurrences IDs '.$collision->legacy_id
                    .' and '.$collision->existing_id.' would collide. '
                    .'Resolve the conflict without discarding completion or overrides, then retry. '
                    .'No occurrence dates were changed.'
                );
            }

            // Existing production DATA alteration only: preserve every ID,
            // completion/override field, timestamp, and nonmatching row.
            // The read and write share one SQLite transaction; a competing
            // writer causes a safe lock/snapshot failure rather than a merge.
            $connection->update(<<<SQL
                UPDATE daily_plan_item_occurrences
                SET occurrence_date = substr(occurrence_date, 1, 10)
                WHERE id IN (
                    SELECT legacy.id
                    FROM daily_plan_item_occurrences AS legacy
                    WHERE {$recognizedMidnight}
                )
                SQL);
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive no-op: original formatting provenance
        // cannot be recovered, and reintroducing midnight strings would break
        // date-only lookups again. Keep corrected data on migration rollback.
        return;
    }
};
