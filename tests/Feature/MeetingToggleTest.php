<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeetingToggleTest extends TestCase
{
    use RefreshDatabase;

    private function subscriber(): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
        ])->save();

        return $user;
    }

    private function meeting(User $user, string $status = 'scheduled'): Meeting
    {
        return Meeting::create([
            'user_id' => $user->id,
            'title' => 'Weekly sync',
            'start_at' => now()->addDay(),
            'status' => $status,
            'meeting_status' => $status,
        ]);
    }

    public function test_web_toggle_marks_meeting_completed_and_back(): void
    {
        $user = $this->subscriber();
        $meeting = $this->meeting($user);

        $this->actingAs($user)
            ->patch(route('meetings.toggle', $meeting))
            ->assertRedirect();

        $this->assertSame('completed', $meeting->fresh()->status);
        $this->assertSame('completed', $meeting->fresh()->meeting_status);

        $this->actingAs($user)
            ->patch(route('meetings.toggle', $meeting))
            ->assertRedirect();

        $this->assertSame('scheduled', $meeting->fresh()->status);
        $this->assertSame('scheduled', $meeting->fresh()->meeting_status);
    }

    public function test_api_toggle_marks_meeting_completed_and_back(): void
    {
        $user = $this->subscriber();
        $meeting = $this->meeting($user);
        Sanctum::actingAs($user);

        $this->patchJson('/api/meetings/'.$meeting->id.'/toggle')
            ->assertOk()
            ->assertJsonPath('status', 'completed');
        $this->assertSame('completed', $meeting->fresh()->status);

        $this->patchJson('/api/meetings/'.$meeting->id.'/toggle')
            ->assertOk()
            ->assertJsonPath('status', 'scheduled');
        $this->assertSame('scheduled', $meeting->fresh()->status);
    }

    public function test_non_owner_cannot_toggle_a_meeting(): void
    {
        $owner = $this->subscriber();
        $other = $this->subscriber();
        $meeting = $this->meeting($owner);
        Sanctum::actingAs($other);

        $this->patchJson('/api/meetings/'.$meeting->id.'/toggle')->assertNotFound();
        $this->assertSame('scheduled', $meeting->fresh()->status);
    }
}
