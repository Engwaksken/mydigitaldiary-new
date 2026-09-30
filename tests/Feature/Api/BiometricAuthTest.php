<?php

namespace Tests\Feature\Api;

use App\Models\BiometricDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BiometricAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_enrol_and_use_a_biometric_device(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $enrol = $this->postJson('/api/biometric/register', [
            'device_id' => 'test-device-1',
            'device_name' => 'Test phone',
        ])->assertCreated()
            ->assertJsonPath('enabled', true);

        $credential = $enrol->json('credential');

        $this->assertNotEmpty($credential);
        $this->assertDatabaseHas('biometric_devices', [
            'user_id' => $user->id,
            'device_id' => 'test-device-1',
            'is_active' => 1,
        ]);
        $this->assertDatabaseMissing('biometric_devices', [
            'credential_hash' => $credential,
        ]);

        // The login endpoint is intentionally unauthenticated. Clearing the
        // test guard simulates the next app launch after the normal token ended.
        app('auth')->forgetGuards();

        $this->postJson('/api/biometric/login', [
            'device_id' => 'test-device-1',
            'credential' => $credential,
        ])->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']])
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_invalid_biometric_credential_is_rejected(): void
    {
        $user = User::factory()->create();

        BiometricDevice::create([
            'user_id' => $user->id,
            'device_id' => 'test-device-2',
            'credential_hash' => hash('sha256', 'correct-secret-value-that-is-long-enough'),
            'is_active' => true,
        ]);

        $this->postJson('/api/biometric/login', [
            'device_id' => 'test-device-2',
            'credential' => 'wrong-secret-value-that-is-definitely-long-enough',
        ])->assertUnauthorized();
    }

    public function test_user_can_revoke_current_biometric_device(): void
    {
        $user = User::factory()->create();

        BiometricDevice::create([
            'user_id' => $user->id,
            'device_id' => 'test-device-3',
            'credential_hash' => hash('sha256', 'credential-value-that-is-long-enough-for-test'),
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/biometric/device', [
            'device_id' => 'test-device-3',
        ])->assertOk();

        $this->assertDatabaseMissing('biometric_devices', [
            'user_id' => $user->id,
            'device_id' => 'test-device-3',
        ]);
    }
}
