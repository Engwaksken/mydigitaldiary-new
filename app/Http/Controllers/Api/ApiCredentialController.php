<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiPlan;
use App\Models\AiProvider;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile equivalent of the web app's ApiCredentialController - same
 * "own key always takes priority, otherwise fall back to the site's
 * shared key up to its monthly per-user limit" logic (see
 * AiPlannerService::generate(), which both web and mobile's AI Plan
 * generation already share). This just exposes that same account
 * data as JSON instead of a Blade view.
 */
class ApiCredentialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $credentials = $user->apiCredentials()->orderByDesc('id')->get();
        $settings = SiteSetting::current();

        $sharedUsedThisMonth = AiPlan::where('user_id', $user->id)
            ->where('used_shared_key', true)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $providers = AiProvider::where('is_enabled', true)->orderBy('sort_order')->get();

        return response()->json([
            'data' => $credentials->map(fn ($c) => [
                'id' => $c->id,
                'label' => $c->label,
                'provider' => $c->provider,
                'is_active' => (bool) $c->is_active,
            ]),
            'has_own_active_key' => $credentials->contains('is_active', true),
            'has_shared_key_configured' => $settings->hasDefaultAiKey(),
            'shared_used_this_month' => $sharedUsedThisMonth,
            'shared_limit_per_month' => $settings->default_ai_free_limit_per_month,
            'providers' => $providers->map(fn ($p) => ['key' => $p->key, 'name' => $p->name]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', 'exists:ai_providers,key'],
            'api_key' => ['required', 'string', 'max:1000'],
        ]);

        $credential = $request->user()->apiCredentials()->create($data);

        // First key a user adds becomes active automatically - same
        // rule as web.
        if ($request->user()->apiCredentials()->count() === 1) {
            $credential->update(['is_active' => true]);
        }

        return response()->json(['message' => 'API key added.'], 201);
    }

    public function activate(Request $request, int $apiCredential): JsonResponse
    {
        $credential = $request->user()->apiCredentials()->findOrFail($apiCredential);

        $request->user()->apiCredentials()->update(['is_active' => false]);
        $credential->update(['is_active' => true]);

        return response()->json(['message' => "\"{$credential->label}\" is now the active API key."]);
    }

    public function destroy(Request $request, int $apiCredential): JsonResponse
    {
        $credential = $request->user()->apiCredentials()->findOrFail($apiCredential);
        $wasActive = $credential->is_active;
        $credential->delete();

        // If we just deleted the active key, promote the next most
        // recent one - same rule as web.
        if ($wasActive) {
            $request->user()->apiCredentials()->latest()->first()?->update(['is_active' => true]);
        }

        return response()->json(['message' => 'API key removed.']);
    }
}
