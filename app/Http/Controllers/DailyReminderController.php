<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use App\Services\DailyReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Web (session + CSRF) side of the daily phone reminders:
 *  - the morning / evening preferences form on the profile page;
 *  - registering this browser / installed web app for push. The FCM web
 *    token is stored as a DeviceToken with platform 'web', next to the
 *    Flutter app's tokens, so FcmService::sendToUser() reaches both.
 */
class DailyReminderController extends Controller
{
    public function __construct(private readonly DailyReminderService $reminders)
    {
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'morning_enabled' => ['sometimes', 'boolean'],
            'morning_time' => ['nullable', 'date_format:H:i'],
            'evening_enabled' => ['sometimes', 'boolean'],
            'evening_time' => ['nullable', 'date_format:H:i'],
        ]);

        $this->reminders->savePreferences($request->user(), $data);

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->publicPreferences($request)]);
        }

        return back()->with('profile_status', 'daily-reminders-updated');
    }

    public function storeWebToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:100'],
            'fcm_token' => ['required', 'string', 'max:4096'],
        ]);

        $user = $request->user();

        // One browser, one owner: if someone else was signed in on this
        // browser before, their reminders must stop arriving here.
        DeviceToken::where('fcm_token', $data['fcm_token'])
            ->where(fn ($query) => $query->where('user_id', '!=', $user->id)->orWhere('device_id', '!=', $data['device_id']))
            ->delete();

        DeviceToken::updateOrCreate(
            ['user_id' => $user->id, 'device_id' => $data['device_id']],
            ['fcm_token' => $data['fcm_token'], 'platform' => 'web']
        );

        // Lets the Logout listener remove this browser's token on sign-out.
        $request->session()->put('pm_push_device_id', $data['device_id']);

        // Turning reminders on from the one-tap button means both slots.
        $prefs = $this->reminders->preferences($user);
        if (! $prefs['morning_enabled'] && ! $prefs['evening_enabled']) {
            $this->reminders->savePreferences($user, ['morning_enabled' => true, 'evening_enabled' => true]);
        }

        return response()->json([
            'message' => 'Daily reminders are on for this device.',
            'data' => $this->publicPreferences($request),
        ]);
    }

    public function destroyWebToken(Request $request): JsonResponse
    {
        $data = $request->validate(['device_id' => ['required', 'string', 'max:100']]);

        DeviceToken::where('user_id', $request->user()->id)
            ->where('device_id', $data['device_id'])
            ->where('platform', 'web')
            ->delete();

        return response()->json(['message' => 'Reminders are off for this device.']);
    }

    private function publicPreferences(Request $request): array
    {
        $prefs = $this->reminders->preferences($request->user());
        unset($prefs['morning_sent_on'], $prefs['evening_sent_on']);

        return $prefs;
    }
}
