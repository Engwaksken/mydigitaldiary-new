<?php

namespace App\Models;

use App\Services\LegalContentFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Singleton settings row (always id=1) for whole-app branding: system
 * name, logo, favicon. Managed by admins at /admin/settings, read by
 * every view via $siteSettings (shared globally in AppServiceProvider).
 */
class SiteSetting extends Model
{
    protected $table = 'site_settings';

    protected $fillable = [
        'site_name', 'monthly_price', 'trial_days', 'logo_path', 'favicon_path',
        'default_currency_code', 'default_currency_symbol', 'default_currency_decimals',
        'default_ai_provider', 'default_ai_api_key', 'default_ai_free_limit_per_month',
        'privacy_policy_content', 'privacy_policy_version', 'terms_of_use_content', 'terms_of_use_version', 'supported_currencies',
        'appreciation_weekly_enabled', 'appreciation_weekly_day', 'appreciation_weekly_time',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'default_ai_api_key' => 'encrypted',
        'trial_days' => 'integer',
        'default_ai_free_limit_per_month' => 'integer',
        'supported_currencies' => 'array',
        'appreciation_weekly_enabled' => 'boolean',
        'appreciation_weekly_day' => 'integer',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], ['site_name' => 'Personal Monitor']);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /**
     * DomPDF blocks loading images from remote HTTP URLs unless
     * `isRemoteEnabled` is explicitly turned on in its own package
     * config — a setting this app doesn't control from application code.
     * Embedding the logo as a base64 data URI sidesteps that entirely:
     * DomPDF just reads the bytes straight out of the HTML, no fetch of
     * any kind involved. Used by PDF templates specifically; email
     * templates should keep using logoUrl() instead, since most email
     * clients strip data URIs for security.
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

    public function faviconUrl(): ?string
    {
        return $this->favicon_path ? Storage::disk('public')->url($this->favicon_path) : null;
    }

    public function hasDefaultAiKey(): bool
    {
        return ! empty($this->default_ai_provider) && ! empty($this->default_ai_api_key);
    }

    /**
     * Formats an amount using the site's BASE currency — "UGX 50,000" by
     * default (0 decimals, since shilling-scale amounts rarely need
     * them), or whatever an admin has configured. Used everywhere a
     * dollar sign used to be hardcoded — see the `format_money()` global
     * helper in app/helpers.php, which just calls this on the current
     * settings so views don't need `SiteSetting::current()->formatMoney(...)`
     * spelled out everywhere.
     */
    public function formatMoney(float $amount): string
    {
        $formatted = number_format($amount, $this->default_currency_decimals);

        return trim($this->default_currency_symbol . ' ' . $formatted);
    }

    /**
     * Resolve display metadata for a requested currency. Additional currency
     * rates are stored as BASE-currency units per 1 foreign unit. Example:
     * USD rate 3700 means 1 USD = UGX 3,700.
     */
    public function currencyMeta(?string $code): array
    {
        $code = strtoupper(trim((string) $code));
        $base = strtoupper($this->default_currency_code ?: 'UGX');

        if ($code === '' || $code === $base) {
            return [
                'code' => $base,
                'symbol' => $this->default_currency_symbol ?: $base,
                'rate' => 1.0,
                'decimals' => (int) ($this->default_currency_decimals ?? 0),
            ];
        }

        foreach ($this->supported_currencies ?? [] as $currency) {
            if (strtoupper((string) ($currency['code'] ?? '')) === $code) {
                return [
                    'code' => $code,
                    'symbol' => $currency['symbol'] ?: $code,
                    'rate' => max(0.0000001, (float) ($currency['rate'] ?? 1)),
                    'decimals' => isset($currency['decimals']) ? (int) $currency['decimals'] : 2,
                ];
            }
        }

        return [
            'code' => $base,
            'symbol' => $this->default_currency_symbol ?: $base,
            'rate' => 1.0,
            'decimals' => (int) ($this->default_currency_decimals ?? 0),
        ];
    }

    public function convertBaseAmount(float $amount, ?string $targetCode): float
    {
        $meta = $this->currencyMeta($targetCode);

        return $meta['rate'] > 0 ? $amount / $meta['rate'] : $amount;
    }

    public function formatMoneyForUser(float $amount, ?User $user = null): string
    {
        $meta = $this->currencyMeta($user?->preferredCurrencyCode());
        $converted = $this->convertBaseAmount($amount, $meta['code']);

        return trim($meta['symbol'] . ' ' . number_format($converted, $meta['decimals']));
    }

    public function currencyOptions(): array
    {
        $options = [];
        $seen = [];

        $push = function (array $entry) use (&$options, &$seen) {
            $code = strtoupper(trim((string) ($entry['code'] ?? '')));
            if ($code === '' || isset($seen[$code])) {
                return;
            }
            $seen[$code] = true;
            $entry['code'] = $code;
            $options[] = $entry;
        };

        $push([
            'code' => strtoupper($this->default_currency_code ?: 'UGX'),
            'symbol' => $this->default_currency_symbol ?: 'UGX',
            'rate' => 1.0,
        ]);

        foreach ($this->supported_currencies ?? [] as $currency) {
            if (! empty($currency['code']) && (float) ($currency['rate'] ?? 0) > 0) {
                $push($currency);
            }
        }

        return $options;
    }

    /**
     * For displaying a SPECIFIC record's own stored amount+currency
     * (a Payment, Invoice, etc.) — NOT the same as formatMoney(), which
     * always uses the site's CURRENT default currency regardless of
     * what currency that record actually recorded. An old payment made
     * back when the site's default was USD would be silently and
     * incorrectly relabeled as UGX by formatMoney() if the site has
     * since switched — this looks up the right symbol for whatever
     * currency the record itself says it's in, falling back to just
     * showing the currency code when it's not the site's own default or
     * one of the additional supported ones with a configured symbol.
     */
    public function formatMoneyInCurrency(float $amount, ?string $currencyCode): string
    {
        if (! $currencyCode || strcasecmp($currencyCode, $this->default_currency_code) === 0) {
            return $this->formatMoney($amount);
        }

        foreach ($this->supported_currencies ?? [] as $currency) {
            if (strcasecmp($currency['code'] ?? '', $currencyCode) === 0) {
                return trim(($currency['symbol'] ?? $currencyCode) . ' ' . number_format($amount, $this->default_currency_decimals));
            }
        }

        return trim($currencyCode . ' ' . number_format($amount, 2));
    }

    /**
     * Returns the admin-edited content if one has been saved, otherwise a
     * sensible starting default — so the /privacy-policy page never shows
     * blank just because an admin hasn't touched Settings yet. Stored and
     * rendered through LegalContentFormatter, which permits only the small
     * structural tag set documented in Admin Settings.
     */
    public function privacyPolicyContent(): string
    {
        return $this->privacy_policy_content ?: <<<'TEXT'
Privacy Policy

Effective date: 16 August 2026

1. Information we store

My Digital Diary stores the information you choose to enter or upload while using the application. Depending on the features you use, this may include your profile information; daily and annual plans; tasks and reminders; income, budgets, expenses, savings and debt records; diet, exercise, sleep and health information; projects and education plans; meetings, notes and recordings; signatures and signed documents; business-card information; spiritual-growth entries; network contacts; relationship notes; support conversations; subscription information; and other content you create in the app.

Some of this information may be sensitive, including financial information, health-related information, personal reflections and relationship information. You decide which optional information to enter into the service.

2. How we use your information

We use your information to provide and improve the features you choose to use. This includes displaying dashboards and reports, calculating summaries and progress, sending reminders and notifications, generating documents and exports, syncing supported calendar services when you connect them, maintaining backups where configured, providing customer support, processing subscriptions and payments, and protecting the security and reliability of the service.

We do not sell your personal information to advertisers. We do not use the contents of your diary for third-party advertising.

3. AI Planner, Today's Insight and AI features

When you use an AI-powered feature, relevant summaries of your information may be sent to the selected AI provider so the requested output can be generated. Depending on the configuration of your account and the service, this may use an API credential you provide or an AI service configured by the application administrator.

AI Planner generation happens when you request a plan or recommendation. Other enabled AI-assisted features, such as rotating dashboard insights or meeting summaries, may run automatically when their related feature is enabled or scheduled. We limit the context sent to what is reasonably relevant to the requested feature and do not intentionally include passwords, authentication tokens or API secrets in AI prompts.

AI providers process submitted information under their own terms and privacy practices. You should avoid entering information into an AI request that you do not want processed by the selected provider.

4. Connected services and service providers

Some features depend on third-party services. These may include payment processors, email and push-notification providers, cloud-storage or backup providers, connected calendar platforms, AI providers and hosting infrastructure. We share only the information reasonably needed to provide the feature you request or to operate the service.

When you connect an external calendar or similar service, the application accesses only the permissions you authorize and uses the connection to provide the related feature. You can disconnect supported integrations from the application where that option is available.

5. Data reports and exports

You can request a personal data report from Privacy & Data in your account. You can choose the reason for the report, the modules to include and the relevant date range. Where enabled, the completed report is generated and sent to your registered email address, and request history may be retained for account management and security purposes.

6. Account deletion and retention

You can request deletion of your account from Privacy & Data. After confirmation, your account is scheduled for permanent deletion after a 30-day grace period. During that period, you may be able to cancel the deletion request and restore normal account status.

Where selected, the service can prepare a backup/export before the deletion countdown is recorded. After the grace period expires, eligible account data is permanently deleted and cannot be restored through the application. Some limited information may be retained where reasonably necessary for security, fraud prevention, dispute resolution, financial record-keeping, or other legal and operational obligations.

7. Security and backups

We use reasonable technical and organizational measures intended to protect application data. No online service can guarantee absolute security. Administrators may configure encrypted or access-controlled backups, including local or cloud storage, to support service recovery. Backup retention depends on the configured retention policy.

8. Your choices and rights

From your account, you can:

- Review and update supported profile information.
- Request customized exports of your personal data.
- Manage supported notification and connected-service settings.
- Request account deletion.
- Cancel a scheduled deletion during the available 30-day grace period.
- Contact us with privacy questions or requests that cannot be completed directly in the application.

9. Changes to this policy

We may update this Privacy Policy as the service changes. The current version is displayed on this page. Material changes should be reflected by updating the policy version and content in the application.

10. Contact

Email: info@digitaldiary.com
Tel: +256 784675790
WhatsApp: +256 704145972
TEXT;
    }

    public function privacyPolicyHtml(): string
    {
        return app(LegalContentFormatter::class)->render($this->privacyPolicyContent());
    }

    public function termsOfUseContent(): string
    {
        return $this->terms_of_use_content ?: <<<'TEXT'
Terms of Use

Effective date: 16 August 2026

1. Acceptance of these terms

By creating an account or using My Digital Diary, you agree to these Terms of Use and the Privacy Policy. If you do not agree, do not create an account or continue using the service.

2. Your account

You are responsible for providing accurate account information, keeping your password and verification methods secure, and for activity performed through your account. Notify support if you believe your account has been accessed without permission.

3. Personal use and acceptable use

You may use the service to manage your personal or authorized organizational information and the tools made available to your account. You must not use the service to break the law, infringe another person's rights, distribute malicious software, attempt unauthorized access, interfere with the service, abuse support channels, or upload content that you do not have the right to use.

4. Your content

You keep ownership of the content you enter or upload. You give the service permission to store, process, back up, transform and transmit that content only as reasonably necessary to provide the features you use, such as reports, reminders, signatures, calendar synchronization, AI-assisted tools and data exports.

You are responsible for ensuring that information you upload about other people is collected and used appropriately and that you have any permissions required to store or process it.

5. AI-generated content

AI Planner, Today's Insight, meeting summaries and other AI-assisted outputs may be incomplete, inaccurate or unsuitable for your circumstances. AI output is provided as assistance and should be reviewed before you rely on it or act on it.

6. Financial, health and other guidance

The application may display budgeting information, financial summaries, wellness suggestions, productivity recommendations and other guidance. These features are informational tools and are not a substitute for professional financial, medical, legal, tax or other regulated advice. You remain responsible for decisions made using information from the service.

7. Trials, subscriptions and billing

Some features may require an active trial or paid subscription. Prices, billing periods, included users and available features are shown before purchase. Unless a payment flow expressly states otherwise, charges are made according to the billing option you select.

You are responsible for maintaining a valid payment method where required. Access to paid functionality may be limited after a trial or subscription expires. Renewal reminders may be sent before expiry. Enterprise or organization plans may have additional written commercial terms.

8. Third-party services

The service may connect with or rely on third-party providers, including AI providers, payment processors, calendars, email, push notifications, cloud storage and hosting services. Third-party services are governed by their own terms and may change or become unavailable independently of My Digital Diary.

9. Availability and changes

We aim to keep the service available and reliable, but uninterrupted operation is not guaranteed. Features may be updated, improved, replaced, limited or discontinued where reasonably necessary for security, maintenance, legal compliance or product development.

10. Account suspension or termination

We may restrict or suspend access where reasonably necessary to protect users or the service, address misuse, investigate security issues, enforce these terms, or comply with legal obligations. You may request deletion of your account through Privacy & Data, subject to the deletion process described in the Privacy Policy.

11. Backups and exports

The service may provide account exports and administrator-configured backups. You should keep copies of information that is especially important to you. Backup availability, frequency and retention depend on the applicable account and system configuration.

12. Limitation of responsibility

To the extent permitted by applicable law, the service is provided on an "as available" basis. We are not responsible for losses caused solely by inaccurate user-provided information, third-party service failures, unsupported uses of the application, or decisions made without independently reviewing AI-generated or informational output. Nothing in these terms excludes rights or responsibilities that cannot lawfully be excluded.

13. Changes to these terms

We may update these Terms of Use as the service changes. The current version is displayed on this page. Continued use after an updated version becomes effective means you accept the updated terms where permitted by applicable law.

14. Contact

Email: info@digitaldiary.com
Tel: +256 784675790
WhatsApp: +256 704145972
TEXT;
    }

    public function termsOfUseHtml(): string
    {
        return app(LegalContentFormatter::class)->render($this->termsOfUseContent());
    }
}
