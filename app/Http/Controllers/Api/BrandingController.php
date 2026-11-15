<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

/**
 * Public - reachable before login, since the login/register/splash
 * screens need the site logo before any auth token exists. No
 * sensitive data here, only what's already shown to any anonymous web
 * visitor.
 */
class BrandingController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = SiteSetting::current();

        return response()->json(['data' => [
            'site_name' => $settings->site_name,
            'logo_url' => $settings->logoUrl(),
            'currency_symbol' => $settings->default_currency_symbol,
            'currency_decimals' => $settings->default_currency_decimals,
        ]]);
    }
}
