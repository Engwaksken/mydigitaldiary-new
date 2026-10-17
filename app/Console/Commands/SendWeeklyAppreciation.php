<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\WeeklyAppreciationMail;
use App\Models\AppreciationEmailLog;
use App\Models\User;
use App\Services\AppreciationMessageService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Weekly AI-written "thank you for joining" email.
 *
 * Recipients: verified, non-suspended, non-admin users who are not pending
 * deletion and have not switched off "weekly_appreciation" in their email
 * preferences. One appreciation_email_logs row per user per ISO week makes
 * the command safe to re-run.
 */
class SendWeeklyAppreciation extends Command
{
    protected $signature = 'appreciation:send-weekly
        {--dry-run : Show who would receive which message without sending or logging anything}
        {--user= : Only process this user (ID or email)}';

    protected $description = 'Send each member a weekly AI-written appreciation email for joining My Digital Diary.';

    public function handle(AppreciationMessageService $messages): int
    {
        $weekKey = AppreciationMessageService::weekKey();
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        $query = $this->recipients($weekKey);

        if (filled($this->option('user'))) {
            $needle = (string) $this->option('user');
            $query->where(fn (Builder $q) => ctype_digit($needle)
                ? $q->whereKey((int) $needle)
                : $q->where('email', $needle));

            if (! (clone $query)->exists()) {
                $this->warn("User {$needle} is not eligible this week (not found, unverified, suspended, opted out or already sent for {$weekKey}).");

                return self::SUCCESS;
            }
        }

        $query->chunkById(100, function ($users) use ($messages, $weekKey, $dryRun, &$sent, &$skipped, &$failed): void {
            foreach ($users as $user) {
                if (! $user->engagementNotificationEnabled('weekly_appreciation')) {
                    $skipped++;
                    continue;
                }

                try {
                    $copy = $messages->compose($user, $weekKey);
                } catch (Throwable $exception) {
                    $failed++;
                    report($exception);
                    continue;
                }

                if ($dryRun) {
                    $this->line("[{$copy['source']}] {$user->email}: {$copy['subject']}");
                    $this->line('    '.implode("\n    ", $copy['paragraphs']));
                    $sent++;
                    continue;
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
                    $skipped++;
                    continue;
                }

                try {
                    Mail::to($user)->send(new WeeklyAppreciationMail($user, $copy['subject'], $copy['paragraphs']));
                    $log->forceFill(['sent_at' => now()])->save();
                    $sent++;
                } catch (Throwable $exception) {
                    // Release the claim so the next run can retry this person.
                    $log->delete();
                    $failed++;
                    Log::warning('Weekly appreciation email could not be sent.', [
                        'user_id' => $user->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        });

        $verb = $dryRun ? 'would be sent' : 'sent';
        $this->info("{$sent} weekly appreciation email(s) {$verb} for {$weekKey}; {$skipped} skipped; {$failed} failed.");

        return self::SUCCESS;
    }

    private function recipients(string $weekKey): Builder
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
}
