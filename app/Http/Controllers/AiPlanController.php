<?php

namespace App\Http\Controllers;

use App\Services\AiPlannerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class AiPlanController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $search = $request->query('q');

        $plans = $request->user()->aiPlans()
            ->when($search, fn ($query) => $query->where('content', 'like', '%' . $search . '%'))
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $base = \App\Models\AiPlan::where('user_id', $userId);
        $stats = [
            ['label' => 'Total generated', 'value' => (string) (clone $base)->count(), 'icon' => 'fa-solid fa-robot', 'color' => 'violet'],
            ['label' => 'This month', 'value' => (string) (clone $base)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(), 'icon' => 'fa-solid fa-calendar-check', 'color' => 'blue'],
            ['label' => 'Via free shared plan', 'value' => (string) (clone $base)->where('used_shared_key', true)->count(), 'icon' => 'fa-solid fa-gift', 'color' => 'amber'],
        ];

        // Last 6 months, oldest first - same trend-chart shape used
        // elsewhere in the app (dashboard, module charts).
        $months = collect(range(5, 0))->map(fn ($m) => now()->subMonthsNoOverflow($m)->startOfMonth());
        $counts = $months->map(function ($month) use ($userId) {
            return \App\Models\AiPlan::where('user_id', $userId)
                ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->count();
        });

        $chart = $counts->sum() > 0 ? [
            'type' => 'bar',
            'title' => 'Plans Generated (last 6 months)',
            'labels' => $months->map(fn ($m) => $m->format('M Y'))->all(),
            'datasets' => [['label' => 'Plans', 'data' => $counts->all()]],
        ] : null;

        return view('ai-plans.index', ['plans' => $plans, 'search' => $search, 'stats' => $stats, 'chart' => $chart]);
    }

    public function store(Request $request, AiPlannerService $planner)
    {
        $data = $request->validate(['custom_prompt' => ['nullable','string','max:3000']]);
        $customPrompt = trim((string) ($data['custom_prompt'] ?? ''));

        try {
            $result = $planner->generate($request->user(), null, $customPrompt);
        } catch (Throwable $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        $credential = $request->user()->activeApiCredential();

        $request->user()->aiPlans()->create([
            'content' => $result['content'],
            'custom_prompt' => $customPrompt !== '' ? $customPrompt : null,
            'provider' => $credential?->provider ?? \App\Models\SiteSetting::current()->default_ai_provider,
            'used_shared_key' => $result['used_shared_key'],
        ]);

        return redirect()->route('ai-plans.index')->with('success', 'New plan generated.');
    }

    /**
     * Download the AI plan as a PDF. The plan is always resolved through
     * the authenticated user's relationship so one user can never download
     * another user's plan.
     */
    public function downloadPdf(Request $request, int $aiPlan)
    {
        $plan = $request->user()->aiPlans()->findOrFail($aiPlan);

        $pdf = Pdf::loadView('ai-plans.pdf', [
            'plan' => $plan,
            'user' => $request->user(),
        ])->setPaper('a4');

        return $pdf->download('ai-plan-' . $plan->created_at->format('Y-m-d') . '.pdf');
    }


    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $deleted = $request->user()->aiPlans()
            ->whereIn('id', $data['ids'])
            ->delete();

        return back()->with('success', $deleted . ' AI plan' . ($deleted === 1 ? '' : 's') . ' deleted.');
    }

    public function destroy(Request $request, int $aiPlan): RedirectResponse
    {
        $plan = $request->user()->aiPlans()->findOrFail($aiPlan);
        $plan->delete();

        return back()->with('success', 'Plan deleted.');
    }
}
