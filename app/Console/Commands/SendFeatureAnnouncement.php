<?php

namespace App\Console\Commands;

use App\Jobs\SendAnnouncementJob;
use App\Models\Announcement;
use App\Models\User;
use App\Services\AnnouncementAudience;
use App\Http\Controllers\Admin\AdminAnnouncementController;
use Illuminate\Console\Command;

/**
 * SSH-friendly way to announce a new feature, e.g.
 *
 *   php artisan announcements:send-feature team_chat \
 *       --title="Team Chat is here" --message="Chat with your team..." --email
 *
 * Creates a new_feature Announcement (credited to the first admin) and
 * queues the same SendAnnouncementJob the admin form uses.
 */
class SendFeatureAnnouncement extends Command
{
    protected $signature = 'announcements:send-feature
        {feature_key : Key from config/features.php}
        {--title= : Announcement title}
        {--message= : Announcement message}
        {--link= : Optional link, a path such as /meetings or an https:// address (defaults to the feature page)}
        {--cta= : Optional button label (defaults to "Explore now")}
        {--no-push : Do not send a push notification}
        {--email : Also send an email to opted-in, verified users}
        {--dry-run : Show recipient counts per channel without creating or sending anything}';

    protected $description = 'Create and send a "New feature" announcement to every user.';

    public function handle(AnnouncementAudience $audience): int
    {
        $featureKey = (string) $this->argument('feature_key');
        $catalogue = Announcement::featureCatalogue();

        if (! array_key_exists($featureKey, $catalogue)) {
            $this->error("Unknown feature \"{$featureKey}\". Choose one of: ".implode(', ', array_keys($catalogue)));

            return self::FAILURE;
        }

        $title = trim((string) $this->option('title'));
        $message = trim((string) $this->option('message'));
        $link = trim((string) $this->option('link'));
        $cta = trim((string) $this->option('cta'));

        $errors = array_filter([
            $title === '' ? '--title is required.' : null,
            mb_strlen($title) > 255 ? '--title must be 255 characters or fewer.' : null,
            $message === '' ? '--message is required.' : null,
            mb_strlen($message) > 2000 ? '--message must be 2000 characters or fewer.' : null,
            $link !== '' && (mb_strlen($link) > 255 || ! AdminAnnouncementController::isAllowedLink($link)) ? '--link must be a path such as /meetings or a full https:// address.' : null,
            mb_strlen($cta) > 40 ? '--cta must be 40 characters or fewer.' : null,
        ]);

        if ($errors) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $sendPush = ! $this->option('no-push');
        $sendEmail = (bool) $this->option('email');

        $announcement = new Announcement([
            'title' => $title,
            'body' => $message,
            'type' => Announcement::TYPE_NEW_FEATURE,
            'feature_key' => $featureKey,
            'link_url' => $link !== '' ? $link : null,
            'cta_label' => $cta !== '' ? $cta : null,
            'send_push' => $sendPush,
            'send_email' => $sendEmail,
        ]);

        $counts = $audience->counts();
        $this->table(['Channel', 'Recipients'], [
            ['In-app', $counts['in_app']],
            ['Push', $sendPush ? $counts['push'] : '0 (--no-push)'],
            ['Email', $sendEmail ? $counts['email'] : '0 (add --email)'],
        ]);
        $this->line('Feature: '.$catalogue[$featureKey]['label']);
        $this->line('Link: '.($announcement->resolvedLinkUrl() ?? '(none)'));
        $this->line('Button: '.$announcement->resolvedCtaLabel());

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing was created or sent.');

            return self::SUCCESS;
        }

        $admin = User::query()->whereIn('role', ['admin', 'super_admin'])->orderBy('id')->first();
        if (! $admin) {
            $this->error('No admin user exists to credit the announcement to.');

            return self::FAILURE;
        }

        $announcement->created_by = $admin->id;
        $announcement->save();

        SendAnnouncementJob::dispatch($announcement);

        $this->info("Announcement #{$announcement->id} created and queued for sending.");

        return self::SUCCESS;
    }
}
