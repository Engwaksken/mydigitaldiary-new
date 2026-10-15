<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranscriptionMinuteUsage extends Model
{
    protected $fillable = [
        'user_id',
        'duration_seconds',
        'plan_seconds',
        'extra_minutes',
        'period_start',
        'status',
    ];
}
