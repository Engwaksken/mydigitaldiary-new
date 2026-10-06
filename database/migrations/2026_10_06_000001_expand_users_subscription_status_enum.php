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

        if (! $schema->hasColumn('users', 'subscription_status')) {
            throw new \RuntimeException('users.subscription_status is missing; this migration only widens an existing enum.');
        }

        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $this->repairSqliteEnumCheck($connection, 'users', 'subscription_status');

            return;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            Log::warning('Subscription status enum repair skipped for unsupported driver; any subscription_status enum or CHECK constraint requires a separate reviewed repair.', [
                'driver' => $driver,
            ]);

            return;
        }

        // Existing VARCHAR columns already accept the canonical states.
        if (strtolower($schema->getColumnType('users', 'subscription_status')) !== 'enum') {
            return;
        }

        $grammar = $connection->getQueryGrammar();
        $table = $grammar->wrapTable('users');
        $column = $grammar->wrap('subscription_status');
        $result = $connection->selectOne('SHOW CREATE TABLE '.$table, [], false);
        $definition = array_values((array) $result)[1] ?? '';

        // Match complete SQL string literals, including escaped and doubled quotes.
        // Preserve the server's literal spelling rather than decoding/requoting it.
        $literal = <<<'REGEX'
'(?:[^'\\]|\\.|'')*'
REGEX;
        $pattern = '/^\s*'.preg_quote($column, '/').'\s+(enum\('.$literal.'(?:,\s*'.$literal.')*\))(.*)$/mi';

        if (! preg_match($pattern, $definition, $matches)) {
            throw new \RuntimeException('Cannot safely parse the live subscription_status enum definition; no alteration performed.');
        }

        preg_match_all('/'.$literal.'/', $matches[1], $values);

        $missing = [];
        foreach (['active', 'trial', 'inactive', 'expired', 'suspended', 'cancelled'] as $status) {
            $quoted = "'".$status."'";

            if (! in_array($quoted, $values[0], true)) {
                $missing[] = $quoted;
            }
        }

        if ($missing === []) {
            return;
        }

        // Append only: existing ENUM ordinal values and all legacy labels survive.
        $expandedType = substr($matches[1], 0, -1).','.implode(',', $missing).')';
        $attributes = preg_replace('/,\s*$/', '', rtrim($matches[2]));

        // Retain the exact live DEFAULT, NULL/NOT NULL, collation, comment and
        // other attributes. This ALTER can acquire locks or rebuild users on
        // some server versions; schedule and review it before production use.
        $connection->statement('ALTER TABLE '.$table.' MODIFY COLUMN '.$column.' '.$expandedType.$attributes);
    }

    public function down(): void
    {
        // Intentional non-destructive NO-OP: retain the widened enum / SQLite
        // VARCHAR. Restoring legacy restrictions could invalidate stored states.
        // Exact reversal needs a separately reviewed, data-validated migration.
        Log::warning('Subscription status rollback is a non-destructive NO-OP: the widened schema is retained.');
    }
};
