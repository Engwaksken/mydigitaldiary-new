<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Drop-in replacement for Breeze's default EmailVerificationNotificationController
 * (the "resend verification email" button on the "please verify your
 * email" notice page). Identical to Breeze's version except the actual
 * send is wrapped in a try/catch — see RegisteredUserController and
 * AuthenticatedSessionController for the same fix and the full reasoning:
 * mail sending happens SYNCHRONOUSLY whenever QUEUE_CONNECTION=sync, so an
 * SMTP rejection (a 550 "classified as spam" response is a real, observed
 * failure mode with this app's mail server) used to crash this endpoint
 * with a 500 rather than showing the user a normal "we couldn't send that
 * right now" message.
 */
class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        // A friendly per-user cooldown on top of the route's throttle:6,1,
        // so a repeat click gets a message instead of a 429 page.
        $key = 'verification-resend:' . $request->user()->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors([
                'verification' => "Please wait {$seconds}s before requesting another link.",
            ]);
        }

        return $this->sendLink($request, $key);
    }

    /**
     * "Wrong address?" on the verification notice page: an unverified user
     * who mistyped their email at sign-up can correct it here and get a new
     * link, without needing the (verified-only) profile page. Organisation
     * members' emails stay locked by LockOrganizationMemberEmail on the route.
     */
    public function updateEmail(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $data = $request->validateWithBag('emailUpdate', [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        if ($data['email'] !== $user->email) {
            $user->forceFill([
                'email' => $data['email'],
                'email_verified_at' => null,
            ])->save();
        }

        // A changed address always gets a fresh link straight away; the
        // route's throttle:6,1 still caps how often this can be used.
        return $this->sendLink($request, 'verification-resend:' . $user->id);
    }

    private function sendLink(Request $request, string $cooldownKey): RedirectResponse
    {
        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            Log::warning('Could not resend the email verification notification.', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'verification' => 'We could not send a verification email right now. Please try again in a moment.',
            ]);
        }

        RateLimiter::hit($cooldownKey, 60);

        return back()->with('status', 'verification-link-sent');
    }
}
