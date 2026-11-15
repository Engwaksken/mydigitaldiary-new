<?php

namespace App\Notifications;

use App\Models\SiteSetting;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class RegistrationVerificationNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $site = SiteSetting::current();
        $siteName = trim((string) ($site->site_name ?: 'My Digital Diary'));
        $primaryColor = method_exists($notifiable, 'themeColor')
            ? (string) $notifiable->themeColor()
            : '#00897B';

        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) {
            $primaryColor = '#00897B';
        }

        return (new MailMessage)
            ->subject("Verify your email - {$siteName}")
            ->view('emails.registration-verification', [
                'siteName' => $siteName,
                'primaryColor' => $primaryColor,
                'recipientName' => trim((string) ($notifiable->name ?? '')),
                'verificationUrl' => $this->verificationUrl($notifiable),
            ]);
    }
}
