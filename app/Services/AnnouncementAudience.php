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

    /**
     * In-app (and the base for push): every non-suspended user, or only the
     * given users when the announcement was sent from the admin Users page.
     *
     * @param  list<int>|null  $userIds  null means everyone
     */
    public function inApp(?array $userIds = null): Builder
    {
        return User::query()
            ->whereNull('suspended_at')
            ->when($userIds !== null, fn (Builder $query) => $query->whereIn('id', $userIds));
    }

    /** @param  list<int>|null  $userIds */
    public function push(?array $userIds = null): Builder
    {
        return $this->inApp($userIds)->has('deviceTokens');
    }

    /**
     * Before the opt-out check, which lives in JSON and is applied in PHP.
     *
     * @param  list<int>|null  $userIds
     */
    public function emailCandidates(?array $userIds = null): Builder
    {
        return $this->inApp($userIds)
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

    /**
     * @param  list<int>|null  $userIds
     * @return array{in_app:int,push:int,email:int}
     */
    public function counts(?array $userIds = null): array
    {
        $email = 0;
        $this->emailCandidates($userIds)->chunkById(500, function ($users) use (&$email): void {
            $email += $users->filter(fn (User $user) => $this->wantsEmail($user))->count();
        });

        return [
            'in_app' => $this->inApp($userIds)->count(),
            'push' => $this->push($userIds)->count(),
            'email' => $email,
        ];
    }
}
