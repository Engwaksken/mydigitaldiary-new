<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired from all three payment-completion paths (card callback,
 * gateway webhook, admin manual approval) - a receipt email already
 * gets sent separately (see PaymentReceiptMail); this is specifically
 * for the in-app/mobile notification list, which nothing populated
 * for payment events before this existed at all.
 */
class PaymentSuccessfulNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'amount' => (float) $this->payment->amount,
            'plan_name' => $this->payment->plan?->name,
        ];
    }
}
