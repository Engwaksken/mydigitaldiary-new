<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MeetingRecordingFileSizeMbTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_size_mb_is_zero_when_audio_path_is_null(): void
    {
        $recording = $this->recordingForUser(audioPath: null);

        $this->assertSame(0.0, $recording->file_size_mb);
    }

    public function test_file_size_mb_is_zero_when_audio_path_is_set_but_file_is_missing(): void
    {
        Storage::fake('public');

        $recording = $this->recordingForUser();

        $this->assertSame(0.0, $recording->file_size_mb);
    }

    public function test_file_size_mb_returns_rounded_megabytes_when_file_exists(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put(
            'meeting-recordings/recording.webm',
            str_repeat('a', 2 * 1024 * 1024)
        );

        $recording = $this->recordingForUser();

        $this->assertSame(2.0, $recording->file_size_mb);
    }

    /**
     * Exercises the controller -> view data path directly without rendering
     * the Blade template. The HTTP end-to-end render of the notes page is
     * covered by the test below (test_notes_page_renders_file_size_mb_over_http).
     */
    public function test_notes_controller_passes_file_size_mb_on_recordings(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put(
            'meeting-recordings/x.webm',
            str_repeat('a', 2 * 1024 * 1024)
        );

        [$user, $meeting] = $this->meetingForUser();
        MeetingRecording::create([
            'meeting_id' => $meeting->id,
            'recorded_by_user_id' => $user->id,
            'status' => 'completed',
            'audio_path' => 'meeting-recordings/x.webm',
        ]);

        $request = \Illuminate\Http\Request::create('/meetings/'.$meeting->id.'/notes', 'GET');
        $request->setUserResolver(fn () => $user);

        $view = app(\App\Http\Controllers\MeetingController::class)
            ->notes($request, (int) $meeting->id);

        $this->assertInstanceOf(\Illuminate\View\View::class, $view);
        $this->assertSame('meetings.notes', $view->name());

        $data = $view->getData();

        $this->assertArrayHasKey('recordings', $data);
        $items = collect($data['recordings']->items());
        $this->assertTrue($items->isNotEmpty());
        $this->assertSame(2.0, $items->first()->file_size_mb);
    }

    public function test_notes_page_renders_file_size_mb_over_http(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put(
            'meeting-recordings/x.webm',
            str_repeat('a', 2 * 1024 * 1024)
        );

        [$user, $meeting] = $this->meetingForUser();
        MeetingRecording::create([
            'meeting_id' => $meeting->id,
            'recorded_by_user_id' => $user->id,
            'status' => 'completed',
            'audio_path' => 'meeting-recordings/x.webm',
        ]);

        $response = $this->actingAs($user)
            ->get(route('meetings.notes', $meeting));

        $response->assertOk();

        $recordings = $response->viewData('recordings');
        $items = collect($recordings->items());

        $this->assertTrue($items->isNotEmpty());
        $this->assertSame(2.0, $items->first()->file_size_mb);
    }

    public function test_notes_page_redirects_guests_to_login(): void
    {
        [, $meeting] = $this->meetingForUser();

        $this->get(route('meetings.notes', $meeting))
            ->assertRedirect(route('login'));
    }

    public function test_notes_page_is_not_visible_to_a_different_user(): void
    {
        [, $meeting] = $this->meetingForUser();
        $otherUser = User::factory()->create([
            'subscription_status' => 'active',
            'subscription_expires_at' => null,
        ]);

        // The notes controller scopes the lookup to the authenticated
        // user's own meetings (findOrFail), so a stranger gets 404 —
        // not a 403 — and never sees another user's recordings.
        $this->actingAs($otherUser)
            ->get(route('meetings.notes', $meeting))
            ->assertNotFound();
    }

    public function test_check_capacity_returns_file_size_mb_json_key(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put(
            'meeting-recordings/x.webm',
            str_repeat('a', 2 * 1024 * 1024)
        );

        [$user, , $recording] = $this->recordingForUserWithMeeting();

        $response = $this->actingAs($user)
            ->post(route('meeting-recordings.check-capacity', $recording));

        $response->assertOk()
            ->assertJsonStructure(['file_size_mb'])
            ->assertJson(['file_size_mb' => 2.0]);
    }

    public function test_check_capacity_is_forbidden_for_a_different_user(): void
    {
        [, , $recording] = $this->recordingForUserWithMeeting();
        $otherUser = User::factory()->create([
            'subscription_status' => 'active',
            'subscription_expires_at' => null,
        ]);

        $this->actingAs($otherUser)
            ->post(route('meeting-recordings.check-capacity', $recording))
            ->assertForbidden();
    }

    public function test_check_capacity_redirects_guests_to_login(): void
    {
        [, , $recording] = $this->recordingForUserWithMeeting();

        $this->post(route('meeting-recordings.check-capacity', $recording))
            ->assertRedirect(route('login'));
    }

    /** @return array{User, Meeting} */
    private function meetingForUser(): array
    {
        $user = User::factory()->create([
            'subscription_status' => 'active',
            'subscription_expires_at' => null,
        ]);

        $meeting = Meeting::create([
            'user_id' => $user->id,
            'title' => 'Diary meeting',
            'start_at' => now(),
            'status' => 'scheduled',
        ]);

        return [$user, $meeting];
    }

    private function recordingForUser(?string $audioPath = 'meeting-recordings/recording.webm'): MeetingRecording
    {
        [$user, $meeting] = $this->meetingForUser();

        return MeetingRecording::create([
            'meeting_id' => $meeting->id,
            'recorded_by_user_id' => $user->id,
            'status' => 'completed',
            'audio_path' => $audioPath,
        ]);
    }

    /** @return array{User, Meeting, MeetingRecording} */
    private function recordingForUserWithMeeting(): array
    {
        [$user, $meeting] = $this->meetingForUser();

        $recording = MeetingRecording::create([
            'meeting_id' => $meeting->id,
            'recorded_by_user_id' => $user->id,
            'status' => 'completed',
            'audio_path' => 'meeting-recordings/x.webm',
        ]);

        return [$user, $meeting, $recording];
    }
}
