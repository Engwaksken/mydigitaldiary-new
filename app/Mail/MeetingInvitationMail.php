<?php

namespace App\Mail;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to each address entered in a meeting's "Attendees" field -
 * plain email addresses, not necessarily registered users, so this goes
 * through Mail::to() directly rather than Laravel's Notification system
 * (which expects a Notifiable model). Triggered from
 * MeetingController::afterSave() and storeMultiple().
 */
class MeetingInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Meeting $meeting, public string $organizerName)
    {
    }

    public function build()
    {
        return $this->subject('Meeting invitation: ' . $this->meeting->title)
            ->markdown('emails.meeting-invitation');
    }
}
