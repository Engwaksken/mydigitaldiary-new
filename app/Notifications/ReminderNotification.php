<?php

namespace App\Notifications;

use App\Models\Reminder;
use App\Models\SiteSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public Reminder $reminder, private ?array $channels = null)
    {
    }

    public function via(object $notifiable): array
    {
        // SendReminders may deliberately dispatch channels separately so a
        // failure in mail cannot prevent the in-app notification (or vice
        // versa). Outside that command, preserve the normal combined behavior.
        if ($this->channels !== null) {
            return $this->channels;
        }

        return $this->reminder->channel === 'mail' ? ['mail', 'database'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = SiteSetting::current();

        // Use the same primary colour the user sees throughout My Digital Diary.
        // themeColor() already provides the application default when the user has
        // not selected a custom colour. Validate again here before placing it into
        // inline email CSS.
        $primaryColor = method_exists($notifiable, 'themeColor')
            ? (string) $notifiable->themeColor()
            : '#00897B';

        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) {
            $primaryColor = '#00897B';
        }

        return (new MailMessage)
            ->subject('Reminder: ' . $this->reminder->title)
            ->view('emails.reminder-notification', [
                'siteName' => $site->site_name ?: 'My Digital Diary',
                'logoUrl' => $site->logoUrl(),
                'primaryColor' => $primaryColor,
                'recipientName' => trim((string) ($notifiable->name ?? '')),
                'reminderTitle' => (string) $this->reminder->title,
                'reminderMessage' => $this->reminder->message ?: 'This is your scheduled reminder.',
                'frequency' => (string) $this->reminder->frequency,
                'dashboardUrl' => url('/dashboard'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        $signature = (string) $this->reminder->source_signature;
        $stage = preg_match('/(?:^|:)nudge:[3-5]$/', $signature) === 1
            ? 'overdue'
            : (preg_match('/(?:^|:)nudge:[0-2]$/', $signature) === 1 ? 'pre_due' : 'scheduled');

        return [
            'reminder_id' => $this->reminder->id,
            'title' => $this->reminder->title,
            'module' => $this->reminder->module,
            'message' => $this->reminder->message,
            'source_type' => $this->reminder->source_type,
            'source_id' => $this->reminder->source_id,
            'reminder_stage' => $stage,
            'url' => \App\Services\ReminderCopyService::linkFor($this->reminder),
        ];
    }
}
