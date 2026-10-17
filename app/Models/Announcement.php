<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class Announcement extends Model
{
    public const TYPE_GENERAL = 'general';
    public const TYPE_NEW_FEATURE = 'new_feature';

    public const TYPES = [
        self::TYPE_GENERAL => 'General update',
        self::TYPE_NEW_FEATURE => 'New feature',
    ];

    public const DEFAULT_FEATURE_CTA = 'Explore now';

    protected $fillable = [
        'created_by', 'title', 'body', 'type', 'feature_key', 'link_url', 'cta_label', 'send_push', 'send_email',
    ];

    protected $attributes = [
        'type' => self::TYPE_GENERAL,
        'send_push' => true,
        'send_email' => false,
    ];

    protected $casts = [
        'send_push' => 'boolean',
        'send_email' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array<string, array{label:string,route:string,icon:string}> */
    public static function featureCatalogue(): array
    {
        return (array) config('features', []);
    }

    public function isNewFeature(): bool
    {
        return $this->type === self::TYPE_NEW_FEATURE;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? self::TYPES[self::TYPE_GENERAL];
    }

    /** @return array{label:string,route:string,icon:string}|null */
    public function feature(): ?array
    {
        return $this->feature_key ? (self::featureCatalogue()[$this->feature_key] ?? null) : null;
    }

    /**
     * Where the call to action points on the web: an explicit link wins
     * (kept as entered, so a relative "/meetings" stays relative), otherwise
     * the catalogue feature's route, otherwise nothing.
     */
    public function resolvedLinkUrl(): ?string
    {
        $link = trim((string) $this->link_url);
        if ($link !== '') {
            return $link;
        }

        $route = $this->feature()['route'] ?? null;

        return $route && Route::has($route) ? route($route) : null;
    }

    /** Absolute version of the link for email and web push; the dashboard when there is none. */
    public function absoluteLinkUrl(): string
    {
        $link = $this->resolvedLinkUrl();

        if ($link === null) {
            return route('dashboard');
        }

        return Str::startsWith($link, ['http://', 'https://']) ? $link : url($link);
    }

    public function resolvedCtaLabel(): ?string
    {
        $label = trim((string) $this->cta_label);
        if ($label !== '') {
            return $label;
        }

        return $this->isNewFeature() ? self::DEFAULT_FEATURE_CTA : null;
    }

    public function pushTitle(): string
    {
        return $this->isNewFeature() ? 'New: '.$this->title : $this->title;
    }

    public function pushBody(): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', (string) $this->body)), 120);
    }

    /** @return list<string> */
    public function channels(): array
    {
        return array_values(array_filter([
            'In-app',
            $this->send_push ? 'Push' : null,
            $this->send_email ? 'Email' : null,
        ]));
    }
}
