<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserTranscriptionCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unset_capacity_defaults_to_thirty_megabytes(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->transcription_capacity_mb);
        $this->assertSame(30, $user->effectiveTranscriptionCapacityMb());
        $this->assertSame(30 * 1024 * 1024, $user->effectiveTranscriptionCapacityBytes());
    }

    public function test_admin_can_set_user_capacity_and_response_reports_saved_and_effective_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $this->actingAs($admin)->patchJson(route('admin.users.transcription-capacity.update', $user), [
            'transcription_capacity_mb' => 75,
        ])->assertOk()->assertJsonPath('transcription_capacity_mb', 75)
            ->assertJsonPath('effective_transcription_capacity_mb', 75);

        $this->assertSame(75, $user->fresh()->transcription_capacity_mb);
    }

    public function test_admin_can_clear_capacity_to_restore_the_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['transcription_capacity_mb' => 80]);

        $this->actingAs($admin)->patchJson(route('admin.users.transcription-capacity.update', $user), [
            'transcription_capacity_mb' => null,
        ])->assertOk()->assertJsonPath('transcription_capacity_mb', null)
            ->assertJsonPath('effective_transcription_capacity_mb', 30);

        $this->assertNull($user->fresh()->transcription_capacity_mb);
    }

    public function test_capacity_can_be_saved_from_the_manage_user_modal_and_reopens_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.transcription-capacity.update', $user), [
                '_managed_user_id' => $user->id,
                'transcription_capacity_mb' => 120,
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('open_manage_user', $user->id);

        $this->assertSame(120, $user->fresh()->transcription_capacity_mb);
    }

    public function test_admin_can_set_capacity_when_creating_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Capacity Test User',
            'email' => 'capacity-user@example.test',
            'role' => 'user',
            'password' => 'temporary-password-123',
            'subscription_status' => 'inactive',
            'transcription_capacity_mb' => 240,
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'capacity-user@example.test',
            'transcription_capacity_mb' => 240,
        ]);
    }

    public function test_capacity_must_be_an_integer_of_at_least_fifty_or_null(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['transcription_capacity_mb' => 70]);

        foreach ([49, 0, -1, 50.5, 'not-a-number'] as $invalid) {
            $this->actingAs($admin)->patchJson(route('admin.users.transcription-capacity.update', $user), [
                'transcription_capacity_mb' => $invalid,
            ])->assertUnprocessable()->assertJsonValidationErrors('transcription_capacity_mb');
            $this->assertSame(70, $user->fresh()->transcription_capacity_mb);
        }
    }

    public function test_non_admin_cannot_update_capacity(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->create())->patchJson(route('admin.users.transcription-capacity.update', $user), [
            'transcription_capacity_mb' => 100,
        ])->assertForbidden();

        $this->assertNull($user->fresh()->transcription_capacity_mb);
    }

    public function test_guest_cannot_update_capacity(): void
    {
        $this->patchJson(route('admin.users.transcription-capacity.update', User::factory()->create()), [
            'transcription_capacity_mb' => 100,
        ])->assertUnauthorized();
    }

    public function test_capacity_update_only_changes_the_target_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();
        $other = User::factory()->create(['transcription_capacity_mb' => 65]);

        $this->actingAs($admin)->patchJson(route('admin.users.transcription-capacity.update', $target), [
            'transcription_capacity_mb' => 90,
        ])->assertOk();

        $this->assertSame(90, $target->fresh()->transcription_capacity_mb);
        $this->assertSame(65, $other->fresh()->transcription_capacity_mb);
    }
}
