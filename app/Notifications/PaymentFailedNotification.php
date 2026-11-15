<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Fired specifically from an admin's manual rejection of a payment
 * (e.g. a bank transfer that couldn't be verified) - real-time card
 * failures already show an error directly to the user mid-checkout,
 * so this covers the "found out later, after the fact" case instead.
 */
class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment, public ?string $reason = null)
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
            'reason' => $this->reason,
        ];
    }
}
