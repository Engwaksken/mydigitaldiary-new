<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_convenience_accounts_are_created_in_testing_environment(): void
    {
        app()->detectEnvironment(fn () => 'testing');

        app(DatabaseSeeder::class)->run();

        $this->assertDatabaseHas('users', ['email' => 'admin@kemmytech.com']);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_convenience_accounts_are_not_created_outside_local_and_testing(): void
    {
        app()->detectEnvironment(fn () => 'production');

        app(DatabaseSeeder::class)->run();

        $this->assertDatabaseMissing('users', ['email' => 'admin@kemmytech.com']);
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
