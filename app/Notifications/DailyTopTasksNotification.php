<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailyTopTasksNotification extends Notification
{
    use Queueable;

    /** @param array<int, array{title:string,source:string}> $tasks */
    public function __construct(public array $tasks)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('My Digital Diary - Your Top '.count($this->tasks).' for today')
            ->greeting('Good morning, '.$notifiable->name.'!')
            ->line('Here are your highest-priority items for today:');

        foreach ($this->tasks as $i => $task) {
            $mail->line(($i + 1).'. '.$task['title'].' - '.$task['source']);
        }

        return $mail
            ->action('Open My Digital Diary', url('/dashboard'))
            ->line('This daily Top 3 reminder is scheduled for 8:00 AM.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'daily_top_tasks',
            'title' => 'Your Top '.count($this->tasks).' for today',
            'tasks' => $this->tasks,
            'date' => today()->toDateString(),
        ];
    }
}
