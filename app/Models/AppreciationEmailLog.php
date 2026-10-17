<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppreciationEmailLog extends Model
{
    protected $fillable = [
        'user_id', 'week_key', 'source', 'theme', 'subject', 'message', 'sent_at', 'sent_by', 'campaign_id',
    ];

    protected $casts = ['sent_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }

    public function sender() { return $this->belongsTo(User::class, 'sent_by'); }

    public function campaign() { return $this->belongsTo(AppreciationCampaign::class, 'campaign_id'); }

    /** "Weekly run" for the automatic email, otherwise "Admin" (with the name when known). */
    public function originLabel(): string
    {
        if ($this->sent_by === null && $this->campaign_id === null) {
            return 'Weekly run';
        }

        return $this->sender?->name ? 'Admin · '.$this->sender->name : 'Admin';
    }

    public function sourceLabel(): string
    {
        return $this->source === 'ai' ? 'AI' : 'Built-in';
    }
}
