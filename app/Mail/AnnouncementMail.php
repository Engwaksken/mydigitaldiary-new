<?php

namespace App\Mail;

use App\Models\Announcement;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AppreciationMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;

/**
 * Email copy of an admin Announcement (sent by SendAnnouncementJob when
 * "Also send email" is ticked). Same branded layout as the weekly
 * appreciation email, with a signed one-click-to-confirm unsubscribe that
 * switches off the "product_updates" preference.
 */
class AnnouncementMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public Announcement $announcement)
    {
    }

    public static function unsubscribeUrl(User $user): string
    {
        return URL::signedRoute('product-updates.unsubscribe', ['user' => $user->id]);
    }

    public function subjectLine(): string
    {
        $site = SiteSetting::current()->site_name ?: 'My Digital Diary';

        return $this->announcement->isNewFeature()
            ? "New in {$site}: {$this->announcement->title}"
            : $this->announcement->title;
    }

    /** View data, shared with the admin preview so it renders identically. */
    public function templateData(): array
    {
        $site = SiteSetting::current();

        $primaryColor = method_exists($this->user, 'themeColor')
            ? (string) $this->user->themeColor()
            : '#00897B';

        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) {
            $primaryColor = '#00897B';
        }

        $paragraphs = array_values(array_filter(
            array_map('trim', preg_split("/\R{2,}/", (string) $this->announcement->body) ?: []),
            fn ($p) => $p !== ''
        ));

        return [
            'siteName' => $site->site_name ?: 'My Digital Diary',
            'logoUrl' => $site->logoUrl(),
            'primaryColor' => $primaryColor,
            'recipientName' => AppreciationMessageService::firstName($this->user),
            'isNewFeature' => $this->announcement->isNewFeature(),
            'title' => $this->announcement->title,
            'paragraphs' => $paragraphs,
            'ctaLabel' => $this->announcement->resolvedCtaLabel() ?: 'Open My Digital Diary',
            'ctaUrl' => $this->announcement->absoluteLinkUrl(),
            'unsubscribeUrl' => self::unsubscribeUrl($this->user),
        ];
    }

    public function build()
    {
        $data = $this->templateData();
        $unsubscribeUrl = $data['unsubscribeUrl'];

        return $this->subject($this->subjectLine())
            ->view('emails.announcement', $data)
            ->withSymfonyMessage(function (Email $message) use ($unsubscribeUrl): void {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', "<{$unsubscribeUrl}>");
            });
    }
}
