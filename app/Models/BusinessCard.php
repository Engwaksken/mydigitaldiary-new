<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BusinessCard extends Model
{
    protected $fillable = [
        'user_id', 'slug', 'photo_path', 'logo_path', 'name', 'title', 'company', 'phone',
        'whatsapp_phone', 'email', 'website', 'address', 'bio', 'social_links', 'is_published',
        'card_color', 'card_color_secondary',
    ];

    protected $casts = [
        'social_links' => 'array',
        'is_published' => 'boolean',
    ];

    /**
     * Same two-color-gradient pattern as User::themeColor()/
     * themeColorLight() - a primary color the user picks explicitly,
     * and a secondary one that's either their own choice or an
     * auto-derived lighter shade of the primary when left blank.
     */
    public function cardColor(): string
    {
        return $this->card_color ?: '#00897B';
    }

    /**
     * Same reasoning as User::themeColorLight() - the paired default
     * (#73BEB6) only applies when NEITHER color is customized; a
     * custom primary with no custom secondary still derives a lighter
     * shade of that primary specifically, not the app's own default.
     */
    public function cardColorSecondary(): string
    {
        if ($this->card_color_secondary) {
            return $this->card_color_secondary;
        }

        if (! $this->card_color) {
            return '#73BEB6';
        }

        return $this->mixWithWhite($this->cardColor(), 0.45);
    }

    private function mixWithWhite(string $hex, float $amount): string
    {
        [$r, $g, $b] = $this->hexToRgb($hex);
        $mix = fn ($c) => (int) round($c + (255 - $c) * $amount);

        return $this->rgbToHex([$mix($r), $mix($g), $mix($b)]);
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function rgbToHex(array $rgb): string
    {
        return '#' . implode('', array_map(fn ($c) => str_pad(dechex(max(0, min(255, $c))), 2, '0', STR_PAD_LEFT), $rgb));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scans()
    {
        return $this->hasMany(BusinessCardScan::class);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /**
     * Same DomPDF-blocks-remote-images reasoning as photoDataUri() -
     * the logo needs a base64 data URI to render inside the PDF card.
     */
    public function logoDataUri(): ?string
    {
        if (! $this->logo_path || ! Storage::disk('public')->exists($this->logo_path)) {
            return null;
        }

        $contents = Storage::disk('public')->get($this->logo_path);
        $mimeType = Storage::disk('public')->mimeType($this->logo_path) ?: 'image/png';

        return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
    }

    /**
     * DomPDF blocks remote HTTP image loads by default (same reasoning
     * as SiteSetting::logoDataUri()) - this is what the PDF template
     * needs instead of photoUrl(), which only works for the public web
     * page and browsers generally.
     */
    public function photoDataUri(): ?string
    {
        if (! $this->photo_path || ! Storage::disk('public')->exists($this->photo_path)) {
            return null;
        }

        $contents = Storage::disk('public')->get($this->photo_path);
        $mimeType = Storage::disk('public')->mimeType($this->photo_path) ?: 'image/jpeg';

        return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
    }

    public function publicUrl(): string
    {
        return route('card.show', $this->slug);
    }

    /**
     * A free, no-API-key QR service (api.qrserver.com) rather than a new
     * Composer package or a hand-rolled QR encoder - the app just needs
     * an image URL, not to generate the codes itself.
     */
    public function qrCodeUrl(int $size = 300): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($this->publicUrl());
    }

    /**
     * Same DomPDF-blocks-remote-images reasoning as photoDataUri() -
     * the PDF template needs this instead of qrCodeUrl(). The fetch
     * itself happens fine since it's the SERVER making the request
     * (not DomPDF), then the result gets embedded as base64 the same
     * way a locally-stored image would be.
     */
    public function qrCodeDataUri(int $size = 300): ?string
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($this->qrCodeUrl($size));

            if (! $response->successful()) {
                return null;
            }

            return 'data:image/png;base64,' . base64_encode($response->body());
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * A unique, URL-friendly slug from the person's name - appends a
     * short random suffix on collision rather than failing the save.
     */
    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'card';
        $slug = $base;
        $attempt = 0;

        while (static::where('slug', $slug)->exists()) {
            $attempt++;
            $slug = $base . '-' . Str::lower(Str::random(4));
            if ($attempt > 20) {
                $slug = $base . '-' . uniqid();
                break;
            }
        }

        return $slug;
    }
}
