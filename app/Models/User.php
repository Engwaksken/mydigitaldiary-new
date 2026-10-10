<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function extraRequests()
    {
        return $this->hasMany(UserExtraRequest::class);
    }

    public function subscriptionRecordingExtraGrants()
    {
        return $this->hasMany(SubscriptionRecordingExtraGrant::class);
    }

    /**
     * Seeded once at self-registration (web AND mobile — see
     * RegisteredUserController and Api\AuthController), not for
     * admin-created accounts, matching the same "defaults a user chooses
     * for themselves, not decided on their behalf" reasoning already
     * applied to data consent. Uses the existing Reminder recurring
     * infrastructure (every_n_minutes) rather than a separate scheduled
     * command — once created, reminders:send handles it exactly like any
     * other recurring reminder, including rescheduling itself forward.
     * A user can edit or delete it like any other reminder afterward.
     */
    public function createDefaultHydrationReminder(): void
    {
        if (! $this->hydration_reminders_enabled) {
            return;
        }

        $this->reminders()->create([
            'title' => 'Drink water',
            'module' => 'health',
            'message' => "Time to hydrate — drink a glass of water. You can turn this off any time in Reminders.",
            'frequency' => 'every_n_minutes',
            'interval_minutes' => 120,
            'next_run_at' => now()->addMinutes(120),
            'channel' => 'mail',
            'is_active' => true,
            'alarm_enabled' => true,
        ]);
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'data_consent_at',
        'data_consent_version',
        'role',
        'suspended_at',
        'avatar_path',
        'theme_color',
        'theme_color_secondary',
        'font_family',
        'font_size',
        'alarms_muted',
        'daily_digest_enabled',
        'hydration_reminders_enabled',
        'subscription_plan_id',
        'subscription_expires_at',
        'extra_recording_quota_minutes',
        'extra_quota_expires_at',
        'last_expiry_reminder_days',
        'last_expiry_reminder_at',
        'preferred_currency_code',
        'deletion_reason', 'deletion_requested_at', 'scheduled_deletion_at', 'timezone',
        'ai_data_permissions', 'onboarding_focuses', 'onboarding_completed_at', 'engagement_notification_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'trial_ends_at' => 'datetime',
            'subscribed_at' => 'datetime',
            'subscription_started_at' => 'datetime',
            'data_consent_at' => 'datetime',
            'suspended_at' => 'datetime',
            'alarms_muted' => 'boolean',
            'daily_digest_enabled' => 'boolean',
            'hydration_reminders_enabled' => 'boolean',
            'subscription_expires_at' => 'datetime',
            'extra_recording_quota_minutes' => 'integer',
            'extra_quota_expires_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'scheduled_deletion_at' => 'datetime',
            'last_expiry_reminder_at' => 'datetime',
            'ai_data_permissions' => 'array',
            'onboarding_focuses' => 'array',
            'onboarding_completed_at' => 'datetime',
            'engagement_notification_preferences' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (! $user->trial_ends_at) {
                // Admin-configurable at /admin/settings ("Trial period"),
                // defaulting to 30 if the setting is somehow missing —
                // was hardcoded to exactly one month before.
                $trialDays = SiteSetting::current()->trial_days ?: 30;
                $user->trial_ends_at = now()->addDays($trialDays);
            }
        });

        static::deleting(function (User $user) {
            Meeting::preserveExternalClassificationForUser($user);
        });
    }

    /**
     * Whether this user currently has paid/trial access to the dashboard
     * and all tracking modules. Used by the `subscribed` route middleware.
     *
     * Admins always pass — they're staff managing the platform, not
     * customers of it, so they shouldn't need a trial/subscription to
     * reach the admin area. Suspended users always fail, regardless of
     * subscription status — suspension is a hard stop.
     *
     * "Active" also checks subscription_expires_at live here, rather than
     * needing a separate scheduled job to flip status to "expired" — a
     * duration-based plan (everything except lifetime) simply stops
     * granting access the moment its term is up, checked fresh on every
     * request. subscription_expires_at is null for the lifetime plan
     * (never expires) and for anyone who hasn't completed a paid term yet.
     */
    public function hasActiveAccess(): bool
    {
        if ($this->isSuspended()) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        // A non-owner org member's access comes from the ORGANIZATION's
        // subscription, not their own — they were never the one who
        // paid, regardless of whether they're plain staff or an
        // org-level admin promoted to manage seats. The owner still goes
        // through the normal checks below (their OWN subscription_status
        // /subscription_expires_at are set directly by the payment flow,
        // same as any individual subscriber) — checked by identity, not
        // role, and guarded against ever recursing into itself.
        if ($this->organization_id) {
            $organization = $this->organization;
            $isOwner = $organization && $organization->owner_user_id === $this->id;

            if ($organization && ! $isOwner) {
                $membership = $this->organizationMembership();

                return $membership
                    && $membership->status === 'active'
                    && $organization->owner
                    && $organization->owner->hasActiveAccess();
            }
        }

        if ($this->subscription_status === 'active') {
            return is_null($this->subscription_expires_at) || $this->subscription_expires_at->isFuture();
        }

        return $this->onTrial();
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function organizationMembership()
    {
        return OrganizationMember::where('organization_id', $this->organization_id)
            ->where('user_id', $this->id)
            ->first();
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function availableExtraRecordingQuotaMinutes(): int
    {
        if ($this->extra_quota_expires_at && $this->extra_quota_expires_at->isPast()) {
            return 0;
        }

        return max(0, (int) $this->extra_recording_quota_minutes);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSuspended(): bool
    {
        return ! is_null($this->suspended_at);
    }

    public function onTrial(): bool
    {
        return in_array($this->subscription_status, ['trial', 'trialing'], true)
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    public function trialDaysLeft(): int
    {
        if (! $this->onTrial()) {
            return 0;
        }

        return (int) floor(now()->diffInHours($this->trial_ends_at) / 24) + 1;
    }

    /**
     * Whichever expiry date is actually relevant to THIS user's current
     * status — trial_ends_at while trialing, subscription_expires_at
     * once they've paid. Null for a lifetime plan (never expires) or an
     * admin. Used by the expiry-reminder command so it doesn't need to
     * separately handle "which date matters" for every user.
     */
    public function relevantExpiryDate(): ?\Illuminate\Support\Carbon
    {
        if (in_array($this->subscription_status, ['trial', 'trialing'], true)) {
            return $this->trial_ends_at;
        }

        if ($this->subscription_status === 'active') {
            return $this->subscription_expires_at; // null = lifetime plan, never expires
        }

        return null;
    }

    public function engagementNotificationPreferences(): array
    {
        $defaults = [
            'goal_progress' => true,
            'monthly_review' => true,
            'finance_insights' => true,
            'productivity_nudges' => true,
            'spiritual_insights' => true,
            'subscription_reminders' => true,
            'daily_affirmations' => true,
        ];

        return array_replace($defaults, is_array($this->engagement_notification_preferences) ? $this->engagement_notification_preferences : []);
    }

    public function engagementNotificationEnabled(string $key): bool
    {
        return (bool) ($this->engagementNotificationPreferences()[$key] ?? false);
    }

    public function apiCredentials()
    {
        return $this->hasMany(ApiCredential::class);
    }

    public function activeApiCredential(): ?ApiCredential
    {
        return $this->apiCredentials()->where('is_active', true)->first();
    }

    public function aiPlans()
    {
        return $this->hasMany(AiPlan::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function hasGivenDataConsent(): bool
    {
        return ! is_null($this->data_consent_at);
    }

    /**
     * Personal accent color system. A user can independently set BOTH
     * gradient colors (theme_color + theme_color_secondary) — the
     * second one falls back to an auto-derived lighter version of the
     * first only when the user hasn't picked their own, so an existing
     * user who's only ever set theme_color keeps their current look
     * with no data migration needed. Hover states and translucent
     * tints stay fully derived either way, from whichever value
     * themeColor()/themeColorLight() actually return. Consumed via CSS
     * custom properties injected in layouts/app.blade.php's <head>,
     * e.g. `bg-[var(--brand-1)]`.
     */
    public function themeColor(): string
    {
        return $this->theme_color ?: '#00897B';
    }

    /**
     * Uses the specific paired default (#73BEB6) only when the user
     * hasn't customized EITHER color — those two were chosen together
     * as the app's own default look. If they've set a custom primary
     * but left secondary blank, deriving a lighter shade of THEIR
     * color is still correct (matches their choice); jumping straight
     * to the app's own default secondary there would look mismatched.
     */
    public function themeColorLight(): string
    {
        if ($this->theme_color_secondary) {
            return $this->theme_color_secondary;
        }

        if (! $this->theme_color) {
            return '#73BEB6';
        }

        return $this->mixWithWhite($this->themeColor(), 0.45);
    }

    public function themeColorDark(): string
    {
        return $this->darken($this->themeColor(), 0.78);
    }

    public function themeColorLightDark(): string
    {
        return $this->darken($this->themeColorLight(), 0.85);
    }

    public function themeColorTint(float $alpha): string
    {
        return $this->toRgba($this->themeColor(), $alpha);
    }

    public function themeColorLightTint(float $alpha): string
    {
        return $this->toRgba($this->themeColorLight(), $alpha);
    }

    /**
     * A short, curated list rather than any arbitrary string — mobile
     * only ships a handful of actual font families bundled with the
     * app (see AppTheme on the Flutter side), so anything outside
     * this list wouldn't actually render as anything different.
     */
    public const FONT_FAMILIES = ['System', 'Roboto', 'Poppins', 'Lato', 'Merriweather'];

    public function fontFamily(): string
    {
        return in_array($this->font_family, self::FONT_FAMILIES, true) ? $this->font_family : 'Lato';
    }

    /**
     * A scale factor as a percentage (100 = normal), not a raw point
     * size — this multiplies every text size in the app rather than
     * setting one fixed size, so headings/body/labels all stay
     * proportional to each other instead of all becoming identical.
     */
    public function fontSize(): int
    {
        $size = (int) ($this->font_size ?? 100);

        return max(80, min(130, $size));
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            $hex = '00897B'; // fall back to the site default if malformed
        }

        return array_map('hexdec', str_split($hex, 2));
    }

    private function rgbToHex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', ...$rgb);
    }

    private function mixWithWhite(string $hex, float $amount): string
    {
        [$r, $g, $b] = $this->hexToRgb($hex);
        $mix = fn ($c) => (int) round($c + (255 - $c) * $amount);

        return $this->rgbToHex([$mix($r), $mix($g), $mix($b)]);
    }

    private function darken(string $hex, float $factor): string
    {
        [$r, $g, $b] = $this->hexToRgb($hex);
        $scale = fn ($c) => (int) round($c * $factor);

        return $this->rgbToHex([$scale($r), $scale($g), $scale($b)]);
    }

    private function toRgba(string $hex, float $alpha): string
    {
        [$r, $g, $b] = $this->hexToRgb($hex);

        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar_path)
            : null;
    }

    public function signatures()
    {
        return $this->hasMany(Signature::class);
    }

    /** Single-letter fallback shown when no avatar has been uploaded. */
    public function initial(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1));
    }

    /** Preferred display currency; falls back to the site's base currency. */
    public function preferredCurrencyCode(): string
    {
        return strtoupper(
            $this->preferred_currency_code
            ?: SiteSetting::current()->default_currency_code
            ?: 'UGX'
        );
    }

    public function aiDataPermissions(): array
    {
        $defaults = ['planning','finance','goals','health','wellbeing','spiritual','notes','meetings','network','education','relationships'];
        $stored = $this->ai_data_permissions;

        return is_array($stored) ? array_values(array_intersect($defaults, $stored)) : $defaults;
    }

    public function aiAllows(string $module): bool
    {
        return in_array($module, $this->aiDataPermissions(), true);
    }

}
