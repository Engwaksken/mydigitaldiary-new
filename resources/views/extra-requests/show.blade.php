@extends('layouts.app')

@section('title', 'Extra Request #' . $extraRequest->id)

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Extra Request #{{ $extraRequest->id }}</h1>
            <p class="text-sm text-slate-600">Created {{ $extraRequest->created_at->format('M d, Y H:i') }}</p>
        </div>
        <a href="{{ auth()->user()->isAdmin() ? route('admin.extra-requests.index') : route('extra-requests.index') }}" class="text-sm text-slate-600 hover:underline">Back</a>
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" class="mb-4" />
    @endif

    <div class="bg-white shadow rounded-lg p-6 border border-slate-200 space-y-4">
        <div class="grid grid-cols-2 gap-4 border-b border-slate-200 pb-4">
            <div>
                <span class="block text-xs text-slate-500 uppercase font-medium">User</span>
                <span class="text-sm font-semibold text-slate-900">{{ $extraRequest->user->name }} ({{ $extraRequest->user->email }})</span>
            </div>
            <div>
                <span class="block text-xs text-slate-500 uppercase font-medium">Status</span>
                <span class="inline-block mt-1 px-2.5 py-1 text-xs rounded-full font-medium
                    @if($extraRequest->status === 'applied') bg-emerald-100 text-emerald-800
                    @elseif($extraRequest->status === 'approved') bg-blue-100 text-blue-800
                    @elseif($extraRequest->status === 'rejected') bg-red-100 text-red-800
                    @else bg-amber-100 text-amber-800 @endif">
                    {{ ucfirst($extraRequest->status) }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 border-b border-slate-200 pb-4">
            <div>
                <span class="block text-xs text-slate-500 uppercase font-medium">Quota Minutes</span>
                <span class="text-lg font-bold text-[var(--brand-1)]">+{{ $extraRequest->quota_amount }} Minutes</span>
            </div>
            <div>
                <span class="block text-xs text-slate-500 uppercase font-medium">Amount</span>
                <span class="text-lg font-bold text-slate-900">{{ number_format($extraRequest->amount, 2) }} {{ $extraRequest->currency }}</span>
            </div>
        </div>

        <div>
            <span class="block text-xs text-slate-500 uppercase font-medium">Description</span>
            <p class="text-sm text-slate-700 mt-1">{{ $extraRequest->description ?? 'N/A' }}</p>
        </div>

        @if($extraRequest->iotecTransaction)
            <div class="bg-slate-50 p-4 rounded-lg border border-slate-200 space-y-2">
                <span class="block text-xs text-slate-500 uppercase font-medium">ioTec Payment Transaction</span>
                <div class="text-xs text-slate-700 grid grid-cols-2 gap-2">
                    <div>External ID: <span class="font-mono">{{ $extraRequest->iotecTransaction->external_id }}</span></div>
                    <div>Gateway Status: <span class="font-semibold uppercase">{{ $extraRequest->iotecTransaction->status }}</span></div>
                    <div>Channel: {{ ucfirst($extraRequest->iotecTransaction->payment_channel) }}</div>
                    <div>Payer: {{ $extraRequest->iotecTransaction->payer }}</div>
                </div>
            </div>
        @endif

        @if(auth()->id() === $extraRequest->user_id && $extraRequest->status === 'pending' && (! $extraRequest->iotecTransaction || $extraRequest->iotecTransaction->status !== 'success'))
            <div class="border-t border-slate-200 pt-4">
                <h3 class="text-md font-semibold text-slate-800 mb-3">Complete Payment via ioTec</h3>
                <form action="{{ route('extra-requests.pay', $extraRequest) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment Channel</label>
                        <select name="payment_channel" class="w-full rounded-lg border-slate-300">
                            <option value="mobile_money">Mobile Money (MTN / Airtel)</option>
                            <option value="card">Credit / Debit Card</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number / Payer ID</label>
                        <input type="text" name="payer" value="{{ old('payer', auth()->user()->phone ?? '') }}" class="w-full rounded-lg border-slate-300" placeholder="e.g. 256770000000" required>
                    </div>
                    <button type="submit" class="w-full py-3 bg-[var(--brand-1)] text-white font-medium rounded-lg shadow hover:opacity-90">
                        Pay Now with ioTec
                    </button>
                </form>
            </div>
        @endif

        @if(auth()->user()->isAdmin())
            <div class="border-t border-slate-200 pt-4 flex gap-3">
                @if($extraRequest->status === 'pending')
                    <form action="{{ route('admin.extra-requests.approve', $extraRequest) }}" method="POST">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">Approve</button>
                    </form>
                    <form action="{{ route('admin.extra-requests.reject', $extraRequest) }}" method="POST">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700">Reject</button>
                    </form>
                @endif
                @if($extraRequest->status !== 'applied')
                    <form action="{{ route('admin.extra-requests.apply', $extraRequest) }}" method="POST">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700">Apply Quota to User</button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
