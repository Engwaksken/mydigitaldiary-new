<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Console\Commands\DispatchScheduledAppreciation;
use App\Http\Controllers\Controller;
use App\Models\AppreciationCampaign;
use App\Models\AppreciationEmailLog;
use App\Services\AppreciationMessageService;
use App\Services\SendTime;
use App\Services\WeeklyAppreciationSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin > Appreciation emails: every appreciation email sent (weekly run or
 * admin), newest first, plus scheduled admin campaigns that can be
 * cancelled or sent now.
 */
class AdminAppreciationController extends Controller
{
    public function index(Request $request): View
    {
        $week = trim((string) $request->query('week', ''));
        $search = trim((string) $request->query('search', ''));

        $logs = AppreciationEmailLog::query()
            ->with(['user:id,name,email', 'sender:id,name'])
            ->when($week !== '', fn ($query) => $query->where('week_key', $week))
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($user) => $user
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderByRaw('COALESCE(sent_at, created_at) DESC')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $weeks = AppreciationEmailLog::query()->select('week_key')->distinct()
            ->orderByDesc('week_key')->limit(60)->pluck('week_key');

        $currentWeek = AppreciationMessageService::weekKey();

        return view('admin.appreciation.index', [
            'logs' => $logs,
            'weeks' => $weeks,
            'week' => $week,
            'search' => $search,
            'scheduled' => AppreciationCampaign::query()->with('creator:id,name')
                ->where('status', AppreciationCampaign::STATUS_SCHEDULED)
                ->orderBy('scheduled_for')->get(),
            'recentCampaigns' => AppreciationCampaign::query()->with('creator:id,name')
                ->where('status', '!=', AppreciationCampaign::STATUS_SCHEDULED)
                ->latest('id')->limit(5)->get(),
            'stats' => [
                'this_week' => AppreciationEmailLog::query()->where('week_key', $currentWeek)->whereNotNull('sent_at')->count(),
                'total' => AppreciationEmailLog::query()->whereNotNull('sent_at')->count(),
                'scheduled' => AppreciationCampaign::query()->where('status', AppreciationCampaign::STATUS_SCHEDULED)->count(),
            ],
            'currentWeek' => $currentWeek,
            'weeklySchedule' => WeeklyAppreciationSchedule::describe(),
            'timezone' => SendTime::timezone(),
        ]);
    }

    public function cancel(AppreciationCampaign $campaign): RedirectResponse
    {
        $cancelled = AppreciationCampaign::query()->whereKey($campaign->id)
            ->where('status', AppreciationCampaign::STATUS_SCHEDULED)
            ->update(['status' => AppreciationCampaign::STATUS_CANCELLED, 'updated_at' => now()]) === 1;

        return back()->with(
            $cancelled ? 'success' : 'error',
            $cancelled ? 'Scheduled appreciation email cancelled. Nothing was sent.' : 'Only a scheduled appreciation email can be cancelled; this one has already started sending.'
        );
    }

    public function sendNow(AppreciationCampaign $campaign): RedirectResponse
    {
        if (! DispatchScheduledAppreciation::start((int) $campaign->id)) {
            return back()->with('error', 'Only a scheduled appreciation email can be sent now; this one has already started sending or was cancelled.');
        }

        return back()->with('success', 'Appreciation email is being sent now to the eligible users.');
    }
}
