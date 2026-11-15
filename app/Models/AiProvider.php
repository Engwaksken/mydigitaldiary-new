<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    protected $fillable = ['key', 'name', 'api_base_url', 'default_model', 'is_enabled', 'sort_order'];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function isBuiltIn(): bool
    {
        return in_array($this->key, ['anthropic', 'openai'], true);
    }

    /**
     * Anything that isn't one of the two built-ins is called via the
     * generic OpenAI-compatible request/response shape - see
     * AiPlannerService::callOpenAiCompatible().
     */
    public function isCustomOpenAiCompatible(): bool
    {
        return ! $this->isBuiltIn();
    }
}
