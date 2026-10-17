<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\OtpVerificationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('login/otp', [OtpVerificationController::class, 'show'])->name('otp.verify');
    Route::post('login/otp', [OtpVerificationController::class, 'verify'])->name('otp.verify.submit');
    Route::post('login/otp/resend', [OtpVerificationController::class, 'resend'])->name('otp.resend');
    Route::post('login/otp/cancel', [OtpVerificationController::class, 'cancel'])->name('otp.cancel');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

/*
| Email verification. The signed link is itself the proof of mailbox
| ownership, so verification.verify works WITHOUT a web session: the link is
| often opened in a phone browser by someone who only uses the mobile app.
| See VerifyEmailController.
*/
Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

/*
| Signed in but NOT necessarily verified. These are the only authenticated
| web routes an unverified user can reach: everything they need to verify
| (notice, resend, correct a mistyped address) or to leave (logout).
| Account deletion (profile.destroy) is the other exemption, in web.php.
*/
Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::put('verify-email/email', [EmailVerificationNotificationController::class, 'updateEmail'])
        ->middleware(['throttle:6,1', \App\Http\Middleware\LockOrganizationMemberEmail::class])
        ->name('verification.email.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');
});
