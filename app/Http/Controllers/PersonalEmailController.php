<?php

namespace App\Http\Controllers;

use App\Mail\PersonalEmailVerificationMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Lets someone who was just offboarded from an organization (see
 * OrganizationController::removeMember()) add and verify a personal
 * email so they can keep using their account under a Free/Individual
 * plan afterward. A 6-digit code, same idea as the login OTP but a
 * separate flow since this is verifying a NEW address, not confirming
 * an existing login.
 */
class PersonalEmailController extends Controller
{
    public function sendCode(Request $request): RedirectResponse
    {
        $data = $request->validate(['personal_email' => ['required', 'email', 'max:255']]);

        $code = (string) random_int(100000, 999999);

        $request->user()->update([
            'personal_email' => $data['personal_email'],
            'personal_email_verified_at' => null,
        ]);

        session(['personal_email_code' => $code, 'personal_email_code_expires_at' => now()->addMinutes(10)->toIso8601String()]);

        Mail::to($data['personal_email'])->send(new PersonalEmailVerificationMail($code));

        return back()->with('success', 'A verification code has been sent to ' . $data['personal_email'] . '.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);

        $expected = session('personal_email_code');
        $expiresAt = session('personal_email_code_expires_at');

        if (! $expected || ! $expiresAt || now()->isAfter($expiresAt)) {
            return back()->withErrors(['code' => 'That code has expired - request a new one.']);
        }

        if ($data['code'] !== $expected) {
            return back()->withErrors(['code' => 'That code is incorrect.']);
        }

        $user = $request->user();
        $user->update([
            'email' => $user->personal_email,
            'personal_email_verified_at' => now(),
            'offboarded_at' => null,
        ]);

        session()->forget(['personal_email_code', 'personal_email_code_expires_at']);

        return redirect()->route('dashboard')->with('success', 'Your personal email is verified - you can keep using your account under a Free/Individual plan.');
    }
}
