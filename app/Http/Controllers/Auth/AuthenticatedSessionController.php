<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Drop-in replacement for Laravel Breeze's AuthenticatedSessionController.
 *
 * The email/password check happens exactly as before, but instead of
 * completing the login, `store()` sends a one-time code to the user's email
 * and redirects them to the OTP verification screen
 * (see OtpVerificationController). The session is only actually
 * authenticated once that code is confirmed.
 */
class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login', [
            'canResetPassword' => Route::has('password.request'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->input('email')) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            event(new Lockout($request));

            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        if (! Auth::validate($request->only('email', 'password'))) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        /** @var User $user */
        $user = User::where('email', $request->input('email'))->firstOrFail();

        if ($user->isSuspended()) {
            throw ValidationException::withMessages([
                'email' => 'This account has been suspended. Contact support if you believe this is a mistake.',
            ]);
        }

        // Stash the pending login in the (still-guest) session and email a code.
        // Auth::login() is deliberately NOT called yet.
        session([
            'otp.user.id' => $user->id,
            'otp.remember' => $request->boolean('remember'),
        ]);

        // LoginOtp::createFor() sends mail synchronously whenever
        // QUEUE_CONNECTION=sync (the recommended local/dev setting). If the
        // mail server rejects the message (observed in practice: a 550
        // "classified as spam" response), that used to throw all the way
        // up and crash the login request with a 500. Worse than the
        // registration-email case - there's no point sending someone to
        // "enter your code" if the code was never actually delivered - so
        // this catches it and shows a clear error instead, keeping the
        // pending-login session state intact so they can just retry
        // rather than re-entering their password too.
        // Prevent a rapid double-submit (double-click, browser retry, slow network)
        // from generating and emailing two different OTPs for the same login.
        $otpSendKey = 'otp-login-send:' . $user->id;

        if (! RateLimiter::tooManyAttempts($otpSendKey, 1)) {
            RateLimiter::hit($otpSendKey, 15);

            try {
                LoginOtp::createFor($user);
            } catch (\Throwable $e) {
                // A failed delivery must not lock the user out of retrying.
                RateLimiter::clear($otpSendKey);

                \Illuminate\Support\Facades\Log::warning('Could not send login OTP email.', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                throw ValidationException::withMessages([
                    'email' => 'We could not send your login code right now. Please try again in a moment, or contact support if this keeps happening.',
                ]);
            }
        }

        return redirect()->route('otp.verify');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
