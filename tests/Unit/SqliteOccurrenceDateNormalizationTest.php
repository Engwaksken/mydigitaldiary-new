<?php

namespace Tests\Unit;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SqliteOccurrenceDateNormalizationTest extends TestCase
{
    private const CONNECTION = 'occurrence_normalization_test';
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = DB::getDefaultConnection();
        config(['database.connections.'.self::CONNECTION => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection(self::CONNECTION);
        // Isolated legacy schema fixtures allow malformed historical text and
        // a missing-index scenario without bypassing application model factories.
        DB::statement('CREATE TABLE daily_plan_item_occurrences (
            id INTEGER PRIMARY KEY, daily_plan_item_id INTEGER NOT NULL, user_id INTEGER NOT NULL,
            occurrence_date TEXT NOT NULL, is_completed INTEGER NOT NULL DEFAULT 0,
            completed_at TEXT, is_skipped INTEGER NOT NULL DEFAULT 0,
            title_override TEXT, description_override TEXT, priority_override TEXT,
            start_time_override TEXT, end_time_override TEXT, personal_goal_id_override INTEGER,
            created_at TEXT, updated_at TEXT
        )');
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);
        DB::setDefaultConnection($this->originalConnection);
        parent::tearDown();
    }

    private function db(): Connection
    {
        return DB::connection(self::CONNECTION);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_06_000003_normalize_sqlite_daily_plan_item_occurrence_dates.php');
    }

    public function test_only_exact_valid_midnight_dates_are_normalized_without_changing_other_data(): void
    {
        $dates = [
            '2026-10-20 00:00:00' => '2026-10-20',
            '2028-02-29 00:00:00' => '2028-02-29',
            '0001-01-01 00:00:00' => '0001-01-01',
            '9999-12-31 00:00:00' => '9999-12-31',
            '2026-10-20' => '2026-10-20',
            '2026-10-20 09:00:00' => '2026-10-20 09:00:00',
            '2026-10-20T00:00:00' => '2026-10-20T00:00:00',
            '2026-10-20 00:00:00.000' => '2026-10-20 00:00:00.000',
            '2026-10-20 00:00:00Z' => '2026-10-20 00:00:00Z',
            '2026-02-30 00:00:00' => '2026-02-30 00:00:00',
            '2026-02-29 00:00:00' => '2026-02-29 00:00:00',
            '2026-13-01 00:00:00' => '2026-13-01 00:00:00',
            '0000-01-01 00:00:00' => '0000-01-01 00:00:00',
            ' 2026-10-20 00:00:00' => ' 2026-10-20 00:00:00',
            '2026-1-20 00:00:00' => '2026-1-20 00:00:00',
        ];
        $expected = [];
        $id = 0;
        foreach ($dates as $legacy => $normalized) {
            $row = [
                'id' => ++$id, 'daily_plan_item_id' => $id, 'user_id' => 7,
                'occurrence_date' => $legacy, 'is_completed' => 1, 'completed_at' => '2026-10-20 09:31:00',
                'is_skipped' => 1, 'title_override' => 'Edited walk', 'description_override' => 'User notes',
                'priority_override' => 'high', 'start_time_override' => '09:05:15', 'end_time_override' => '09:35:45',
                'personal_goal_id_override' => 42, 'created_at' => '2026-10-01 10:00:00', 'updated_at' => '2026-10-20 09:31:00',
            ];
            $this->db()->table('daily_plan_item_occurrences')->insert($row);
            $expected[] = (object) array_replace($row, ['occurrence_date' => $normalized]);
        }

        $migration = $this->migration();
        $migration->up();
        $this->assertEquals($expected, $this->db()->table('daily_plan_item_occurrences')->orderBy('id')->get()->all());
        $migration->up();
        $migration->down();
        $this->assertEquals($expected, $this->db()->table('daily_plan_item_occurrences')->orderBy('id')->get()->all());
    }

    public static function collisions(): array
    {
        return [
            'canonical same user' => ['2026-10-20', 7, true],
            'canonical different user actual unique key' => ['2026-10-20', 8, true],
            'duplicate legacy without unique index' => ['2026-10-20 00:00:00', 7, false],
        ];
    }

    #[DataProvider('collisions')]
    public function test_collisions_refuse_all_changes_including_noncolliding_rows(string $existingDate, int $existingUser, bool $indexed): void
    {
        $db = $this->db();
        if ($indexed) {
            $db->statement('CREATE UNIQUE INDEX occurrence_unique ON daily_plan_item_occurrences (daily_plan_item_id, occurrence_date)');
        }
        $db->table('daily_plan_item_occurrences')->insert([
            ['id' => 1, 'daily_plan_item_id' => 1, 'user_id' => 7, 'occurrence_date' => '2026-10-06 00:00:00', 'is_completed' => 0, 'title_override' => 'Noncolliding'],
            ['id' => 2, 'daily_plan_item_id' => 2, 'user_id' => 7, 'occurrence_date' => '2026-10-20 00:00:00', 'is_completed' => 1, 'title_override' => 'Legacy edits'],
            ['id' => 3, 'daily_plan_item_id' => 2, 'user_id' => $existingUser, 'occurrence_date' => $existingDate, 'is_completed' => 0, 'title_override' => 'Other edits'],
        ]);
        $before = $db->table('daily_plan_item_occurrences')->orderBy('id')->get()->all();
        $level = $db->transactionLevel();

        try {
            $this->migration()->up();
            $this->fail('Colliding occurrence records must not be merged or discarded.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('normalization refused', $e->getMessage());
            $this->assertStringContainsString('No occurrence dates were changed', $e->getMessage());
        }

        $this->assertEquals($before, $db->table('daily_plan_item_occurrences')->orderBy('id')->get()->all());
        $this->assertSame($level, $db->transactionLevel());
    }
}
