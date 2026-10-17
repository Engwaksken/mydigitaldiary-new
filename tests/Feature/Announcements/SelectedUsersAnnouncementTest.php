<?php

namespace Tests\Feature\Announcements;

use App\Jobs\SendAnnouncementJob;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SelectedUsersAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_users_page_bulk_action_opens_the_form_for_the_selected_users(): void
    {
        $picked = User::factory()->count(2)->create();

        $this->actingAs($this->admin())
            ->post(route('admin.users.bulk'), ['ids' => $picked->pluck('id')->all(), 'action' => 'announcement'])
            ->assertRedirect(route('admin.announcements.create', ['recipients' => $picked->pluck('id')->implode(',')]));

        $this->get(route('admin.users.index'))
            ->assertSee('<option value="announcement">Send Announcement</option>', false);
    }

    public function test_form_shows_the_selected_recipients_and_their_counts(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Akello']);
        $bob = User::factory()->create(['name' => 'Bob Byaru']);
        User::factory()->count(3)->create();

        $this->actingAs($this->admin())
            ->get(route('admin.announcements.create', ['recipients' => "{$alice->id},{$bob->id},999999"]))
            ->assertOk()
            ->assertSee('Sending to 2 selected users')
            ->assertSee('Alice Akello, Bob Byaru')
            ->assertSee('name="recipient_ids[]" value="'.$alice->id.'"', false)
            ->assertSee('Send to everyone instead')
            ->assertSee('<span class="text-slate-500">2 users</span>', false);

        $this->get(route('admin.announcements.create'))
            ->assertOk()
            ->assertDontSee('data-announcement-recipients', false);
    }

    public function test_announcement_reaches_only_the_selected_users(): void
    {
        $picked = User::factory()->count(2)->create();
        $others = User::factory()->count(2)->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Just for you',
            'body' => 'A message for a few people.',
            'type' => 'general',
            'recipient_ids' => $picked->pluck('id')->all(),
            'send_push' => '0',
            'send_email' => '0',
        ])->assertRedirect(route('admin.announcements.index'))
            ->assertSessionHas('success', 'Announcement is being sent to 2 selected users (In-app).');

        $announcement = Announcement::latest('id')->firstOrFail();
        $this->assertSame($picked->pluck('id')->all(), $announcement->recipientIds());

        foreach ($picked as $user) {
            $this->assertSame(1, $user->notifications()->where('type', AnnouncementNotification::class)->count());
        }
        foreach ([...$others, $admin] as $user) {
            $this->assertSame(0, $user->notifications()->count());
        }

        $this->get(route('admin.announcements.index'))->assertSee('2 selected users');
    }

    public function test_announcements_without_recipients_still_go_to_everyone(): void
    {
        Queue::fake();

        $this->actingAs($this->admin())->post(route('admin.announcements.store'), [
            'title' => 'For everyone',
            'body' => 'Hello all.',
            'type' => 'general',
            'send_push' => '0',
            'send_email' => '0',
        ])->assertSessionHas('success', 'Announcement is being sent to everyone (In-app).');

        $this->assertNull(Announcement::latest('id')->firstOrFail()->recipientIds());
        Queue::assertPushed(SendAnnouncementJob::class);
    }
}
