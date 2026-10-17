<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppreciationEmailLog extends Model
{
    protected $fillable = [
        'user_id', 'week_key', 'source', 'theme', 'subject', 'message', 'sent_at',
    ];

    protected $casts = ['sent_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
}
