<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminAiProviderController;
use App\Http\Controllers\Admin\AdminBackupController;
use App\Http\Controllers\Admin\AdminFeedbackController;
use App\Http\Controllers\Admin\AdminPaymentGatewayController;
use App\Http\Controllers\Admin\AdminPaymentsController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminSocialMediaProviderController;
use App\Http\Controllers\Admin\AdminSmsProviderController;
use App\Http\Controllers\Admin\AdminStatisticsController;
use App\Http\Controllers\Admin\AdminSubscriptionPlanController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\CurrencySettingsController;
use App\Http\Controllers\Admin\IndependentActivationRequestAdminController;
use App\Http\Controllers\Admin\SupportController;
use App\Http\Controllers\Admin\UserManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Gated by ['auth', 'admin'] — NOT 'subscribed'.
|
| Administrators manage the platform itself, including:
|
| - accounts;
| - subscriptions;
| - payments;
| - statistics;
| - settings;
| - currency;
| - support;
| - AI providers;
| - SMS providers;
| - payment gateways;
| - enterprise enquiries;
| - invoices;
| - feedback.
|
| Admin users do not need their own active subscription in order to access
| the administration area.
|
*/

/*
|--------------------------------------------------------------------------
| Support Staff Routes
|--------------------------------------------------------------------------
|
| Support staff are separated from the main admin middleware so authorised
| support agents can manage conversations without requiring full admin
| access.
|
*/
Route::middleware([
    'auth',
    'support.staff',
])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {

        Route::get(
            'support',
            [SupportController::class, 'index']
        )->name('support.index');

        Route::get(
            'support/{conversation}',
            [SupportController::class, 'show']
        )->name('support.show');

        Route::patch(
            'support/{conversation}/assign',
            [SupportController::class, 'assign']
        )->name('support.assign');

        Route::patch(
            'support/{conversation}/unassign',
            [SupportController::class, 'unassign']
        )->name('support.unassign');

        Route::post(
            'support/{conversation}/reply',
            [SupportController::class, 'reply']
        )->name('support.reply');
    });

/*
|--------------------------------------------------------------------------
| Main Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware([
    'auth',
    'admin',
])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {

        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */

        Route::get(
            'users',
            [AdminUserController::class, 'index']
        )->name('users.index');

        Route::get(
            'users/create',
            [AdminUserController::class, 'create']
        )->name('users.create');

        Route::post(
            'users/bulk',
            [AdminUserController::class, 'bulk']
        )->name('users.bulk');

        Route::post(
            'users',
            [AdminUserController::class, 'store']
        )->name('users.store');

        Route::get(
            'users/{user}',
            [AdminUserController::class, 'show']
        )->name('users.show');

        Route::patch(
            'users/{user}/transcription-capacity',
            [AdminUserController::class, 'updateTranscriptionCapacity']
        )->whereNumber('user')
          ->name('users.transcription-capacity.update');

        Route::patch('users/{user}/subscription-price', [AdminUserController::class, 'updateSubscriptionPrice'])
            ->whereNumber('user')->name('users.subscription-price.update');

        Route::post(
            'users/{user}/suspend',
            [AdminUserController::class, 'suspend']
        )->name('users.suspend');

        Route::post(
            'users/{user}/unsuspend',
            [AdminUserController::class, 'unsuspend']
        )->name('users.unsuspend');

        Route::patch(
            'users/{user}/subscription',
            [AdminUserController::class, 'updateSubscription']
        )->whereNumber('user')
          ->name('users.subscription.update');

        /*
         * Temporary backwards-compatible POST endpoint for older deployed
         * admin user modals. Remove after all views use the PATCH route above.
         */
        Route::post(
            'users/{user}/subscription',
            [AdminUserController::class, 'updateSubscription']
        )->whereNumber('user')
          ->name('users.subscription');

        Route::post(
            'users/{user}/role',
            [AdminUserController::class, 'updateRole']
        )->name('users.role');

        Route::delete(
            'users/{user}',
            [AdminUserController::class, 'destroy']
        )->name('users.destroy');

        Route::get(
            'user-management',
            [UserManagementController::class, 'index']
        )->name('user-management.index');

        Route::put(
            'user-management/{user}/role',
            [UserManagementController::class, 'role']
        )->whereNumber('user')
          ->name('user-management.role');

        Route::post(
            'user-management/{user}/suspend',
            [UserManagementController::class, 'suspend']
        )->whereNumber('user')
          ->name('user-management.suspend');

        Route::post(
            'user-management/{user}/reactivate',
            [UserManagementController::class, 'reactivate']
        )->whereNumber('user')
          ->name('user-management.reactivate');

        Route::delete(
            'user-management/{user}',
            [UserManagementController::class, 'destroy']
        )->whereNumber('user')
          ->name('user-management.destroy');

        /*
        |--------------------------------------------------------------------------
        | Statistics
        |--------------------------------------------------------------------------
        */

        Route::get(
            'statistics',
            [AdminStatisticsController::class, 'index']
        )->name('statistics');

        /*
        |--------------------------------------------------------------------------
        | General Settings
        |--------------------------------------------------------------------------
        */

        Route::get(
            'settings',
            [AdminSettingsController::class, 'edit']
        )->name('settings.edit');

        Route::post(
            'settings',
            [AdminSettingsController::class, 'update']
        )->name('settings.update');

        Route::post(
            'settings/ai/test-connection',
            [AdminSettingsController::class, 'testAiConnection']
        )->middleware('throttle:5,10')->name('settings.ai.test-connection');

        Route::post(
            'settings/privacy',
            [AdminSettingsController::class, 'updatePrivacy']
        )->name('settings.privacy.update');

        Route::post(
            'settings/terms',
            [AdminSettingsController::class, 'updateTerms']
        )->name('settings.terms.update');

        /*
        |--------------------------------------------------------------------------
        | Currency Settings
        |--------------------------------------------------------------------------
        |
        | Laravel administrator customises:
        |
        | - system base currency;
        | - default display currency;
        | - exchange-rate refresh interval;
        | - whether users may choose their own display currency.
        |
        | Final URLs:
        |
        | GET  /admin/settings/currency
        | PUT  /admin/settings/currency
        |
        */

        Route::get(
            'settings/currency',
            [CurrencySettingsController::class, 'edit']
        )->name('settings.currency.edit');

        Route::put(
            'settings/currency',
            [CurrencySettingsController::class, 'update']
        )->name('settings.currency.update');

        /*
        |--------------------------------------------------------------------------
        | Backups
        |--------------------------------------------------------------------------
        */

        Route::post(
            'settings/backups',
            [AdminBackupController::class, 'update']
        )->name('backups.update');

        Route::post(
            'settings/backups/run',
            [AdminBackupController::class, 'run']
        )->name('backups.run');

        Route::get(
            'independent-activation-requests',
            [IndependentActivationRequestAdminController::class, 'index']
        )->name('independent-activation-requests.index');

        Route::post(
            'independent-activation-requests/{activationRequest}/approve',
            [IndependentActivationRequestAdminController::class, 'approve']
        )->whereNumber('activationRequest')
          ->name('independent-activation-requests.approve');

        Route::post(
            'independent-activation-requests/{activationRequest}/reject',
            [IndependentActivationRequestAdminController::class, 'reject']
        )->whereNumber('activationRequest')
          ->name('independent-activation-requests.reject');

        /*
        |--------------------------------------------------------------------------
        | Meeting Platforms
        |--------------------------------------------------------------------------
        */

        Route::post(
            'meeting-platforms/{meetingPlatformConfig}',
            [
                \App\Http\Controllers\Admin\AdminMeetingPlatformController::class,
                'update',
            ]
        )->name('meeting-platforms.update');

        /*
        |--------------------------------------------------------------------------
        | AI Providers
        |--------------------------------------------------------------------------
        */

        Route::post(
            'ai-providers',
            [AdminAiProviderController::class, 'store']
        )->name('ai-providers.store');

        Route::put(
            'ai-providers/{aiProvider}',
            [AdminAiProviderController::class, 'update']
        )->name('ai-providers.update');

        Route::post(
            'ai-providers/{aiProvider}/toggle',
            [AdminAiProviderController::class, 'toggle']
        )->name('ai-providers.toggle');

        Route::delete(
            'ai-providers/{aiProvider}',
            [AdminAiProviderController::class, 'destroy']
        )->name('ai-providers.destroy');

        /*
        |--------------------------------------------------------------------------
        | Payment Gateways
        |--------------------------------------------------------------------------
        */

        Route::resource(
            'payment-gateways',
            AdminPaymentGatewayController::class
        )->except([
            'show',
        ]);

        Route::post(
            'payment-gateways/{paymentGateway}/toggle',
            [AdminPaymentGatewayController::class, 'toggle']
        )->name('payment-gateways.toggle');

        Route::post(
            'payment-gateways/{paymentGateway}/set-default',
            [AdminPaymentGatewayController::class, 'setDefault']
        )->name('payment-gateways.set-default');

        Route::post(
            'payment-gateways/{paymentGateway}/test-connection',
            [AdminPaymentGatewayController::class, 'testConnection']
        )->name('payment-gateways.test-connection');

        /*
        |--------------------------------------------------------------------------
        | Announcements
        |--------------------------------------------------------------------------
        */

        Route::delete(
            'announcements/bulk-destroy',
            [
                \App\Http\Controllers\Admin\AdminAnnouncementController::class,
                'bulkDestroy',
            ]
        )->name('announcements.bulk-destroy');

        Route::resource(
            'announcements',
            \App\Http\Controllers\Admin\AdminAnnouncementController::class
        )->only([
            'index',
            'create',
            'store',
            'destroy',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Payments
        |--------------------------------------------------------------------------
        */

        Route::get(
            'payments',
            [AdminPaymentsController::class, 'index']
        )->name('payments.index');

        Route::delete(
            'payments/bulk-destroy',
            [AdminPaymentsController::class, 'bulkDestroy']
        )->name('payments.bulk-destroy');

        Route::post(
            'payments/{payment}/approve',
            [AdminPaymentsController::class, 'approve']
        )->name('payments.approve');

        Route::post(
            'payments/{payment}/reject',
            [AdminPaymentsController::class, 'reject']
        )->name('payments.reject');

        /*
        |--------------------------------------------------------------------------
        | Feedback
        |--------------------------------------------------------------------------
        */

        Route::get(
            'feedback',
            [AdminFeedbackController::class, 'index']
        )->name('feedback.index');

        Route::post(
            'feedback/{feedback}',
            [AdminFeedbackController::class, 'update']
        )->name('feedback.update');

        Route::delete(
            'feedback/{feedback}',
            [AdminFeedbackController::class, 'destroy']
        )->name('feedback.destroy');

        /*
        |--------------------------------------------------------------------------
        | Billing Logs
        |--------------------------------------------------------------------------
        */

        Route::get(
            'billing-logs',
            [
                \App\Http\Controllers\Admin\AdminBillingLogController::class,
                'index',
            ]
        )->name('billing-logs.index');

        Route::delete(
            'billing-logs/bulk-destroy',
            [
                \App\Http\Controllers\Admin\AdminBillingLogController::class,
                'bulkDestroy',
            ]
        )->name('billing-logs.bulk-destroy');

        /*
        |--------------------------------------------------------------------------
        | Enterprise Inquiries
        |--------------------------------------------------------------------------
        */

        Route::get(
            'enterprise-inquiries',
            [
                \App\Http\Controllers\Admin\AdminEnterpriseInquiryController::class,
                'index',
            ]
        )->name('enterprise-inquiries.index');

        Route::delete(
            'enterprise-inquiries/bulk-destroy',
            [
                \App\Http\Controllers\Admin\AdminEnterpriseInquiryController::class,
                'bulkDestroy',
            ]
        )->name('enterprise-inquiries.bulk-destroy');

        Route::patch(
            'enterprise-inquiries/{enterpriseInquiry}/status',
            [
                \App\Http\Controllers\Admin\AdminEnterpriseInquiryController::class,
                'updateStatus',
            ]
        )->name('enterprise-inquiries.status');

        Route::post(
            'enterprise-inquiries/{enterpriseInquiry}/quotation',
            [
                \App\Http\Controllers\Admin\AdminEnterpriseInquiryController::class,
                'sendQuotation',
            ]
        )->name('enterprise-inquiries.quotation');

        Route::post(
            'enterprise-inquiries/{enterpriseInquiry}/invoices/{invoice}/convert',
            [
                \App\Http\Controllers\Admin\AdminEnterpriseInquiryController::class,
                'convertToInvoice',
            ]
        )->name('enterprise-inquiries.convert-invoice');

        Route::post(
            'enterprise-inquiries/{enterpriseInquiry}/invoices/{invoice}/resend',
            [
                \App\Http\Controllers\Admin\AdminEnterpriseInquiryController::class,
                'resendInvoice',
            ]
        )->name('enterprise-inquiries.resend-invoice');

        Route::post(
            'enterprise-inquiries/{enterpriseInquiry}/receipt',
            [
                \App\Http\Controllers\Admin\AdminEnterpriseInquiryController::class,
                'sendReceipt',
            ]
        )->name('enterprise-inquiries.receipt');

        Route::post(
            'enterprise-inquiries/{enterpriseInquiry}/reply',
            [
                \App\Http\Controllers\Admin\AdminEnterpriseInquiryController::class,
                'replyToInquiry',
            ]
        )->name('enterprise-inquiries.reply');

        /*
        |--------------------------------------------------------------------------
        | Invoices / Receipts
        |--------------------------------------------------------------------------
        */

        Route::get(
            'invoices',
            [
                \App\Http\Controllers\Admin\AdminInvoiceController::class,
                'index',
            ]
        )->name('invoices.index');

        Route::delete(
            'invoices/bulk-destroy',
            [
                \App\Http\Controllers\Admin\AdminInvoiceController::class,
                'bulkDestroy',
            ]
        )->name('invoices.bulk-destroy');

        Route::delete(
            'receipts/bulk-destroy',
            [
                \App\Http\Controllers\Admin\AdminInvoiceController::class,
                'bulkDestroyReceipts',
            ]
        )->name('receipts.bulk-destroy');

        Route::delete(
            'invoices/{invoice}',
            [
                \App\Http\Controllers\Admin\AdminInvoiceController::class,
                'destroy',
            ]
        )->name('invoices.destroy');

        /*
        |--------------------------------------------------------------------------
        | Subscription Plans
        |--------------------------------------------------------------------------
        */

        Route::resource(
            'subscription-plans',
            AdminSubscriptionPlanController::class
        )->except([
            'show',
        ]);

        Route::post(
            'subscription-plans/{subscriptionPlan}/toggle',
            [AdminSubscriptionPlanController::class, 'toggle']
        )->name('subscription-plans.toggle');

        /*
        |--------------------------------------------------------------------------
        | SMS Providers
        |--------------------------------------------------------------------------
        */

        Route::get(
            'sms-providers',
            [AdminSmsProviderController::class, 'index']
        )->name('sms-providers.index');

        Route::post(
            'sms-providers',
            [AdminSmsProviderController::class, 'store']
        )->name('sms-providers.store');

        Route::put(
            'sms-providers/{smsProvider}',
            [AdminSmsProviderController::class, 'update']
        )->name('sms-providers.update');

        Route::delete(
            'sms-providers/{smsProvider}',
            [AdminSmsProviderController::class, 'destroy']
        )->name('sms-providers.destroy');

        Route::post(
            'sms-providers/test',
            [AdminSmsProviderController::class, 'test']
        )->middleware('throttle:5,10')->name('sms-providers.test');

        Route::get(
            'social-media-providers',
            [AdminSocialMediaProviderController::class, 'index']
        )->name('social-media-providers.index');

        Route::post(
            'social-media-providers',
            [AdminSocialMediaProviderController::class, 'store']
        )->name('social-media-providers.store');

        Route::put(
            'social-media-providers/{provider}',
            [AdminSocialMediaProviderController::class, 'update']
        )->whereNumber('provider')
          ->name('social-media-providers.update');

        Route::post(
            'social-media-providers/{provider}/test',
            [AdminSocialMediaProviderController::class, 'test']
        )->whereNumber('provider')
          ->name('social-media-providers.test');

        Route::post(
            'social-media-providers/{provider}/toggle',
            [AdminSocialMediaProviderController::class, 'toggle']
        )->whereNumber('provider')
          ->name('social-media-providers.toggle');

        Route::post(
            'social-media-providers/{provider}/default',
            [AdminSocialMediaProviderController::class, 'makeDefault']
        )->whereNumber('provider')
          ->name('social-media-providers.default');

        Route::delete(
            'social-media-providers/{provider}',
            [AdminSocialMediaProviderController::class, 'destroy']
        )->whereNumber('provider')
          ->name('social-media-providers.destroy');

        Route::get('extra-requests', [\App\Http\Controllers\Admin\ExtraRequestController::class, 'index'])->name('extra-requests.index');
        Route::get('extra-requests/{extra_request}', [\App\Http\Controllers\Admin\ExtraRequestController::class, 'show'])->name('extra-requests.show');
        Route::post('extra-requests/{extra_request}/approve', [\App\Http\Controllers\Admin\ExtraRequestController::class, 'approve'])->name('extra-requests.approve');
        Route::post('extra-requests/{extra_request}/reject', [\App\Http\Controllers\Admin\ExtraRequestController::class, 'reject'])->name('extra-requests.reject');
        Route::post('extra-requests/{extra_request}/apply', [\App\Http\Controllers\Admin\ExtraRequestController::class, 'apply'])->name('extra-requests.apply');
    });
