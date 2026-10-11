<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiPlan extends Model
{
    protected $fillable = ['user_id', 'content', 'custom_prompt', 'provider', 'used_shared_key'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * $content as-stored may still contain literal Markdown syntax —
     * either from plans generated before the prompt explicitly forbade it
     * (see AiPlannerService::buildPrompt()), or because a model
     * occasionally ignores that instruction anyway. Rather than rendering
     * Markdown as HTML (a heavier change, and this content is also shown
     * inside a plain-text PDF), this just strips the symbols so headings
     * and emphasis read as clean plain text instead of raw "##"/"**"
     * clutter. Used everywhere $plan->content is displayed — the index
     * preview, the view modal, and the PDF.
     */
    public function cleanContent(): string
    {
        $text = (string) $this->content;

        // "## Heading" / "### Heading" -> "Heading"
        $text = preg_replace('/^#{1,6}\s*/m', '', $text);
        // "**bold**" or "__bold__" -> "bold"
        $text = preg_replace('/(\*\*|__)(.*?)\1/s', '$2', $text);
        // A "- " or "* " bullet marker at the start of a line -> nothing
        // (numbered lines like "1. " are left alone; those aren't Markdown-specific)
        $text = preg_replace('/^[\-\*]\s+/m', '', $text);

        return trim(str_replace(['—', '–'], '-', $text));
    }
}
