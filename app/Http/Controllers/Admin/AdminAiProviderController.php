<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manages ADDITIONAL, custom AI providers beyond the two built-in ones
 * (Anthropic/OpenAI, seeded by migration, protected from edit/delete
 * here since AiPlannerService has genuinely different request-handling
 * code for those two specifically). Anything added here is assumed
 * OpenAI-compatible - see the ai_providers migration and
 * AiPlannerService::callCustomProvider() for the full reasoning.
 */
class AdminAiProviderController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        AiProvider::create($data);

        return $this->backToAiTab()->with('success', 'AI provider added.');
    }

    public function update(Request $request, AiProvider $aiProvider): RedirectResponse
    {
        if ($aiProvider->isBuiltIn()) {
            return $this->backToAiTab()->withErrors(['provider' => 'Claude and ChatGPT are built-in and cannot be edited here.']);
        }

        $data = $this->validated($request, $aiProvider->id);
        $aiProvider->update($data);

        return $this->backToAiTab()->with('success', 'AI provider updated.');
    }

    public function toggle(AiProvider $aiProvider): RedirectResponse
    {
        $aiProvider->update(['is_enabled' => ! $aiProvider->is_enabled]);

        return $this->backToAiTab()->with('success', $aiProvider->name . ' is now ' . ($aiProvider->is_enabled ? 'enabled' : 'disabled') . '.');
    }

    public function destroy(AiProvider $aiProvider): RedirectResponse
    {
        if ($aiProvider->isBuiltIn()) {
            return $this->backToAiTab()->withErrors(['provider' => 'Claude and ChatGPT are built-in and cannot be removed.']);
        }

        $aiProvider->delete();

        return $this->backToAiTab()->with('success', 'AI provider removed.');
    }

    private function backToAiTab(): RedirectResponse
    {
        return redirect()->route('admin.settings.edit', ['tab' => 'ai']);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:ai_providers,key' . ($ignoreId ? ",{$ignoreId}" : '')],
            'api_base_url' => ['required', 'url', 'max:500'],
            'default_model' => ['required', 'string', 'max:255'],
            'is_enabled' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'name' => $data['name'],
            'key' => $data['key'],
            'api_base_url' => $data['api_base_url'],
            'default_model' => $data['default_model'],
            'is_enabled' => $request->boolean('is_enabled', true),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }
}
