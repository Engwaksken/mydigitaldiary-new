<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Weekly nudge to users whose subscription has lapsed. Delivered by the
 * subscriptions:send-expired-weekly-reminder command (once per user per
 * week, guarded in the command) and stops as soon as they renew.
 */
class SubscriptionExpiredWeeklyReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your My Digital Diary subscription has expired')
            ->greeting('Hi ' . $notifiable->name . ',')
            ->line("We noticed your subscription to My Digital Diary has expired. You can still log in and view everything you've already entered, but adding and editing are on hold until you renew.")
            ->action('Renew Subscription', route('subscription.show'))
            ->line('This is a weekly reminder — it stops as soon as you renew.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Subscription expired',
            'body' => 'Your subscription has expired. Renew to keep adding and editing your diary.',
            'type' => 'subscription_expired_weekly_reminder',
            'url' => route('subscription.show'),
        ];
    }
}
