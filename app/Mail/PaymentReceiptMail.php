<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment)
    {
    }

    public function build()
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('subscription.receipt-pdf', ['payment' => $this->payment])->setPaper('a4');

        return $this->subject('Payment received - receipt #' . ($this->payment->receipt_number ?? $this->payment->id))
            ->markdown('emails.payment-receipt')
            ->attachData($pdf->output(), 'receipt-' . ($this->payment->receipt_number ?? $this->payment->id) . '.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
