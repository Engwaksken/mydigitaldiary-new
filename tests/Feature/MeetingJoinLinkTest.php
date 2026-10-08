<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\User;
use App\Mail\MeetingInvitationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingJoinLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_meeting_exposes_a_join_link_to_its_owner_and_invited_attendee(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $attendee = User::factory()->create(['email' => 'attendee@example.com']);
        $meeting = Meeting::create([
            'user_id' => $owner->id,
            'title' => 'Diary meeting',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
            'attendees' => ' attendee@example.com ; other@example.com ',
        ]);

        $this->assertSame(route('meetings.join', $meeting), $meeting->diary_join_url);
        $this->actingAs($owner)->get($meeting->diary_join_url)
            ->assertOk()
            ->assertSee('Diary meeting');
        $this->actingAs($attendee)->get($meeting->diary_join_url)
            ->assertOk()
            ->assertSee('Diary meeting');
    }

    public function test_join_link_requires_authentication_and_an_exact_invited_email(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $stranger = User::factory()->create(['email' => 'attendee@example.com.evil']);
        $meeting = Meeting::create([
            'user_id' => $owner->id,
            'title' => 'Diary meeting',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
            'attendees' => 'attendee@example.com',
        ]);

        $this->get($meeting->diary_join_url)->assertRedirect(route('login'));
        $this->actingAs($stranger)->get($meeting->diary_join_url)->assertForbidden();
    }

    public function test_authorized_internal_join_shows_an_external_destination_interstitial(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $attendee = User::factory()->create(['email' => 'attendee@example.com']);
        $meeting = Meeting::create([
            'user_id' => $owner->id,
            'title' => 'Hosted meeting',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
            'attendees' => 'attendee@example.com',
            'location' => 'https://meet.example.test/room',
        ]);

        $this->actingAs($owner)->get($meeting->diary_join_url)
            ->assertOk()
            ->assertSee('You’re leaving My Digital Diary')
            ->assertSee('meet.example.test')
            ->assertSee('https://meet.example.test/room', false);
        $this->actingAs($attendee)->get($meeting->diary_join_url)
            ->assertOk()
            ->assertSee('You’re leaving My Digital Diary');
    }

    public function test_external_meeting_has_no_diary_join_link_and_join_route_is_not_found(): void
    {
        $owner = User::factory()->create();
        $meeting = Meeting::create([
            'user_id' => $owner->id,
            'title' => 'Imported meeting',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
            'external_platform' => 'google',
            'external_id' => 'calendar-event-1',
            'location' => 'https://meet.example.test/existing-link',
        ]);

        $this->assertNull($meeting->diary_join_url);
        $this->actingAs($owner)->get(route('meetings.join', $meeting))->assertNotFound();
    }

    public function test_join_route_uses_authentication_without_the_subscription_gate(): void
    {
        $middleware = app('router')->getRoutes()->getByName('meetings.join')->gatherMiddleware();

        $this->assertContains('auth', $middleware);
        $this->assertContains('verified', $middleware);
        $this->assertNotContains(\App\Http\Middleware\EnsureSubscribedOrOrganizationMember::class, $middleware);
    }

    public function test_join_link_requires_a_verified_email_for_owners_and_invitees(): void
    {
        $owner = User::factory()->unverified()->create(['email' => 'owner@example.com']);
        $invitee = User::factory()->unverified()->create(['email' => 'invitee@example.com']);
        $meeting = Meeting::create([
            'user_id' => $owner->id,
            'title' => 'Diary meeting',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
            'attendees' => 'invitee@example.com',
        ]);

        $this->actingAs($owner)->get($meeting->diary_join_url)
            ->assertRedirect(route('verification.notice'));
        $this->actingAs($invitee)->get($meeting->diary_join_url)
            ->assertRedirect(route('verification.notice'));
    }

    public function test_copied_external_meeting_stays_external_after_its_source_is_deleted(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $source = Meeting::create([
            'user_id' => $owner->id,
            'title' => 'Imported meeting',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
            'attendees' => 'invitee@example.com',
            'external_platform' => 'google',
            'external_id' => 'external-event',
        ]);

        $request = \Illuminate\Http\Request::create(route('meetings.add-to-calendar', $source), 'POST');
        $request->setUserResolver(fn () => $invitee);
        app(\App\Http\Controllers\MeetingController::class)->addToCalendar($request, $source->id);
        $copy = Meeting::where('user_id', $invitee->id)->sole();
        $this->assertFalse($copy->isInternallyCreated());
        $source->delete();

        $copy->refresh();
        $this->assertNull($copy->copied_from_meeting_id);
        $this->assertFalse($copy->isInternallyCreated());
        $this->assertNull($copy->diary_join_url);
        $this->actingAs($invitee)->get(route('meetings.join', $copy))->assertNotFound();
    }

    public function test_bulk_deleting_an_external_source_preserves_its_copy_classification(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $source = Meeting::create([
            'user_id' => $owner->id,
            'title' => 'Imported meeting',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
            'attendees' => 'invitee@example.com',
            'external_platform' => 'google',
            'external_id' => 'external-event',
        ]);

        $copyRequest = \Illuminate\Http\Request::create(route('meetings.add-to-calendar', $source), 'POST');
        $copyRequest->setUserResolver(fn () => $invitee);
        app(\App\Http\Controllers\MeetingController::class)->addToCalendar($copyRequest, $source->id);

        $deleteRequest = \Illuminate\Http\Request::create(route('meetings.bulk-destroy'), 'DELETE', [
            'ids' => [$source->id],
        ]);
        $deleteRequest->setUserResolver(fn () => $owner);
        app(\App\Http\Controllers\MeetingController::class)->bulkDestroy($deleteRequest);

        $copy = Meeting::where('user_id', $invitee->id)->sole();
        $this->assertNull($copy->copied_from_meeting_id);
        $this->assertFalse($copy->isInternallyCreated());
    }

    public function test_api_bulk_deleting_an_external_source_preserves_its_copy_classification(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $source = Meeting::create([
            'user_id' => $owner->id,
            'title' => 'Imported meeting',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
            'external_platform' => 'google',
            'external_id' => 'external-event',
        ]);
        $copy = Meeting::create([
            'user_id' => $invitee->id,
            'copied_from_meeting_id' => $source->id,
            'title' => 'Copied imported meeting',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $request = \Illuminate\Http\Request::create('/api/meetings/bulk-delete', 'POST', [
            'ids' => [$source->id],
        ]);
        $request->setUserResolver(fn () => $owner);
        app(\App\Http\Controllers\Api\MeetingController::class)->bulkDestroy($request);

        $copy->refresh();
        $this->assertNull($copy->copied_from_meeting_id);
        $this->assertFalse($copy->isInternallyCreated());
    }

    public function test_only_http_and_https_locations_are_available_as_external_urls(): void
    {
        $meeting = new Meeting(['location' => 'javascript:alert(1)']);
        $this->assertNull($meeting->safe_external_url);

        $meeting->location = 'data:text/html,unsafe';
        $this->assertNull($meeting->safe_external_url);

        $meeting->location = 'https://meet.example.test/room';
        $this->assertSame('https://meet.example.test/room', $meeting->safe_external_url);
    }

    public function test_invitation_mail_includes_diary_join_link_only_for_internal_meetings(): void
    {
        $owner = User::factory()->create();
        $internal = Meeting::create([
            'user_id' => $owner->id,
            'title' => 'Internal',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);
        $external = Meeting::create([
            'user_id' => $owner->id,
            'title' => 'External',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
            'external_platform' => 'google',
            'external_id' => 'event',
        ]);

        $internalMail = (new MeetingInvitationMail($internal, 'Owner'))->render();
        $externalMail = (new MeetingInvitationMail($external, 'Owner'))->render();

        $this->assertStringContainsString(route('meetings.join', $internal), $internalMail);
        $this->assertStringNotContainsString('Join meeting', $externalMail);
        $this->assertStringNotContainsString('href="https://meet.example.test/existing-link"', $externalMail);
    }

    public function test_meeting_ui_join_links_use_the_protected_route_and_not_an_external_url(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $meeting = Meeting::create([
            'user_id' => $owner->id,
            'title' => 'Hosted meeting',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
            'location' => 'https://meet.example.test/room',
        ]);

        $this->actingAs($owner);
        $calendar = view('crud.extras.meetings-top', [
            'nearestMeeting' => $meeting,
            'statusFilter' => null,
        ])->render();

        $this->assertStringContainsString(route('meetings.join', $meeting), $calendar);
        $this->assertStringNotContainsString('href="https://meet.example.test/room"', $calendar);
        // The table cell markup lives in crud/_cell.blade.php (included by crud/index).
        $this->assertStringContainsString('href="{{ $item->diary_join_url }}"', file_get_contents(resource_path('views/crud/_cell.blade.php')));
        $this->assertStringNotContainsString('href="{{ $meetingSafeExternalUrl }}"', file_get_contents(resource_path('views/crud/_cell.blade.php')));
    }

    public function test_join_feature_does_not_expose_recording_stream_to_invitees(): void
    {
        $middleware = app('router')->getRoutes()->getByName('meeting-recordings.audio.stream')->gatherMiddleware();

        $this->assertContains(\App\Http\Middleware\EnsureSubscribedOrOrganizationMember::class, $middleware);
        $this->assertStringNotContainsString(
            'meeting-recordings.audio.stream',
            file_get_contents(resource_path('views/meetings/join.blade.php'))
        );
    }
    
    public function test_invitation_email_for_internal_meeting_with_external_url_does_not_expose_raw_url(): void
{
    $owner = User::factory()->create();
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Hosted meeting',
        'start_at' => now()->addDay(),
        'status' => 'scheduled',
        'location' => 'https://meet.example.test/room',
    ]);

    $html = (new MeetingInvitationMail($meeting, 'Owner'))->render();

    $this->assertStringNotContainsString('https://meet.example.test/room', $html);
    $this->assertStringContainsString(route('meetings.join', $meeting), $html);
}
}
