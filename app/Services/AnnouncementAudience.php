<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * Who receives an announcement on each channel. Shared by
 * SendAnnouncementJob, the admin form's recipient counts and
 * `announcements:send-feature --dry-run`, so the numbers always agree.
 *
 * $skipFeatureKey leaves out people already told about that feature: users
 * with a delivery from an earlier, sent new_feature announcement with the
 * same feature_key (any channel that reached them counts).
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
    public function inApp(?array $userIds = null, ?string $skipFeatureKey = null, ?int $exceptAnnouncementId = null): Builder
    {
        return User::query()
            ->whereNull('suspended_at')
            ->when($userIds !== null, fn (Builder $query) => $query->whereIn('id', $userIds))
            ->when(filled($skipFeatureKey), fn (Builder $query) => $query->whereNotExists(
                fn (QueryBuilder $sub) => $this->previousDeliveries($sub, (string) $skipFeatureKey, $exceptAnnouncementId)
            ));
    }

    /** @param  list<int>|null  $userIds */
    public function push(?array $userIds = null, ?string $skipFeatureKey = null, ?int $exceptAnnouncementId = null): Builder
    {
        return $this->inApp($userIds, $skipFeatureKey, $exceptAnnouncementId)->has('deviceTokens');
    }

    /**
     * Before the opt-out check, which lives in JSON and is applied in PHP.
     *
     * @param  list<int>|null  $userIds
     */
    public function emailCandidates(?array $userIds = null, ?string $skipFeatureKey = null, ?int $exceptAnnouncementId = null): Builder
    {
        return $this->inApp($userIds, $skipFeatureKey, $exceptAnnouncementId)
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
    public function counts(?array $userIds = null, ?string $skipFeatureKey = null, ?int $exceptAnnouncementId = null): array
    {
        $email = 0;
        $this->emailCandidates($userIds, $skipFeatureKey, $exceptAnnouncementId)->chunkById(500, function ($users) use (&$email): void {
            $email += $users->filter(fn (User $user) => $this->wantsEmail($user))->count();
        });

        return [
            'in_app' => $this->inApp($userIds, $skipFeatureKey, $exceptAnnouncementId)->count(),
            'push' => $this->push($userIds, $skipFeatureKey, $exceptAnnouncementId)->count(),
            'email' => $email,
        ];
    }

    /**
     * How many of these (non-suspended) users were already told about the
     * feature, and when it was last announced to them.
     *
     * @param  list<int>|null  $userIds
     * @return array{count:int, last_sent_at:?Carbon}
     */
    public function alreadyTold(?array $userIds, ?string $featureKey, ?int $exceptAnnouncementId = null): array
    {
        if (blank($featureKey)) {
            return ['count' => 0, 'last_sent_at' => null];
        }

        $count = User::query()
            ->whereNull('suspended_at')
            ->when($userIds !== null, fn (Builder $query) => $query->whereIn('id', $userIds))
            ->whereExists(fn (QueryBuilder $sub) => $this->previousDeliveries($sub, (string) $featureKey, $exceptAnnouncementId))
            ->count();

        $lastSentAt = null;
        if ($count > 0) {
            $value = $this->previousAnnouncements((string) $featureKey, $exceptAnnouncementId)
                ->whereExists(fn (QueryBuilder $sub) => $sub->selectRaw('1')
                    ->from('announcement_deliveries')
                    ->whereColumn('announcement_deliveries.announcement_id', 'announcements.id')
                    ->when($userIds !== null, fn (QueryBuilder $q) => $q->whereIn('announcement_deliveries.user_id', $userIds)))
                ->max('sent_at');
            $lastSentAt = $value ? Carbon::parse($value, config('app.timezone')) : null;
        }

        return ['count' => $count, 'last_sent_at' => $lastSentAt];
    }

    /** "Already announced to 12 of these users on 20 Oct 2026", or null when nobody was. */
    public function alreadyToldNotice(array $told): ?string
    {
        if ($told['count'] < 1) {
            return null;
        }

        $users = number_format($told['count']).' of these users';
        $date = $told['last_sent_at']?->copy()->timezone(config('app.timezone'))->format('j M Y');

        return 'Already announced to '.$users.($date ? ' on '.$date : '').'.';
    }

    private function previousAnnouncements(string $featureKey, ?int $exceptAnnouncementId): Builder
    {
        return Announcement::query()
            ->where('type', Announcement::TYPE_NEW_FEATURE)
            ->where('feature_key', $featureKey)
            ->where('status', Announcement::STATUS_SENT)
            ->when($exceptAnnouncementId !== null, fn (Builder $query) => $query->whereKeyNot($exceptAnnouncementId));
    }

    /** Correlated subquery: a delivery to users.id from an earlier sent announcement of this feature. */
    private function previousDeliveries(QueryBuilder $sub, string $featureKey, ?int $exceptAnnouncementId): QueryBuilder
    {
        return $sub->selectRaw('1')
            ->from('announcement_deliveries')
            ->join('announcements', 'announcements.id', '=', 'announcement_deliveries.announcement_id')
            ->whereColumn('announcement_deliveries.user_id', 'users.id')
            ->where('announcements.type', Announcement::TYPE_NEW_FEATURE)
            ->where('announcements.feature_key', $featureKey)
            ->where('announcements.status', Announcement::STATUS_SENT)
            ->when($exceptAnnouncementId !== null, fn (QueryBuilder $q) => $q->where('announcements.id', '!=', $exceptAnnouncementId))
            ->where(fn (QueryBuilder $q) => $q->whereNotNull('announcement_deliveries.in_app_at')
                ->orWhereNotNull('announcement_deliveries.push_at')
                ->orWhereNotNull('announcement_deliveries.email_at'));
    }
}
