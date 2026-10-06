<?php

trait RepairsSqliteEnumChecks
{
    private function repairSqliteEnumCheck($connection, string $table, string $column): void
    {
        $tableName = $connection->getTablePrefix().$table;
        $quote = static fn (string $name): string => '"'.str_replace('"', '""', $name).'"';
        $definition = $connection->selectOne(
            "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?",
            [$tableName], false,
        );
        $sql = (string) ($definition->sql ?? '');

        // Recognize Laravel's inline ENUM CHECK only. Keep every other byte of
        // the live declaration, including other CHECKs, defaults and FK clauses.
        $identifier = '(?:"'.preg_quote($column, '~').'"|`'.preg_quote($column, '~').'`|\['.preg_quote($column, '~').'\]|\b'.preg_quote($column, '~').'\b)';
        $literal = "'(?:[^']|'')*'";
        $pattern = '~\bCHECK\s*\(\s*'.$identifier.'\s+IN\s*\(\s*'.$literal.'(?:\s*,\s*'.$literal.')*\s*\)\s*\)~i';
        $repaired = preg_replace($pattern, '', $sql, -1, $count);

        if ($count === 0) {
            if (preg_match('~\bCHECK\s*\([^)]*'.$identifier.'~i', $sql)) {
                throw new \RuntimeException("Cannot recognize the SQLite {$table}.{$column} enum CHECK safely; no alteration performed.");
            }

            return;
        }

        // A table-level/named CHECK needs a separate parser/review, not this repair.
        $checkBody = substr($pattern, 1, -2);
        if ($count !== 1 || preg_match('~(?:,|\bCONSTRAINT\s+\S+)\s*'.$checkBody.'~i', $sql)) {
            throw new \RuntimeException('Ambiguous SQLite enum CHECK; no alteration performed.');
        }
        $columns = $connection->getSchemaBuilder()->getColumns($table);
        $target = array_values(array_filter($columns, static fn ($item) => $item['name'] === $column))[0];
        if (! in_array($target['type_name'], ['varchar', 'text'], true)) {
            throw new \RuntimeException('SQLite enum repair requires a VARCHAR/TEXT column; no alteration performed.');
        }

        $temporary = '__enum_repair_'.$tableName;
        $header = '~\ACREATE\s+TABLE\s+(?:"'.preg_quote($tableName, '~').'"|`'.preg_quote($tableName, '~').'`|\['.preg_quote($tableName, '~').'\]|'.preg_quote($tableName, '~').')\s*\(~i';
        $create = preg_replace($header, 'CREATE TABLE '.$quote($temporary).' (', $repaired, 1, $headers);
        if ($headers !== 1 || $connection->getSchemaBuilder()->hasTable($temporary)) {
            throw new \RuntimeException('Cannot safely prepare SQLite enum repair table; no alteration performed.');
        }
        $objects = $connection->select(
            "SELECT sql FROM sqlite_master WHERE tbl_name = ? AND type IN ('index', 'trigger') AND sql IS NOT NULL ORDER BY type, name",
            [$tableName], false,
        );
        $names = implode(', ', array_map($quote, array_column(array_filter(
            $columns, static fn ($item) => $item['generation'] === null,
        ), 'name')));
        $foreignKeys = (bool) $connection->scalar('PRAGMA foreign_keys');
        if ($connection->transactionLevel() !== 0) {
            throw new \RuntimeException('SQLite enum repair must run outside an existing transaction so foreign-key enforcement can be safely restored.');
        }

        // Preserve AUTOINCREMENT high-water marks, including deleted row IDs.
        $sequence = null;
        if ($connection->selectOne("SELECT name FROM sqlite_master WHERE name = 'sqlite_sequence'", [], false)) {
            $sequence = $connection->selectOne('SELECT seq FROM sqlite_sequence WHERE name = ?', [$tableName], false)?->seq;
        }

        // Production risk: this copies the entire table and physically drops /
        // replaces it inside a transaction. Use a backup and maintenance window;
        // disk space and an exclusive writer lock are required. No row is removed
        // logically, and all original indexes, triggers and FK clauses survive.
        $connection->statement('PRAGMA foreign_keys = OFF');
        try {
            $connection->transaction(function () use ($connection, $create, $quote, $temporary, $tableName, $names, $objects, $sequence): void {
                $connection->statement($create);
                $connection->statement('INSERT INTO '.$quote($temporary).' ('.$names.') SELECT '.$names.' FROM '.$quote($tableName));
                $connection->statement('DROP TABLE '.$quote($tableName));
                $connection->statement('ALTER TABLE '.$quote($temporary).' RENAME TO '.$quote($tableName));
                foreach ($objects as $object) {
                    $connection->statement($object->sql);
                }
                if ($sequence !== null) {
                    if ($connection->update('UPDATE sqlite_sequence SET seq = MAX(seq, ?) WHERE name = ?', [$sequence, $tableName]) === 0) {
                        $connection->insert('INSERT INTO sqlite_sequence (name, seq) VALUES (?, ?)', [$tableName, $sequence]);
                    }
                }
                if ($connection->select('PRAGMA foreign_key_check', [], false) !== []) {
                    throw new \RuntimeException('SQLite foreign-key validation failed; enum repair rolled back.');
                }
            });
        } finally {
            $connection->statement('PRAGMA foreign_keys = '.($foreignKeys ? 'ON' : 'OFF'));
        }
    }
}
