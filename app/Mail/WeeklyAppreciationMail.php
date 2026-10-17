<?php

namespace App\Mail;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;

class WeeklyAppreciationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $paragraphs  Plain text; escaped by the template.
     */
    public function __construct(public User $user, public string $subjectLine, public array $paragraphs)
    {
    }

    public static function unsubscribeUrl(User $user): string
    {
        return URL::signedRoute('appreciation.unsubscribe', ['user' => $user->id]);
    }

    public function build()
    {
        $site = SiteSetting::current();
        $unsubscribeUrl = self::unsubscribeUrl($this->user);

        $primaryColor = method_exists($this->user, 'themeColor')
            ? (string) $this->user->themeColor()
            : '#00897B';

        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) {
            $primaryColor = '#00897B';
        }

        return $this->subject($this->subjectLine)
            ->view('emails.weekly-appreciation', [
                'siteName' => $site->site_name ?: 'My Digital Diary',
                'logoUrl' => $site->logoUrl(),
                'primaryColor' => $primaryColor,
                'recipientName' => \App\Services\AppreciationMessageService::firstName($this->user),
                'paragraphs' => $this->paragraphs,
                'dashboardUrl' => url('/dashboard'),
                'unsubscribeUrl' => $unsubscribeUrl,
            ])
            ->withSymfonyMessage(function (Email $message) use ($unsubscribeUrl): void {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', "<{$unsubscribeUrl}>");
            });
    }
}
