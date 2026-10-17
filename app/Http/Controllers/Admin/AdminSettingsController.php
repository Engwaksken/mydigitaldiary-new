<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Models\BackupHistory;
use App\Models\BackupSetting;
use App\Models\CurrencySetting;
use App\Models\MeetingPlatformConfig;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Ai\ActiveAiClient;
use App\Services\LegalContentFormatter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class AdminSettingsController extends Controller
{
    /**
     * Display the main administration settings page.
     *
     * Optional settings modules are loaded defensively so one missing
     * migration/table cannot take down the entire /admin/settings page.
     */
    public function edit(): View
    {
        $settings = SiteSetting::current();

        /*
        |--------------------------------------------------------------------------
        | AI Providers
        |--------------------------------------------------------------------------
        */
        $aiProviders = collect();

        try {
            if (Schema::hasTable('ai_providers')) {
                $aiProviders = AiProvider::query()
                    ->orderBy('sort_order')
                    ->get();
            }
        } catch (Throwable $e) {
            Log::warning(
                'Could not load AI providers on admin settings.',
                [
                    'error' => $e->getMessage(),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Meeting Platforms
        |--------------------------------------------------------------------------
        */
        $meetingPlatforms = collect();

        try {
            if (Schema::hasTable('meeting_platform_configs')) {
                $meetingPlatforms = MeetingPlatformConfig::query()
                    ->orderBy('platform')
                    ->get();
            }
        } catch (Throwable $e) {
            Log::warning(
                'Could not load meeting platform settings.',
                [
                    'error' => $e->getMessage(),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Backup Settings
        |--------------------------------------------------------------------------
        */
        $backupSetting = null;

        try {
            if (Schema::hasTable('backup_settings')) {
                $backupSetting = BackupSetting::current();
            }
        } catch (Throwable $e) {
            Log::warning(
                'Could not load backup settings.',
                [
                    'error' => $e->getMessage(),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Backup History
        |--------------------------------------------------------------------------
        */
        $backupHistory = collect();

        try {
            if (Schema::hasTable('backup_histories')) {
                $query = BackupHistory::query();

                /*
                 * Load creator only where the relationship can be used.
                 * If the relationship itself has a problem, the catch keeps
                 * the main Settings page available.
                 */
                try {
                    $query->with('creator');
                } catch (Throwable) {
                    // History can still be displayed without creator data.
                }

                $backupHistory = $query
                    ->latest()
                    ->limit(20)
                    ->get();
            }
        } catch (Throwable $e) {
            Log::warning(
                'Could not load backup history.',
                [
                    'error' => $e->getMessage(),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Currency Settings
        |--------------------------------------------------------------------------
        |
        | SiteSetting remains the primary source for the existing Laravel
        | application.
        |
        | CurrencySetting powers the new live-rate/mobile currency feature.
        |
        | Both are passed to the view so the UI can clearly distinguish:
        |
        | - base/default system currency;
        | - live-rate display currency;
        | - supported currencies.
        |
        */
        $currencySetting = null;

        try {
            if (Schema::hasTable('currency_settings')) {
                $currencySetting = CurrencySetting::current();
            }
        } catch (Throwable $e) {
            Log::warning(
                'Could not load live currency settings.',
                [
                    'error' => $e->getMessage(),
                ]
            );
        }

        return view(
            'admin.settings.edit',
            [
                'settings' => $settings,

                'aiProviders' => $aiProviders,

                'meetingPlatforms' =>
                    $meetingPlatforms,

                'backupSetting' =>
                    $backupSetting,

                'backupHistory' =>
                    $backupHistory,

                'currencySetting' =>
                    $currencySetting,
            ]
        );
    }

    /**
     * Update general application settings.
     */
    public function update(
        Request $request
    ): RedirectResponse {
        $data = $request->validate([
            /*
            |--------------------------------------------------------------------------
            | General
            |--------------------------------------------------------------------------
            */
            'site_name' => [
                'required',
                'string',
                'max:255',
            ],

            'monthly_price' => [
                'required',
                'numeric',
                'min:0',
            ],

            /*
            |--------------------------------------------------------------------------
            | Currency
            |--------------------------------------------------------------------------
            */
            'default_currency_code' => [
                'required',
                'string',
                'size:3',
            ],

            'default_currency_symbol' => [
                'required',
                'string',
                'max:10',
            ],

            'default_currency_decimals' => [
                'required',
                'integer',
                'min:0',
                'max:4',
            ],

            'currency_codes' => [
                'nullable',
                'array',
            ],

            'currency_codes.*' => [
                'nullable',
                'string',
                'size:3',
            ],

            'currency_symbols' => [
                'nullable',
                'array',
            ],

            'currency_symbols.*' => [
                'nullable',
                'string',
                'max:10',
            ],

            'currency_rates' => [
                'nullable',
                'array',
            ],

            'currency_rates.*' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            /*
            |--------------------------------------------------------------------------
            | Trial
            |--------------------------------------------------------------------------
            */
            'trial_days' => [
                'required',
                'integer',
                'min:0',
                'max:365',
            ],

            /*
            |--------------------------------------------------------------------------
            | Branding
            |--------------------------------------------------------------------------
            */
            'logo' => [
                'nullable',
                'image',
                'max:1024',
            ],

            'favicon' => [
                'nullable',
                'image',
                'max:512',
            ],

            /*
            |--------------------------------------------------------------------------
            | AI
            |--------------------------------------------------------------------------
            */
            'default_ai_provider' => [
                'nullable',
                'string',
            ],

            'default_ai_api_key' => [
                'nullable',
                'string',
            ],

            'default_ai_free_limit_per_month' => [
                'required',
                'integer',
                'min:0',
                'max:1000',
            ],

            /*
            |--------------------------------------------------------------------------
            | Privacy
            |--------------------------------------------------------------------------
            */
            'privacy_policy_content' => [
                'nullable',
                'string',
            ],

            'privacy_policy_version' => [
                'nullable',
                'string',
                'max:20',
            ],

            /*
            |--------------------------------------------------------------------------
            | Terms
            |--------------------------------------------------------------------------
            */
            'terms_of_use_content' => [
                'nullable',
                'string',
            ],

            'terms_of_use_version' => [
                'nullable',
                'string',
                'max:20',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Cast form values
        |--------------------------------------------------------------------------
        */
        $data['trial_days'] =
            (int) $data['trial_days'];

        $data['default_currency_decimals'] =
            (int) $data[
                'default_currency_decimals'
            ];

        $data['default_ai_free_limit_per_month'] =
            (int) $data[
                'default_ai_free_limit_per_month'
            ];

        $data['monthly_price'] =
            (float) $data['monthly_price'];

        /*
        |--------------------------------------------------------------------------
        | Site Settings
        |--------------------------------------------------------------------------
        */
        $settings =
            SiteSetting::current();

        $oldTrialDays =
            (int) ($settings->trial_days ?? 0);

        $settings->site_name =
            trim($data['site_name']);

        $settings->monthly_price =
            $data['monthly_price'];

        $settings->default_currency_code =
            strtoupper(
                trim(
                    $data[
                        'default_currency_code'
                    ]
                )
            );

        $settings->default_currency_symbol =
            trim(
                $data[
                    'default_currency_symbol'
                ]
            );

        $settings->default_currency_decimals =
            $data[
                'default_currency_decimals'
            ];

        $settings->trial_days =
            $data['trial_days'];

        /*
        |--------------------------------------------------------------------------
        | AI Provider
        |--------------------------------------------------------------------------
        */
        if (
            filled(
                $data[
                    'default_ai_provider'
                ]
                ?? null
            )
        ) {
            $providerKey =
                trim(
                    (string) $data[
                        'default_ai_provider'
                    ]
                );

            /*
             * Only accept an existing provider where that table exists.
             */
            if (
                ! Schema::hasTable(
                    'ai_providers'
                )
                || AiProvider::query()
                    ->where(
                        'key',
                        $providerKey
                    )
                    ->exists()
            ) {
                $settings->default_ai_provider =
                    $providerKey;
            }
        }

        $settings->default_ai_free_limit_per_month =
            $data[
                'default_ai_free_limit_per_month'
            ];

        if (
            filled(
                $data[
                    'default_ai_api_key'
                ]
                ?? null
            )
        ) {
            $settings->default_ai_api_key =
                trim(
                    $data[
                        'default_ai_api_key'
                    ]
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Privacy Policy
        |--------------------------------------------------------------------------
        */
        if (
            array_key_exists(
                'privacy_policy_content',
                $data
            )
        ) {
            $settings->privacy_policy_content =
                filled(
                    $data[
                        'privacy_policy_content'
                    ]
                    ?? null
                )
                    ? app(LegalContentFormatter::class)->sanitize(trim(
                        $data[
                            'privacy_policy_content'
                        ]
                    ))
                    : null;
        }

        $settings->privacy_policy_version =
            filled(
                $data[
                    'privacy_policy_version'
                ]
                ?? null
            )
                ? trim(
                    $data[
                        'privacy_policy_version'
                    ]
                )
                : (
                    $settings
                        ->privacy_policy_version
                    ?: '1.0'
                );

        /*
        |--------------------------------------------------------------------------
        | Terms of Use
        |--------------------------------------------------------------------------
        */
        if (
            array_key_exists(
                'terms_of_use_content',
                $data
            )
        ) {
            $settings->terms_of_use_content =
                filled(
                    $data[
                        'terms_of_use_content'
                    ]
                    ?? null
                )
                    ? app(LegalContentFormatter::class)->sanitize(trim(
                        $data[
                            'terms_of_use_content'
                        ]
                    ))
                    : null;
        }

        $settings->terms_of_use_version =
            filled(
                $data[
                    'terms_of_use_version'
                ]
                ?? null
            )
                ? trim(
                    $data[
                        'terms_of_use_version'
                    ]
                )
                : (
                    $settings
                        ->terms_of_use_version
                    ?: '1.0'
                );

        /*
        |--------------------------------------------------------------------------
        | Supported Currencies
        |--------------------------------------------------------------------------
        |
        | Preserve the existing SiteSetting structure:
        |
        | [
        |   [
        |       'code'   => 'USD',
        |       'symbol' => '$',
        |       'rate'   => 0.00027,
        |   ],
        | ]
        |
        */
        $currencies = [];

        $codes =
            $data[
                'currency_codes'
            ]
            ?? [];

        $symbols =
            $data[
                'currency_symbols'
            ]
            ?? [];

        $rates =
            $data[
                'currency_rates'
            ]
            ?? [];

        foreach (
            $codes as $i => $code
        ) {
            $code =
                strtoupper(
                    trim(
                        (string) $code
                    )
                );

            if ($code === '') {
                continue;
            }

            $rate =
                $rates[$i]
                ?? null;

            /*
             * Do not use empty() here because valid numeric zero would be
             * incorrectly treated as missing.
             */
            if (
                $rate === null
                || $rate === ''
            ) {
                continue;
            }

            $symbol =
                trim(
                    (string) (
                        $symbols[$i]
                        ?? $code
                    )
                );

            $currencies[] = [
                'code' => $code,

                'symbol' =>
                    $symbol !== ''
                        ? $symbol
                        : $code,

                'rate' =>
                    (float) $rate,
            ];
        }

        /*
         * Always make sure the default/base currency exists with rate 1.
         */
        $defaultCode =
            $settings
                ->default_currency_code;

        $hasDefault =
            collect($currencies)
                ->contains(
                    fn (array $currency): bool =>
                        strtoupper(
                            (string) (
                                $currency['code']
                                ?? ''
                            )
                        )
                        === $defaultCode
                );

        if (! $hasDefault) {
            array_unshift(
                $currencies,
                [
                    'code' =>
                        $defaultCode,

                    'symbol' =>
                        $settings
                            ->default_currency_symbol,

                    'rate' => 1.0,
                ]
            );
        }

        $settings->supported_currencies =
            $currencies;

        /*
        |--------------------------------------------------------------------------
        | Branding uploads
        |--------------------------------------------------------------------------
        */
        if (
            $request->hasFile(
                'logo'
            )
        ) {
            if (
                filled(
                    $settings->logo_path
                )
            ) {
                Storage::disk('public')
                    ->delete(
                        $settings->logo_path
                    );
            }

            $settings->logo_path =
                $request
                    ->file('logo')
                    ->store(
                        'branding',
                        'public'
                    );
        }

        if (
            $request->hasFile(
                'favicon'
            )
        ) {
            if (
                filled(
                    $settings->favicon_path
                )
            ) {
                Storage::disk('public')
                    ->delete(
                        $settings->favicon_path
                    );
            }

            $settings->favicon_path =
                $request
                    ->file('favicon')
                    ->store(
                        'branding',
                        'public'
                    );
        }

        $settings->save();
        // SiteSetting::current() is database-backed, but reload this instance
        // so the encrypted shared key is immediately available in this request.
        $settings->refresh();

        /*
        |--------------------------------------------------------------------------
        | Synchronise live currency settings
        |--------------------------------------------------------------------------
        |
        | This prevents SiteSetting and CurrencySetting from disagreeing.
        |
        */
        try {
            if (
                Schema::hasTable(
                    'currency_settings'
                )
            ) {
                $currencySetting =
                    CurrencySetting::current();

                $currencySetting->base_currency =
                    $settings
                        ->default_currency_code;

                /*
                 * Preserve the administrator's current display-currency
                 * choice unless it is blank.
                 */
                if (
                    blank(
                        $currencySetting
                            ->display_currency
                    )
                ) {
                    $currencySetting
                        ->display_currency =
                        $settings
                            ->default_currency_code;
                }

                $currencySetting->save();
            }
        } catch (Throwable $e) {
            /*
             * Saving the main site settings should not fail just because the
             * optional live exchange-rate module has a problem.
             */
            Log::warning(
                'Site settings saved but live currency settings could not be synchronised.',
                [
                    'error' =>
                        $e->getMessage(),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Update existing trial users
        |--------------------------------------------------------------------------
        */
        $updatedTrialCount = 0;

        if (
            $oldTrialDays
            !==
            $data['trial_days']
        ) {
            User::query()
                ->where(
                    'subscription_status',
                    'trialing'
                )
                ->chunkById(
                    100,
                    function (
                        $users
                    ) use (
                        $data,
                        &$updatedTrialCount
                    ): void {
                        foreach (
                            $users as $user
                        ) {
                            /*
                             * If an old user does not have created_at for any
                             * reason, avoid crashing the whole settings save.
                             */
                            if (
                                ! $user
                                    ->created_at
                            ) {
                                continue;
                            }

                            $user
                                ->trial_ends_at =
                                $user
                                    ->created_at
                                    ->copy()
                                    ->addDays(
                                        $data[
                                            'trial_days'
                                        ]
                                    );

                            $user->save();

                            $updatedTrialCount++;
                        }
                    }
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */
        $message = $request->input('settings_section') === 'ai'
            ? 'AI configuration updated successfully.'
            : 'Site settings updated successfully.';

        if (
            $updatedTrialCount > 0
        ) {
            $message .=
                " Trial period updated for {$updatedTrialCount} user(s) currently on trial.";
        }

        return redirect()
            ->route(
                'admin.settings.edit'
            )
            ->with(
                'success',
                $message
            );
    }

    public function testAiConnection(Request $request): RedirectResponse
    {
        try {
            app(ActiveAiClient::class)->testConnection();

            $note = '';
            if ($this->usesPersonalOpenAiKey($request->user())) {
                $note = ' Note: your profile has an active personal API key, which takes priority over the shared key for your own requests.';
            }

            return redirect()
                ->route('admin.settings.edit', ['tab' => 'ai'])
                ->with('success', 'OpenAI connection successful.' . $note);
        } catch (Throwable $e) {
            Log::warning('Admin AI connection test failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.settings.edit', ['tab' => 'ai'])
                ->withErrors(['ai_connection' => $this->describeAiConnectionFailure($e)]);
        }
    }

    private function usesPersonalOpenAiKey(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $credential = $user->activeApiCredential();

        return $credential
            && strtolower(trim((string) $credential->provider)) === 'openai'
            && trim((string) $credential->api_key) !== '';
    }

    private function maskApiKey(string $key): string
    {
        $key = trim($key);

        if ($key === '') {
            return '(empty)';
        }

        if (mb_strlen($key) <= 8) {
            return str_repeat('•', mb_strlen($key));
        }

        return mb_substr($key, 0, 4) . '…' . mb_substr($key, -4);
    }

    private function describeAiConnectionFailure(Throwable $e, ?User $user = null): string
    {
        $message = mb_strtolower(trim((string) $e->getMessage()));
        $personal = $this->usesPersonalOpenAiKey($user);
        $keyLabel = $personal ? 'Your personal API key' : 'The shared admin API key';

        if (str_contains($message, 'no default ai provider')) {
            return 'No shared AI provider or API key is configured. Select a Default Provider and enter the Shared API Key in AI Configuration, then retry.';
        }

        if (str_contains($message, 'incomplete')) {
            return 'The AI configuration is incomplete. Choose the Default Provider and enter the Shared API Key above, then retry.';
        }

        if (str_contains($message, 'is disabled')) {
            return 'The selected AI provider is disabled. Enable it in AI Providers, then retry.';
        }

        if (str_contains($message, 'missing from ai providers')) {
            return 'The selected Default Provider is not in the AI Providers list. Choose a provider that is listed and enabled, then retry.';
        }

        if (str_contains($message, 'no api endpoint') || str_contains($message, 'no default model')) {
            return 'The selected provider is missing its API endpoint or default model. Edit it in AI Providers, then retry.';
        }

        if (str_contains($message, 'api error (401)')) {
            return $personal
                ? 'Your personal API key was rejected (HTTP 401). Set a valid active key under Profile → API Keys, then retry.'
                : $keyLabel . ' was rejected (HTTP 401). Check the key saved in Admin Settings → AI Configuration, then retry.';
        }

        if (str_contains($message, 'api error (403)')) {
            return $keyLabel . ' was refused (HTTP 403). The key may lack permission or the account may be restricted. Check it and retry.';
        }

        if (str_contains($message, 'api error (429)')) {
            if ($personal) {
                return 'Your personal API key has no credits remaining (HTTP 429). Add credits at https://platform.openai.com/settings/organization/billing, or set a different active key under Profile → API Keys, then retry.';
            }

            return 'The shared admin API key has no credits remaining (HTTP 429). Stored key: ' . $this->maskApiKey((string) SiteSetting::current()->default_ai_api_key) . '. This usually means the saved key belongs to a different OpenAI organization or project than the one where the credits were added. Verify the key at https://platform.openai.com/api-keys and check the organization/project it is attached to, confirm the balance under https://platform.openai.com/settings/organization/billing, then save the correct key in Admin Settings → AI Configuration → Shared API Key and retry.';
        }

        if (preg_match('/api error \((\d+)\)/', $message, $statusMatches) === 1) {
            return 'The AI provider returned an error (HTTP ' . $statusMatches[1] . '). See the server logs for full details, then retry.';
        }

        if (str_contains($message, 'timed out')) {
            return 'The connection to the AI provider timed out. Make sure this server can reach the provider endpoint (network/firewall), then retry.';
        }

        if (str_contains($message, 'did not return valid insight json')) {
            return 'The provider responded, but the reply was not valid JSON. The selected model or endpoint may not support JSON mode; check it in AI Providers, then retry.';
        }

        $summary = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $e->getMessage())));
        if ($summary !== '') {
            return 'OpenAI connection could not be verified: ' . mb_substr($summary, 0, 300) . ' See the server logs for full details.';
        }

        return 'OpenAI connection could not be verified. Check the server logs for details.';
    }

    /**
     * Update Privacy Policy.
     */
    /**
     * Weekly Email tab: when the automatic weekly appreciation email runs
     * (on/off, day and time in the app timezone). Read every minute by the
     * scheduler through WeeklyAppreciationSchedule.
     */
    public function updateWeeklyAppreciation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'appreciation_weekly_enabled' => ['nullable', 'boolean'],
            'appreciation_weekly_day' => ['required', 'integer', 'between:1,7'],
            'appreciation_weekly_time' => ['required', 'date_format:H:i'],
        ]);

        $settings = SiteSetting::current();
        $settings->forceFill([
            'appreciation_weekly_enabled' => $request->boolean('appreciation_weekly_enabled'),
            'appreciation_weekly_day' => (int) $data['appreciation_weekly_day'],
            'appreciation_weekly_time' => $data['appreciation_weekly_time'],
        ])->save();

        return redirect()
            ->route('admin.settings.edit', ['tab' => 'weekly-email'])
            ->with('success', 'Weekly appreciation email: '.\App\Services\WeeklyAppreciationSchedule::describe().'.');
    }

    public function updatePrivacy(
        Request $request
    ): RedirectResponse {
        $data =
            $request->validate([
                'privacy_policy_content' => [
                    'nullable',
                    'string',
                ],

                'privacy_policy_version' => [
                    'required',
                    'string',
                    'max:20',
                ],
            ]);

        $settings =
            SiteSetting::current();

        $settings->privacy_policy_content =
            filled(
                $data[
                    'privacy_policy_content'
                ]
                ?? null
            )
                ? app(LegalContentFormatter::class)->sanitize(trim(
                    $data[
                        'privacy_policy_content'
                    ]
                ))
                : null;

        $settings->privacy_policy_version =
            trim(
                $data[
                    'privacy_policy_version'
                ]
            );

        $settings->save();

        $settings->refresh();

        return redirect()
            ->route(
                'admin.settings.edit',
                [
                    'tab' =>
                        'privacy',
                ]
            )
            ->with(
                'success',
                'Privacy Policy updated successfully.'
            );
    }

    /**
     * Update Terms of Use.
     */
    public function updateTerms(
        Request $request
    ): RedirectResponse {
        $data =
            $request->validate([
                'terms_of_use_content' => [
                    'nullable',
                    'string',
                ],

                'terms_of_use_version' => [
                    'required',
                    'string',
                    'max:20',
                ],
            ]);

        $settings =
            SiteSetting::current();

        $settings->terms_of_use_content =
            filled(
                $data[
                    'terms_of_use_content'
                ]
                ?? null
            )
                ? app(LegalContentFormatter::class)->sanitize(trim(
                    $data[
                        'terms_of_use_content'
                    ]
                ))
                : null;

        $settings->terms_of_use_version =
            trim(
                $data[
                    'terms_of_use_version'
                ]
            );

        $settings->save();

        return redirect()
            ->route(
                'admin.settings.edit',
                [
                    'tab' =>
                        'terms',
                ]
            )
            ->with(
                'success',
                'Terms of Use updated successfully.'
            );
    }
}
