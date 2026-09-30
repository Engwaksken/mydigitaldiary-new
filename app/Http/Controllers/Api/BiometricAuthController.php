<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BiometricAuthController extends Controller
{
    /**
     * Enrol the currently authenticated mobile device.
     *
     * Laravel never receives fingerprint/Face ID data. Flutter first asks the
     * operating system to verify the user, then calls this endpoint with the
     * normal authenticated Sanctum session. We return a random device
     * credential exactly once and only retain its SHA-256 hash server-side.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $plainCredential = Str::random(96);

        BiometricDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $data['device_id'],
            ],
            [
                'device_name' => $data['device_name'] ?? null,
                'credential_hash' => hash('sha256', $plainCredential),
                'is_active' => true,
                'last_used_at' => null,
            ]
        );

        Log::info('Mobile biometric device enrolled.', [
            'user_id' => $user->id,
            'device_id' => $data['device_id'],
        ]);

        return response()->json([
            'enabled' => true,
            'device_id' => $data['device_id'],
            'credential' => $plainCredential,
        ], 201);
    }

    public function status(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
        ]);

        $device = BiometricDevice::query()
            ->where('user_id', $request->user()->id)
            ->where('device_id', $data['device_id'])
            ->where('is_active', true)
            ->first();

        return response()->json([
            'enabled' => $device !== null,
            'device_id' => $data['device_id'],
            'last_used_at' => $device?->last_used_at?->toIso8601String(),
        ]);
    }

    /**
     * Exchange a locally protected device credential for a fresh Sanctum token.
     * This endpoint is intentionally public because the prior Sanctum token may
     * have expired; possession of the high-entropy device credential plus a
     * successful OS biometric check is what authorises the mobile flow.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'credential' => ['required', 'string', 'min:40', 'max:255'],
        ]);

        $device = BiometricDevice::query()
            ->with('user')
            ->where('device_id', $data['device_id'])
            ->where('credential_hash', hash('sha256', $data['credential']))
            ->where('is_active', true)
            ->first();

        if (! $device || ! $device->user) {
            return response()->json([
                'message' => 'Biometric login is no longer available for this device. Sign in with email and password.',
            ], 401);
        }

        $user = $device->user;

        if ($user->isSuspended()) {
            return response()->json(['message' => 'This account has been suspended.'], 403);
        }

        $device->forceFill(['last_used_at' => now()])->save();

        // A fresh short-lived/revocable app session is created after every
        // successful local biometric verification. The biometric credential
        // itself is not used as the Bearer token for normal API traffic.
        $token = $user->createToken('flutter-biometric')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
        ]);

        $deleted = BiometricDevice::query()
            ->where('user_id', $request->user()->id)
            ->where('device_id', $data['device_id'])
            ->delete();

        if ($deleted > 0) {
            Log::info('Mobile biometric device revoked.', [
                'user_id' => $request->user()->id,
                'device_id' => $data['device_id'],
            ]);
        }

        return response()->json([
            'message' => 'Biometric login has been disabled for this device.',
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'avatar_url' => $user->avatarUrl(),
            'theme_color' => $user->themeColor(),
            'theme_color_secondary' => $user->themeColorLight(),
            'font_family' => $user->fontFamily(),
            'font_size' => $user->fontSize(),
            'subscription_status' => $user->subscription_status,
            'has_active_access' => $user->hasActiveAccess(),
            'email_verified' => ! is_null($user->email_verified_at),
            'alarms_muted' => (bool) $user->alarms_muted,
            'onboarding_completed' => ! is_null($user->onboarding_completed_at),
            'onboarding_focuses' => $user->onboarding_focuses ?? [],
        ];
    }
}
