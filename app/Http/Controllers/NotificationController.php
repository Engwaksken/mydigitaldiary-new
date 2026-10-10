<?php

namespace App\Http\Controllers;

use App\Services\NotificationCenterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationCenterService $center): View
    {
        return view('notifications.index', [
            'notificationCenter' => $center->forUser($request->user(), 100),
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        $notification?->markAsRead();

        $redirect = trim((string) $request->input('redirect'));
        return redirect()->to($redirect !== '' ? $redirect : route('dashboard'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'Notifications marked as read.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        $notification?->delete();

        return back()->with('status', 'Notification deleted.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
        ]);

        $deleted = $request->user()->notifications()
            ->whereIn('id', $data['ids'])
            ->delete();

        return back()->with('status', $deleted === 1
            ? '1 notification deleted.'
            : "{$deleted} notifications deleted.");
    }
}
