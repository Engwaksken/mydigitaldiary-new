<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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

    public function test_meetings_page_exposes_day_week_and_month_filters(): void
    {
        $html = $this->actingAs($this->subscriber())
            ->get(route('meetings.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<option value="daily"', $html);
        $this->assertStringContainsString('<option value="weekly"', $html);
        $this->assertStringContainsString('<option value="monthly"', $html);
        $this->assertStringContainsString('Today', $html);
        $this->assertStringContainsString('This week', $html);
        $this->assertStringContainsString('This month', $html);
        $this->assertStringContainsString('aria-label="Filter meetings by date"', $html);
        $this->assertStringContainsString(route('meetings.index', ['period' => 'daily']), $html);
        $this->assertStringContainsString(route('meetings.index', ['period' => 'weekly']), $html);
        $this->assertStringContainsString(route('meetings.index', ['period' => 'monthly']), $html);
    }

    public function test_happening_now_prefers_an_active_meeting_over_one_that_has_ended(): void
    {
        $user = $this->subscriber();
        Meeting::create([
            'user_id' => $user->id,
            'title' => 'Already ended',
            'start_at' => now()->subHours(2),
            'end_at' => now()->subHour(),
            'status' => 'scheduled',
        ]);
        Meeting::create([
            'user_id' => $user->id,
            'title' => 'Currently active',
            'start_at' => now()->subMinutes(20),
            'end_at' => now()->addMinutes(20),
            'status' => 'scheduled',
        ]);

        $html = $this->actingAs($user)->get(route('meetings.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Happening now', $html);
        $this->assertStringContainsString('Currently active', $html);
    }

    public function test_due_reminder_for_completed_meeting_is_deactivated_without_sending(): void
    {
        $user = $this->subscriber();
        $meeting = Meeting::create([
            'user_id' => $user->id,
            'title' => 'Finished meeting',
            'start_at' => now()->subHour(),
            'status' => 'completed',
        ]);
        $reminder = Reminder::create([
            'user_id' => $user->id,
            'title' => 'Meeting: Finished meeting',
            'frequency' => 'once',
            'next_run_at' => now()->subMinute(),
            'channel' => 'database',
            'is_active' => true,
            'source_type' => Meeting::class,
            'source_id' => $meeting->id,
        ]);

        Artisan::call('reminders:send');

        $this->assertFalse($reminder->fresh()->is_active);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $user->id,
            'type' => \App\Notifications\ReminderNotification::class,
        ]);
    }
}
