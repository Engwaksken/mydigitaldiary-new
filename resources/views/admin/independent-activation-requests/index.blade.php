@extends('layouts.app')
@section('title', 'Independent Activation Requests')
@section('content')
<h1 class="text-2xl font-bold text-slate-800 mb-6">Independent Activation Requests</h1>

<div class="pm-card-bg rounded-xl border border-slate-100 shadow-sm overflow-x-auto">
<table class="min-w-[900px] w-full text-sm">
<thead class="bg-slate-50">
<tr>
<th class="px-4 py-3 text-left">User</th>
<th class="px-4 py-3 text-left">Reason</th>
<th class="px-4 py-3 text-left">Status</th>
<th class="px-4 py-3 text-left">Requested</th>
<th class="px-4 py-3 text-right">Actions</th>
</tr>
</thead>
<tbody>
@forelse($requests as $item)
<tr class="border-t border-slate-100">
<td class="px-4 py-3">
<div class="font-semibold">{{ $item->user->name ?? 'User' }}</div>
<div class="text-xs text-slate-500">{{ $item->user->email ?? '' }}</div>
</td>
<td class="px-4 py-3">{{ $item->reason ?: '-' }}</td>
<td class="px-4 py-3">{{ ucfirst($item->status) }}</td>
<td class="px-4 py-3">{{ $item->created_at?->format('d M Y, g:i A') }}</td>
<td class="px-4 py-3 text-right">
@if($item->status === 'pending')
<form method="POST" action="{{ route('admin.independent-activation-requests.approve', $item) }}" class="inline">
@csrf
<button class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold">Approve</button>
</form>
<form method="POST" action="{{ route('admin.independent-activation-requests.reject', $item) }}" class="inline">
@csrf
<button class="px-3 py-2 rounded-lg bg-rose-600 text-white text-xs font-semibold">Reject</button>
</form>
@endif
</td>
</tr>
@empty
<tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No requests yet.</td></tr>
@endforelse
</tbody>
</table>
</div>
<div class="mt-4">{{ $requests->links() }}</div>
@endsection
