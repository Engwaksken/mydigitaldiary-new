<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiPlan;
use App\Models\SiteSetting;
use App\Services\AiPlannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Mobile equivalent of AiPlanController - same generate/list/delete
 * flow, JSON instead of a Blade view. PDF download reuses the existing
 * web route directly (same approach as invoice/receipt downloads
 * elsewhere in this app) rather than duplicating PDF generation here.
 */
class AiPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $plans = $request->user()->aiPlans()->orderByDesc('id')->limit(20)->get();

        return response()->json(['data' => $plans->map(fn ($plan) => [
            'id' => $plan->id,
            'content' => $plan->content,
            'custom_prompt' => $plan->custom_prompt,
            'provider' => $plan->provider,
            'used_shared_key' => (bool) $plan->used_shared_key,
            'created_at' => $plan->created_at->toIso8601String(),
            'pdf_url' => route('ai-plans.pdf', $plan->id),
        ])]);
    }

    public function store(Request $request, AiPlannerService $planner): JsonResponse
    {
        $validated = $request->validate(['custom_prompt' => ['nullable','string','max:3000']]);
        $customPrompt = trim((string) ($validated['custom_prompt'] ?? ''));
        // Optional - the mobile app sends its own local ISO8601
        // datetime here (see AiPlanService.generate() on the Flutter
        // side) so the plan's "next 7 days" reasoning uses the
        // phone's actual current time rather than the server's,
        // which could be a different timezone or genuinely wrong.
        // Falls back to server time (unchanged behavior) if absent or
        // unparseable, e.g. from the web app, which doesn't send this.
        $clientTime = null;
        if ($request->filled('client_datetime')) {
            try {
                $clientTime = \Illuminate\Support\Carbon::parse($request->input('client_datetime'));
            } catch (\Throwable $e) {
                $clientTime = null;
            }
        }

        try {
            $result = $planner->generate($request->user(), $clientTime, $customPrompt);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $credential = $request->user()->activeApiCredential();

        $plan = $request->user()->aiPlans()->create([
            'content' => $result['content'],
            'custom_prompt' => $customPrompt !== '' ? $customPrompt : null,
            'provider' => $credential?->provider ?? SiteSetting::current()->default_ai_provider,
            'used_shared_key' => $result['used_shared_key'],
        ]);

        return response()->json(['data' => [
            'id' => $plan->id,
            'content' => $plan->content,
            'custom_prompt' => $plan->custom_prompt,
            'provider' => $plan->provider,
            'used_shared_key' => (bool) $plan->used_shared_key,
            'created_at' => $plan->created_at->toIso8601String(),
            'pdf_url' => route('ai-plans.pdf', $plan->id),
        ]]);
    }

    public function destroy(Request $request, AiPlan $aiPlan): JsonResponse
    {
        abort_unless($aiPlan->user_id === $request->user()->id, 403);
        $aiPlan->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
