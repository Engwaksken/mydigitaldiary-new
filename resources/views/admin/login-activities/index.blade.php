@extends('layouts.app')

@section('title', 'Login Activities')

@section('content')
<div class="apple-page space-y-5">
    <div class="apple-hero">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-[var(--brand-1)] flex items-center justify-center shrink-0">
                <i class="fa-solid fa-shield-halved text-lg" aria-hidden="true"></i>
            </div>
            <div>
                <p class="apple-eyebrow">Admin Security</p>
                <h1>Login Activities</h1>
                <p>Review successful sign-ins across all user accounts by day, week, month, or custom date range.</p>
            </div>
        </div>
        <a href="{{ route('admin.users.index') }}" class="apple-btn">
            <i class="fa-solid fa-users"></i> Manage Users
        </a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach ([
            ['label' => 'All logins', 'value' => $stats['total'], 'icon' => 'fa-right-to-bracket'],
            ['label' => 'Today', 'value' => $stats['today'], 'icon' => 'fa-calendar-day'],
            ['label' => 'This week', 'value' => $stats['week'], 'icon' => 'fa-calendar-week'],
            ['label' => 'This month', 'value' => $stats['month'], 'icon' => 'fa-calendar'],
        ] as $stat)
            <div class="apple-surface p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-500">{{ $stat['label'] }}</p>
                        <p class="text-2xl font-extrabold text-slate-900 mt-1" data-countup>{{ $stat['value'] }}</p>
                    </div>
                    <span class="w-10 h-10 rounded-xl bg-emerald-50 text-[var(--brand-1)] grid place-items-center">
                        <i class="fa-solid {{ $stat['icon'] }}"></i>
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    <div class="apple-surface">
        <form method="GET" action="{{ route('admin.login-activities.index') }}" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-7 gap-3 items-end">
            <div class="xl:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Search</label>
                <input type="search" name="q" value="{{ $search }}" placeholder="Name, email, IP, browser..." class="pm-input text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">User</label>
                <select name="user_id" class="pm-input text-sm">
                    <option value="">All users</option>
                    @foreach ($users as $filterUser)
                        <option value="{{ $filterUser->id }}" @selected($userId === $filterUser->id)>{{ $filterUser->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Period</label>
                <select name="period" class="pm-input text-sm" onchange="pmAdminLoginRange(this.value)">
                    <option value="" @selected(!$period)>All time</option>
                    <option value="daily" @selected($period === 'daily')>Today</option>
                    <option value="weekly" @selected($period === 'weekly')>This week</option>
                    <option value="monthly" @selected($period === 'monthly')>This month</option>
                    <option value="range" @selected($period === 'range')>Custom range</option>
                </select>
            </div>
            <div id="admin-login-from" style="{{ $period === 'range' ? '' : 'display:none' }}">
                <label class="block text-xs font-semibold text-slate-600 mb-1">From</label>
                <input type="date" name="from" value="{{ $from }}" class="pm-input text-sm">
            </div>
            <div id="admin-login-to" style="{{ $period === 'range' ? '' : 'display:none' }}">
                <label class="block text-xs font-semibold text-slate-600 mb-1">To</label>
                <input type="date" name="to" value="{{ $to }}" class="pm-input text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Per page</label>
                <select name="per_page" class="pm-input text-sm">
                    @foreach ([10,25,50,100] as $size)
                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="xl:col-span-7 flex gap-2">
                <button class="apple-btn apple-btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
                @if ($search || $period || $userId || $perPage !== 25)
                    <a href="{{ route('admin.login-activities.index') }}" class="apple-btn">Clear</a>
                @endif
            </div>
        </form>
    </div>

    <div class="apple-surface p-0 overflow-hidden">
        <div class="overflow-x-auto pm-admin-table-scroll">
            <table class="apple-table min-w-full pm-admin-horizontal-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Date & Time</th>
                        <th>Device / Browser</th>
                        <th>IP Address</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activity as $login)
                        <tr>
                            <td>
                                <div class="font-semibold text-slate-800">{{ $login->user?->name ?? 'Deleted user' }}</div>
                                <div class="text-xs text-slate-400 mt-0.5">{{ $login->user?->email ?? '-' }}</div>
                            </td>
                            <td class="whitespace-nowrap">
                                <div class="font-medium text-slate-700">{{ optional($login->logged_in_at)->format('d M Y, h:i A') }}</div>
                                <div class="text-xs text-slate-400 mt-0.5">{{ optional($login->logged_in_at)->diffForHumans() }}</div>
                            </td>
                            <td class="min-w-[220px]">
                                <div class="font-medium text-slate-700">{{ $login->deviceLabel() }}</div>
                                <div class="text-xs text-slate-400 max-w-xs truncate" title="{{ $login->user_agent }}">{{ $login->user_agent ?: 'Unavailable' }}</div>
                            </td>
                            <td class="whitespace-nowrap">{{ $login->ip_address ?: 'Unavailable' }}</td>
                            <td>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 text-emerald-700 px-2.5 py-1 text-xs font-semibold">
                                    <i class="fa-solid fa-circle-check"></i> Successful
                                </span>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                @if ($login->user)
                                    <a href="{{ route('admin.users.show', $login->user) }}" class="apple-link">Manage user</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-12 text-center text-slate-400">No login activity matches these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($activity->total() > 0)
        <div>{{ $activity->links() }}</div>
    @endif
</div>
<script>
function pmAdminLoginRange(value){
    var show=value==='range';
    document.getElementById('admin-login-from').style.display=show?'':'none';
    document.getElementById('admin-login-to').style.display=show?'':'none';
}
</script>
@endsection
