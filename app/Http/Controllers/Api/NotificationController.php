<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reads from Laravel's built-in notifications table (User already uses
 * the Notifiable trait) — the same notifications already being created
 * elsewhere (subscription expiry reminders, etc.) show up here, rather
 * than needing a second, separate mobile-only notification concept.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->limit(30)->get();

        return response()->json([
            'data' => $notifications->map(fn ($n) => [
                'id' => $n->id,
                'type' => class_basename($n->type),
                'data' => $n->data,
                'read' => ! is_null($n->read_at),
                'created_at' => $n->created_at->toIso8601String(),
            ]),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Lightweight count for the app's bell and launcher-icon badges.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        $notification?->markAsRead();

        return response()->json(['message' => 'Marked as read.']);
    }


    public function markReminderRead(Request $request, int $reminderId): JsonResponse
    {
        $notifications = $request->user()->unreadNotifications()
            ->where('type', \App\Notifications\ReminderNotification::class)
            ->latest('created_at')
            ->limit(50)
            ->get();

        foreach ($notifications as $notification) {
            $data = is_array($notification->data) ? $notification->data : [];
            if ((int) ($data['reminder_id'] ?? 0) === $reminderId) {
                $notification->markAsRead();
            }
        }

        return response()->json(['message' => 'Reminder notification marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        $notification?->delete();

        return response()->json(['message' => 'Notification deleted.']);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
        ])['ids'];

        $deleted = $request->user()->notifications()->whereIn('id', $ids)->delete();

        return response()->json([
            'message' => "{$deleted} notification(s) deleted.",
            'deleted' => $deleted,
        ]);
    }
}
