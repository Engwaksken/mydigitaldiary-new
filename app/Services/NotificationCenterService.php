<?php

namespace App\Services;

use App\Models\EnterpriseInquiry;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NotificationCenterService
{
    /**
     * Build one notification feed for the dashboard bell.
     *
     * Normal users receive their Laravel database notifications.
     * Admin/support staff additionally receive live operational alerts that
     * remain visible until the underlying item is handled (support request,
     * enterprise inquiry, expiring subscription).
     */
    public function forUser(User $user, int $limit = 20): array
    {
        $items = collect();

        $databaseNotifications = $user->notifications()
            ->latest('created_at')
            ->limit(30)
            ->get();

        foreach ($databaseNotifications as $notification) {
            $items->push($this->mapDatabaseNotification($notification));
        }

        if ($this->isAdminStaff($user)) {
            $items = $items->concat($this->adminOperationalItems($user));
        }

        $items = $items
            ->sortByDesc(fn (array $item) => $item['sort_at'] ?? '')
            ->values();

        $unreadCount = $items->filter(fn (array $item) => (bool) ($item['unread'] ?? false))->count();
        $actionCount = $items->filter(fn (array $item) => (bool) ($item['action_required'] ?? false))->count();

        $categoryCounts = $items
            ->filter(fn (array $item) => (bool) ($item['action_required'] ?? false))
            ->countBy(fn (array $item) => (string) ($item['category'] ?? 'other'))
            ->all();

        return [
            'items' => $items->take($limit)->values()->all(),
            'unread_count' => $unreadCount,
            'action_count' => $actionCount,
            'badge_count' => $unreadCount + $actionCount,
            'category_counts' => $categoryCounts,
        ];
    }

    private function mapDatabaseNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $class = class_basename((string) $notification->type);

        $title = trim((string) ($data['title'] ?? ''));
        $message = trim((string) ($data['message'] ?? $data['body'] ?? ''));
        $route = route('dashboard');
        $icon = 'fa-bell';
        $tone = 'slate';
        $badge = null;
        $cta = null;

        if ($class === 'SubscriptionExpiryReminderNotification') {
            $days = (int) ($data['days_remaining'] ?? 0);
            $title = $title ?: ($days > 0 ? "Subscription expires in {$days} day(s)" : 'Subscription expiry notice');
            $message = $message ?: (! empty($data['expiry_date']) ? 'Expiry date: '.$data['expiry_date'] : 'Review your subscription.');
            $route = route('subscription.show');
            $icon = 'fa-credit-card';
            $tone = 'amber';
        } elseif ($class === 'ReminderNotification' || $class === 'SimpleDatabaseNotification') {
            $title = $title ?: 'Reminder';
            $message = $message ?: 'You have a new reminder.';
            $route = RouteSafe::route('reminders.index', route('dashboard'));
            $icon = 'fa-bell';
            $tone = 'sky';
        } elseif ($class === 'DailyTopTasksNotification') {
            $title = $title ?: 'Your priorities for today';
            $message = $message ?: 'Review your top tasks for today.';
            $route = RouteSafe::route('daily-planner.index', route('dashboard'));
            $icon = 'fa-list-check';
            $tone = 'emerald';
        } elseif ($class === 'PaymentSuccessfulNotification') {
            $title = $title ?: 'Payment received';
            $message = $message ?: 'Your payment was completed successfully.';
            $route = route('subscription.show');
            $icon = 'fa-circle-check';
            $tone = 'emerald';
        } elseif ($class === 'PaymentFailedNotification') {
            $title = $title ?: 'Payment needs attention';
            $message = $message ?: 'A payment attempt was not completed.';
            $route = route('subscription.show');
            $icon = 'fa-triangle-exclamation';
            $tone = 'rose';
        } elseif ($class === 'AnnouncementNotification') {
            $title = $title ?: 'Announcement';
            $message = $message ?: trim((string) ($data['body'] ?? 'A new announcement is available.'));
            $icon = 'fa-bullhorn';
            $tone = 'violet';

            $announcementType = (string) ($data['type'] ?? 'general');
            $link = trim((string) ($data['link_url'] ?? ''));
            if ($link !== '') {
                $route = $link;
            }
            $cta = trim((string) ($data['cta_label'] ?? '')) ?: null;
            if ($announcementType === 'new_feature') {
                $badge = 'New feature';
                $featureIcon = config('features.'.($data['feature_key'] ?? '').'.icon');
                if (is_string($featureIcon) && $featureIcon !== '') {
                    // The views prefix "fa-solid", so keep only the icon name.
                    $icon = trim(str_replace(['fa-solid', 'fa-regular'], '', $featureIcon));
                }
                $cta = $cta ?: 'Explore now';
            }
        }

        if ($title === '') {
            $title = preg_replace('/(?<!^)([A-Z])/', ' $1', str_replace('Notification', '', $class)) ?: 'Notification';
        }
        if ($message === '') {
            $message = 'Open My Digital Diary to view this update.';
        }

        return [
            'id' => 'db-'.$notification->id,
            'database_id' => $notification->id,
            'source' => 'database',
            'category' => 'notification',
            'title' => $title,
            'message' => $message,
            'url' => $route,
            'icon' => $icon,
            'tone' => $tone,
            'badge' => $badge,
            'cta_label' => $cta,
            'unread' => is_null($notification->read_at),
            'action_required' => false,
            'created_at' => $notification->created_at,
            'sort_at' => optional($notification->created_at)->toIso8601String(),
        ];
    }

    private function adminOperationalItems(User $user): Collection
    {
        $items = collect();

        // Support conversations remain actionable until ended. Prioritise
        // conversations whose latest message came from the customer.
        $conversations = SupportConversation::query()
            ->whereNull('ended_at')
            ->with(['user'])
            ->with(['messages' => fn ($q) => $q->latest('id')->limit(1)])
            ->orderByDesc('last_message_at')
            ->limit(8)
            ->get();

        foreach ($conversations as $conversation) {
            $latest = $conversation->messages->first();
            $waitingOnStaff = ! $latest || $latest->sender_type === 'user';
            if (! $waitingOnStaff && $conversation->assigned_to_user_id) {
                continue;
            }

            $items->push([
                'id' => 'support-'.$conversation->id,
                'source' => 'admin',
                'category' => 'support',
                'title' => $conversation->subject ?: 'Support conversation',
                'message' => ($conversation->user?->name ?: 'A user').' is waiting for support'.($conversation->assigned_to_user_id ? '.' : ' and is not assigned yet.'),
                'url' => route('admin.support.show', $conversation),
                'icon' => 'fa-comments',
                'tone' => 'sky',
                'unread' => false,
                'action_required' => true,
                'created_at' => $conversation->last_message_at ?: $conversation->updated_at,
                'sort_at' => optional($conversation->last_message_at ?: $conversation->updated_at)->toIso8601String(),
            ]);
        }

        $inquiries = EnterpriseInquiry::query()
            ->where('status', 'new')
            ->latest('created_at')
            ->limit(8)
            ->get();

        foreach ($inquiries as $inquiry) {
            $items->push([
                'id' => 'inquiry-'.$inquiry->id,
                'source' => 'admin',
                'category' => 'enterprise',
                'title' => 'New enterprise inquiry',
                'message' => ($inquiry->email ?: 'A prospect').' submitted an enterprise enquiry'.($inquiry->country ? ' from '.$inquiry->country : '').'.',
                'url' => route('admin.enterprise-inquiries.index', ['status' => 'new']),
                'icon' => 'fa-handshake',
                'tone' => 'violet',
                'unread' => false,
                'action_required' => true,
                'created_at' => $inquiry->created_at,
                'sort_at' => optional($inquiry->created_at)->toIso8601String(),
            ]);
        }

        $today = Carbon::now()->startOfDay();
        $cutoff = Carbon::now()->addDays(14)->endOfDay();
        $expiringUsers = User::query()
            ->whereNotIn('role', ['admin', 'super_admin', 'support'])
            ->whereNull('suspended_at')
            ->where(function ($q) use ($today, $cutoff) {
                $q->where(function ($sub) use ($today, $cutoff) {
                    $sub->where('subscription_status', 'trialing')
                        ->whereBetween('trial_ends_at', [$today, $cutoff]);
                })->orWhere(function ($sub) use ($today, $cutoff) {
                    $sub->where('subscription_status', 'active')
                        ->whereNotNull('subscription_expires_at')
                        ->whereBetween('subscription_expires_at', [$today, $cutoff]);
                });
            })
            ->orderByRaw("COALESCE(subscription_expires_at, trial_ends_at) ASC")
            ->limit(10)
            ->get();

        foreach ($expiringUsers as $expiringUser) {
            $expiry = $expiringUser->relevantExpiryDate();
            if (! $expiry) {
                continue;
            }
            $days = max(0, Carbon::today()->diffInDays($expiry->copy()->startOfDay(), false));
            $items->push([
                'id' => 'expiry-'.$expiringUser->id,
                'source' => 'admin',
                'category' => 'subscription',
                'title' => 'Subscription expiring'.($days === 0 ? ' today' : " in {$days} day(s)"),
                'message' => $expiringUser->name.' · '.$expiringUser->email,
                'url' => route('admin.users.show', $expiringUser),
                'icon' => 'fa-hourglass-half',
                'tone' => 'amber',
                'unread' => false,
                'action_required' => true,
                'created_at' => $expiry,
                // Sort urgent expiries near the top while still preserving a
                // stable timestamp for the UI.
                'sort_at' => Carbon::now()->subMinutes($days)->toIso8601String(),
            ]);
        }

        return $items;
    }

    private function isAdminStaff(User $user): bool
    {
        return in_array((string) $user->role, ['admin', 'super_admin'], true);
    }
}

/** Small helper to avoid optional feature routes breaking the bell. */
final class RouteSafe
{
    public static function route(string $name, string $fallback): string
    {
        return \Illuminate\Support\Facades\Route::has($name) ? route($name) : $fallback;
    }
}
