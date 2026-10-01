<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id', 'beneficiary_user_id', 'payment_gateway_id', 'subscription_plan_id', 'method', 'amount', 'currency', 'status', 'reference', 'gateway_transaction_id', 'notes', 'receipt_number',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The user whose subscription this payment activates when the payer
     * (user_id) paid on their behalf. Null means the payer themselves.
     */
    public function beneficiary()
    {
        return $this->belongsTo(User::class, 'beneficiary_user_id');
    }

    /**
     * Who receives the subscription when this payment completes: the
     * beneficiary if one was set, otherwise the payer. Invoices,
     * receipts and payment notifications always stay with the payer.
     */
    public function subscriptionRecipient(): ?User
    {
        if ($this->beneficiary_user_id) {
            $beneficiary = $this->beneficiary;

            if ($beneficiary) {
                return $beneficiary;
            }
        }

        return $this->user;
    }

    public function gateway()
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function transactionLogs()
    {
        return $this->hasMany(PaymentTransactionLog::class);
    }

    public function subscriptionRecordingExtraGrant()
    {
        return $this->hasOne(SubscriptionRecordingExtraGrant::class);
    }

    /**
     * The most recent gateway transaction recorded for this payment.
     *
     * Billing, invoice and admin screens eager-load this relation when
     * they need the latest phone number/network/reference without loading
     * the complete transaction history.
     */
    public function latestTransaction()
    {
        return $this->hasOne(PaymentTransactionLog::class)->latestOfMany();
    }

    public function paymentContactPhone(): ?string
    {
        $logged = $this->relationLoaded('transactionLogs')
            ? $this->transactionLogs->sortByDesc('id')->first(fn ($log) => filled($log->phone_number))?->phone_number
            : $this->transactionLogs()->whereNotNull('phone_number')->latest('id')->value('phone_number');

        return $logged ?: $this->user?->phone_number;
    }

    /**
     * Assigns a receipt number the first time a payment is actually
     * completed — never on creation, since a pending/failed attempt
     * shouldn't consume a receipt number. Format is YEAR-0001, resetting
     * to 0001 at the start of each new year rather than running as one
     * long sequence forever.
     */
    public function assignReceiptNumber(): void
    {
        if ($this->receipt_number) {
            return;
        }

        $year = now()->year;
        $lastNumber = static::where('receipt_number', 'like', $year . '-%')
            ->orderByDesc('receipt_number')
            ->value('receipt_number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, 5)) + 1 : 1;

        $this->update(['receipt_number' => $year . '-' . str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT)]);
    }
}
