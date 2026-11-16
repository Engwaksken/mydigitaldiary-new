<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Services\Ai\AiTextFormatter;

class SimpleDatabaseNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public string $type = 'general'
    ) {
        $this->title = AiTextFormatter::normalize($this->title);
        $this->body = AiTextFormatter::normalize($this->body);
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type,
        ];
    }
}
