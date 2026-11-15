<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeetingPlatformConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lets an admin register THIS APP as an "OAuth app" on each meeting
 * platform's own developer console, then paste the resulting client
 * ID/secret here. This alone doesn't fetch anyone's meetings - it's what
 * lets a USER then connect their own account (see
 * MeetingConnectionController) to fetch theirs. The four platform rows
 * are seeded once by migration; this only ever updates them, never
 * creates/deletes.
 */
class AdminMeetingPlatformController extends Controller
{
    public function update(Request $request, MeetingPlatformConfig $meetingPlatformConfig): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'string'],
            'client_secret' => ['nullable', 'string'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        $clientId = trim((string) ($data['client_id'] ?? ''));
        $clientSecret = trim((string) ($data['client_secret'] ?? ''));

        // Client IDs are displayed back to the administrator, so allow an
        // explicitly supplied value to replace the existing value. An empty
        // field keeps the current value to avoid accidental credential loss.
        if ($clientId !== '') {
            $meetingPlatformConfig->client_id = $clientId;
        }

        // Never send the stored secret back to the browser. Leaving this field
        // blank means "keep the existing encrypted secret".
        if ($clientSecret !== '') {
            $meetingPlatformConfig->client_secret = $clientSecret;
        }

        $requestedEnabled = $request->boolean('is_enabled');
        $meetingPlatformConfig->is_enabled = $requestedEnabled && $meetingPlatformConfig->isConfigured();
        $meetingPlatformConfig->save();

        $message = $meetingPlatformConfig->isConfigured()
            ? "{$meetingPlatformConfig->name} credentials saved" . ($meetingPlatformConfig->is_enabled ? ' and enabled for users.' : '. Turn on Enable when you are ready for users to authorize it.')
            : "{$meetingPlatformConfig->name} settings saved, but both Client ID and Client Secret are required before it can be enabled.";

        return back()
            ->with('success', $message)
            ->with('settings_tab', 'meetings');
    }
}
