<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Site-wide, admin-managed pricing tiers — NOT scoped to a user (unlike
 * every tracking module), so this deliberately doesn't extend
 * CrudController. Prices for duration-based plans (everything except
 * lifetime) are computed from SiteSetting::monthly_price plus this plan's
 * own discount_percent, rather than stored as a raw dollar amount — so
 * changing the site's base monthly price automatically recalculates every
 * tier's price too, and an admin only ever has to think in terms of
 * "X% off," not keep four separate dollar figures in sync by hand.
 */
class SubscriptionPlan extends Model
{
    protected $fillable = [
        'key', 'name', 'category', 'duration_months', 'discount_percent', 'flat_price',
        'included_seats', 'additional_user_price', 'included_extra_recording_minutes', 'is_enabled', 'sort_order', 'features',
        'color', 'badge', 'is_recommended', 'is_best_value', 'transcription_capacity_mb',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'discount_percent' => 'decimal:2',
        'flat_price' => 'decimal:2',
        'additional_user_price' => 'decimal:2',
        'included_extra_recording_minutes' => 'integer',
        'features' => 'array',
        'is_recommended' => 'boolean',
        'is_best_value' => 'boolean',
        'transcription_capacity_mb' => 'integer',
    ];

    public function isIndividual(): bool
    {
        return $this->category === 'individual';
    }

    public function includedExtraRecordingMinutes(): int
    {
        return max(0, (int) ($this->included_extra_recording_minutes ?? 0));
    }

    public function isFamilyTeam(): bool
    {
        return $this->category === 'family_team';
    }

    public function isOrganization(): bool
    {
        return $this->category === 'organization';
    }

    /**
     * The effective per-seat rate for an organization tier — what the
     * "decreasing per-user pricing at higher tiers" requirement actually
     * shows an admin/buyer: computedPrice() divided across included_seats.
     * Not meaningful for individual plans (seats = 1, so this equals the
     * plan price itself).
     */
    public function pricePerSeat(float $monthlyPrice): float
    {
        $seats = max(1, $this->included_seats);

        return round($this->computedPrice($monthlyPrice) / $seats, 2);
    }

    /**
     * A plan with no `features` set restricts nothing — every module is
     * included by default, and an admin only narrows this down
     * deliberately per plan.
     */
    public function includesFeature(string $key): bool
    {
        return empty($this->features) || in_array($key, $this->features, true);
    }

    public function isLifetime(): bool
    {
        return is_null($this->duration_months);
    }

    /**
     * What a user actually pays for one purchase of this plan. A flat
     * price (if set) always wins, regardless of duration — that's what
     * lets an organization tier bill monthly with a fixed total instead
     * of going through the "duration x site base price" formula, which
     * only makes sense for individual plans priced as a discount off
     * that base. Lifetime plans are just the special case of a flat
     * price with no duration at all.
     */
    public function computedPrice(float $monthlyPrice): float
    {
        if (! is_null($this->flat_price)) {
            return (float) $this->flat_price;
        }

        $base = $monthlyPrice * $this->duration_months;
        $discount = (float) $this->discount_percent;

        return round($base * (1 - $discount / 100), 2);
    }

    /**
     * "20% off" style badge text, or null when there's nothing meaningful
     * to show (monthly itself, an undiscounted plan, or lifetime — which
     * isn't a percentage-off-monthly comparison at all).
     */
    public function savingsLabel(): ?string
    {
        if ($this->isLifetime() || $this->duration_months <= 1 || $this->discount_percent <= 0) {
            return null;
        }

        return number_format((float) $this->discount_percent, 0) . '% off';
    }

    /**
     * A light, ~8% tint of the plan's color for the card background —
     * computed from the hex directly (appending alpha in 8-digit hex
     * notation, which every modern browser supports) rather than
     * relying on Tailwind to generate a matching -50/-100 shade
     * dynamically. Falls back to a neutral gray if color isn't a valid
     * hex (e.g. a plan saved under the old named-color scheme that
     * hasn't been re-picked yet).
     */
    public function colorTintStyle(): string
    {
        $hex = $this->normalizedHex();

        return "background-color: {$hex}14; border-color: {$hex}40;";
    }

    public function colorSolidStyle(): string
    {
        return 'background-color: ' . $this->normalizedHex() . ';';
    }

    public function colorTextStyle(): string
    {
        return 'color: ' . $this->normalizedHex() . ';';
    }

    public function normalizedHex(): string
    {
        $color = trim((string) $this->color);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#475569';
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function recordingExtraGrants(): HasMany
    {
        return $this->hasMany(SubscriptionRecordingExtraGrant::class, 'subscription_plan_id');
    }
}
