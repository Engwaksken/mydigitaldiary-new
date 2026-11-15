<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Platform-level statistics only - user counts, subscription mix, signup
 * trend, and real collected payments. Deliberately queries nothing from
 * any personal-tracking table (plans, expenses, health, etc.) - see the
 * boundary note on AdminUserController.
 */
class AdminStatisticsController extends Controller
{
    public function index(): View
    {
        $totalUsers = User::count();
        $adminCount = User::where('role', 'admin')->count();
        $suspendedCount = User::whereNotNull('suspended_at')->count();

        $byStatus = User::selectRaw('subscription_status, COUNT(*) as total')
            ->groupBy('subscription_status')
            ->pluck('total', 'subscription_status');

        $activeCount = (int) ($byStatus['active'] ?? 0);
        $monthlyPrice = (float) SiteSetting::current()->monthly_price;
        $estimatedMonthlyRevenue = $activeCount * $monthlyPrice;

        $totalCollected = (float) Payment::where('status', 'completed')->sum('amount');
        $pendingPaymentsCount = Payment::where('status', 'pending')->count();

        // Daily signups for the last 30 days, including days with zero.
        $since = Carbon::now()->subDays(29)->startOfDay();
        $signupsRaw = User::where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $signupLabels = [];
        $signupCounts = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i)->format('Y-m-d');
            $signupLabels[] = Carbon::now()->subDays($i)->format('M j');
            $signupCounts[] = (int) ($signupsRaw[$day] ?? 0);
        }

        return view('admin.statistics', [
            'totalUsers' => $totalUsers,
            'adminCount' => $adminCount,
            'suspendedCount' => $suspendedCount,
            'byStatus' => $byStatus,
            'estimatedMonthlyRevenue' => $estimatedMonthlyRevenue,
            'totalCollected' => $totalCollected,
            'pendingPaymentsCount' => $pendingPaymentsCount,
            'signupLabels' => $signupLabels,
            'signupCounts' => $signupCounts,
        ]);
    }
}
