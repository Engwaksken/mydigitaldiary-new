<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Throwable;

/**
 * Drop-in replacement for Breeze's RegisteredUserController. Identical to
 * the default except: registration now requires an explicit, unticked-by-
 * default data-processing consent checkbox (this app stores sensitive
 * health and financial data), and the current consent + a policy version
 * are recorded on the user at signup.
 */
class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'data_consent' => ['accepted'],
        ], [
            'data_consent.accepted' => 'Please review and accept how your data is processed before creating an account.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'data_consent_at' => now(),
            'data_consent_version' => '1.0',
        ]);

        $user->createDefaultHydrationReminder();

        // The account record above is already saved at this point. Sending
        // the verification email happens SYNCHRONOUSLY whenever
        // QUEUE_CONNECTION=sync (the recommended local/dev setting — see
        // the Reminder scheduling section of this README) — so if the mail
        // server rejects the message (a 550 "classified as spam" response
        // is a real, observed failure mode), the exception used to
        // propagate all the way up and crash the entire registration
        // request with a 500, even though the account had already been
        // created successfully. A user in that state has a working
        // account they don't know exists, staring at a scary error page
        // instead of their dashboard. Catching it here means registration
        // always completes; the user can request a fresh verification
        // email from the "please verify your email" notice page either way.
        try {
            event(new Registered($user));
        } catch (Throwable $e) {
            Log::warning('Registration succeeded but the verification email failed to send.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        Auth::login($user);

        // Straight to "check your email": nothing past this is reachable
        // until the address is verified (every authenticated route group
        // carries the 'verified' middleware).
        return redirect(route('verification.notice', absolute: false));
    }
}
