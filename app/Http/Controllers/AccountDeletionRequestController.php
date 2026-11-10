<?php

namespace App\Http\Controllers;

use App\Mail\AccountDeletionConfirmationMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Public account-deletion request page required by Google Play: people must
 * be able to ask for deletion without opening the app or signing in.
 *
 * Flow: enter email -> we email a signed, 60-minute confirmation link ->
 * GET shows a confirm button (so mail scanners that prefetch links cannot
 * delete anyone) -> POST schedules deletion with the same 30-day grace
 * period as Privacy & Data, after which accounts:purge-scheduled-deletions removes it.
 *
 * The request step always shows the same message, whether or not the email
 * belongs to an account, so the form cannot be used to discover accounts.
 */
class AccountDeletionRequestController extends Controller
{
    public const GRACE_DAYS = 30;

    public function show(): View
    {
        return view('account-deletion.request');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user && $user->scheduled_deletion_at === null) {
            $url = URL::temporarySignedRoute(
                'account-deletion.confirm',
                now()->addMinutes(60),
                ['user' => $user->id]
            );

            try {
                Mail::to($user->email)->send(new AccountDeletionConfirmationMail($url));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', 'If an account exists for that email, we have sent a link to confirm deletion. The link expires in 60 minutes.');
    }

    public function confirmShow(Request $request, User $user): View
    {
        return view('account-deletion.confirm', [
            'user' => $user,
            'actionUrl' => $request->fullUrl(),
        ]);
    }

    public function confirm(Request $request, User $user): View
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
            'confirm_delete' => ['accepted'],
        ]);

        if ($user->scheduled_deletion_at === null) {
            $user->update([
                'deletion_reason' => $data['reason'] ?: 'Requested via public delete-account page',
                'deletion_requested_at' => now(),
                'scheduled_deletion_at' => now()->addDays(self::GRACE_DAYS),
            ]);
            $user->tokens()->delete();
        }

        return view('account-deletion.confirm', [
            'user' => $user->fresh(),
            'actionUrl' => $request->fullUrl(),
        ]);
    }
}
