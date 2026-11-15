<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiCredential extends Model
{
    protected $fillable = ['user_id', 'label', 'provider', 'api_key', 'is_active'];

    protected $casts = [
        // Laravel's built-in 'encrypted' cast - encrypted with APP_KEY on
        // write, decrypted on read. Never stored or logged in plain text.
        'api_key' => 'encrypted',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
