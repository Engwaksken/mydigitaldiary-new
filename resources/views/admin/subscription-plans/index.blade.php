@extends('layouts.app')

@section('title', 'Subscription Plans')

@section('content')
    @php $canManagePlans = auth()->user()->system_role === 'super_admin' || auth()->user()->role === 'super_admin'; @endphp
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-teal-100 text-teal-600 flex items-center justify-center shadow-sm shrink-0">
                <i class="fa-solid fa-tags text-xl" aria-hidden="true"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Subscription Plans</h1>
        </div>
        @if ($canManagePlans)
            <a href="{{ route('admin.subscription-plans.create') }}"
               class="inline-flex items-center justify-center gap-2 btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <span>Add Plan</span>
            </a>
        @endif
    </div>

    <p class="text-sm text-slate-500 mb-4">
        Plans may use a price calculated from the site's base monthly price or a super-admin-set flat price.
        The current base is <strong>{{ format_money($monthlyPrice) }}</strong> (set at
        <a href="{{ route('admin.settings.edit') }}" class="text-[var(--brand-1)] hover:underline">Admin &rarr; Settings</a>).
        Individual plans are grouped into Starter, Gold, Premium, and Custom tiers, each with a per-meeting transcription duration limit.
    </p>

    <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl overflow-x-auto pm-admin-table-scroll" role="region" aria-label="Subscription plans table" tabindex="0">
        <table class="min-w-full text-sm pm-admin-horizontal-table">
            <caption class="sr-only">Subscription plans with computed price, discount, and enabled state.</caption>
            <thead class="bg-slate-50 text-left border-b border-slate-100">
                <tr>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Plan</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Category</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Duration</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Discount</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Computed Price</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Status</th>
                    @if ($canManagePlans)<th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($plans as $plan)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3 text-slate-700 font-medium">
                            {{ $plan->name }}
                            @if ($plan->badge)
                                <span class="ms-1 inline-flex items-center text-xs font-medium px-2 py-0.5 rounded-full" style="{{ $plan->colorTintStyle() }} {{ $plan->colorTextStyle() }}">{{ $plan->badge }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2 py-0.5 rounded-full" style="{{ $plan->colorTintStyle() }} {{ $plan->colorTextStyle() }}">
                                {{ match($plan->category) { 'family_team' => 'Family/Team', 'organization' => 'Enterprise', default => 'Individual' } }}
                            </span>
                            @if ($plan->individualTierLabel())
                                <span class="block text-xs text-slate-500 mt-1">{{ $plan->individualTierLabel() }}</span>
                            @endif
                            @if ($plan->category !== 'individual')
                                <span class="block text-xs text-slate-400 mt-1">{{ $plan->included_seats }} members</span>
                                @if ($plan->includedExtraRecordingMinutes())
                                    <span class="block text-xs text-slate-400 mt-1">{{ $plan->includedExtraRecordingMinutes() }} extra min</span>
                                @endif
                            @endif
                            <span class="block text-xs text-slate-500 mt-1">{{ number_format($plan->max_transcription_minutes_per_meeting ?? 60) }} min per meeting</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $plan->isLifetime() ? 'Never expires' : $plan->duration_months . ' month(s)' }}
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $plan->isLifetime() ? '—' : number_format($plan->discount_percent, 0) . '%' }}
                        </td>
                        <td class="px-4 py-3 font-semibold text-slate-800">
                            {{ format_money($plan->computedPrice($monthlyPrice)) }}
                            @if ($plan->savingsLabel())
                                <span class="ml-1 text-xs font-medium text-emerald-600">({{ $plan->savingsLabel() }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($plan->is_enabled)
                                <span class="text-emerald-700 font-medium">Enabled</span>
                            @else
                                <span class="text-slate-400">Disabled</span>
                            @endif
                        </td>
                        @if ($canManagePlans)
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <form action="{{ route('admin.subscription-plans.toggle', $plan->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-[var(--brand-1)] hover:underline mr-3">
                                    {{ $plan->is_enabled ? 'Disable' : 'Enable' }}
                                </button>
                            </form>
                            <a href="{{ route('admin.subscription-plans.edit', $plan->id) }}"
                               class="text-[var(--brand-1)] hover:underline mr-3">
                                Edit
                            </a>
                            <form action="{{ route('admin.subscription-plans.destroy', $plan->id) }}" method="POST" class="inline"
                                  data-confirm="Remove this subscription plan?" data-confirm-title="Delete subscription plan?" data-confirm-text="Delete">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-600 hover:underline">Delete</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManagePlans ? 7 : 6 }}" class="px-4 py-6 text-center text-slate-500">
                            No plans configured — users will see only the demo "Subscribe" fallback.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($plans->hasPages())
        <div class="mt-4">{{ $plans->links() }}</div>
    @endif

@endsection
