<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

require_once __DIR__.'/support/RepairsSqliteEnumChecks.php';

return new class extends Migration
{
    use RepairsSqliteEnumChecks;

    // MySQL DDL may implicitly commit; do not imply transactional rollback.
    public $withinTransaction = false;

    public function up(): void
    {
        $connection = DB::connection($this->getConnection());
        $schema = $connection->getSchemaBuilder();

        if (! $schema->hasColumn('reminders', 'channel')) {
            throw new \RuntimeException('reminders.channel is missing; this migration only widens an existing enum.');
        }

        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $this->repairSqliteEnumCheck($connection, 'reminders', 'channel');

            return;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            Log::warning('Reminder channel enum repair skipped for unsupported driver; any channel enum or CHECK constraint requires a separate reviewed repair.', [
                'driver' => $driver,
            ]);

            return;
        }

        // Non-enum schemas are left untouched, as in the subscription correction.
        if (strtolower($schema->getColumnType('reminders', 'channel')) !== 'enum') {
            return;
        }

        $grammar = $connection->getQueryGrammar();
        $table = $grammar->wrapTable('reminders');
        $column = $grammar->wrap('channel');
        $result = $connection->selectOne('SHOW CREATE TABLE '.$table, [], false);
        $definition = array_values((array) $result)[1] ?? '';

        // Preserve SQL literal spelling, including escaped and doubled quotes.
        $literal = <<<'REGEX'
'(?:[^'\\]|\\.|'')*'
REGEX;
        $pattern = '/^\s*'.preg_quote($column, '/').'\s+(enum\('.$literal.'(?:,\s*'.$literal.')*\))(.*)$/mi';

        if (! preg_match($pattern, $definition, $matches)) {
            throw new \RuntimeException('Cannot safely parse the live reminders.channel enum definition; no alteration performed.');
        }

        preg_match_all('/'.$literal.'/', $matches[1], $values);

        $missing = [];
        foreach (['in_app', 'push', 'email', 'multiple'] as $channel) {
            $quoted = "'".$channel."'";

            if (! in_array($quoted, $values[0], true)) {
                $missing[] = $quoted;
            }
        }

        if ($missing === []) {
            return;
        }

        // Append only: preserve every legacy label and its ENUM ordinal value.
        $expandedType = substr($matches[1], 0, -1).','.implode(',', $missing).')';
        $attributes = preg_replace('/,\s*$/', '', rtrim($matches[2]));

        // Keep the live DEFAULT, nullability, collation, comments and attributes.
        // ALTER may lock/rebuild reminders depending on server version and size.
        $connection->statement('ALTER TABLE '.$table.' MODIFY COLUMN '.$column.' '.$expandedType.$attributes);
    }

    public function down(): void
    {
        // Intentional non-destructive rollback: retain the expanded schema.
        // The original deployment-specific enum is not recorded, and removing
        // labels could corrupt reminders created after up(). This does not undo
        // the widening; an exact reversal needs a reviewed, data-validated repair.
        Log::warning('Reminder channel rollback is a non-destructive NO-OP: the expanded enum is retained. Exact schema reversal requires a separately reviewed migration.');
    }
};
