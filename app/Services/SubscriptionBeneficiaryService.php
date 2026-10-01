<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Resolves the existing user a subscription payment is made for when the
 * payer is paying on someone else's behalf. The payer stays the owner of
 * the payment, invoice and receipt; only the subscription is activated on
 * the beneficiary.
 */
class SubscriptionBeneficiaryService
{
    /**
     * Find an active (not suspended / offboarded) user by exact email,
     * case-insensitively. Returns null when no such user exists.
     */
    public function findActiveByEmail(string $email): ?User
    {
        $email = mb_strtolower(trim($email));

        if ($email === '') {
            return null;
        }

        $query = User::query()->whereRaw('LOWER(email) = ?', [$email]);

        if (Schema::hasColumn('users', 'suspended_at')) {
            $query->whereNull('suspended_at');
        }

        if (Schema::hasColumn('users', 'offboarded_at')) {
            $query->whereNull('offboarded_at');
        }

        if (Schema::hasColumn('users', 'account_status')) {
            $query->where(function ($sub) {
                $sub->whereNull('account_status')
                    ->orWhere('account_status', 'active');
            });
        }

        return $query->first();
    }

    public function isOwnEmail(User $payer, string $email): bool
    {
        return mb_strtolower(trim($email)) === mb_strtolower(trim((string) $payer->email));
    }

    /**
     * For payment endpoints: returns the beneficiary for an optional
     * beneficiary_email, or null when none was given. Throws a 422
     * validation error on the beneficiary_email field when the email is
     * the payer's own or doesn't belong to an active user.
     */
    public function resolveForPayment(User $payer, ?string $email): ?User
    {
        $email = trim((string) $email);

        if ($email === '') {
            return null;
        }

        if ($this->isOwnEmail($payer, $email)) {
            throw ValidationException::withMessages([
                'beneficiary_email' => 'That is your own email. Leave it empty to pay for your own subscription.',
            ]);
        }

        $beneficiary = $this->findActiveByEmail($email);

        if (! $beneficiary || (int) $beneficiary->id === (int) $payer->id) {
            throw ValidationException::withMessages([
                'beneficiary_email' => 'We could not find an active My Digital Diary account with that email.',
            ]);
        }

        return $beneficiary;
    }

    public function describe(?User $beneficiary): ?array
    {
        return $beneficiary
            ? ['id' => $beneficiary->id, 'name' => $beneficiary->name]
            : null;
    }
}
