<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules;
use Throwable;

/**
 * Mobile equivalent of Auth/RegisteredUserController +
 * Auth/AuthenticatedSessionController + Auth/OtpVerificationController,
 * combined — the web app uses server-side SESSIONS (cookies), which make
 * no sense for a native mobile app, so this issues a Sanctum Bearer TOKEN
 * instead. Same two-step login (password, then a 6-digit emailed code)
 * and the same "don't crash if the mail server rejects the message"
 * protection as the web controllers — see README's "Mail delivery
 * failures no longer crash requests" section for the full reasoning,
 * which applies identically here.
 *
 * Flow for the Flutter app:
 *   1. POST /api/register  -> account created (unverified), no token yet
 *   2. POST /api/login     -> password checked, OTP emailed, NO token yet
 *                             (matches the web app: nothing is
 *                             authenticated until the code is confirmed)
 *   3. POST /api/verify-otp -> code checked, returns a real Bearer token
 *   4. Every other endpoint -> Authorization: Bearer {token}
 */
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'data_consent' => ['accepted'],
        ], [
            'data_consent.accepted' => 'You must accept how your data is processed before creating an account.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'data_consent_at' => now(),
            'data_consent_version' => '1.0',
        ]);

        $user->createDefaultHydrationReminder();

        try {
            event(new \Illuminate\Auth\Events\Registered($user));
        } catch (Throwable $e) {
            Log::warning('Mobile registration succeeded but the verification email failed to send.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Account created. Please check your email to verify your address, then log in.',
            'email_verified' => $user->hasVerifiedEmail(),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = \Illuminate\Support\Str::transliterate(
            \Illuminate\Support\Str::lower($request->email) . '|' . $request->ip()
        );

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => "Too many attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey);

            return response()->json(['message' => 'These credentials do not match our records.'], 401);
        }

        RateLimiter::clear($throttleKey);

        if ($user->isSuspended()) {
            return response()->json(['message' => 'This account has been suspended.'], 403);
        }

        // Debounce duplicate mobile login requests. A second tap/network retry
        // within this short window reuses the pending OTP instead of emailing
        // another code. The explicit resend endpoint keeps its own 60s limit.
        $otpSendKey = 'otp-login-send:' . $user->id;

        if (! RateLimiter::tooManyAttempts($otpSendKey, 1)) {
            RateLimiter::hit($otpSendKey, 15);

            try {
                LoginOtp::createFor($user);
            } catch (Throwable $e) {
                RateLimiter::clear($otpSendKey);

                Log::warning('Could not send mobile login OTP email.', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'We could not send your login code right now. Please try again in a moment.',
                ], 503);
            }
        }

        return response()->json([
            'message' => 'A 6-digit code has been emailed to you.',
            'user_id' => $user->id,
            'email_verified' => $user->hasVerifiedEmail(),
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'code' => ['required', 'digits:6'],
        ]);

        $otp = LoginOtp::where('user_id', $request->user_id)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $otp || $otp->isExpired()) {
            return response()->json(['message' => 'This code has expired. Please request a new one.'], 422);
        }

        if ($otp->attempts >= 5) {
            return response()->json(['message' => 'Too many incorrect attempts. Please request a new code.'], 422);
        }

        if (! Hash::check($request->code, $otp->code)) {
            $otp->increment('attempts');

            return response()->json(['message' => 'That code is incorrect.'], 422);
        }

        $otp->update(['consumed_at' => now()]);

        $user = User::findOrFail($request->user_id);

        // The code was emailed to $user->email, so entering it proves the
        // person controls that mailbox: the same proof the verification
        // link gives. Count it as verification (see OtpVerificationController).
        $user->markEmailVerifiedByOtp();

        // 'flutter-app' names the token so it's identifiable at
        // /admin — not that admins can see personal DATA there, but this
        // ties an active session to "logged in from the mobile app" for
        // support purposes. Each login creates a fresh token rather than
        // reusing one, so logging in on a new device doesn't invalidate a
        // session already active on another.
        $token = $user->createToken('flutter-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'email_verified' => $user->hasVerifiedEmail(),
            'user' => $this->userPayload($user),
        ]);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        $key = 'otp-resend:' . $request->user_id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json(['message' => "Please wait {$seconds}s before requesting another code."], 429);
        }

        RateLimiter::hit($key, 60);

        try {
            LoginOtp::createFor(User::findOrFail($request->user_id));
        } catch (Throwable $e) {
            Log::warning('Could not resend mobile login OTP email.', [
                'user_id' => $request->user_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'We could not send a new code right now.'], 503);
        }

        return response()->json(['message' => 'A new code has been sent to your email.']);
    }

    public function logout(Request $request): JsonResponse
    {
        // Only revokes the token used for THIS request — logging out on
        // one device doesn't sign out other devices/sessions.
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Mobile equivalent of Breeze's web-only "forgot password" form —
     * calls the exact same underlying Password::sendResetLink() Breeze
     * itself uses, just exposed here without CSRF (Breeze's own
     * password.email route is a standard web form submission and
     * would reject a token-less mobile POST). The resulting email's
     * reset link points at the existing web reset-password form —
     * completing it happens in the phone's browser, not in-app, since
     * that flow is already fully built and working via Breeze.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Same generic message regardless of whether the email exists —
        // avoids leaking which addresses are registered.
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'If an account exists for that email, a password reset link has been sent.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    /**
     * POST /api/email/verification-notification — reachable while
     * unverified (no 'verified.api'), throttled 6/min by the route.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
                'email_verified' => true,
            ]);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            Log::warning('Could not resend the mobile email verification notification.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'We could not send a verification email right now. Please try again in a moment.',
                'email_verified' => false,
            ], 503);
        }

        return response()->json([
            'message' => 'Verification link sent.',
            'email_verified' => false,
        ]);
    }

    /**
     * GET /api/email/verification-status — polled by the app after the user
     * taps the link in their email. Reachable while unverified.
     */
    public function verificationStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'email_verified' => $user->hasVerifiedEmail(),
            'email' => $user->email,
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'avatar_url' => $user->avatarUrl(),
            'theme_color' => $user->themeColor(),
            'theme_color_secondary' => $user->themeColorLight(),
            'font_family' => $user->fontFamily(),
            'font_size' => $user->fontSize(),
            'subscription_status' => $user->subscription_status,
            'has_active_access' => $user->hasActiveAccess(),
            'email_verified' => ! is_null($user->email_verified_at),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'alarms_muted' => (bool) $user->alarms_muted,
            'onboarding_completed' => ! is_null($user->onboarding_completed_at),
            'onboarding_focuses' => $user->onboarding_focuses ?? [],
        ];
    }
}
