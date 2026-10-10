<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationManagementTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user): string
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\Notifications\AnnouncementNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['title' => 'Hello', 'body' => 'World']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_notifications_page_renders_delete_controls(): void
    {
        $user = User::factory()->create();
        $this->notify($user);

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Delete selected', false)
            ->assertSee('name="ids[]"', false);
    }

    public function test_user_can_delete_one_notification(): void
    {
        $user = User::factory()->create();
        $id = $this->notify($user);

        $this->actingAs($user)
            ->delete(route('notifications.destroy', $id))
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', ['id' => $id]);
    }

    public function test_user_can_bulk_delete_notifications(): void
    {
        $user = User::factory()->create();
        $a = $this->notify($user);
        $b = $this->notify($user);

        $this->actingAs($user)
            ->delete(route('notifications.bulk-destroy'), ['ids' => [$a, $b]])
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', ['id' => $a]);
        $this->assertDatabaseMissing('notifications', ['id' => $b]);
    }
}
