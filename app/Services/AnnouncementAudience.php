<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who receives an announcement on each channel. Shared by
 * SendAnnouncementJob, the admin form's recipient counts and
 * `announcements:send-feature --dry-run`, so the numbers always agree.
 */
class AnnouncementAudience
{
    public const EMAIL_PREFERENCE = 'product_updates';

    /** In-app (and the base for push): every non-suspended user. */
    public function inApp(): Builder
    {
        return User::query()->whereNull('suspended_at');
    }

    public function push(): Builder
    {
        return $this->inApp()->has('deviceTokens');
    }

    /** Before the opt-out check, which lives in JSON and is applied in PHP. */
    public function emailCandidates(): Builder
    {
        return $this->inApp()
            ->whereNotNull('email')
            ->whereNotNull('email_verified_at')
            ->whereNull('deletion_requested_at')
            ->whereNull('scheduled_deletion_at');
    }

    public function wantsEmail(User $user): bool
    {
        return $user->suspended_at === null
            && filled($user->email)
            && $user->email_verified_at !== null
            && $user->deletion_requested_at === null
            && $user->scheduled_deletion_at === null
            && $user->engagementNotificationEnabled(self::EMAIL_PREFERENCE);
    }

    /** @return array{in_app:int,push:int,email:int} */
    public function counts(): array
    {
        $email = 0;
        $this->emailCandidates()->chunkById(500, function ($users) use (&$email): void {
            $email += $users->filter(fn (User $user) => $this->wantsEmail($user))->count();
        });

        return [
            'in_app' => $this->inApp()->count(),
            'push' => $this->push()->count(),
            'email' => $email,
        ];
    }
}
