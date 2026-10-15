<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StickyNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_a_note_color_and_see_sticky_note_cards(): void
    {
        $user = User::factory()->create(['subscription_status' => 'active']);

        $this->actingAs($user)->post(route('notes.store'), [
            'title' => 'Ideas for later',
            'content' => 'A colorful note.',
            'category' => 'Personal',
            'color' => 'lavender',
        ])->assertRedirect(route('notes.index'));

        $note = Note::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('lavender', $note->color);

        $this->actingAs($user)->get(route('notes.index'))
            ->assertOk()
            ->assertSee('pm-sticky-note')
            ->assertSee('Ideas for later')
            ->assertSee('A colorful note.')
            ->assertSee('var(--note-paper)');
    }

    public function test_note_api_accepts_only_supported_sticky_note_colors(): void
    {
        $user = User::factory()->create(['subscription_status' => 'active']);
        Sanctum::actingAs($user);

        $this->postJson('/api/notes', [
            'title' => 'Mobile color note',
            'color' => 'mint',
        ])->assertCreated()->assertJsonPath('color', 'mint');

        $this->postJson('/api/notes', [
            'title' => 'Invalid color note',
            'color' => 'neon',
        ])->assertUnprocessable()->assertJsonValidationErrors('color');
    }
}
