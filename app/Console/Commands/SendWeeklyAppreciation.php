<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AppreciationMessageService;
use App\Services\AppreciationSender;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
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

    public function handle(AppreciationMessageService $messages, AppreciationSender $sender): int
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
                $this->warn("User {$needle} is not eligible this week: {$this->ineligibilityReason($needle, $weekKey)}.");

                return self::SUCCESS;
            }
        }

        $query->chunkById(100, function ($users) use ($messages, $sender, $weekKey, $dryRun, &$sent, &$skipped, &$failed): void {
            foreach ($users as $user) {
                if (! $dryRun) {
                    match ($sender->send($user, $weekKey)) {
                        AppreciationSender::SENT => $sent++,
                        AppreciationSender::SKIPPED => $skipped++,
                        default => $failed++,
                    };

                    continue;
                }

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

                $this->line("[{$copy['source']}] {$user->email}: {$copy['subject']}");
                $this->line('    '.implode("
    ", $copy['paragraphs']));
                $sent++;
            }
        });

        $verb = $dryRun ? 'would be sent' : 'sent';
        $this->info("{$sent} weekly appreciation email(s) {$verb} for {$weekKey}; {$skipped} skipped; {$failed} failed.");

        return self::SUCCESS;
    }

    /** Explains why --user matched nobody, checking each recipients() condition in turn. */
    private function ineligibilityReason(string $needle, string $weekKey): string
    {
        $user = ctype_digit($needle)
            ? User::query()->find((int) $needle)
            : User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($needle)])->first();

        return match (true) {
            $user === null => 'no user with that ID or email in the '.config('database.default').' database',
            ! ctype_digit($needle) && $user->email !== $needle => "the stored email is {$user->email} (check the capitalisation)",
            $user->email_verified_at === null => 'their email address is not verified',
            $user->suspended_at !== null => 'the account is suspended',
            $user->deletion_requested_at !== null || $user->scheduled_deletion_at !== null => 'the account is pending deletion',
            $user->role === 'admin' => 'admin accounts do not receive this email',
            default => "they have already been sent this week's email ({$weekKey})",
        };
    }

    private function recipients(string $weekKey): Builder
    {
        return AppreciationSender::recipients($weekKey);
    }
}
