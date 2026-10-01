<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IoTecSubscriptionTransaction extends Model
{
    protected $table = 'iotec_subscription_transactions';

    protected $fillable = [
        'user_id',
        'beneficiary_user_id',
        'subscription_plan_id',
        'external_id',
        'iotec_request_id',
        'payment_channel',
        'card_brand',
        'payer',
        'amount',
        'currency',
        'status',
        'status_code',
        'status_message',
        'payment_id',
        'card_redirect_url',
        'gateway_response',
        'paid_at',
        'activated_at',
    ];

    protected $casts = [
        'gateway_response' => 'array',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The user whose subscription this payment activates when the payer
     * (user_id) paid on their behalf. Null means the payer themselves.
     */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_user_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
