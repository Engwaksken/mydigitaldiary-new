<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Sent on the schedule SendSubscriptionExpiryReminders runs - 14, 12, 10,
 * 8, 6, 4, 2 days before expiry, and on the expiry date itself.
 * `database` channel (not just `mail`) so it also shows up as an in-app
 * notification the moment a user logs in, per the "in-system
 * notification/alarm upon login" requirement - see
 * DashboardController/layout for how unread ones surface.
 */
class SubscriptionExpiryReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Carbon $expiryDate, public int $daysRemaining)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subjectLine());

        $plan = $notifiable->subscriptionPlan;
        $settings = \App\Models\SiteSetting::current();
        $amountDue = $plan ? format_money($plan->computedPrice((float) $settings->monthly_price)) : format_money((float) $settings->monthly_price);
        $gatewayNames = \App\Models\PaymentGateway::where('is_enabled', true)->get()->map(fn ($g) => $g->display_name ?: $g->name)->implode(', ');

        if ($this->daysRemaining > 0) {
            $mail->greeting('Hi ' . $notifiable->name . ',')
                ->line("Your subscription is set to expire on {$this->expiryDate->format('l, F j, Y')} - that's {$this->daysRemaining} day(s) from now.")
                ->line('Renewing keeps every feature working without interruption - nothing changes until it actually expires.');
        } else {
            $mail->greeting('Hi ' . $notifiable->name . ',')
                ->line("Your subscription expired on {$this->expiryDate->format('l, F j, Y')}.")
                ->line("You can still log in and view everything you've already entered, but adding, editing, or deleting records, downloading reports, and other premium features are on hold until you renew.");
        }

        if ($plan) {
            $mail->line("**Current plan:** {$plan->name}");
        }
        $mail->line("**Amount due to renew:** {$amountDue}");
        if ($gatewayNames) {
            $mail->line("**Available payment methods:** {$gatewayNames}");
        }

        return $mail
            ->action('Renew Subscription', url('/subscription'))
            ->line('Questions about your subscription? Just reply to this email.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'expiry_date' => $this->expiryDate->toDateString(),
            'days_remaining' => $this->daysRemaining,
        ];
    }

    private function subjectLine(): string
    {
        if ($this->daysRemaining > 1) {
            return "Your subscription expires in {$this->daysRemaining} days";
        }

        if ($this->daysRemaining === 1) {
            return 'Your subscription expires tomorrow';
        }

        if ($this->daysRemaining === 0) {
            return 'Your subscription expires today';
        }

        return 'Your subscription has expired';
    }
}
