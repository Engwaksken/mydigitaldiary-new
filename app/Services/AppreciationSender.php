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
        return self::recipients($weekKey)
            ->whereIn('id', collect($ids)->all())
            ->get()
            ->filter(fn (User $user) => $user->engagementNotificationEnabled('weekly_appreciation'))
            ->map(fn (User $user) => (int) $user->id)
            ->values()
            ->all();
    }

    /** Why $user will not get this week's email, or null when they will. */
    public static function ineligibleReason(User $user, string $weekKey): ?string
    {
        return match (true) {
            blank($user->email) => 'No email address',
            $user->email_verified_at === null => 'Email not verified',
            $user->suspended_at !== null => 'Account suspended',
            $user->deletion_requested_at !== null || $user->scheduled_deletion_at !== null => 'Account pending deletion',
            $user->role === 'admin' => 'Admin account',
            ! $user->engagementNotificationEnabled('weekly_appreciation') => 'Opted out',
            AppreciationEmailLog::query()->where('user_id', $user->id)->where('week_key', $weekKey)->exists() => 'Already sent this week',
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

    private static function draftKey(User $user, string $weekKey): string
    {
        return "appreciation-draft:{$weekKey}:{$user->id}";
    }

    /** @return self::SENT|self::SKIPPED|self::FAILED */
    public function send(User $user, string $weekKey): string
    {
        if (! $user->engagementNotificationEnabled('weekly_appreciation')) {
            return self::SKIPPED;
        }

        try {
            $copy = $this->draft($user, $weekKey);
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
