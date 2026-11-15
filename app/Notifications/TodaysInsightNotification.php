<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TodaysInsightNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int|string|null $insightId,
        private readonly string $title,
        private readonly string $message,
        private readonly string $fingerprint,
        private readonly ?string $actionUrl = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Today's Insight - ".$this->title)
            ->greeting('Hello '.($notifiable->name ?: 'there').',')
            ->line($this->message)
            ->action('Open Today’s Insight', $this->actionUrl ?: route('dashboard'))
            ->line('Your Today’s Insight has changed, so this is the latest update.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'todays_insight',
            'insight_id' => $this->insightId,
            'fingerprint' => $this->fingerprint,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->actionUrl ?: route('dashboard'),
            'show_popup' => true,
        ];
    }
}
