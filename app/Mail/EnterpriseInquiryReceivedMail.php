<?php

namespace App\Mail;

use App\Models\EnterpriseInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EnterpriseInquiryReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public EnterpriseInquiry $inquiry)
    {
    }

    public function build()
    {
        return $this->subject('New Enterprise inquiry - ' . $this->inquiry->email)
            ->markdown('emails.enterprise-inquiry-received');
    }
}
