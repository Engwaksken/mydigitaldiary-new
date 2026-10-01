<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingsTableTest extends TestCase
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

    public function test_external_meeting_link_shows_as_view_not_the_full_url(): void
    {
        $user = $this->subscriber();
        $url = 'https://us02web.zoom.us/j/81234567890?pwd=averyveryverylongpasswordtoken';

        Meeting::create([
            'user_id' => $user->id,
            'title' => 'Synced call',
            'start_at' => now()->addDay(),
            'location' => $url,
            'status' => 'scheduled',
            // Imported from a calendar, so it isn't joinable through the diary.
            'calendar_provider' => 'google',
            'external_event_id' => 'evt-123',
        ]);

        $html = $this->actingAs($user)->get(route('meetings.index'))->assertOk()->getContent();

        $this->assertStringContainsString('title="View meeting link"', $html);
        // Neither printed as cell text nor linked to directly.
        $this->assertStringNotContainsString(\Illuminate\Support\Str::limit($url, 34), $html);
        $this->assertStringNotContainsString('href="' . e($url) . '"', $html);
    }

    public function test_owner_rows_have_a_record_meeting_icon(): void
    {
        $user = $this->subscriber();
        $meeting = Meeting::create([
            'user_id' => $user->id,
            'title' => 'Weekly sync',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $this->actingAs($user)
            ->get(route('meetings.index'))
            ->assertOk()
            ->assertSee(route('meetings.notes', $meeting->id) . '#record-meeting', false)
            ->assertSee('title="Record meeting"', false);
    }
}
