<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to admins after a one-off extension grant
 * (subscriptions:grant-extension) so the operator knows exactly how many
 * free days were offered, to whom, and that a weekly reminder now runs for
 * anyone still expired.
 */
class SubscriptionExtensionSummaryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $trialCount,
        public int $trialDays,
        public int $expiredCount,
        public int $expiredDays
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Subscription extensions granted')
            ->greeting('Hi ' . $notifiable->name . ',')
            ->line('A free-day extension was just applied to My Digital Diary accounts:')
            ->line('**Trial users:** ' . $this->trialCount . ' granted ' . $this->trialDays . ' extra day(s).')
            ->line('**Expired subscribers:** ' . $this->expiredCount . ' granted ' . $this->expiredDays . ' free day(s).')
            ->line('Expired users now receive a weekly reminder until they renew.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Subscription extensions granted',
            'body' => sprintf(
                '%d trial user(s) granted %d day(s); %d expired subscriber(s) granted %d day(s).',
                $this->trialCount,
                $this->trialDays,
                $this->expiredCount,
                $this->expiredDays
            ),
            'type' => 'subscription_extension_summary',
            'trial_count' => $this->trialCount,
            'trial_days' => $this->trialDays,
            'expired_count' => $this->expiredCount,
            'expired_days' => $this->expiredDays,
        ];
    }
}
