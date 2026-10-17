<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Signed, login-free opt-out for the weekly appreciation email.
 *
 * GET only shows a confirmation so mail scanners that prefetch links cannot
 * unsubscribe anyone; the POST (to the same signed URL) stores the choice in
 * the user's existing email preferences, where it can be switched back on
 * from Profile > Personalisation.
 */
class AppreciationUnsubscribeController extends Controller
{
    public function show(Request $request, User $user): View
    {
        return view('appreciation.unsubscribe', [
            'user' => $user,
            'unsubscribed' => ! $user->engagementNotificationEnabled('weekly_appreciation'),
            'actionUrl' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, User $user): View
    {
        $user->forceFill([
            'engagement_notification_preferences' => array_replace(
                $user->engagementNotificationPreferences(),
                ['weekly_appreciation' => false]
            ),
        ])->save();

        return view('appreciation.unsubscribe', [
            'user' => $user,
            'unsubscribed' => true,
            'actionUrl' => $request->fullUrl(),
        ]);
    }
}
