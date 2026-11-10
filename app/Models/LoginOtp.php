<?php

namespace App\Models;

use App\Notifications\LoginOtpNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class LoginOtp extends Model
{
    protected $fillable = ['user_id', 'code', 'attempts', 'expires_at', 'consumed_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return ! is_null($this->consumed_at);
    }

    /**
     * Generate a fresh 6-digit OTP for the given user, invalidate any
     * previous unconsumed codes, email it, and return the new record.
     * The plain code is never persisted or logged — only its hash is.
     */
    public static function createFor(User $user): self
    {
        static::where('user_id', $user->id)->whereNull('consumed_at')->delete();

        $reviewCode = static::reviewAccountCode($user);
        $plainCode = $reviewCode ?? (string) random_int(100000, 999999);

        $otp = static::create([
            'user_id' => $user->id,
            'code' => Hash::make($plainCode),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        if ($reviewCode !== null) {
            // Store review account: the reviewer types the fixed code, so no
            // email is sent (its mailbox may not exist).
            Log::info('Login code issued for the store review account.', ['user_id' => $user->id]);
        } else {
            $user->notify(new LoginOtpNotification($plainCode));
        }

        return $otp;
    }

    /**
     * The fixed code for the configured store review account, or null for
     * every other user. Both REVIEW_ACCOUNT_EMAIL and REVIEW_ACCOUNT_OTP
     * must be set, and the code must be exactly 6 digits.
     */
    public static function reviewAccountCode(User $user): ?string
    {
        $email = trim((string) config('auth.review_account.email'));
        $code = trim((string) config('auth.review_account.otp'));

        if ($email === '' || ! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        return strcasecmp($email, (string) $user->email) === 0 ? $code : null;
    }
}
