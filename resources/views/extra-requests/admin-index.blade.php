@extends('layouts.app')

@section('title', 'Manage Extra Requests')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Manage Extra Recording Quota Requests</h1>
            <p class="text-sm text-slate-600">Review and apply customer extra quota requests across the platform.</p>
        </div>
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" class="mb-4" />
    @endif

    <div class="bg-white shadow rounded-lg overflow-hidden border border-slate-200">
        @if($requests->isEmpty())
            <div class="p-8 text-center text-slate-500">
                <i class="fa-solid fa-inbox text-4xl mb-3 text-slate-300"></i>
                <p>No extra quota requests found.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider border-b border-slate-200">
                            <th class="p-4">ID</th>
                            <th class="p-4">User</th>
                            <th class="p-4">Quota</th>
                            <th class="p-4">Amount</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Date</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-sm">
                        @foreach($requests as $req)
                            <tr>
                                <td class="p-4 font-medium">#{{ $req->id }}</td>
                                <td class="p-4">
                                    <div class="font-medium text-slate-900">{{ $req->user->name ?? 'Unknown' }}</div>
                                    <div class="text-xs text-slate-500">{{ $req->user->email ?? '' }}</div>
                                </td>
                                <td class="p-4 font-semibold text-[var(--brand-1)]">+{{ $req->quota_amount }} min</td>
                                <td class="p-4">{{ number_format($req->amount, 2) }} {{ $req->currency }}</td>
                                <td class="p-4">
                                    <span class="px-2.5 py-1 text-xs rounded-full font-medium
                                        @if($req->status === 'applied') bg-emerald-100 text-emerald-800
                                        @elseif($req->status === 'approved') bg-blue-100 text-blue-800
                                        @elseif($req->status === 'rejected') bg-red-100 text-red-800
                                        @else bg-amber-100 text-amber-800 @endif">
                                        {{ ucfirst($req->status) }}
                                    </span>
                                </td>
                                <td class="p-4 text-slate-500 text-xs">{{ $req->created_at->format('M d, Y') }}</td>
                                <td class="p-4 text-right space-x-2">
                                    <a href="{{ route('admin.extra-requests.show', $req) }}" class="text-[var(--brand-1)] hover:underline font-medium text-xs">Review</a>
                                    @if($req->status !== 'applied')
                                        <form action="{{ route('admin.extra-requests.apply', $req) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-emerald-600 hover:underline font-medium text-xs">Apply</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-200">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
