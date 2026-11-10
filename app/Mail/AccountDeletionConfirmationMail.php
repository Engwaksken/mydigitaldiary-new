<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent synchronously (not queued): the link expires in 60 minutes, so it
 * must not sit waiting for a queue worker.
 */
class AccountDeletionConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $url)
    {
    }

    public function build()
    {
        return $this->subject('Confirm your account deletion request')
            ->markdown('emails.account-deletion-confirmation');
    }
}
