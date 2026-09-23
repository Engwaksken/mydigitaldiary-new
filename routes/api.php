<?php

use App\Http\Controllers\Api\DailyRoutineController;
use App\Http\Controllers\Api\SavingsOverviewController;
use App\Http\Controllers\Api\DebtReminderController;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminSocialMediaController;
use App\Http\Controllers\Api\EngagementReviewController;
use App\Http\Controllers\Api\SocialMediaAccountController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\DietLogController;
use App\Http\Controllers\Api\EducationPlanController;
use App\Http\Controllers\Api\ExerciseLogController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\HealthCheckupController;
use App\Http\Controllers\Api\IncomeController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\NetworkContactController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectTaskController;
use App\Http\Controllers\Api\RelationshipController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\SavingsContributionController;
use App\Http\Controllers\Api\SavingsGoalController;
use App\Http\Controllers\Api\SleepLogController;
use App\Http\Controllers\Api\SocialMediaPlannerController;
use App\Http\Controllers\Api\SocialMediaAnalyticsController;
use App\Http\Controllers\Api\SocialMediaReportController;
use App\Http\Controllers\Api\SpiritualPracticeController;
use App\Http\Controllers\SupportAttachmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (Flutter mobile app)
|--------------------------------------------------------------------------
|
| Registered in bootstrap/app.php via withRouting():
|
|   ->withRouting(
|       web: __DIR__.'/../routes/web.php',
|       api: __DIR__.'/../routes/api.php',
|       commands: __DIR__.'/../routes/console.php',
|       health: '/up',
|   )
|
| Every route below except register/login/verify-otp/resend-otp requires
| a Sanctum Bearer token (Authorization: Bearer {token}), obtained from
| POST /api/verify-otp. There is no CSRF/session concern here at all —
| unlike the web app's cookie-based auth, this is stateless token auth,
| the same mechanism a Flutter HTTP client naturally works with.
*/

Route::get('branding', [\App\Http\Controllers\Api\BrandingController::class, 'show']);
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('resend-otp', [AuthController::class, 'resendOtp']);

// Every route name in this group gets an 'api.' prefix — Route::apiResource()
// auto-generates names (plans.index, incomes.index, etc.) using the exact
// same convention as the web app's own Route::resource() calls, which
// caused a real, wide-reaching bug: route('plans.index') in the sidebar
// was resolving to this API route instead of the web page, since two
// routes shared the same name and Laravel's route() helper just picks
// whichever one it finds. This prefix makes every name here unique,
// fixing that collision for all ~20 affected modules at once and
// preventing it from ever recurring for any route added to this group
// in the future.
Route::middleware(['auth:sanctum', 'mobile.idempotent'])->name('api.')->group(function () {
    Route::get('support/attachments/{attachment}', [SupportAttachmentController::class, 'download'])
        ->where('attachment', '[^/]+')
        ->name('support.attachment.download');
    Route::get('me', [AuthController::class, 'me']);
    Route::get('sync/status', [\App\Http\Controllers\Api\SyncStatusController::class, 'show']);

    Route::get('api-credentials', [\App\Http\Controllers\Api\ApiCredentialController::class, 'index']);
    Route::post('api-credentials', [\App\Http\Controllers\Api\ApiCredentialController::class, 'store']);
    Route::post('api-credentials/{apiCredential}/activate', [\App\Http\Controllers\Api\ApiCredentialController::class, 'activate']);
    Route::delete('api-credentials/{apiCredential}', [\App\Http\Controllers\Api\ApiCredentialController::class, 'destroy']);
    Route::get('search', [\App\Http\Controllers\Api\SearchController::class, 'search']);
    Route::post('profile/appearance', [\App\Http\Controllers\Api\ProfileController::class, 'updateAppearance']);
    Route::put('profile', [\App\Http\Controllers\Api\ProfileController::class, 'updateProfile']);
    Route::put('profile/password', [\App\Http\Controllers\Api\ProfileController::class, 'updatePassword']);
    Route::post('profile/avatar', [\App\Http\Controllers\Api\ProfileController::class, 'updateAvatar']);
    Route::get('profile/avatar/image', [\App\Http\Controllers\Api\ProfileController::class, 'avatarImage']);

    // Social Media Settings under the authenticated user's Profile.
    Route::get('profile/social-media', [SocialMediaAccountController::class, 'index'])
        ->name('profile.social-media.index');
    Route::put('profile/social-media/whatsapp', [SocialMediaAccountController::class, 'updateWhatsApp'])
        ->name('profile.social-media.whatsapp');
    Route::post('profile/social-media/accounts', [SocialMediaAccountController::class, 'store'])
        ->name('profile.social-media.accounts.store');
    Route::delete('profile/social-media/accounts/{account}', [SocialMediaAccountController::class, 'destroy'])
        ->whereNumber('account')
        ->name('profile.social-media.accounts.destroy');

    // Admin/Super Admin Social Media management.
    Route::get('admin/social-media', [AdminSocialMediaController::class, 'index'])
        ->name('admin.social-media.index');
    Route::put('admin/social-media/users/{user}/whatsapp', [AdminSocialMediaController::class, 'updateWhatsApp'])
        ->whereNumber('user')
        ->name('admin.social-media.users.whatsapp');
    Route::delete('admin/social-media/users/{user}/accounts/{account}', [AdminSocialMediaController::class, 'destroyAccount'])
        ->whereNumber('user')
        ->whereNumber('account')
        ->name('admin.social-media.accounts.destroy');
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('privacy', [\App\Http\Controllers\Api\PrivacyController::class, 'index']);
    Route::post('privacy/reports', [\App\Http\Controllers\Api\PrivacyController::class, 'requestReport']);
    Route::delete('privacy/account', [\App\Http\Controllers\Api\PrivacyController::class, 'scheduleDeletion']);
    Route::post('privacy/account/cancel-deletion', [\App\Http\Controllers\Api\PrivacyController::class, 'cancelDeletion']);


    Route::post('device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('device-tokens', [DeviceTokenController::class, 'destroy']);

    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('dashboard/today-focus', [DashboardController::class, 'todayFocus']);
    Route::get('dashboard/today-insight', [DashboardController::class, 'todayInsight']);
    Route::get('dashboard/finance-summary', [DashboardController::class, 'financeSummaryData']);
    Route::get('dashboard/recent-activity', [DashboardController::class, 'recentActivityFull']);
    Route::get('savings', [SavingsOverviewController::class, 'index']);
    Route::post('debts/{debt}/reminders/send', [DebtReminderController::class, 'send']);
    Route::get('debts/{debt}/reminders/history', [DebtReminderController::class, 'history']);
    Route::get('daily-routine/start', [DailyRoutineController::class, 'start']);
    Route::get('daily-routine/end', [DailyRoutineController::class, 'end']);
    Route::get('monthly-review', [\App\Http\Controllers\Api\MonthlyReviewController::class, 'show']);

    /*
    |--------------------------------------------------------------------------
    | Social Media Planner
    |--------------------------------------------------------------------------
    |
    | Registered directly in api.php so Mobile does not depend on a separate
    | routes/social_media_api.php file. These routes inherit auth:sanctum,
    | mobile.idempotent and the api.* name prefix from the parent group.
    |
    | IMPORTANT: ready-to-share is declared before the {socialMediaPost}
    | routes so literal path segments can never be interpreted as model IDs.
    */
    Route::get('social-media-planner/ready-to-share', [
        SocialMediaPlannerController::class,
        'readyToShare',
    ])->name('social-media-planner.ready-to-share');

    Route::get('social-media-planner/reports', [
        SocialMediaReportController::class,
        'index',
    ])->name('social-media-planner.reports');

    Route::get('social-media-planner/{socialMediaPost}/analytics', [SocialMediaAnalyticsController::class, 'show'])
        ->whereNumber('socialMediaPost')->name('social-media-planner.analytics.show');
    Route::put('social-media-planner/{socialMediaPost}/analytics', [SocialMediaAnalyticsController::class, 'update'])
        ->whereNumber('socialMediaPost')->name('social-media-planner.analytics.update');

    Route::get('social-media-planner', [
        SocialMediaPlannerController::class,
        'index',
    ])->name('social-media-planner.index');

    Route::post('social-media-planner', [
        SocialMediaPlannerController::class,
        'store',
    ])->name('social-media-planner.store');

    Route::post('social-media-planner/bulk-delete', [
        SocialMediaPlannerController::class,
        'bulkDestroy',
    ])->name('social-media-planner.bulk-delete');

    Route::post('social-media-planner/{socialMediaPost}/post-now', [
        SocialMediaPlannerController::class,
        'postNow',
    ])->whereNumber('socialMediaPost')
      ->name('social-media-planner.post-now');

    Route::patch('social-media-planner/{socialMediaPost}/mark-published', [
        SocialMediaPlannerController::class,
        'markPublished',
    ])->whereNumber('socialMediaPost')
      ->name('social-media-planner.mark-published');

    Route::put('social-media-planner/{socialMediaPost}', [
        SocialMediaPlannerController::class,
        'update',
    ])->whereNumber('socialMediaPost')
      ->name('social-media-planner.update');

    Route::delete('social-media-planner/{socialMediaPost}', [
        SocialMediaPlannerController::class,
        'destroy',
    ])->whereNumber('socialMediaPost')
      ->name('social-media-planner.destroy');

    Route::get('goal-intelligence', [\App\Http\Controllers\Api\GoalIntelligenceController::class, 'show']);
    Route::get('personal-goals/{goal}/execution', [\App\Http\Controllers\Api\GoalExecutionController::class, 'show']);
    Route::get('personal-goals/{goal}/progress', [\App\Http\Controllers\Api\GoalProgressController::class, 'show']);
    Route::post('personal-goals/{goal}/milestones', [\App\Http\Controllers\Api\GoalProgressController::class, 'storeMilestone']);
    Route::patch('personal-goals/{goal}/milestones/{milestone}/toggle', [\App\Http\Controllers\Api\GoalProgressController::class, 'toggleMilestone']);
    Route::delete('personal-goals/{goal}/milestones/{milestone}', [\App\Http\Controllers\Api\GoalProgressController::class, 'destroyMilestone']);
    Route::patch('personal-goals/{goal}/milestones/{milestone}/reschedule', [\App\Http\Controllers\Api\GoalProgressController::class, 'rescheduleMilestone']);
    Route::post('personal-goals/{goal}/check-in', [\App\Http\Controllers\Api\GoalProgressController::class, 'storeCheckin']);
    Route::post('personal-goals/{goal}/reflections', [\App\Http\Controllers\Api\GoalProgressController::class, 'storeReflection']);
    Route::delete('personal-goals/{goal}/reflections/{reflection}', [\App\Http\Controllers\Api\GoalProgressController::class, 'destroyReflection']);
    Route::get('personalisation', [\App\Http\Controllers\Api\PersonalisationController::class, 'show']);
    Route::put('personalisation', [\App\Http\Controllers\Api\PersonalisationController::class, 'update']);



    // Financial Planner + Daily Planner mobile endpoints
    Route::get('financial-planner', [\App\Http\Controllers\Api\FinancialPlannerController::class, 'index']);
    Route::put('financial-planner', [\App\Http\Controllers\Api\FinancialPlannerController::class, 'update']);
    Route::get('daily-planner', [\App\Http\Controllers\Api\DailyPlannerController::class, 'index']);
    Route::get('daily-planner/history', [\App\Http\Controllers\Api\DailyPlannerController::class, 'history']);
    Route::put('daily-planner', [\App\Http\Controllers\Api\DailyPlannerController::class, 'updatePlan']);
    Route::post('daily-planner/items', [\App\Http\Controllers\Api\DailyPlannerController::class, 'storeItem']);
    Route::put('daily-planner/items/{item}', [\App\Http\Controllers\Api\DailyPlannerController::class, 'updateItem']);
    Route::patch('daily-planner/items/{item}/toggle', [\App\Http\Controllers\Api\DailyPlannerController::class, 'toggle']);
    Route::post('daily-planner/items/bulk-delete', [\App\Http\Controllers\Api\DailyPlannerController::class, 'bulkDestroy']);
    Route::delete('daily-planner/items/{item}', [\App\Http\Controllers\Api\DailyPlannerController::class, 'destroyItem']);


    // Annual Plans mobile endpoints
    Route::get('annual-plans', [\App\Http\Controllers\Api\AnnualPlanController::class, 'index']);
    Route::post('annual-plans', [\App\Http\Controllers\Api\AnnualPlanController::class, 'store']);
    Route::put('annual-plans/{annualPlan}', [\App\Http\Controllers\Api\AnnualPlanController::class, 'update']);
    Route::patch('annual-plans/{annualPlan}/toggle', [\App\Http\Controllers\Api\AnnualPlanController::class, 'toggle']);
    Route::post('annual-plans/bulk-delete', [\App\Http\Controllers\Api\AnnualPlanController::class, 'bulkDestroy']);
    Route::delete('annual-plans/{annualPlan}', [\App\Http\Controllers\Api\AnnualPlanController::class, 'destroy']);

    // Literal module actions MUST remain before the shared apiResource loop.
    Route::post('meetings/sync-calendar', [MeetingController::class, 'syncCalendar'])->name('meetings.sync-calendar');
    Route::post('budgets/extract', [BudgetController::class, 'extractImport'])->name('budgets.extract');
    Route::post('budgets/import/confirm', [BudgetController::class, 'confirmImport'])->name('budgets.import.confirm');
    Route::post('dashboard/today-insight/refresh', [DashboardController::class, 'refreshTodayInsight'])->name('dashboard.today-insight.refresh');

    // These literal routes must precede the wellbeing apiResource below,
    // otherwise its /wellbeing/{id} route treats "steps" as a record ID.
    Route::get('wellbeing/steps', [\App\Http\Controllers\Api\DailyStepController::class, 'show']);
    Route::get('wellbeing/steps/history', [\App\Http\Controllers\Api\DailyStepController::class, 'history']);
    Route::post('wellbeing/steps/start', [\App\Http\Controllers\Api\DailyStepController::class, 'start']);
    Route::post('wellbeing/steps/stop', [\App\Http\Controllers\Api\DailyStepController::class, 'stop']);
    Route::post('wellbeing/steps/sync', [\App\Http\Controllers\Api\DailyStepController::class, 'sync']);

    // Full module set — every web CRUD module now has a matching JSON
    // endpoint here, following the exact same ApiCrudController pattern.
    Route::get('reminders/due-now', [\App\Http\Controllers\Api\ReminderController::class, 'dueNow']);
    Route::post('reminders/toggle-mute', [\App\Http\Controllers\Api\ReminderController::class, 'toggleMute']);
    Route::get('reminders/items-for-module', [\App\Http\Controllers\Api\ReminderController::class, 'itemsForModule']);
    // archive/unarchive routes are registered BEFORE each apiResource
    // for the same reason reminders/due-now had to be registered
    // before Route::apiResource('reminders', ...) earlier — a
    // {module}/{id} wildcard route would otherwise treat "archive" as
    // if it were an {id} value.
    foreach ([
        'reminders' => ReminderController::class,
        'meetings' => MeetingController::class,
        'expenses' => ExpenseController::class,
        'plans' => PlanController::class,
        'incomes' => IncomeController::class,
        'budgets' => BudgetController::class,
        'debts' => DebtController::class,
        'savings-goals' => SavingsGoalController::class,
        'savings-contributions' => SavingsContributionController::class,
        'diet-logs' => DietLogController::class,
        'exercise-logs' => ExerciseLogController::class,
        'sleep-logs' => SleepLogController::class,
        'health-checkups' => HealthCheckupController::class,
        'projects' => ProjectController::class,
        'project-tasks' => ProjectTaskController::class,
        'education-plans' => EducationPlanController::class,
        'network-contacts' => NetworkContactController::class,
        'relationships' => RelationshipController::class,
        'spiritual-practices' => SpiritualPracticeController::class,
        'notes' => \App\Http\Controllers\Api\NoteController::class,
        'personal-goals' => \App\Http\Controllers\Api\PersonalGoalController::class,
        'wellbeing' => \App\Http\Controllers\Api\DailyWellbeingLogController::class,
        'feedback' => FeedbackController::class,
    ] as $endpoint => $controller) {
        Route::post("{$endpoint}/bulk-delete", [$controller, 'bulkDestroy']);
        Route::post("{$endpoint}/{id}/archive", [$controller, 'archive'])->where('id', '[0-9]+');
        Route::post("{$endpoint}/{id}/unarchive", [$controller, 'unarchive'])->where('id', '[0-9]+');
        Route::get("{$endpoint}/stats", [$controller, 'stats']);
        Route::get("{$endpoint}/report/pdf", [$controller, 'downloadPdf']);
        Route::apiResource($endpoint, $controller);
    }

    // Business Card
    Route::get('business-card', [\App\Http\Controllers\Api\BusinessCardController::class, 'show']);
    Route::get('business-card/photo', [\App\Http\Controllers\Api\BusinessCardController::class, 'photo']);
    Route::get('business-card/logo', [\App\Http\Controllers\Api\BusinessCardController::class, 'logo']);
    Route::post('business-card', [\App\Http\Controllers\Api\BusinessCardController::class, 'update']);
    Route::post('business-card/toggle-published', [\App\Http\Controllers\Api\BusinessCardController::class, 'togglePublished']);
    Route::get('business-card/pdf', [\App\Http\Controllers\Api\BusinessCardController::class, 'downloadPdf']);

    // Signatures — view/download only on mobile, no editor
    Route::get('signatures', [\App\Http\Controllers\Api\SignatureController::class, 'signatures']);
    Route::get('signatures/{signature}/image', [\App\Http\Controllers\Api\SignatureController::class, 'image']);
    Route::post('signatures', [\App\Http\Controllers\Api\SignatureController::class, 'storeSignature']);
    Route::delete('signatures/{signature}', [\App\Http\Controllers\Api\SignatureController::class, 'destroySignature']);
    Route::get('signed-documents', [\App\Http\Controllers\Api\SignatureController::class, 'documents']);
    Route::delete('signed-documents/{signedDocument}', [\App\Http\Controllers\Api\SignatureController::class, 'destroyDocument']);
    Route::post('signed-documents/stamp-image', [\App\Http\Controllers\Api\SignatureController::class, 'stampImage']);
    Route::post('signed-documents/bundle-pages', [\App\Http\Controllers\Api\SignatureController::class, 'bundlePages']);
    Route::post('signed-documents/bulk-delete', [\App\Http\Controllers\Api\SignatureController::class, 'bulkDestroyDocuments']);

    // Subscription / billing
    Route::get('subscription/status', [\App\Http\Controllers\Api\SubscriptionController::class, 'status']);
    Route::get('subscription/plans', [\App\Http\Controllers\Api\SubscriptionController::class, 'plans']);
    Route::get('subscription/gateways', [\App\Http\Controllers\Api\SubscriptionController::class, 'gateways']);
    Route::get('subscription/payments', [\App\Http\Controllers\Api\SubscriptionController::class, 'payments']);
    Route::post('subscription/pay/card', [\App\Http\Controllers\Api\SubscriptionController::class, 'payWithCard']);
    Route::post('subscription/pay/mobile-money', [\App\Http\Controllers\Api\SubscriptionController::class, 'payWithMobileMoney']);
    Route::post('subscription/pay/manual', [\App\Http\Controllers\Api\SubscriptionController::class, 'submitManualPayment']);
    Route::post('subscription/payments/{payment}/mobile-money', [\App\Http\Controllers\Api\SubscriptionController::class, 'retryPendingMobileMoney']);
    Route::post('subscription/payments/{payment}/bank', [\App\Http\Controllers\Api\SubscriptionController::class, 'submitPendingBankPayment']);

    // Meeting recordings
    Route::get('meetings/{meeting}/recordings', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'index']);
    Route::post('meetings/{meeting}/recordings', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'store']);
    Route::patch('meeting-recordings/{recording}/status', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'updateStatus']);
    Route::post('meeting-recordings/{recording}/stop', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'stop']);
    Route::post('meeting-recordings/{recording}/transcribe', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'transcribe']);
    Route::put('meeting-recordings/{recording}/transcript', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'updateTranscript']);
    Route::post('meeting-recordings/{recording}/summarize', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'generateSummary']);
    Route::delete('meeting-recordings/{recording}', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'destroy']);

    // Meeting recording segments
    Route::get('meeting-recordings/{recording}/segments', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'listSegments']);
    Route::post('meeting-recordings/{recording}/segments', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'createSegment']);
    Route::post('meeting-recording-segments/{segment}/transcribe', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'transcribeSegment']);
    Route::post('meeting-recording-segments/{segment}/summarize', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'generateSegmentSummary']);
    Route::delete('meeting-recording-segments/{segment}', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'destroySegment']);

    // Organization / Team management
    Route::get('organization', [\App\Http\Controllers\Api\OrganizationController::class, 'show']);
    Route::post('organization/invite', [\App\Http\Controllers\Api\OrganizationController::class, 'invite']);
    Route::put('organization/members/{member}/role', [\App\Http\Controllers\Api\OrganizationController::class, 'updateRole']);
    Route::post('organization/members/{member}/activate', [\App\Http\Controllers\Api\OrganizationController::class, 'activate']);
    Route::post('organization/members/{member}/deactivate', [\App\Http\Controllers\Api\OrganizationController::class, 'deactivate']);
    Route::post('organization/members/{member}/replace', [\App\Http\Controllers\Api\OrganizationController::class, 'replace']);
    Route::delete('organization/members/{member}', [\App\Http\Controllers\Api\OrganizationController::class, 'removeMember']);

    // Notifications
    Route::get('notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::post('notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markRead']);
    Route::post('notifications/reminder/{reminderId}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markReminderRead']);
    Route::post('notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllRead']);

    // AI Planner
    Route::get('ai-plans', [\App\Http\Controllers\Api\AiPlanController::class, 'index']);
    Route::post('ai-plans', [\App\Http\Controllers\Api\AiPlanController::class, 'store']);
    Route::delete('ai-plans/{aiPlan}', [\App\Http\Controllers\Api\AiPlanController::class, 'destroy']);

    // Daily Engagement / Retention
    Route::get('engagement/today', [
    \App\Http\Controllers\Api\EngagementController::class,
    'today',
]);

    Route::post('engagement/checkin/{type}', [
    \App\Http\Controllers\Api\EngagementController::class,
    'checkin',
])->whereIn('type', [
    'start-day',
    'close-day',
]);

    Route::post('engagement/meaningful-action', [
    \App\Http\Controllers\Api\EngagementController::class,
    'meaningfulAction',
]);

    Route::get('engagement/review/week', [
        EngagementReviewController::class,
        'week',
    ])->name('engagement.review.week');

    Route::get('engagement/review/month', [
        EngagementReviewController::class,
        'month',
    ])->name('engagement.review.month');

    Route::get('engagement/share-card/{period}', [
    \App\Http\Controllers\Api\EngagementController::class,
    'shareCard',
])->whereIn('period', [
    'week',
    'month',
]);

    Route::apiResource('extra-requests', \App\Http\Controllers\Api\ExtraRequestController::class)->except(['update', 'destroy']);
    Route::post('extra-requests/{extra_request}/pay', [\App\Http\Controllers\Api\ExtraRequestController::class, 'pay']);

});

/*
|--------------------------------------------------------------------------
| API Route Modules
|--------------------------------------------------------------------------
|
| Main mobile endpoints live above. These independent modules are loaded
| once here and must not also be copied into api.php.
|
*/

require __DIR__.'/growth_api.php';

Route::middleware('auth:sanctum')->get(
    'organization/access-context',
    \App\Http\Controllers\Api\OrganizationAccessController::class
);
require __DIR__.'/team_chat_api.php';

// Flutter parity routes.
require __DIR__ . '/flutter_updates_api.php';
