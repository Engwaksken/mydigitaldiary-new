<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingEventLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only audit trail for admins - every invoice/receipt generated or
 * emailed, every expiry reminder sent, every payment status change,
 * whether it succeeded and to whom. Nothing here is editable; it's a
 * record of what already happened.
 */
class AdminBillingLogController extends Controller
{
    public function index(Request $request): View
    {
        $eventType = $request->query('event_type');
        $status = $request->query('status');
        $search = $request->query('q');
        $perPage = (int) $request->query('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $logs = BillingEventLog::with(['user', 'payment.latestTransaction', 'invoice.enterpriseInquiry', 'invoice.payment.latestTransaction'])
            ->when($eventType, fn ($q) => $q->where('event_type', $eventType))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('recipient_email', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('payment.latestTransaction', fn ($tx) => $tx->where('phone_number', 'like', "%{$search}%"))
                    ->orWhereHas('invoice.enterpriseInquiry', fn ($inq) => $inq->where('phone', 'like', "%{$search}%"));
            }))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $stats = [
            'total' => BillingEventLog::count(),
            'failed' => BillingEventLog::where('status', 'failed')->count(),
            'reminders_sent' => BillingEventLog::where('event_type', 'reminder_sent')->where('status', 'success')->count(),
        ];

        return view('admin.billing-logs.index', compact('logs', 'eventType', 'status', 'search', 'perPage', 'stats'));
    }
}
