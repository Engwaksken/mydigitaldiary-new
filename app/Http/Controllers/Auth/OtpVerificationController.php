<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class OtpVerificationController extends Controller
{
    /**
     * Show the "enter your code" screen. Only reachable if a login attempt
     * has already passed the email/password check (see
     * AuthenticatedSessionController@store) and is waiting on OTP.
     */
    public function show(): View|RedirectResponse
    {
        if (! session('otp.user.id')) {
            return redirect()->route('login');
        }

        return view('auth.verify-otp');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $userId = session('otp.user.id');
        abort_unless($userId, 403);

        $otp = LoginOtp::where('user_id', $userId)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $otp || $otp->isExpired()) {
            return back()->withErrors([
                'code' => 'This code has expired. Please request a new one.',
            ]);
        }

        if ($otp->attempts >= 5) {
            return back()->withErrors([
                'code' => 'Too many incorrect attempts. Please request a new code.',
            ]);
        }

        if (! Hash::check($request->input('code'), $otp->code)) {
            $otp->increment('attempts');

            return back()->withErrors([
                'code' => 'That code is incorrect.',
            ]);
        }

        // Verification must ONLY consume the code and authenticate the user.
        // It must never create/send a fresh OTP. Resending is handled exclusively
        // by the separate resend() endpoint/form.
        $otp->update(['consumed_at' => now()]);

        $user = User::findOrFail($userId);

        // The code was emailed to $user->email, so entering it proves the
        // person controls that mailbox — the same proof the verification link
        // gives. It is email (never SMS) only: LoginOtpNotification::via()
        // returns ['mail']. If an SMS channel is ever added, this must only
        // run for codes delivered by email.
        $user->markEmailVerifiedByOtp();

        Auth::login($user, (bool) session('otp.remember', false));

        // Clear temporary OTP throttles after a successful verification so a
        // future, separate login starts from a clean state. This does NOT send mail.
        RateLimiter::clear('otp-login-send:' . $userId);
        RateLimiter::clear('otp-resend:' . $userId);

        session()->forget(['otp.user.id', 'otp.remember']);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Resend a fresh OTP, rate-limited to once every 60 seconds per user.
     */
    public function resend(Request $request): RedirectResponse
    {
        $userId = session('otp.user.id');
        abort_unless($userId, 403);

        $key = 'otp-resend:' . $userId;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors([
                'code' => "Please wait {$seconds}s before requesting another code.",
            ]);
        }

        RateLimiter::hit($key, 60);

        try {
            LoginOtp::createFor(User::findOrFail($userId));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Could not resend login OTP email.', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'code' => 'We could not send a new code right now. Please try again in a moment.',
            ]);
        }

        return back()->with('status', 'A new code has been sent to your email.');
    }

    /**
     * Abandon a pending OTP login (e.g. "this isn't me" / wrong account)
     * and send the user back to the login form. No session is authenticated
     * at this stage, so there's nothing to log out of — just clear the
     * pending state.
     */
    public function cancel(Request $request): RedirectResponse
    {
        session()->forget(['otp.user.id', 'otp.remember']);

        return redirect()->route('login');
    }
}
