<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AnnouncementAudience;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Signed, login-free opt-out for new feature (announcement) emails, the
 * same shape as AppreciationUnsubscribeController: GET only confirms, so
 * mail scanners that prefetch links cannot unsubscribe anyone; the POST
 * to the same signed URL switches off "product_updates", which can be
 * switched back on from Profile > Personalisation.
 */
class ProductUpdatesUnsubscribeController extends Controller
{
    public function show(Request $request, User $user): View
    {
        return view('announcements.unsubscribe', [
            'user' => $user,
            'unsubscribed' => ! $user->engagementNotificationEnabled(AnnouncementAudience::EMAIL_PREFERENCE),
            'actionUrl' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, User $user): View
    {
        $user->forceFill([
            'engagement_notification_preferences' => array_replace(
                $user->engagementNotificationPreferences(),
                [AnnouncementAudience::EMAIL_PREFERENCE => false]
            ),
        ])->save();

        return view('announcements.unsubscribe', [
            'user' => $user,
            'unsubscribed' => true,
            'actionUrl' => $request->fullUrl(),
        ]);
    }
}
