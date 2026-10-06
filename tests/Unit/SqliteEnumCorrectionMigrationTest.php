<?php

namespace Tests\Unit;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SqliteEnumCorrectionMigrationTest extends TestCase
{
    private const CONNECTION = 'enum_correction_test';
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = DB::getDefaultConnection();
        // A fresh isolated database is required: the repair deliberately refuses
        // RefreshDatabase's outer transaction while toggling foreign keys.
        config(['database.connections.'.self::CONNECTION => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection(self::CONNECTION);
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);
        DB::setDefaultConnection($this->originalConnection);
        parent::tearDown();
    }

    public static function corrections(): array
    {
        return [
            'subscription status' => ['users', 'subscription_status', 'trialing', 'canceled', 'trial', '2026_10_06_000001_expand_users_subscription_status_enum.php'],
            'reminder channel' => ['reminders', 'channel', 'mail', 'database', 'multiple', '2026_10_06_000002_expand_reminders_channel_enum.php'],
        ];
    }

    private function fixture(string $table, string $column, string $first, string $second): Connection
    {
        $db = DB::connection(self::CONNECTION);
        // These are legacy schema/data fixtures, not application model setup.
        $db->statement('CREATE TABLE owners (id INTEGER PRIMARY KEY)');
        $db->table('owners')->insert(['id' => 1]);
        $db->statement("CREATE TABLE \"{$table}\" (id INTEGER PRIMARY KEY AUTOINCREMENT, owner_id INTEGER NOT NULL REFERENCES owners(id), \"{$column}\" VARCHAR NOT NULL DEFAULT '{$first}' CHECK (\"{$column}\" IN ('{$first}', '{$second}')), marker INTEGER NOT NULL DEFAULT 7 CHECK (marker >= 0))");
        $db->statement("CREATE UNIQUE INDEX legacy_unique ON \"{$table}\" (owner_id, marker)");
        $db->statement("CREATE INDEX legacy_partial ON \"{$table}\" (\"{$column}\") WHERE marker > 0");
        $db->statement("CREATE TABLE dependents (id INTEGER PRIMARY KEY, target_id INTEGER NOT NULL REFERENCES \"{$table}\"(id) ON DELETE CASCADE)");
        $db->statement('CREATE TABLE audit (target_id INTEGER NOT NULL)');
        $db->statement("CREATE TRIGGER legacy_audit AFTER INSERT ON \"{$table}\" BEGIN INSERT INTO audit (target_id) VALUES (NEW.id); END");
        $db->table($table)->insert([
            ['id' => 1, 'owner_id' => 1, $column => $first, 'marker' => 10],
            ['id' => 2, 'owner_id' => 1, $column => $second, 'marker' => 20],
        ]);
        $db->table('dependents')->insert(['id' => 1, 'target_id' => 1]);
        return $db;
    }

    private function migration(string $file): object
    {
        $migration = require database_path('migrations/'.$file);
        return $migration;
    }

    #[DataProvider('corrections')]
    public function test_widening_preserves_legacy_rows_and_accepts_new_values(string $table, string $column, string $first, string $second, string $new, string $file): void
    {
        $db = $this->fixture($table, $column, $first, $second);
        $before = $db->table($table)->orderBy('id')->get()->toArray();
        try {
            $db->table($table)->where('id', 1)->update([$column => $new]);
            $this->fail('The legacy enum CHECK must reject the new value before repair.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('CHECK constraint failed', $e->getMessage());
        }

        $migration = $this->migration($file);
        $migration->up();

        $this->assertEquals($before, $db->table($table)->orderBy('id')->get()->toArray());
        $db->table($table)->where('id', 1)->update([$column => $new]);
        $this->assertSame($new, $db->table($table)->where('id', 1)->value($column));
        // Re-running and non-destructive down must not discard new values.
        $migration->up();
        $migration->down();
        $this->assertSame($new, $db->table($table)->where('id', 1)->value($column));
    }

    #[DataProvider('corrections')]
    public function test_rebuild_preserves_indexes_triggers_and_foreign_keys(string $table, string $column, string $first, string $second, string $new, string $file): void
    {
        $db = $this->fixture($table, $column, $first, $second);
        $objects = fn () => $db->select("SELECT name, sql FROM sqlite_master WHERE tbl_name = ? AND type IN ('index', 'trigger') ORDER BY name", [$table]);
        $before = $objects();
        $foreignKeys = $db->select("PRAGMA foreign_key_list(\"{$table}\")");

        $this->migration($file)->up();

        $this->assertEquals($before, $objects());
        $this->assertEquals($foreignKeys, $db->select("PRAGMA foreign_key_list(\"{$table}\")"));
        $this->assertSame(1, (int) $db->scalar('PRAGMA foreign_keys'));
        $this->assertSame([], $db->select('PRAGMA foreign_key_check'));
        $this->assertSame(1, $db->table('dependents')->count(), 'Rebuild must not cascade-delete referencing rows.');
        $this->assertSame(2, $db->table('audit')->count(), 'Copying rows must not fire restored triggers.');
        $id = $db->table($table)->insertGetId(['owner_id' => 1, $column => $new, 'marker' => 30]);
        $this->assertSame(1, $db->table('audit')->where('target_id', $id)->count());
    }

    #[DataProvider('corrections')]
    public function test_rebuild_retains_defaults_and_unrelated_checks(string $table, string $column, string $first, string $second, string $new, string $file): void
    {
        $db = $this->fixture($table, $column, $first, $second);
        $this->migration($file)->up();
        $id = $db->table($table)->insertGetId(['owner_id' => 1]);
        $this->assertSame($first, $db->table($table)->where('id', $id)->value($column));
        $this->assertSame(7, $db->table($table)->where('id', $id)->value('marker'));

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('CHECK constraint failed');
        $db->table($table)->where('id', $id)->update(['marker' => -1]);
    }

    #[DataProvider('corrections')]
    public function test_rebuild_preserves_deleted_autoincrement_high_water_mark(string $table, string $column, string $first, string $second, string $new, string $file): void
    {
        $db = $this->fixture($table, $column, $first, $second);
        $db->table($table)->insert(['id' => 100, 'owner_id' => 1, $column => $first, 'marker' => 100]);
        $db->table($table)->where('id', 100)->delete();

        $this->migration($file)->up();

        $this->assertSame(101, $db->table($table)->insertGetId(['owner_id' => 1, $column => $new, 'marker' => 30]));
    }

    #[DataProvider('corrections')]
    public function test_failed_foreign_key_validation_rolls_back_rebuild_and_restores_enforcement(string $table, string $column, string $first, string $second, string $new, string $file): void
    {
        $db = $this->fixture($table, $column, $first, $second);
        $db->statement('PRAGMA foreign_keys = OFF');
        $db->table('dependents')->insert(['id' => 2, 'target_id' => 999]);
        $db->statement('PRAGMA foreign_keys = ON');
        $definition = $db->scalar("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);
        $before = $db->table($table)->orderBy('id')->get()->toArray();

        try {
            $this->migration($file)->up();
            $this->fail('Invalid legacy foreign keys must abort the rebuild.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('foreign-key validation failed', $e->getMessage());
        }

        $this->assertSame($definition, $db->scalar("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]));
        $this->assertEquals($before, $db->table($table)->orderBy('id')->get()->toArray());
        $this->assertSame(1, (int) $db->scalar('PRAGMA foreign_keys'));
        $this->assertFalse($db->getSchemaBuilder()->hasTable('__enum_repair_'.$table));
    }
}
