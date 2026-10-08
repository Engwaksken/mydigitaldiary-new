@extends('layouts.app')

@section('title', 'Extra Recording Quota Requests')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Extra Recording Quota Requests</h1>
            <p class="text-sm text-slate-600">Request and manage additional transcription and recording minutes.</p>
        </div>
        <a href="{{ route('extra-requests.create') }}" class="inline-flex items-center px-4 py-2 bg-[var(--brand-1)] text-white text-sm font-medium rounded-lg shadow hover:opacity-90">
            <i class="fa-solid fa-plus mr-2"></i> Request Extra Quota
        </a>
    </div>

    @if (session('success'))
        <div class="p-4 mb-4 text-sm text-emerald-700 bg-emerald-100 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="pm-dt-wrap pm-er-card">
        <div class="p-4 border-b border-slate-100 flex flex-wrap gap-2 justify-between items-center">
            <span class="text-sm font-semibold text-slate-700">Your Current Quota: {{ auth()->user()->extra_recording_quota_minutes ?? 0 }} minutes</span>
            @if(auth()->user()->extra_quota_expires_at)
                <span class="text-xs text-slate-500">Expires: {{ auth()->user()->extra_quota_expires_at->format('M d, Y H:i') }}</span>
            @endif
        </div>
        @if($requests->isEmpty())
            <div class="p-8 text-center text-slate-500">
                <i class="fa-solid fa-microphone-slash text-4xl mb-3 text-slate-300"></i>
                <p>No extra quota requests found.</p>
            </div>
        @else
            <table class="pm-dt">
                <caption class="sr-only">Your extra recording quota requests.</caption>
                <thead>
                    <tr>
                        <th scope="col">Request</th>
                        <th scope="col">Minutes</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="pm-dt-num">Amount</th>
                        <th scope="col" class="pm-dt-actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $req)
                        @php
                            $erPill = match ($req->status) {
                                'applied' => 'is-green',
                                'approved' => 'is-sky',
                                'rejected' => 'is-rose',
                                default => 'is-amber',
                            };
                        @endphp
                        <tr>
                            <td class="pm-dt-main">
                                <a href="{{ route('extra-requests.show', $req) }}" class="pm-dt-title hover:text-[var(--brand-1)]" title="{{ $req->description }}">{{ $req->description ?: 'Request #' . $req->id }}</a>
                                <span class="pm-dt-sub"><span>#{{ $req->id }}</span><span>{{ $req->created_at->format('M d, Y') }}</span></span>
                            </td>
                            <td class="pm-dt-aux font-semibold text-[var(--brand-1)]">+{{ $req->quota_amount }} min</td>
                            <td class="pm-dt-aux">
                                <span class="pm-dt-pill {{ $erPill }}">{{ ucfirst($req->status) }}</span>
                            </td>
                            <td class="pm-dt-num">{{ number_format($req->amount, 2) }} {{ $req->currency }}</td>
                            <td class="pm-dt-actions">
                                <a href="{{ route('extra-requests.show', $req) }}" class="pm-dt-icon-btn" title="View request" aria-label="View request #{{ $req->id }}">
                                    <i class="fa-solid fa-eye text-xs" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="p-4 border-t border-slate-100">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>
<style>
    /* The table sits under the quota bar, so its header has square corners. */
    .pm-er-card .pm-dt thead th { border-radius: 0; }
    .pm-er-card .pm-dt tbody tr:last-child td { border-radius: 0; }
</style>
@endsection
