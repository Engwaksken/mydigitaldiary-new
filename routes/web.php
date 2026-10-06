<?php

use App\Http\Controllers\AiPlanController;
use App\Http\Controllers\Api\EngagementController;
use App\Http\Controllers\ApiCredentialController;
use App\Http\Controllers\BusinessCardController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DailyPlannerController;
use App\Http\Controllers\FinancialPlannerController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\DietLogController;
use App\Http\Controllers\ExerciseLogController;
use App\Http\Controllers\EducationPlanController;
use App\Http\Controllers\EngagementReviewController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\TipsController;
use App\Http\Controllers\HealthCheckupController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MeetingConnectionController;
use App\Http\Controllers\MeetingRecordingController;
use App\Http\Controllers\NetworkContactController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PersonalRelationshipController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavingsContributionController;
use App\Http\Controllers\SavingsGoalController;
use App\Http\Controllers\SavingsOverviewController;
use App\Http\Controllers\SocialMediaAccountController;
use App\Http\Controllers\SleepLogController;
use App\Http\Controllers\SocialMediaPlannerController;
use App\Http\Controllers\SocialMediaAnalyticsController;
use App\Http\Controllers\SocialMediaReportController;
use App\Http\Controllers\SpiritualPracticeController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\SupportAttachmentController;
use App\Http\Controllers\TermsOfUseController;
use App\Http\Controllers\IoTecSubscriptionPaymentController;
use App\Http\Controllers\IoTecSubscriptionWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file assumes you already have Laravel's default auth scaffolding
| (e.g. via `laravel/breeze`) providing /login, /register, /logout and the
| "auth" middleware, plus the 'subscribed' and 'admin' middleware aliases
| registered in bootstrap/app.php (see README.md).
|
*/

Route::get('/', function () {
    return redirect()->route('dashboard');
});

/*
|--------------------------------------------------------------------------
| Progressive Web App
|--------------------------------------------------------------------------
|
| Public, and deliberately outside every auth and subscription middleware
| group: a browser fetches the manifest from the <head> of the login page and
| checks the worker script before anyone has signed in, and a PWA that cannot
| be installed while logged out is a PWA nobody installs.
|
| /sw.js is a route rather than a file in public/ so its cache key and its
| precache list are derived from the current build. See PwaController.
|
*/
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/favicon.ico', [PwaController::class, 'favicon'])->name('pwa.favicon');
Route::get('/pwa-icons/{version}/{variant}.png', [PwaController::class, 'icon'])
    ->where('version', '[a-f0-9]{16}')
    ->where('variant', 'icon-192|icon-512|maskable-512|apple-touch-icon')
    ->name('pwa.icon');
Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.service-worker');

// Public — reachable without an account, since it's linked from the
// registration consent checkbox.
Route::get('/privacy-policy', [PrivacyPolicyController::class, 'show'])->name('privacy-policy');
Route::get('/terms-of-use', [TermsOfUseController::class, 'show'])->name('terms-of-use');

// Public — whoever scans a user's business-card QR code or opens their
// shared link lands here with no account needed, same reasoning as
// privacy-policy above.
Route::get('/card/{slug}', [BusinessCardController::class, 'showPublic'])->name('card.show');

Route::get('/signed-documents/{signedDocument}/shared', [\App\Http\Controllers\SignatureController::class, 'showSharedDocument'])
    ->middleware('signed')
    ->name('signature.documents.shared');

// Public — a prospect considering Enterprise may not have an account yet;
// an existing user can also reach this to inquire about upgrading.
Route::get('/enterprise/contact', [\App\Http\Controllers\EnterpriseInquiryController::class, 'show'])->name('enterprise.contact');
Route::post('/enterprise/contact', [\App\Http\Controllers\EnterpriseInquiryController::class, 'store'])->name('enterprise.contact.submit');

// Public — reached from an emailed invite link before the recipient
// necessarily has an account; handles both cases itself (see
// OrganizationController::acceptInvite()).
Route::get('/organization/accept-invite/{token}', [\App\Http\Controllers\OrganizationController::class, 'acceptInvite'])->name('organization.accept-invite');

// Public — Stripe redirects the BROWSER back here after checkout, which
// has no session at all if checkout was started from the mobile app (an
// API request, with the checkout URL opened in an external browser).
// Safe without auth because it's looked up by Stripe's own unique,
// unguessable session id, never by "whoever is currently logged in."
Route::get('/subscription/pay/card/callback', [SubscriptionController::class, 'payWithCardCallback'])->name('subscription.pay.card.callback');


// Public browser return from ioTec Visa / MasterCard hosted checkout.
Route::get(
    '/subscription/pay/iotec/callback',
    [IoTecSubscriptionPaymentController::class, 'callback']
)->name('subscription.pay.iotec.callback');

// ioTec subscription webhook.
Route::post(
    '/webhooks/iotec/subscriptions',
    IoTecSubscriptionWebhookController::class
)->middleware('throttle:10,1')->name('webhooks.iotec.subscriptions');

Route::post(
    '/webhooks/iotec',
    [\App\Http\Controllers\PaymentGatewayWebhookController::class, 'handle']
)
    ->defaults('gatewayCode', 'iotec')
    ->middleware('throttle:10,1')
    ->name('webhooks.iotec');


// Generic — for any FUTURE aggregator, register
// https://yourdomain/webhooks/{gateway_code} on their side (matching
// whatever gateway_code you set in Admin -> Payment Gateways) instead of
// needing a new named route added here each time.
Route::post('/webhooks/{gatewayCode}', [\App\Http\Controllers\PaymentGatewayWebhookController::class, 'handle'])
    ->middleware('throttle:10,1')
    ->name('webhooks.gateway');

// Subscription/billing + profile routes are intentionally OUTSIDE the
// 'subscribed' gate below — a user whose trial has lapsed still needs to
// be able to reach these to fix billing or update their own account.
//
// NOTE: /profile/* here are Breeze's own routes. Because this file
// REPLACES the web.php that `breeze:install` generates (which normally
// defines these itself), they have to be restored explicitly — the same
// issue as the 'login' route fixed earlier. Skipping this reintroduces a
// RouteNotFoundException the moment anything links to route('profile.edit').
Route::middleware(['auth'])->group(function () {
    Route::get('/support/attachments/{attachment}', [SupportAttachmentController::class, 'download'])
        ->where('attachment', '[^/]+')
        ->name('support.attachment.download');
    Route::get('/subscription', [SubscriptionController::class, 'show'])->middleware(\App\Http\Middleware\OrganizationMemberBillingContext::class)
        ->name('subscription.show');
    // Checkout modal "pay for someone else" lookup — same JSON as the
    // mobile API endpoint. Throttled to limit account enumeration.
    Route::get('/subscription/beneficiary', [\App\Http\Controllers\Api\SubscriptionController::class, 'beneficiary'])
        ->middleware('throttle:10,1')
        ->name('subscription.beneficiary');
    Route::post('/subscription/subscribe', [SubscriptionController::class, 'subscribe'])->middleware(\App\Http\Middleware\OrganizationMemberBillingContext::class)
        ->name('subscription.subscribe');
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel'])->middleware(\App\Http\Middleware\OrganizationMemberBillingContext::class)
        ->name('subscription.cancel');
    Route::put('/subscription/auto-renew', [SubscriptionController::class, 'updateAutoRenew'])
        ->middleware(\App\Http\Middleware\OrganizationMemberBillingContext::class)
        ->name('subscription.auto-renew');
    Route::post('/subscription/pay/card', [SubscriptionController::class, 'payWithCard'])->middleware(\App\Http\Middleware\OrganizationMemberBillingContext::class)
        ->name('subscription.pay.card');
    Route::post('/subscription/pay/mobile-money', [SubscriptionController::class, 'payWithMobileMoney'])->middleware(\App\Http\Middleware\OrganizationMemberBillingContext::class)
        ->name('subscription.pay.mobile-money');

    // ioTec subscription payments: Mobile Money or Visa / MasterCard.
    Route::post(
        '/subscription/pay/iotec',
        [IoTecSubscriptionPaymentController::class, 'initiate']
    )->middleware(\App\Http\Middleware\OrganizationMemberBillingContext::class)
        ->name('subscription.pay.iotec');

    Route::get(
        '/subscription/pay/iotec/{transaction}/status',
        [IoTecSubscriptionPaymentController::class, 'status']
    )
        ->whereNumber('transaction')
        ->name('subscription.pay.iotec.status');
    Route::get('/subscription/receipt/{payment}', [SubscriptionController::class, 'downloadReceipt'])->name('subscription.receipt');
    Route::get('/subscription/invoice/{invoice}', [SubscriptionController::class, 'downloadInvoice'])->name('subscription.invoice');
    Route::post('/subscription/pay/manual', [SubscriptionController::class, 'submitManualPayment'])->middleware(\App\Http\Middleware\OrganizationMemberBillingContext::class)
        ->name('subscription.pay.manual');
    Route::post('/subscription/payment/{payment}/mobile-money', [SubscriptionController::class, 'retryPendingMobileMoney'])->middleware(\App\Http\Middleware\OrganizationMemberBillingContext::class)
        ->name('subscription.payment.mobile-money');
    Route::post('/subscription/payment/{payment}/bank', [SubscriptionController::class, 'submitPendingBankPayment'])->middleware(\App\Http\Middleware\OrganizationMemberBillingContext::class)
        ->name('subscription.payment.bank');
    Route::post(
        '/subscription/payment/{payment}/cancel',
        [SubscriptionController::class, 'cancelPendingPayment']
    )
        ->whereNumber('payment')
        ->middleware(\App\Http\Middleware\OrganizationMemberBillingContext::class)
        ->name('subscription.payment.cancel');

    // Same reasoning: data export/deletion are rights a user should be
    // able to exercise even if their trial/subscription has lapsed.
    Route::get('/privacy', [PrivacyController::class, 'show'])->name('privacy.show');
    Route::get('/help', [\App\Http\Controllers\HelpController::class, 'show'])->name('help.show');
    Route::post('/privacy/export/request', [PrivacyController::class, 'requestExport'])->name('privacy.export.request');
    Route::get('/privacy/reports/{privacyReport}/download', [PrivacyController::class, 'downloadReport'])->name('privacy.report.download');
    Route::delete('/privacy/account', [PrivacyController::class, 'destroyAccount'])->name('privacy.destroy-account');
    Route::post('/privacy/account/cancel-deletion', [PrivacyController::class, 'cancelDeletion'])->name('privacy.cancel-deletion');


    // User support chat remains available even when subscription access is limited.
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
    Route::post('/support/send', [SupportController::class, 'send'])->name('support.send');
    Route::post('/support/end', [SupportController::class, 'end'])->name('support.end');
    Route::get('/support/widget/history', [SupportController::class, 'widgetHistory'])->name('support.widget.history');
    Route::post('/support/widget/send', [SupportController::class, 'widgetSend'])->name('support.widget.send');

    // Notification centre is available even if a subscription has expired.
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->middleware(\App\Http\Middleware\LockOrganizationMemberEmail::class)
        ->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');
    Route::put('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');
    Route::put('/profile/personalisation', [ProfileController::class, 'updatePersonalisation'])->name('profile.personalisation');

    // Social media settings belong to the user's profile and remain available
    // even if the subscription has expired.
    Route::get('/profile/social-media', [SocialMediaAccountController::class, 'index'])
        ->name('profile.social-media');
    Route::post('/profile/social-media/accounts', [SocialMediaAccountController::class, 'store'])
        ->name('profile.social-media.accounts.store');

    Route::put(
        '/profile/social-media/accounts/{account}',
        [SocialMediaAccountController::class, 'update']
    )
        ->whereNumber('account')
        ->name('profile.social-media.accounts.update');
    Route::delete('/profile/social-media/accounts/{account}', [SocialMediaAccountController::class, 'destroy'])
        ->whereNumber('account')
        ->name('profile.social-media.accounts.destroy');
    Route::put('/profile/social-media/whatsapp', [SocialMediaAccountController::class, 'updateWhatsApp'])
        ->name('profile.social-media.whatsapp');
    // Invitees may not have a subscription, but must be authenticated; the
    // controller verifies that the current account owns or was invited to it.
    Route::get('meetings/{meeting}/join', [MeetingController::class, 'join'])
        ->middleware('verified')
        ->name('meetings.join');
});

Route::middleware(['auth', 'verified', \App\Http\Middleware\EnsureSubscribedOrOrganizationMember::class])->group(function () {
    Route::get('/getting-started', [\App\Http\Controllers\OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/getting-started', [\App\Http\Controllers\OnboardingController::class, 'store'])->name('onboarding.store');
    Route::get('/dashboard', DashboardController::class . '@index')->name('dashboard');
    Route::post('/dashboard/today-insight/refresh', [DashboardController::class, 'refreshTodayInsight'])->name('dashboard.today-insight.refresh');
    Route::get('/activity', DashboardController::class . '@activity')->name('activity');
    Route::get('wellbeing/steps', [\App\Http\Controllers\DailyStepController::class, 'show'])->name('wellbeing.steps');
    Route::get('wellbeing/steps/live', [\App\Http\Controllers\DailyStepController::class, 'live'])->name('wellbeing.steps.live');
    Route::get('/monthly-review', \App\Http\Controllers\MonthlyReviewController::class)->name('monthly-review');

    /*
    |--------------------------------------------------------------------------
    | Social Media Planner
    |--------------------------------------------------------------------------
    |
    | Registered directly in web.php so deployment does not depend on a
    | separate routes/social_media_web.php file being present.
    |
    */
    Route::prefix('social-media-planner')
        ->name('social-media-planner.')
        ->group(function () {
            Route::get('/', [SocialMediaPlannerController::class, 'index'])
                ->name('index');

            Route::get('/reports', [SocialMediaReportController::class, 'index'])
                ->name('reports.index');
            Route::post('/reports/sync', [SocialMediaReportController::class, 'sync'])
                ->name('reports.sync');
            Route::get('/reports/csv', [SocialMediaReportController::class, 'csv'])
                ->name('reports.csv');

            Route::get('/reports/pdf', [SocialMediaReportController::class, 'pdf'])
                ->name('reports.pdf');

            Route::get('/{socialMediaPost}/analytics', [SocialMediaAnalyticsController::class, 'show'])
                ->whereNumber('socialMediaPost')->name('analytics.show');
            Route::post('/{socialMediaPost}/analytics/sync', [SocialMediaAnalyticsController::class, 'sync'])
                ->whereNumber('socialMediaPost')->name('analytics.sync');
            Route::put('/{socialMediaPost}/analytics', [SocialMediaAnalyticsController::class, 'update'])
                ->whereNumber('socialMediaPost')->name('analytics.update');

            Route::post('/', [SocialMediaPlannerController::class, 'store'])
                ->name('store');

            // Literal routes must be before /{socialMediaPost}.
            Route::delete('/bulk-destroy', [SocialMediaPlannerController::class, 'bulkDestroy'])
                ->name('bulk-destroy');

            Route::get('/ready-to-share/list', [SocialMediaPlannerController::class, 'readyToShare'])
                ->name('ready-to-share');

            Route::post('/{socialMediaPost}/post-now', [SocialMediaPlannerController::class, 'postNow'])
                ->whereNumber('socialMediaPost')
                ->name('post-now');

            Route::patch('/{socialMediaPost}/mark-published', [SocialMediaPlannerController::class, 'markPublished'])
                ->whereNumber('socialMediaPost')
                ->name('mark-published');

            Route::put('/{socialMediaPost}', [SocialMediaPlannerController::class, 'update'])
                ->whereNumber('socialMediaPost')
                ->name('update');

            Route::delete('/{socialMediaPost}', [SocialMediaPlannerController::class, 'destroy'])
                ->whereNumber('socialMediaPost')
                ->name('destroy');
        });

    /*
    |--------------------------------------------------------------------------
    | Daily Engagement / Retention — Web Session Routes
    |--------------------------------------------------------------------------
    |
    | These routes are intentionally registered in web.php so the Laravel
    | browser dashboard can use the authenticated web session + CSRF token.
    | Flutter continues using the separate /api/engagement/* Sanctum routes.
    |
    */
    Route::prefix('engagement')->name('engagement.')->group(function () {
        Route::get('/today', [EngagementController::class, 'today'])
            ->name('today');

        Route::post('/checkin/{type}', [EngagementController::class, 'checkin'])
            ->whereIn('type', ['start-day', 'close-day'])
            ->name('checkin');

        Route::post('/meaningful-action', [EngagementController::class, 'meaningfulAction'])
            ->name('meaningful-action');

        Route::get('/review/week', [EngagementReviewController::class, 'week'])
            ->name('review.week');

        Route::get('/review/month', [EngagementReviewController::class, 'month'])
            ->name('review.month');

        Route::get('/share-card/{period}', [EngagementController::class, 'shareCard'])
            ->whereIn('period', ['week', 'month'])
            ->name('share-card');
    });
    Route::get('/goals-next-actions', \App\Http\Controllers\GoalIntelligenceController::class)->name('goal-intelligence');
    Route::get('/personal-goals/{goal}/progress', [\App\Http\Controllers\GoalProgressController::class, 'show'])->name('personal-goals.progress');
    Route::post('/personal-goals/{goal}/milestones', [\App\Http\Controllers\GoalProgressController::class, 'storeMilestone'])->name('personal-goals.milestones.store');
    Route::patch('/personal-goals/{goal}/milestones/{milestone}/toggle', [\App\Http\Controllers\GoalProgressController::class, 'toggleMilestone'])->name('personal-goals.milestones.toggle');
    Route::delete('/personal-goals/{goal}/milestones/{milestone}', [\App\Http\Controllers\GoalProgressController::class, 'destroyMilestone'])->name('personal-goals.milestones.destroy');
    Route::patch('/personal-goals/{goal}/milestones/{milestone}/reschedule', [\App\Http\Controllers\GoalProgressController::class, 'rescheduleMilestone'])->name('personal-goals.milestones.reschedule');
    Route::post('/personal-goals/{goal}/check-in', [\App\Http\Controllers\GoalProgressController::class, 'storeCheckin'])->name('personal-goals.checkin.store');
    Route::post('/personal-goals/{goal}/reflections', [\App\Http\Controllers\GoalProgressController::class, 'storeReflection'])->name('personal-goals.reflections.store');
    Route::delete('/personal-goals/{goal}/reflections/{reflection}', [\App\Http\Controllers\GoalProgressController::class, 'destroyReflection'])->name('personal-goals.reflections.destroy');

    // Planner modules. The old Plans module was replaced by Annual Plans.
    Route::get('plans', fn () => redirect()->route('annual-plans.index'))->name('plans.index');

    Route::get('financial-planner', [FinancialPlannerController::class, 'index'])->name('financial-planner.index');
    Route::put('financial-planner', [FinancialPlannerController::class, 'update'])->name('financial-planner.update');

    Route::get('daily-planner', [DailyPlannerController::class, 'index'])->name('daily-planner.index');
    Route::put('daily-planner', [DailyPlannerController::class, 'updatePlan'])->name('daily-planner.update');
    Route::post('daily-planner/week', [DailyPlannerController::class, 'storeWeek'])->name('daily-planner.week.store');
    Route::post('daily-planner/items', [DailyPlannerController::class, 'storeItem'])->name('daily-planner.items.store');
    Route::patch('daily-planner/items/bulk-move', [DailyPlannerController::class, 'bulkMove'])->name('daily-planner.items.bulk-move');
    Route::patch('daily-planner/items/bulk-complete', [DailyPlannerController::class, 'bulkComplete'])->name('daily-planner.items.bulk-complete');
    Route::delete('daily-planner/items/bulk-destroy', [DailyPlannerController::class, 'bulkDestroy'])->name('daily-planner.items.bulk-destroy');
    Route::patch('daily-planner/items/{item}/move', [DailyPlannerController::class, 'moveItem'])->name('daily-planner.items.move');
    Route::patch('daily-planner/items/{item}/toggle', [DailyPlannerController::class, 'toggle'])->name('daily-planner.items.toggle');
    Route::put('daily-planner/items/{item}', [DailyPlannerController::class, 'updateItem'])->name('daily-planner.items.update');
    Route::delete('daily-planner/items/{item}', [DailyPlannerController::class, 'destroyItem'])->name('daily-planner.items.destroy');

    Route::get('annual-plans', [PlanController::class, 'index'])->name('annual-plans.index');
    Route::post('annual-plans', [PlanController::class, 'store'])->name('annual-plans.store');
    Route::put('annual-plans/{annualPlan}', [PlanController::class, 'update'])->name('annual-plans.update');
    Route::patch('annual-plans/{annualPlan}/toggle', [PlanController::class, 'toggle'])->name('annual-plans.toggle');
    Route::delete('annual-plans/bulk-destroy', [PlanController::class, 'bulkDestroy'])->name('annual-plans.bulk-destroy');
    Route::delete('annual-plans/{annualPlan}', [PlanController::class, 'destroy'])->name('annual-plans.destroy');
    // Bulk-delete routes must be declared BEFORE resource routes so the literal
    // `bulk-destroy` segment is never interpreted as a model ID.
    Route::delete('incomes/bulk-destroy', [IncomeController::class, 'bulkDestroy'])->name('incomes.bulk-destroy');
    Route::delete('budgets/bulk-destroy', [BudgetController::class, 'bulkDestroy'])->name('budgets.bulk-destroy');
    Route::delete('expenses/bulk-destroy', [ExpenseController::class, 'bulkDestroy'])->name('expenses.bulk-destroy');
    Route::delete('debts/bulk-destroy', [DebtController::class, 'bulkDestroy'])->name('debts.bulk-destroy');
    Route::delete('savings-goals/bulk-destroy', [SavingsGoalController::class, 'bulkDestroy'])->name('savings-goals.bulk-destroy');
    Route::delete('savings-contributions/bulk-destroy', [SavingsContributionController::class, 'bulkDestroy'])->name('savings-contributions.bulk-destroy');
    Route::delete('diet-logs/bulk-destroy', [DietLogController::class, 'bulkDestroy'])->name('diet-logs.bulk-destroy');
    Route::delete('exercise-logs/bulk-destroy', [ExerciseLogController::class, 'bulkDestroy'])->name('exercise-logs.bulk-destroy');
    Route::delete('sleep-logs/bulk-destroy', [SleepLogController::class, 'bulkDestroy'])->name('sleep-logs.bulk-destroy');
    Route::delete('health-checkups/bulk-destroy', [HealthCheckupController::class, 'bulkDestroy'])->name('health-checkups.bulk-destroy');
    Route::delete('projects/bulk-destroy', [ProjectController::class, 'bulkDestroy'])->name('projects.bulk-destroy');
    Route::delete('project-tasks/bulk-destroy', [ProjectTaskController::class, 'bulkDestroy'])->name('project-tasks.bulk-destroy');
    Route::delete('meetings/bulk-destroy', [MeetingController::class, 'bulkDestroy'])->name('meetings.bulk-destroy');
    Route::delete('reminders/bulk-destroy', [ReminderController::class, 'bulkDestroy'])->name('reminders.bulk-destroy');
    Route::delete('spiritual-practices/bulk-destroy', [SpiritualPracticeController::class, 'bulkDestroy'])->name('spiritual-practices.bulk-destroy');
    Route::delete('education-plans/bulk-destroy', [EducationPlanController::class, 'bulkDestroy'])->name('education-plans.bulk-destroy');
    Route::delete('notes/bulk-destroy', [NoteController::class, 'bulkDestroy'])->name('notes.bulk-destroy');
    Route::delete('network-contacts/bulk-destroy', [NetworkContactController::class, 'bulkDestroy'])->name('network-contacts.bulk-destroy');
    Route::delete('relationships/bulk-destroy', [PersonalRelationshipController::class, 'bulkDestroy'])->name('relationships.bulk-destroy');
    Route::delete('feedback/bulk-destroy', [FeedbackController::class, 'bulkDestroy'])->name('feedback.bulk-destroy');

    // Budget document extraction/import routes must be before Route::resource('budgets', ...).
    Route::post('budgets/extract', [BudgetController::class, 'extractImport'])->middleware('throttle:5,1')->name('budgets.extract');
    Route::post('budgets/import/confirm', [BudgetController::class, 'confirmImport'])->name('budgets.import.confirm');
    Route::post('budgets/{budget}/expense-status', [BudgetController::class, 'setExpenseStatus'])->name('budgets.expense-status');

    Route::resource('incomes', IncomeController::class);
    Route::resource('budgets', BudgetController::class);
    Route::resource('expenses', ExpenseController::class);
    Route::get('debts/reminders', [DebtController::class, 'remindersPage'])->name('debts.reminders');
    Route::post('debts/{debt}/reminders/send', [DebtController::class, 'sendReminder'])->name('debts.reminders.send');
    Route::get('debts/{debt}/reminders/history', [DebtController::class, 'reminderHistory'])->name('debts.reminders.history');
    Route::resource('debts', DebtController::class);
    Route::get('savings', [SavingsOverviewController::class, 'index'])->name('savings.index');
    Route::resource('savings-goals', SavingsGoalController::class);
    Route::resource('savings-contributions', SavingsContributionController::class);
    Route::resource('diet-logs', DietLogController::class);
    Route::resource('exercise-logs', ExerciseLogController::class);
    Route::resource('sleep-logs', SleepLogController::class);
    Route::resource('health-checkups', HealthCheckupController::class);
    Route::resource('projects', ProjectController::class);
    Route::resource('project-tasks', ProjectTaskController::class);

    Route::post('meetings/multiple', [MeetingController::class, 'storeMultiple'])->name('meetings.store-multiple');
    Route::get('meetings/{meeting}/notes', [MeetingController::class, 'notes'])->name('meetings.notes');
    Route::put('meetings/{meeting}/notes', [MeetingController::class, 'updateNotes'])->name('meetings.notes.update');
    Route::get('meetings/{meeting}/notes/pdf', [MeetingController::class, 'downloadNotesPdf'])->name('meetings.notes.pdf');
    Route::post('meetings/{meeting}/notes/email', [MeetingController::class, 'emailNotes'])->name('meetings.notes.email');

    Route::post('meetings/{meeting}/recordings', [MeetingRecordingController::class, 'store'])->name('meetings.recordings.store');
    Route::post('meetings/{meeting}/recordings/upload', [MeetingRecordingController::class, 'upload'])->middleware('throttle:5,1')->name('meetings.recordings.upload');
    Route::patch('meeting-recordings/{recording}/status', [MeetingRecordingController::class, 'updateStatus'])->name('meeting-recordings.status');
    Route::post('meeting-recordings/{recording}/stop', [MeetingRecordingController::class, 'stop'])->name('meeting-recordings.stop');
    Route::post('meeting-recordings/{recording}/transcribe', [MeetingRecordingController::class, 'transcribe'])->middleware('throttle:5,1')->name('meeting-recordings.transcribe');
    Route::post('meeting-recordings/{recording}/check-capacity', [MeetingRecordingController::class, 'checkTranscriptionCapacity'])->name('meeting-recordings.check-capacity');
    Route::post('meeting-recordings/{recording}/process', [MeetingRecordingController::class, 'transcribeAndSummarize'])->name('meeting-recordings.process');
    Route::put('meeting-recordings/{recording}/transcript', [MeetingRecordingController::class, 'updateTranscript'])->name('meeting-recordings.transcript.update');
    Route::post('meeting-recordings/{recording}/summarize', [MeetingRecordingController::class, 'generateSummary'])->middleware('throttle:5,1')->name('meeting-recordings.summarize');
    Route::get('meeting-recordings/{recording}/audio/stream', [MeetingRecordingController::class, 'streamAudio'])->name('meeting-recordings.audio.stream');
    Route::get('meeting-recordings/{recording}/audio', [MeetingRecordingController::class, 'downloadAudio'])->name('meeting-recordings.audio');
    Route::get('meeting-recordings/{recording}/transcript-download', [MeetingRecordingController::class, 'downloadTranscript'])->name('meeting-recordings.transcript.download');
    Route::get('meeting-recordings/{recording}/summary-download', [MeetingRecordingController::class, 'downloadSummary'])->name('meeting-recordings.summary.download');
    Route::post('meeting-recordings/{recording}/email-summary', [MeetingRecordingController::class, 'emailSummary'])->name('meeting-recordings.email-summary');
    Route::delete('meeting-recordings/{recording}', [MeetingRecordingController::class, 'destroy'])->name('meeting-recordings.destroy');

    Route::post('meeting-recordings/{recording}/segments', [MeetingRecordingController::class, 'createSegment'])->name('meeting-recordings.segments.store');
    Route::get('meeting-recordings/{recording}/segments', [MeetingRecordingController::class, 'listSegments'])->name('meeting-recordings.segments.index');
    Route::post('meeting-recording-segments/{segment}/transcribe', [MeetingRecordingController::class, 'transcribeSegment'])->middleware('throttle:5,1')->name('meeting-recording-segments.transcribe');
    Route::post('meeting-recording-segments/{segment}/summarize', [MeetingRecordingController::class, 'generateSegmentSummary'])->middleware('throttle:5,1')->name('meeting-recording-segments.summarize');
    Route::put('meeting-recording-segments/{segment}', [MeetingRecordingController::class, 'updateSegment'])->name('meeting-recording-segments.update');
    Route::delete('meeting-recording-segments/{segment}', [MeetingRecordingController::class, 'destroySegment'])->name('meeting-recording-segments.destroy');
    Route::get('meeting-recording-segments/{segment}/audio/stream', [MeetingRecordingController::class, 'streamSegmentAudio'])->name('meeting-recording-segments.audio.stream');
    Route::get('meeting-recording-segments/{segment}/audio', [MeetingRecordingController::class, 'downloadSegmentAudio'])->name('meeting-recording-segments.audio');
    Route::get('meetings/connect/{platform}', [MeetingConnectionController::class, 'connect'])->name('meetings.connect');
    Route::get('meetings/connect/{platform}/callback', [MeetingConnectionController::class, 'callback'])->name('meetings.connect.callback');
    Route::delete('meetings/connect/{platform}', [MeetingConnectionController::class, 'disconnect'])->name('meetings.disconnect');
    Route::post('meetings/sync', [MeetingConnectionController::class, 'sync'])->name('meetings.sync');
    Route::post('meetings/{meeting}/add-to-calendar', [MeetingController::class, 'addToCalendar'])->name('meetings.add-to-calendar');
    Route::resource('meetings', MeetingController::class);
    // Must be registered BEFORE the resource() call below — otherwise
    // Route::resource's GET /reminders/{reminder} would greedily match
    // /reminders/due-now, treating "due-now" as a reminder ID.
    Route::get('reminders/due-now', [ReminderController::class, 'dueNow'])->name('reminders.due-now');
    Route::post('reminders/toggle-mute', [ReminderController::class, 'toggleMute'])->name('reminders.toggle-mute');
    Route::get('reminders/items-for-module', [ReminderController::class, 'itemsForModule'])->name('reminders.items-for-module');
    Route::resource('reminders', ReminderController::class);

    Route::resource('education-plans', EducationPlanController::class)->except(['show']);
    Route::resource('network-contacts', NetworkContactController::class);
    Route::resource('relationships', PersonalRelationshipController::class);
    Route::resource('spiritual-practices', SpiritualPracticeController::class);
    Route::delete('personal-goals/bulk-destroy', [\App\Http\Controllers\PersonalGoalController::class, 'bulkDestroy'])->name('personal-goals.bulk-destroy');
    Route::resource('personal-goals', \App\Http\Controllers\PersonalGoalController::class)->except(['create','edit','show']);
    Route::delete('wellbeing/bulk-destroy', [\App\Http\Controllers\DailyWellbeingLogController::class, 'bulkDestroy'])->name('wellbeing.bulk-destroy');
    Route::resource('wellbeing', \App\Http\Controllers\DailyWellbeingLogController::class)->except(['create','edit','show']);
    Route::resource('notes', NoteController::class);
    Route::resource('feedback', FeedbackController::class);
    Route::get('tips', [TipsController::class, 'index'])->name('tips');
    Route::get('signature', [SignatureController::class, 'show'])->name('signature.show');
    Route::post('signature/signatures', [SignatureController::class, 'storeSignature'])->name('signature.signatures.store');
    Route::delete('signature/signatures/{signature}', [SignatureController::class, 'destroySignature'])->name('signature.signatures.destroy');
    Route::post('signature/documents/preview', [SignatureController::class, 'previewDocument'])->name('signature.documents.preview');
    Route::get('signature/documents/preview-file', [SignatureController::class, 'previewPendingFile'])->name('signature.documents.preview-file');
    Route::post('signature/documents/confirm', [SignatureController::class, 'confirmDocument'])->name('signature.documents.confirm');
    Route::post('signature/documents/cancel', [SignatureController::class, 'cancelPreview'])->name('signature.documents.cancel');
    Route::get('signature/documents/{signedDocument}/download', [SignatureController::class, 'downloadDocument'])->name('signature.documents.download');
    Route::get('signature/documents/{signedDocument}/view', [SignatureController::class, 'viewDocument'])->name('signature.documents.view');
    Route::post('signature/documents/bulk-destroy', [SignatureController::class, 'bulkDestroyDocuments'])->name('signature.documents.bulk-destroy');
    Route::delete('signature/documents/{signedDocument}', [SignatureController::class, 'destroyDocument'])->name('signature.documents.destroy');

    Route::get('business-card', [BusinessCardController::class, 'edit'])->name('business-card.edit');

    Route::get('organization', [\App\Http\Controllers\OrganizationController::class, 'show'])->name('organization.show');
    Route::post('organization/invite', [\App\Http\Controllers\OrganizationController::class, 'invite'])->name('organization.invite');
    Route::put('organization/members/{member}', [\App\Http\Controllers\OrganizationController::class, 'editMember'])->name('organization.members.edit');
    Route::put('organization/members/{member}/role', [\App\Http\Controllers\OrganizationController::class, 'updateRole'])->name('organization.members.role');
    Route::post('organization/members/{member}/activate', [\App\Http\Controllers\OrganizationController::class, 'activate'])->name('organization.members.activate');
    Route::post('organization/members/{member}/deactivate', [\App\Http\Controllers\OrganizationController::class, 'deactivate'])->name('organization.members.deactivate');
    Route::post('organization/members/{member}/replace', [\App\Http\Controllers\OrganizationController::class, 'replace'])->name('organization.members.replace');
    Route::delete('organization/members/{member}', [\App\Http\Controllers\OrganizationController::class, 'removeMember'])->name('organization.members.remove');

    Route::post('personal-email/send-code', [\App\Http\Controllers\PersonalEmailController::class, 'sendCode'])->name('personal-email.send-code');
    Route::post('personal-email/verify', [\App\Http\Controllers\PersonalEmailController::class, 'verify'])->name('personal-email.verify');
    Route::post('business-card', [BusinessCardController::class, 'update'])->name('business-card.update');
    Route::post('business-card/toggle-published', [BusinessCardController::class, 'togglePublished'])->name('business-card.toggle-published');
    Route::get('business-card/pdf', [BusinessCardController::class, 'downloadPdf'])->name('business-card.pdf');

    // Bring-your-own AI API key management + the AI Planner itself.
    Route::resource('api-credentials', ApiCredentialController::class)->only(['index', 'create', 'store', 'destroy']);
    Route::post('api-credentials/{apiCredential}/activate', [ApiCredentialController::class, 'activate'])
        ->name('api-credentials.activate');

    Route::get('ai-plans', [AiPlanController::class, 'index'])->name('ai-plans.index');
    Route::post('ai-plans', [AiPlanController::class, 'store'])->name('ai-plans.store');
    Route::get('ai-plans/{aiPlan}/pdf', [AiPlanController::class, 'downloadPdf'])->name('ai-plans.pdf');
    Route::delete('ai-plans/bulk-destroy', [AiPlanController::class, 'bulkDestroy'])->name('ai-plans.bulk-destroy');
    Route::delete('ai-plans/{aiPlan}', [AiPlanController::class, 'destroy'])->name('ai-plans.destroy');

    // Full cross-module "Personal Report" as a downloadable PDF (DomPDF).
    Route::get('report/pdf', [ReportController::class, 'download'])->name('report.download');
    
    Route::resource('extra-requests', \App\Http\Controllers\ExtraRequestController::class)->except(['edit', 'update', 'destroy']);
    Route::post('extra-requests/{extra_request}/pay', [\App\Http\Controllers\ExtraRequestController::class, 'pay'])->name('extra-requests.pay');
    
});

/*
|--------------------------------------------------------------------------
| Route Modules
|--------------------------------------------------------------------------
|
| Each feature route file below is loaded exactly once.
| Do not re-add the retired patch files.
|
*/

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/growth_web.php';
require __DIR__.'/team_workspace.php';
require __DIR__.'/team_chat.php';

// Health, diet, sleep and Daily Wellbeing AI routes.
require __DIR__ . '/health-ai.php';

require __DIR__ . '/wellbeing_linked_updates.php';

// Daily Food Journal compatibility routes.
require __DIR__ . '/daily_food_journal_compat.php';

// AI-assisted CRUD forms: Spiritual Practices, Relationships and Networks.
require __DIR__ . '/ai-form-assist.php';
