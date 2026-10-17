<?php

namespace Tests\Feature;

use App\Jobs\SendAppreciationToUsersJob;
use App\Mail\WeeklyAppreciationMail;
use App\Models\AppreciationEmailLog;
use App\Models\User;
use App\Services\Ai\ActiveAiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class AdminBulkAppreciationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-19 09:00:00', 'Africa/Kampala'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_bulk_action_queues_appreciation_for_eligible_selected_users_only(): void
    {
        Bus::fake();
        $eligible = User::factory()->count(2)->create();
        $unverified = User::factory()->unverified()->create();
        $optedOut = User::factory()->create(['engagement_notification_preferences' => ['weekly_appreciation' => false]]);
        $alreadySent = User::factory()->create();
        AppreciationEmailLog::create(['user_id' => $alreadySent->id, 'week_key' => '2026-W43', 'source' => 'ai', 'sent_at' => now()]);
        $notSelected = User::factory()->create();

        $ids = [...$eligible->pluck('id'), $unverified->id, $optedOut->id, $alreadySent->id];

        $this->actingAs($this->admin())
            ->post(route('admin.users.bulk'), ['ids' => $ids, 'action' => 'appreciation'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Appreciation email is being sent to 2 users. 3 skipped (unverified, admin, suspended, opted out or already sent this week).');

        Bus::assertDispatched(SendAppreciationToUsersJob::class, fn ($job) => $job->weekKey === '2026-W43'
            && collect($job->userIds)->sort()->values()->all() === $eligible->pluck('id')->sort()->values()->all()
            && ! in_array($notSelected->id, $job->userIds, true));
    }

    public function test_bulk_action_reports_when_nobody_selected_is_eligible(): void
    {
        Bus::fake();
        $unverified = User::factory()->unverified()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.users.bulk'), ['ids' => [$unverified->id], 'action' => 'appreciation'])
            ->assertRedirect()
            ->assertSessionHasErrors('bulk');

        Bus::assertNotDispatched(SendAppreciationToUsersJob::class);
    }

    public function test_job_sends_one_email_per_user_and_respects_the_weekly_limit(): void
    {
        $this->mock(ActiveAiClient::class, fn ($mock) => $mock->shouldReceive('json')->andThrow(new RuntimeException('no AI')));
        Mail::fake();
        $users = User::factory()->count(2)->create();

        (new SendAppreciationToUsersJob($users->pluck('id')->all(), '2026-W43'))->handle(app(\App\Services\AppreciationSender::class));
        (new SendAppreciationToUsersJob($users->pluck('id')->all(), '2026-W43'))->handle(app(\App\Services\AppreciationSender::class));

        Mail::assertQueued(WeeklyAppreciationMail::class, 2);
        $this->assertSame(2, AppreciationEmailLog::where('week_key', '2026-W43')->count());
    }

    public function test_users_page_offers_the_appreciation_bulk_action(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('<option value="appreciation">Send Appreciation Email</option>', false)
            ->assertSee('admin-bulk-appreciation-fields', false);
    }
}
