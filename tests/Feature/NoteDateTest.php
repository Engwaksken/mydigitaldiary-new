<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_note_defaults_to_today_when_no_date_is_given(): void
    {
        $user = User::factory()->create();

        $note = Note::create(['user_id' => $user->id, 'title' => 'Journal entry']);

        $this->assertSame(now()->toDateString(), $note->fresh()->note_date->toDateString());
    }

    public function test_note_keeps_an_explicit_date(): void
    {
        $user = User::factory()->create();

        $note = Note::create(['user_id' => $user->id, 'title' => 'Backdated', 'note_date' => '2026-10-01']);

        $this->assertSame('2026-10-01', $note->fresh()->note_date->toDateString());
    }
}
