<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    protected $fillable = [
        'type', 'name', 'is_enabled', 'instructions', 'config',
        'gateway_code', 'display_name', 'provider_type', 'description', 'is_default', 'sandbox_mode',
        'token_url', 'collect_url', 'base_url', 'status_url',
        'client_id', 'client_secret', 'wallet_guid', 'callback_url', 'return_url',
        'supports_collection', 'supports_disbursement', 'supports_mtn', 'supports_airtel',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_default' => 'boolean',
        'sandbox_mode' => 'boolean',
        'supports_collection' => 'boolean',
        'supports_disbursement' => 'boolean',
        'supports_mtn' => 'boolean',
        'supports_airtel' => 'boolean',
        // Built-in Laravel cast: JSON-encodes then encrypts with APP_KEY,
        // decrypts + decodes on read. Safe place for a Stripe secret key.
        'config' => 'encrypted:array',
        // Same encrypted-at-rest treatment for the aggregator credentials.
        'client_id' => 'encrypted',
        'client_secret' => 'encrypted',
        'wallet_guid' => 'encrypted',
        'supported_payment_methods' => 'array',
        'supported_currencies' => 'array',
    ];

    // Read-only aliases matching the more generic terminology used in
    // the admin form and elsewhere ("API key" / "API secret" /
    // "merchant ID") - same encrypted columns underneath (client_id,
    // client_secret, wallet_guid), not duplicate storage. Kept as
    // aliases rather than renaming those columns outright, since IoTec
    // was already wired up against the original names and a rename
    // would risk breaking that for no real benefit.
    public function getApiKeyAttribute()
    {
        return $this->client_id;
    }

    public function getApiSecretAttribute()
    {
        return $this->client_secret;
    }

    public function getMerchantIdAttribute()
    {
        return $this->wallet_guid;
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function transactionLogs()
    {
        return $this->hasMany(PaymentTransactionLog::class);
    }

    public function isCard(): bool
    {
        return $this->type === 'card';
    }

    public function isManual(): bool
    {
        return in_array($this->type, ['bank', 'mobile_money'], true);
    }

    public function isAggregator(): bool
    {
        return $this->type === 'aggregator';
    }

    /**
     * A 'mobile_money' gateway can now optionally carry aggregator
     * credentials (API URL, key, secret, etc.) for automated instant
     * collection, instead of only ever being the manual "submit your
     * transaction reference for admin approval" flow. This is true
     * once those credentials are actually filled in - false means it's
     * still the plain manual gateway it always was.
     */
    public function collectsAutomatically(): bool
    {
        return in_array($this->type, ['mobile_money', 'aggregator'], true) && $this->isConfigured();
    }

    /** Convenience accessor for a single config value, e.g. $gateway->configValue('stripe_secret_key'). */
    public function configValue(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->client_id) && ! empty($this->client_secret) && ! empty($this->token_url) && ! empty($this->collect_url);
    }
}
