<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\WeeklyAppreciationMail;
use App\Models\AppreciationEmailLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the appreciation email to one person, shared by the weekly
 * appreciation:send-weekly command and the admin Users page bulk action so
 * both follow the same rules: verified, non-suspended, non-admin, not pending
 * deletion, opted in, and at most one per user per ISO week.
 */
class AppreciationSender
{
    public const SENT = 'sent';

    public const SKIPPED = 'skipped';

    public const FAILED = 'failed';

    public function __construct(private readonly AppreciationMessageService $messages)
    {
    }

    /** Users who may receive this week's email (the opt-out is checked per user in send()). */
    public static function recipients(string $weekKey): Builder
    {
        return User::query()
            ->whereNotNull('email')
            ->whereNotNull('email_verified_at')
            ->whereNull('suspended_at')
            ->whereNull('deletion_requested_at')
            ->whereNull('scheduled_deletion_at')
            ->where(fn (Builder $q) => $q->whereNull('role')->orWhere('role', '!=', 'admin'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('appreciation_email_logs')
                ->whereColumn('appreciation_email_logs.user_id', 'users.id')
                ->where('appreciation_email_logs.week_key', $weekKey));
    }

    /**
     * IDs from $ids that will actually be emailed this week.
     *
     * @param  iterable<int>  $ids
     * @return list<int>
     */
    public static function eligibleIds(iterable $ids, string $weekKey): array
    {
        $eligible = [];

        // Chunked so a selection of thousands never builds one huge IN list.
        foreach (collect($ids)->map(fn ($id) => (int) $id)->unique()->chunk(500) as $chunk) {
            self::recipients($weekKey)
                ->whereIn('id', $chunk->values()->all())
                ->get(['id', 'engagement_notification_preferences'])
                ->filter(fn (User $user) => $user->engagementNotificationEnabled('weekly_appreciation'))
                ->each(function (User $user) use (&$eligible): void {
                    $eligible[] = (int) $user->id;
                });
        }

        sort($eligible);

        return $eligible;
    }

    /** Why $user will not get this week's email, or null when they will. */
    public static function ineligibleReason(User $user, string $weekKey, ?bool $alreadySentThisWeek = null): ?string
    {
        $alreadySent = fn (): bool => $alreadySentThisWeek
            ?? AppreciationEmailLog::query()->where('user_id', $user->id)->where('week_key', $weekKey)->exists();

        return match (true) {
            blank($user->email) => 'No email address',
            $user->email_verified_at === null => 'Email not verified',
            $user->suspended_at !== null => 'Account suspended',
            $user->deletion_requested_at !== null || $user->scheduled_deletion_at !== null => 'Account pending deletion',
            $user->role === 'admin' => 'Admin account',
            ! $user->engagementNotificationEnabled('weekly_appreciation') => 'Opted out',
            $alreadySent() => 'Already sent this week',
            default => null,
        };
    }

    /**
     * This week's message for $user, kept for a few hours so the copy an
     * admin previews is exactly the copy that is sent.
     *
     * @return array{subject:string, paragraphs:list<string>, source:string, theme:string}
     */
    public function draft(User $user, string $weekKey, bool $fresh = false): array
    {
        $key = self::draftKey($user, $weekKey);

        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addHours(6), fn () => $this->messages->compose($user, $weekKey));
    }

    /**
     * The draft already written for this user this week, without writing one.
     *
     * @return array{subject:string, paragraphs:list<string>, source:string, theme:string}|null
     */
    public static function cachedDraft(int $userId, string $weekKey): ?array
    {
        $draft = Cache::get(self::draftKeyFor($userId, $weekKey));

        return is_array($draft) ? $draft : null;
    }

    /** Remembers that $adminId opened this person's preview, so scheduling can keep that copy. */
    public static function rememberPreviewed(int $adminId, int $userId, string $weekKey): void
    {
        $key = self::previewedKey($adminId, $weekKey);
        $ids = (array) Cache::get($key, []);
        $ids[$userId] = true;
        Cache::put($key, $ids, now()->addHours(6));
    }

    /**
     * Previewed drafts among $userIds, keyed by user ID, ready to store on
     * an AppreciationCampaign.
     *
     * @param  list<int>  $userIds
     * @return array<string, array{subject:string, paragraphs:list<string>, source:string, theme:string}>
     */
    public static function previewedDrafts(int $adminId, array $userIds, string $weekKey): array
    {
        $previewed = array_keys((array) Cache::get(self::previewedKey($adminId, $weekKey), []));
        $wanted = array_flip($userIds);
        $drafts = [];

        foreach ($previewed as $userId) {
            if (isset($wanted[(int) $userId]) && ($draft = self::cachedDraft((int) $userId, $weekKey))) {
                $drafts[(string) $userId] = $draft;
            }
        }

        return $drafts;
    }

    private static function draftKey(User $user, string $weekKey): string
    {
        return self::draftKeyFor((int) $user->id, $weekKey);
    }

    private static function draftKeyFor(int $userId, string $weekKey): string
    {
        return "appreciation-draft:{$weekKey}:{$userId}";
    }

    private static function previewedKey(int $adminId, string $weekKey): string
    {
        return "appreciation-previewed:{$weekKey}:{$adminId}";
    }

    /**
     * @param  array{subject:string, paragraphs:list<string>, source:string, theme:string}|null  $copy
     *         the previewed copy to send; null uses this week's draft (written now if needed)
     * @param  int|null  $sentBy  the admin who sent it; null for the weekly automatic run
     * @return self::SENT|self::SKIPPED|self::FAILED
     */
    public function send(User $user, string $weekKey, ?array $copy = null, ?int $sentBy = null, ?int $campaignId = null): string
    {
        if (! $user->engagementNotificationEnabled('weekly_appreciation')) {
            return self::SKIPPED;
        }

        try {
            $copy ??= $this->draft($user, $weekKey);
        } catch (Throwable $exception) {
            report($exception);

            return self::FAILED;
        }

        // Claim the week first: the unique index stops a parallel or
        // repeated run from emailing the same person twice.
        try {
            $log = AppreciationEmailLog::create([
                'user_id' => $user->id,
                'week_key' => $weekKey,
                'source' => $copy['source'],
                'theme' => $copy['theme'],
                'subject' => $copy['subject'],
                'message' => implode("\n\n", $copy['paragraphs']),
                'sent_by' => $sentBy,
                'campaign_id' => $campaignId,
            ]);
        } catch (QueryException) {
            return self::SKIPPED;
        }

        try {
            Mail::to($user)->send(new WeeklyAppreciationMail($user, $copy['subject'], $copy['paragraphs']));
            $log->forceFill(['sent_at' => now()])->save();
            Cache::forget(self::draftKey($user, $weekKey));

            return self::SENT;
        } catch (Throwable $exception) {
            // Release the claim so the next run can retry this person.
            $log->delete();
            Log::warning('Weekly appreciation email could not be sent.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return self::FAILED;
        }
    }
}
