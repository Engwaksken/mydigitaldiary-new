<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionAdminNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Support\OrganizationFactory;
use Tests\Feature\Support\SubscriptionPlanFactory;
use Tests\TestCase;

class AdminUserSubscriptionUpdateTest extends TestCase
{
    use RefreshDatabase;

    public static function entryPoints(): array
    {
        return ['single' => [false], 'bulk' => [true]];
    }

    private function subscriptionEdit(bool $bulk, User $user, array $data)
    {
        return $bulk
            ? $this->post(route('admin.users.bulk'), ['ids' => [$user->id], 'action' => 'subscription', ...$data])
            : $this->patch(route('admin.users.subscription.update', $user), $data);
    }

    public function test_show_receives_every_enabled_plan_in_the_same_order_as_index(): void
    {
        $enabled = SubscriptionPlanFactory::new()->count(3)->sequence(
            ['name' => 'ZZ Regression plan'], ['name' => 'AA Regression plan'], ['name' => 'MM Regression plan']
        )->create();
        $disabled = SubscriptionPlanFactory::new()->create(['is_enabled' => false]);
        $user = User::factory()->create(['subscription_plan_id' => $disabled->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        // HTML injection middleware replaces the response's original content;
        // observe view data during the real HTTP render without disabling it.
        $rendered = [];
        View::composer(['admin.users.index', 'admin.users.show'], function ($view) use (&$rendered): void {
            $rendered[$view->name()] = $view->getData()['plans'];
        });
        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.users.show', $user))->assertOk();
        $index = $rendered['admin.users.index'];
        $show = $rendered['admin.users.show'];

        $this->assertSame($index->modelKeys(), $show->modelKeys());
        foreach ($enabled as $plan) {
            $this->assertTrue($show->contains('id', $plan->id));
        }
        $this->assertFalse($show->contains('id', $disabled->id));
        $this->assertTrue($show->every(fn ($plan) => $plan->is_enabled));
    }

    public function test_managed_user_recovery_marker_is_flashed_on_validation_failure(): void
    {
        $user = User::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->from(route('admin.users.show', $user))->patch(route('admin.users.subscription.update', $user), [
                '_managed_user_id' => (string) $user->id, 'subscription_status' => 'pending',
                'subscription_started_at' => '2026-10-06',
            ])->assertRedirect(route('admin.users.show', $user))->assertSessionHasErrors('subscription_status')
            ->assertSessionHasInput('_managed_user_id', (string) $user->id)
            ->assertSessionHasInput('subscription_started_at', '2026-10-06');
        $this->assertSame('trial', $user->fresh()->subscription_status);
    }

    public function test_successful_subscription_edit_does_not_persist_managed_user_marker(): void
    {
        $user = User::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.users.subscription.update', $user), ['subscription_status' => 'expired', '_managed_user_id' => (string) $user->id])
            ->assertSessionHasNoErrors();
        $this->assertSame('expired', $user->fresh()->subscription_status);
        $this->assertArrayNotHasKey('_managed_user_id', $user->fresh()->getAttributes());
    }

    public function test_non_admin_cannot_read_admin_user_show(): void
    {
        $user = User::factory()->create();
        $this->actingAs(User::factory()->create())->getJson(route('admin.users.show', $user))->assertForbidden();
    }

    public function test_guest_cannot_read_admin_user_show(): void
    {
        $this->getJson(route('admin.users.show', User::factory()->create()))->assertUnauthorized();
    }

    #[DataProvider('entryPoints')]
    public function test_activation_defaults_and_recording_grant_are_idempotent_across_entry_points(bool $bulk): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-06 12:34:56'));
        Notification::fake();
        $plan = SubscriptionPlanFactory::new()->create(['included_extra_recording_minutes' => 120, 'duration_months' => 2]);
        $user = User::factory()->create(['subscription_started_at' => null, 'subscription_expires_at' => null]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $data = ['subscription_status' => 'active', 'subscription_plan_id' => $plan->id];

        $this->subscriptionEdit($bulk, $user, $data)->assertSessionHasNoErrors();
        $first = $user->fresh()->getRawOriginal();
        $this->assertSame('2026-10-06 00:00:00', $first['subscription_started_at']);
        $this->assertSame('2026-12-06 23:59:59', $first['subscription_expires_at']);
        $this->assertNull($first['trial_ends_at']);
        $this->assertSame(120, $user->fresh()->extra_recording_quota_minutes);

        $this->travel(1)->hours();
        $this->subscriptionEdit(! $bulk, $user, $data)->assertSessionHasNoErrors();
        $this->assertSame($first, $user->fresh()->getRawOriginal(), 'A repeat save through the other entry point must not renew timestamps or quota.');
        $this->assertDatabaseCount('subscription_recording_extra_grants', 1);
    }

    #[DataProvider('entryPoints')]
    public function test_trial_default_matches_for_single_and_bulk(bool $bulk): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-06 12:34:56'));
        $user = User::factory()->create(['subscription_status' => 'inactive']);
        $user->forceFill(['trial_ends_at' => null])->save();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->subscriptionEdit($bulk, $user, ['subscription_status' => 'trialing'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'subscription_status' => 'trial', 'trial_ends_at' => '2026-10-20 23:59:59']);
    }

    #[DataProvider('entryPoints')]
    public function test_suspension_and_reactivation_flags_match_for_single_and_bulk(bool $bulk): void
    {
        Notification::fake();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-06 12:34:56'));
        $user = User::factory()->create(['subscription_status' => 'active', 'auto_renew_subscription' => true]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->subscriptionEdit($bulk, $user, ['subscription_status' => 'suspended'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'account_status' => 'suspended', 'suspended_at' => '2026-10-06 12:34:56',
            'auto_renew_subscription' => false, 'auto_renew_disabled_at' => '2026-10-06 12:34:56', 'trial_ends_at' => null]);
        $user->forceFill(['suspended_reason' => 'Admin review'])->save();
        $this->travel(1)->hours();

        $this->subscriptionEdit($bulk, $user, ['subscription_status' => 'active'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'account_status' => 'active', 'suspended_at' => null, 'suspended_reason' => null,
            'auto_renew_subscription' => false, 'auto_renew_disabled_at' => '2026-10-06 12:34:56']);
    }

    #[DataProvider('entryPoints')]
    public function test_cancelled_alias_disables_auto_renew_without_refreshing_disabled_timestamp(bool $bulk): void
    {
        $user = User::factory()->create(['subscription_status' => 'active', 'auto_renew_subscription' => true,
            'auto_renew_disabled_at' => '2026-09-01 11:22:33']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->subscriptionEdit($bulk, $user, ['subscription_status' => 'canceled'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'subscription_status' => 'cancelled',
            'auto_renew_subscription' => false, 'auto_renew_disabled_at' => '2026-09-01 11:22:33']);
    }

    public function test_bulk_plan_only_edit_preserves_omitted_status_and_exact_timestamps(): void
    {
        $plan = SubscriptionPlanFactory::new()->create();
        $user = User::factory()->create(['subscription_status' => 'trialing', 'subscription_started_at' => '2026-10-01 11:22:33',
            'subscription_expires_at' => '2027-10-01 14:15:16', 'trial_ends_at' => '2026-10-20 17:18:19']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->subscriptionEdit(true, $user, ['subscription_plan_id' => $plan->id, 'subscription_status' => '',
            'subscription_started_at' => '', 'subscription_expires_at' => '', 'trial_ends_at' => ''])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'subscription_plan_id' => $plan->id, 'subscription_status' => 'trialing',
            'subscription_started_at' => '2026-10-01 11:22:33', 'subscription_expires_at' => '2027-10-01 14:15:16', 'trial_ends_at' => '2026-10-20 17:18:19']);
    }

    #[DataProvider('entryPoints')]
    public function test_status_only_partial_edit_retains_nonboundary_subscription_timestamps(bool $bulk): void
    {
        $plan = SubscriptionPlanFactory::new()->create();
        $user = User::factory()->create(['subscription_status' => 'active', 'subscription_plan_id' => $plan->id,
            'subscription_started_at' => '2026-10-01 11:22:33', 'subscription_expires_at' => '2027-10-01 14:15:16', 'trial_ends_at' => null]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->subscriptionEdit($bulk, $user, ['subscription_status' => 'expired'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'subscription_status' => 'expired', 'subscription_plan_id' => $plan->id,
            'subscription_started_at' => '2026-10-01 11:22:33', 'subscription_expires_at' => '2027-10-01 14:15:16']);
    }

    #[DataProvider('entryPoints')]
    public function test_organization_plan_is_synced_on_partial_plan_change(bool $bulk): void
    {
        Notification::fake();
        $oldPlan = SubscriptionPlanFactory::new()->create(['category' => 'organization']);
        $newPlan = SubscriptionPlanFactory::new()->create(['category' => 'organization']);
        $user = User::factory()->create(['subscription_status' => 'active', 'subscription_plan_id' => $oldPlan->id,
            'subscription_started_at' => '2026-10-01 11:22:33', 'subscription_expires_at' => '2027-10-01 14:15:16']);
        $org = OrganizationFactory::new()->create(['owner_user_id' => $user->id, 'subscription_plan_id' => $oldPlan->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $data = ['subscription_plan_id' => $newPlan->id];
        if (! $bulk) {
            $data['subscription_status'] = 'active';
        }

        $this->subscriptionEdit($bulk, $user, $data)->assertSessionHasNoErrors();

        $this->assertSame($newPlan->id, $org->fresh()->subscription_plan_id);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'subscription_status' => 'active', 'subscription_plan_id' => $newPlan->id,
            'subscription_started_at' => '2026-10-01 11:22:33', 'subscription_expires_at' => '2027-10-01 14:15:16']);
    }

    public function test_bulk_effective_date_validation_rolls_back_earlier_user_quota_and_org_updates(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-06 12:34:56'));
        Notification::fake();
        $plan = SubscriptionPlanFactory::new()->create(['category' => 'organization', 'included_extra_recording_minutes' => 120]);
        $first = User::factory()->create(['subscription_started_at' => null, 'subscription_expires_at' => null]);
        $second = User::factory()->create(['subscription_started_at' => null, 'subscription_expires_at' => '2026-10-05 23:59:59']);
        $org = OrganizationFactory::new()->create(['owner_user_id' => $first->id, 'subscription_plan_id' => null]);
        $before = [$first->fresh()->getRawOriginal(), $second->fresh()->getRawOriginal()];

        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.users.bulk'), [
            'ids' => [$first->id, $second->id], 'action' => 'subscription', 'subscription_status' => 'active', 'subscription_plan_id' => $plan->id,
        ])->assertSessionHasErrors('subscription_expires_at');

        $this->assertSame($before, [$first->fresh()->getRawOriginal(), $second->fresh()->getRawOriginal()]);
        $this->assertNull($org->fresh()->subscription_plan_id);
        $this->assertDatabaseCount('subscription_recording_extra_grants', 0);
        Notification::assertNothingSent();
    }

    #[DataProvider('entryPoints')]
    public function test_activation_notifications_wait_for_outer_commit(bool $bulk): void
    {
        $user = User::factory()->create();
        $other = $bulk ? User::factory()->create() : null;
        $baseLevel = DB::transactionLevel();
        $notified = [];
        $this->mock(SubscriptionAdminNotificationService::class, function ($mock) use (&$notified, $baseLevel): void {
            $mock->shouldReceive('notify')->andReturnUsing(function (User $subscriber) use (&$notified, $baseLevel): void {
                $this->assertSame($baseLevel, DB::transactionLevel(), 'Callbacks must wait until the explicit outer transaction commits.');
                $this->assertSame('active', $subscriber->fresh()->subscription_status);
                $notified[] = $subscriber->id;
            });
        });
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        DB::beginTransaction();
        try {
            if ($bulk) {
                $this->post(route('admin.users.bulk'), ['ids' => [$user->id, $other->id], 'action' => 'subscription', 'subscription_status' => 'active'])->assertSessionHasNoErrors();
            } else {
                $this->subscriptionEdit(false, $user, ['subscription_status' => 'active'])->assertSessionHasNoErrors();
            }
            $this->assertSame([], $notified);
            DB::commit();
            $this->assertEqualsCanonicalizing($bulk ? [$user->id, $other->id] : [$user->id], $notified);
        } finally {
            while (DB::transactionLevel() > $baseLevel) {
                DB::rollBack();
            }
        }
    }

    public function test_non_admin_cannot_bulk_update_subscriptions(): void
    {
        $user = User::factory()->create();
        $this->actingAs(User::factory()->create())->postJson(route('admin.users.bulk'), [
            'ids' => [$user->id], 'action' => 'subscription', 'subscription_status' => 'active',
        ])->assertForbidden();
        $this->assertSame('trial', $user->fresh()->subscription_status);
    }

    public function test_guest_cannot_bulk_update_subscriptions(): void
    {
        $user = User::factory()->create();
        $this->postJson(route('admin.users.bulk'), ['ids' => [$user->id], 'action' => 'subscription', 'subscription_status' => 'active'])->assertUnauthorized();
    }

    public static function legacyStatuses(): array
    {
        return ['trialing alias' => [' TRIALING ', 'trial'], 'canceled alias' => [' CANCELED ', 'cancelled']];
    }

    #[DataProvider('legacyStatuses')]
    public function test_legacy_status_aliases_are_normalized(string $input, string $expected): void
    {
        $user = User::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patchJson(route('admin.users.subscription.update', $user), ['subscription_status' => $input])
            ->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();
        $this->assertSame($expected, $user->fresh()->subscription_status);
    }

    public function test_status_only_edit_preserves_plan_and_dates(): void
    {
        $plan = SubscriptionPlan::query()->firstOrFail();
        $user = User::factory()->create([
            'subscription_status' => 'active', 'subscription_plan_id' => $plan->id,
            'subscription_started_at' => '2026-10-01 00:00:00', 'subscription_expires_at' => '2027-10-01 23:59:59',
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.users.subscription.update', $user), ['subscription_status' => 'expired'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'id' => $user->id, 'subscription_status' => 'expired', 'subscription_plan_id' => $plan->id,
            'subscription_started_at' => '2026-10-01 00:00:00', 'subscription_expires_at' => '2027-10-01 23:59:59',
        ]);
    }

    public function test_notification_failure_does_not_misreport_or_undo_activation(): void
    {
        $user = User::factory()->create();
        $this->mock(SubscriptionAdminNotificationService::class, function ($mock) use ($user): void {
            $mock->shouldReceive('notify')->once()->withArgs(function (User $notified) use ($user): bool {
                return $notified->id === $user->id && $user->fresh()->subscription_status === 'active';
            })->andThrow(new \RuntimeException('Transport unavailable'));
        });
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.users.subscription.update', $user), [
                'subscription_status' => 'active', 'subscription_started_at' => '2026-10-01', 'subscription_expires_at' => '2027-10-01',
            ])->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('success')->assertSessionHas('warning');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'subscription_status' => 'active', 'subscription_expires_at' => '2027-10-01 23:59:59']);
    }

    public function test_non_admin_cannot_update_subscription(): void
    {
        $user = User::factory()->create();
        $this->actingAs(User::factory()->create())->patchJson(route('admin.users.subscription.update', $user), ['subscription_status' => 'active'])->assertForbidden();
        $this->assertSame('trial', $user->fresh()->subscription_status);
    }

    public function test_guest_cannot_update_subscription(): void
    {
        $user = User::factory()->create();
        $this->patchJson(route('admin.users.subscription.update', $user), ['subscription_status' => 'active'])->assertUnauthorized();
    }

    public function test_admin_trial_alias_update_grants_access_to_planner_route(): void
    {
        $user = User::factory()->create(['trial_ends_at' => now()->subDay()]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.users.subscription.update', $user), [
                'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(7)->toDateString(),
            ])->assertSessionHasNoErrors();
        $this->actingAs($user->fresh())->get(route('daily-planner.index'))->assertOk();
    }

    public function test_admin_can_update_user_subscription_through_admin_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $plan = SubscriptionPlan::query()->firstOrFail();

        $response = $this->actingAs($admin)->patch(route('admin.users.subscription.update', $user), [
            'subscription_status' => 'active',
            'subscription_plan_id' => $plan->id,
            'subscription_started_at' => '2026-10-01',
            'subscription_expires_at' => '2027-10-01',
            'trial_ends_at' => '2026-10-15',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'subscription_status' => 'active',
            'subscription_plan_id' => $plan->id,
            'subscription_started_at' => '2026-10-01 00:00:00',
            'subscription_expires_at' => '2027-10-01 23:59:59',
            'trial_ends_at' => null,
        ]);
    }

    public function test_invalid_status_is_rejected_without_changing_subscription(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->patch(route('admin.users.subscription.update', $user), [
            'subscription_status' => 'pending',
        ]);

        $response->assertSessionHasErrors('subscription_status');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'subscription_status' => 'trial',
        ]);
    }

    public function test_expiry_before_start_is_rejected_without_changing_subscription(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->patch(route('admin.users.subscription.update', $user), [
            'subscription_status' => 'active',
            'subscription_started_at' => '2026-10-02',
            'subscription_expires_at' => '2026-10-01',
        ]);

        $response->assertSessionHasErrors('subscription_expires_at');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'subscription_status' => 'trial',
        ]);
    }
}
