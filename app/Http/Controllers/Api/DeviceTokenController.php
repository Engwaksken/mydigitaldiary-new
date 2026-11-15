<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Flutter app calls store() once after login (and again any time
 * Firebase hands it a rotated token - FCM tokens are NOT permanent) so
 * SendReminders / FcmService knows where to push reminder notifications
 * for this user. Keyed on (user_id, device_id) rather than just user_id,
 * since a user might have the app on more than one device - all of them
 * should get the push, and each device's token needs to be updated
 * independently as it rotates.
 */
class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:255'],
            'fcm_token' => ['required', 'string'],
            'platform' => ['nullable', 'in:ios,android'],
        ]);

        DeviceToken::updateOrCreate(
            ['user_id' => $request->user()->id, 'device_id' => $data['device_id']],
            ['fcm_token' => $data['fcm_token'], 'platform' => $data['platform'] ?? null]
        );

        return response()->json(['message' => 'Device registered for push notifications.']);
    }

    /**
     * Called on logout (or when a user disables notifications) so a
     * device that's no longer actively logged in stops receiving pushes
     * meant for this account.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['device_id' => ['required', 'string']]);

        DeviceToken::where('user_id', $request->user()->id)
            ->where('device_id', $request->device_id)
            ->delete();

        return response()->json(['message' => 'Device unregistered.']);
    }
}
