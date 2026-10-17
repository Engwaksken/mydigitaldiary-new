<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonalisationController extends Controller
{
    private const AI_MODULES = ['planning','finance','goals','health','wellbeing','spiritual','notes','meetings','network','education','relationships'];
    private const FOCUSES = ['money','day','goals','health','work','growth','everything'];

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'ai_data_permissions' => $user->aiDataPermissions(),
            'onboarding_focuses' => $user->onboarding_focuses ?? [],
            'onboarding_completed' => ! is_null($user->onboarding_completed_at),
            'engagement_notification_preferences' => $user->engagementNotificationPreferences(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ai_data_permissions' => ['sometimes','array'],
            'ai_data_permissions.*' => [Rule::in(self::AI_MODULES)],
            'onboarding_focuses' => ['sometimes','array','max:7'],
            'onboarding_focuses.*' => [Rule::in(self::FOCUSES)],
            'complete_onboarding' => ['sometimes','boolean'],
            'engagement_notification_preferences' => ['sometimes','array'],
            'engagement_notification_preferences.*' => ['boolean'],
        ]);

        $user = $request->user();
        if (array_key_exists('ai_data_permissions', $data)) $user->ai_data_permissions = array_values(array_unique($data['ai_data_permissions']));
        if (array_key_exists('onboarding_focuses', $data)) $user->onboarding_focuses = array_values(array_unique($data['onboarding_focuses']));
        if (!empty($data['complete_onboarding'])) $user->onboarding_completed_at = now();
        if (array_key_exists('engagement_notification_preferences', $data)) {
            $allowed = ['goal_progress','monthly_review','finance_insights','productivity_nudges','spiritual_insights','subscription_reminders','daily_affirmations','weekly_appreciation'];
            $user->engagement_notification_preferences = collect($data['engagement_notification_preferences'])->only($allowed)->map(fn ($v) => (bool) $v)->all();
        }
        $user->save();

        return $this->show($request);
    }
}
