<?php

namespace Tests\Unit;

use Tests\TestCase;

class DatabaseQueueAfterCommitTest extends TestCase
{
    public function test_database_queue_defers_jobs_until_transaction_commit(): void
    {
        $this->assertTrue(config('queue.connections.database.after_commit'));
    }
}
