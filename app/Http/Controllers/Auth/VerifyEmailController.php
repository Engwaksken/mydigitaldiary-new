<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The link in the verification email. Unlike Breeze's version this does NOT
 * need a web session: the route's 'signed' middleware plus the email hash
 * check below are the proof that the person controls the mailbox, which is
 * all verification means. That matters because mobile-app users open the
 * link in a phone browser where they are not signed in to the web app.
 *
 * - Signed in as that same user: back into the app, as before.
 * - Anyone else (usually a phone browser with no session): a confirmation
 *   page telling them to return to the app.
 */
class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse|View
    {
        $user = User::find($id);

        abort_unless(
            $user && hash_equals(sha1($user->getEmailForVerification()), $hash),
            403,
            'This verification link is invalid.'
        );

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if ($request->user()?->is($user)) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        return view('auth.email-verified', ['email' => $user->email]);
    }
}
