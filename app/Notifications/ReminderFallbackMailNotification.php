<?php

namespace App\Notifications;

use App\Models\Reminder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Minimal fallback reminder email.
 *
 * This deliberately uses Laravel's standard notification template - the
 * same low-complexity path used by the login OTP message - so a provider
 * that rejects the richer branded HTML can still accept the reminder.
 */
class ReminderFallbackMailNotification extends Notification
{
    use Queueable;

    public function __construct(public Reminder $reminder)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reminder: ' . $this->reminder->title)
            ->greeting('Hi ' . trim((string) ($notifiable->name ?? 'there')) . ',')
            ->line($this->reminder->message ?: 'This is your scheduled reminder.')
            ->action('Open My Digital Diary', url('/dashboard'))
            ->line('You are receiving this because you set up a ' . $this->reminder->frequency . ' reminder.')
            ->salutation('Regards, My Digital Diary');
    }
}
