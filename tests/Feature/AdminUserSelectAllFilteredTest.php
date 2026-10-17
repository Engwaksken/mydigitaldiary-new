<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AdminUserController;
use App\Jobs\SendAnnouncementJob;
use App\Jobs\SendAppreciationToUsersJob;
use App\Models\Announcement;
use App\Models\AppreciationEmailLog;
use App\Models\User;
use App\Services\AdminUserSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminUserSelectAllFilteredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-10-19 10:00:00', 'Africa/Kampala'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'subscription_status' => 'trial']);
    }

    /** 30 unverified active users (more than one page of 25) plus some that do not match. */
    private function seedUsers(): array
    {
        $matching = User::factory()->count(30)->unverified()->create(['subscription_status' => 'active']);
        User::factory()->count(3)->create(['subscription_status' => 'active']);
        User::factory()->count(2)->unverified()->create(['subscription_status' => 'trial']);

        return $matching->pluck('id')->sort()->values()->all();
    }

    private function selectionFrom(string $location): array
    {
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return AdminUserSelection::get($query['selection'] ?? null) ?? [];
    }

    public function test_index_and_bulk_actions_share_one_query(): void
    {
        $matching = $this->seedUsers();
        $filters = ['verification' => 'unverified', 'subscription_status' => 'active'];

        $this->assertSame($matching, AdminUserController::filteredUsersQuery($filters)->orderBy('id')->pluck('id')->all());

        $this->actingAs($this->admin())
            ->get(route('admin.users.index', $filters))
            ->assertOk()
            ->assertSee('id="admin-select-all-bar"', false)
            ->assertSee('data-total="30"', false)
            ->assertSee('All 25 on this page are selected.')
            ->assertSee('Select all 30 users matching these filters')
            ->assertSee('Clear selection');
    }

    public function test_select_all_filtered_opens_the_announcement_form_for_every_match(): void
    {
        $matching = $this->seedUsers();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.users.bulk'), [
            'action' => 'announcement',
            'scope' => 'filtered',
            'filter' => ['verification' => 'unverified', 'subscription_status' => 'active'],
        ]);

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(route('admin.announcements.create'), $location);
        $this->assertStringNotContainsString('recipients=', $location, 'thousands of ids never go in the URL');
        $selection = $this->selectionFrom($location);
        $this->assertSame($matching, collect($selection['ids'])->sort()->values()->all());

        $this->get($location)
            ->assertOk()
            ->assertSee('Sending to 30 users matching: Unverified email, Active subscription')
            ->assertSee('Send to everyone instead')
            ->assertSee('<span class="text-slate-500">30 users</span>', false)
            ->assertDontSee('<input type="hidden" name="recipient_ids[]"', false);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        Queue::fake();
        $this->post(route('admin.announcements.store'), [
            'title' => 'Please verify', 'body' => 'Verify your email to keep your diary safe.', 'type' => 'general',
            'selection' => $query['selection'], 'send_push' => '0', 'send_email' => '0',
        ])->assertRedirect(route('admin.announcements.index'))
            ->assertSessionHas('success', 'Announcement is being sent to 30 selected users (In-app).');

        $announcement = Announcement::sole();
        $this->assertSame($matching, collect($announcement->recipientIds())->sort()->values()->all());
        Queue::assertPushed(SendAnnouncementJob::class);
    }

    public function test_expired_selection_is_reported(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.announcements.create', ['selection' => str_repeat('a', 40)]))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors('bulk');

        Queue::fake();
        $this->post(route('admin.announcements.store'), [
            'title' => 'x', 'body' => 'y', 'type' => 'general', 'selection' => str_repeat('a', 40),
        ])->assertSessionHasErrors('selection');
        $this->assertSame(0, Announcement::count());
    }

    public function test_select_all_filtered_opens_a_paginated_appreciation_preview(): void
    {
        Bus::fake();
        // 60 verified users who have not had this week's email, one who has.
        User::factory()->count(60)->create(['subscription_status' => 'active']);
        $sent = User::factory()->create(['subscription_status' => 'active', 'name' => 'Zed Already']);
        AppreciationEmailLog::create(['user_id' => $sent->id, 'week_key' => '2026-W43', 'source' => 'ai', 'sent_at' => now()]);
        User::factory()->count(4)->create(['subscription_status' => 'trial']);
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.users.bulk'), [
            'action' => 'appreciation',
            'scope' => 'filtered',
            'filter' => ['subscription_status' => 'active'],
        ]);
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(route('admin.users.appreciation.preview'), $location);
        $this->assertCount(61, $this->selectionFrom($location)['ids']);

        $page = $this->get($location)->assertOk()
            ->assertSee('Selected: 61 users matching Active subscription')
            ->assertSee('Send to 60 users')
            ->assertSee('data-skipped-reason="Already sent this week"', false)
            ->assertSee('Zed Already')
            ->assertSee('name="selection"', false)
            ->assertDontSee('name="ids[]"', false);
        $this->assertSame(50, substr_count($page->getContent(), 'class="appreciation-recipient'));

        $first = User::query()->where('subscription_status', 'active')->whereKeyNot($sent->id)->orderBy('name')->orderBy('id')->first();
        $this->get($location.'&q='.urlencode($first->email))->assertOk()
            ->assertSee($first->name);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->post(route('admin.users.appreciation.send'), ['selection' => $query['selection']])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success', 'Appreciation email is being sent to 60 users. 1 skipped (unverified, admin, suspended, opted out or already sent this week).');

        Bus::assertDispatched(SendAppreciationToUsersJob::class, fn ($job) => count($job->userIds) === 60 && $job->campaignId !== null);
    }

    public function test_filtered_scope_is_rejected_for_destructive_and_role_actions(): void
    {
        $this->seedUsers();
        $admin = $this->admin();
        $before = User::count();

        foreach (['delete', 'suspend', 'reactivate', 'role', 'subscription'] as $action) {
            $this->actingAs($admin)->post(route('admin.users.bulk'), [
                'action' => $action,
                'scope' => 'filtered',
                'filter' => ['verification' => 'unverified'],
                'role' => 'admin',
                'subscription_status' => 'expired',
                'confirmation' => 'DELETE',
            ])->assertSessionHasErrors(['scope' => '"Select all users matching these filters" only works for Send Appreciation Email and Send Announcement. For this action, tick the users individually on the page.']);
        }

        $this->assertSame($before, User::count());
        $this->assertSame(0, User::where('role', 'admin')->whereKeyNot($admin->id)->count());
        $this->assertSame(0, User::whereNotNull('suspended_at')->count());

        // Explicit ids still work, and are still required without scope=filtered.
        $this->post(route('admin.users.bulk'), ['action' => 'suspend'])->assertSessionHasErrors('ids');
    }

    public function test_users_table_shows_the_appreciation_column_and_filter(): void
    {
        $thisWeek = User::factory()->create(['name' => 'Wendy Week']);
        $earlier = User::factory()->create(['name' => 'Eli Earlier']);
        $never = User::factory()->create(['name' => 'Nina Never']);
        AppreciationEmailLog::create(['user_id' => $thisWeek->id, 'week_key' => '2026-W43', 'source' => 'ai', 'sent_at' => now()]);
        AppreciationEmailLog::create(['user_id' => $earlier->id, 'week_key' => '2026-W40', 'source' => 'ai', 'sent_at' => Carbon::parse('2026-09-28 09:00', 'Africa/Kampala')]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('<th class="px-4 py-3 text-left">Appreciation</th>', false)
            ->assertSee('Choose appreciation status (optional)')
            ->assertSee('Sent this week')
            ->assertSee('28 Sep 2026');

        $this->get(route('admin.users.index', ['appreciation' => 'sent_this_week']))
            ->assertOk()
            ->assertSee('Wendy Week')
            ->assertDontSee('Eli Earlier')
            ->assertDontSee('Nina Never');

        $this->get(route('admin.users.index', ['appreciation' => 'not_sent_this_week']))
            ->assertOk()
            ->assertDontSee('Wendy Week')
            ->assertSee('Eli Earlier')
            ->assertSee('Nina Never');

        // The filter also drives select-all-filtered.
        $response = $this->post(route('admin.users.bulk'), [
            'action' => 'announcement', 'scope' => 'filtered', 'filter' => ['appreciation' => 'not_sent_this_week'],
        ]);
        $ids = $this->selectionFrom($response->headers->get('Location'))['ids'];
        $this->assertEqualsCanonicalizing([$earlier->id, $never->id, $admin->id], $ids);
    }
}
