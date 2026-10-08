<?php

namespace App\Providers;

use App\Http\Controllers\UserGuideController;
use App\Http\Controllers\UserDataController;
use App\Services\UserDataVaultService;
use App\Models\LoginActivity;
use App\Models\SiteSetting;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use App\Http\Controllers\SupportController as UserSupportController;
use App\Http\Controllers\Api\SupportController as ApiSupportController;
use App\Http\Controllers\Api\CurrencyPreferenceController;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Keep project helpers available globally without requiring a
        // composer dump-autoload deployment step.
        require_once app_path('helpers.php');
    }

    public function boot(): void
    {
        // Sanitize the configured sender before Symfony Mailer builds any
        // message. This protects every mail/notification in the application
        // from accidental CR/LF characters copied into .env/settings.
        $cleanMailAddress = static function ($value): string {
            $value = (string) $value;
            $value = preg_replace('/[\r\n\t]+/', '', $value) ?? $value;
            $value = trim($value);

            if (preg_match('/mailto:([^\)\]\s>]+)/i', $value, $matches)) {
                $value = $matches[1];
            } elseif (preg_match('/<([^<>\s]+@[^<>\s]+)>/', $value, $matches)) {
                $value = $matches[1];
            }

            return trim($value, " \t\n\r\0\x0B\"'");
        };

        $fromAddress = $cleanMailAddress(config('mail.from.address'));
        if ($fromAddress !== '') {
            config(['mail.from.address' => $fromAddress]);
        }

        /*
        |------------------------------------------------------------------
        | Application timezone
        |------------------------------------------------------------------
        | Keep Carbon, datetime-local values and scheduled reminders on the
        | same configured clock. This preserves the recent reminder fix.
        */
        $timezone = (string) config('digital_diary.timezone', 'Africa/Kampala');
        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);

        /*
        |------------------------------------------------------------------
        | Persistent browser sessions
        |------------------------------------------------------------------
        | Keep normal signed-in browser sessions alive for up to 30 days by
        | default instead of Laravel's shorter default window. Production can
        | override this with SESSION_LIFETIME in .env. Browser close should
        | not invalidate the cookie unless explicitly requested in .env.
        */
        config([
            'session.lifetime' => (int) env('SESSION_LIFETIME', 43200),
            'session.expire_on_close' => filter_var(
                env('SESSION_EXPIRE_ON_CLOSE', false),
                FILTER_VALIDATE_BOOL
            ),
        ]);

        View::composer('*', function ($view) {
            $view->with('siteSettings', SiteSetting::current());
        });

        /*
        |------------------------------------------------------------------
        | Public User Guide + authenticated Login Activity
        |------------------------------------------------------------------
        | These routes intentionally live here because routes/web.php is a
        | heavily customised file that has received several feature merges.
        | Keeping these small routes here prevents another web.php overwrite
        | from removing them.
        */
        Route::middleware('web')
            ->get('/user-guide', [UserGuideController::class, 'index'])
            ->name('user-guide');


        // Backward-compatible route name for older cached/profile/activity views.
        // Login history is admin-only now: admins are sent to the admin report;
        // normal users are returned to their general Activity Log without exposing
        // cross-account login records.
        Route::middleware(['web', 'auth'])->get('/login-activity', function () {
            $user = auth()->user();
            if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
                return redirect()->route('admin.login-activities.index');
            }
            return redirect()->route('activity');
        })->name('login-activity.index');

        /*
        |------------------------------------------------------------------
        | Successful login history
        |------------------------------------------------------------------
        | Record only completed authentication events. Logging must never
        | prevent a user from signing in if the table is unavailable.
        */
        /*
        |------------------------------------------------------------------
        | Browser push follows the signed-in person
        |------------------------------------------------------------------
        | A diary's morning reminder names today's tasks. When someone signs
        | out of the web app, this browser's push token is dropped so the
        | next person holding the device does not see them. (The session
        | still exists while Logout fires; it is invalidated afterwards.)
        */
        Event::listen(Logout::class, function (Logout $event): void {
            try {
                $deviceId = request()->hasSession() ? request()->session()->get('pm_push_device_id') : null;

                if ($event->user && $deviceId) {
                    \App\Models\DeviceToken::where('user_id', $event->user->getAuthIdentifier())
                        ->where('device_id', $deviceId)
                        ->where('platform', 'web')
                        ->delete();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        });

        Event::listen(Login::class, function (Login $event): void {
            if (! Schema::hasTable('login_activities')) {
                return;
            }

            try {
                LoginActivity::create([
                    'user_id' => $event->user->getAuthIdentifier(),
                    'guard' => $event->guard,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'session_id' => request()->hasSession() ? request()->session()->getId() : null,
                    'logged_in_at' => now(),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        });


        /*
        |------------------------------------------------------------------
        | Currency preference + AI/human support routes
        |------------------------------------------------------------------
        | Registered here intentionally so this feature does not replace the
        | heavily merged routes/web.php or routes/api.php files.
        */
        Route::middleware(['web', 'auth', 'verified'])->group(function () {
            Route::put('/profile/currency', [\App\Http\Controllers\ProfileController::class, 'updateCurrency'])->name('profile.currency');
            Route::get('/support/widget/history', [UserSupportController::class, 'widgetHistory'])->name('support.widget.history');
            Route::post('/support/widget/send', [UserSupportController::class, 'widgetSend'])->name('support.widget.send');
        });

        Route::middleware(['web', 'auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
            Route::get('/login-activities', [\App\Http\Controllers\Admin\AdminLoginActivityController::class, 'index'])->name('login-activities.index');
            Route::post('/users/{user}/restore-data', [\App\Http\Controllers\Admin\AdminUserController::class, 'restoreData'])->name('users.restore-data');
        });

        // Personal data centre: activity, backup, recycle bin and usage progress.
        Route::middleware(['web', 'auth', 'verified'])->prefix('account-data')->name('account-data.')->group(function () {
            Route::get('/', [UserDataController::class, 'index'])->name('index');
            Route::get('/backup', [UserDataController::class, 'backup'])->name('backup');
            Route::post('/trash/{item}/restore', [UserDataController::class, 'restore'])->name('restore');
            Route::delete('/trash/{item}', [UserDataController::class, 'destroy'])->name('destroy');
        });

        Route::middleware(['api', 'auth:sanctum'])->prefix('api')->name('api.')->group(function () {
            Route::get('/profile/currency', [CurrencyPreferenceController::class, 'show'])->name('profile.currency.show');
            Route::put('/profile/currency', [CurrencyPreferenceController::class, 'update'])->name('profile.currency.update');
            Route::get('/account-data/usage', [UserDataController::class, 'usage'])->name('account-data.usage');
            Route::get('/account-data/backup', [UserDataController::class, 'backup'])->name('account-data.backup');
            Route::get('/account-data/trash', [UserDataController::class, 'trashJson'])->name('account-data.trash');
            Route::post('/account-data/trash/{item}/restore', [UserDataController::class, 'restoreJson'])->name('account-data.restore');
            Route::delete('/account-data/trash/{item}', [UserDataController::class, 'destroyJson'])->name('account-data.destroy');

            Route::get('/support', [ApiSupportController::class, 'show'])->name('support.show');
            Route::post('/support', [ApiSupportController::class, 'send'])->name('support.send');
        });

        // Inject the floating support chatbot into every authenticated HTML
        // page without replacing layouts/app.blade.php. This keeps the widget
        // visible even as the main layout continues to receive other updates.
        app('router')->pushMiddlewareToGroup('web', \App\Http\Middleware\InjectSupportWidget::class);


        // Keep Personal Goal progress in sync with linked execution records. This
        // is intentionally event-driven so Web, Mobile and offline replays all
        // produce the same progress without requiring a manual refresh action.
        $syncGoalProgress = function ($model): void {
            try {
                if (! Schema::hasTable('personal_goals')) return;
                $current = $model->personal_goal_id ?? null;
                $original = method_exists($model, 'getOriginal') ? $model->getOriginal('personal_goal_id') : null;
                $service = app(\App\Services\GoalProgressService::class);
                if ($current) $service->recalculate((int)$current);
                if ($original && (int)$original !== (int)$current) $service->recalculate((int)$original);
            } catch (\Throwable $e) { report($e); }
        };
        foreach ([\App\Models\Plan::class, \App\Models\DailyPlanItem::class, \App\Models\ProjectTask::class, \App\Models\GoalMilestone::class] as $goalLinkedModel) {
            $goalLinkedModel::saved($syncGoalProgress);
            $goalLinkedModel::deleted($syncGoalProgress);
        }

        \App\Models\PersonalGoal::saved(function ($goal): void {
            try {
                if (Schema::hasTable('personal_goals')) app(\App\Services\GoalProgressService::class)->recalculate($goal);
            } catch (\Throwable $e) { report($e); }
        });

        // Capture supported user-owned Eloquent records just before deletion so
        // accidental deletes can be restored for 30 days. Fail-open: recycle-bin
        // problems must never block the original requested delete.
        Event::listen('eloquent.deleting: *', function (string $eventName, array $data): void {
            $model = $data[0] ?? null;
            if (! $model instanceof \Illuminate\Database\Eloquent\Model) return;
            try { app(UserDataVaultService::class)->capture($model); } catch (\Throwable $e) { report($e); }
        });

        /*
        | IMPORTANT:
        | Do NOT manually register Laravel's Registered ->
        | SendEmailVerificationNotification listener here. Laravel already
        | provides it for MustVerifyEmail users; adding it here would send
        | registration verification twice.
        */
    }
}
