@extends('layouts.app')

@section('title', ($plan->exists ? 'Edit' : 'New') . ' Subscription Plan')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-teal-100 text-teal-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-tags text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">{{ $plan->exists ? 'Edit' : 'New' }} Subscription Plan</h1>
    </div>

    @php
        $isLifetime = old('pricing_type', $plan->exists && is_null($plan->duration_months) ? 'lifetime' : null);
        $pricingType = old('pricing_type', $plan->exists
            ? (is_null($plan->duration_months) ? 'lifetime' : (! is_null($plan->flat_price) ? 'flat' : 'discount'))
            : 'discount');
        $category = old('category', $plan->category ?? 'individual');
    @endphp

    <form method="POST"
          action="{{ $plan->exists ? route('admin.subscription-plans.update', $plan->id) : route('admin.subscription-plans.store') }}"
          class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 max-w-2xl space-y-5">
        @csrf
        @if ($plan->exists)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Display Name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $plan->name) }}"
                       placeholder="e.g. 3 Months" required aria-required="true"
                       class="pm-input">
                @error('name')
                    <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="key" class="block text-sm font-medium text-slate-700 mb-1">Key</label>
                <input type="text" id="key" name="key" value="{{ old('key', $plan->key) }}"
                       placeholder="e.g. quarterly" required aria-required="true"
                       class="pm-input font-mono text-sm">
                @error('key')
                    <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="category" class="block text-sm font-medium text-slate-700 mb-1">Category</label>
            <select id="category" name="category" onchange="pmToggleSeatFields(this.value)" class="pm-input">
                <option value="individual" @selected($category === 'individual')>Individual</option>
                <option value="family_team" @selected($category === 'family_team')>Family &amp; Small Team</option>
                <option value="organization" @selected($category === 'organization')>Enterprise</option>
            </select>
            <p class="text-xs text-slate-400 mt-1">Controls which section of the pricing page this plan appears under.</p>
        </div>

        <div>
            <label for="pricing_type" class="block text-sm font-medium text-slate-700 mb-1">Pricing Type</label>
            <select id="pricing_type" name="pricing_type" onchange="pmTogglePricingFields(this.value)" class="pm-input">
                <option value="discount" @selected($pricingType === 'discount')>Discount off base monthly price</option>
                <option value="flat" @selected($pricingType === 'flat')>Flat price, recurring (e.g. a fixed monthly member bundle)</option>
                <option value="lifetime" @selected($pricingType === 'lifetime')>Flat price, lifetime (never expires)</option>
            </select>
        </div>

        <div id="fields-discount" class="space-y-4 border-t pt-4">
            <div>
                <label for="duration_months" class="block text-sm font-medium text-slate-700 mb-1">Duration (months)</label>
                <input type="number" id="duration_months" name="duration_months" min="1" max="120"
                       value="{{ old('duration_months', $plan->duration_months) }}"
                       class="pm-input">
                @error('duration_months')
                    <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="discount_percent" class="block text-sm font-medium text-slate-700 mb-1">Discount (%)</label>
                <input type="number" id="discount_percent" name="discount_percent" min="0" max="100" step="0.01"
                       value="{{ old('discount_percent', $plan->discount_percent) }}"
                       class="pm-input">
                <p class="text-xs text-slate-400 mt-1">
                    Off the site's base monthly price &times; duration. Keep this modest (5&ndash;15% is
                    typical) &mdash; a very large discount erodes margin fast once renewals compound.
                </p>
                @error('discount_percent')
                    <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div id="fields-flat-duration" class="space-y-4 border-t pt-4" style="display: none;">
            <label for="duration_months_flat" class="block text-sm font-medium text-slate-700 mb-1">Billed every (months)</label>
            <input type="number" id="duration_months_flat" name="duration_months_flat" min="1" max="120" value="{{ old('duration_months_flat', $plan->exists && ! is_null($plan->flat_price) && ! is_null($plan->duration_months) ? $plan->duration_months : 1) }}" class="pm-input">
            <p class="text-xs text-slate-400 mt-1">E.g. 1 for a monthly member bundle, 12 for annual billing of the same flat total.</p>
        </div>

        <div id="fields-flat-price" class="space-y-4 border-t pt-4" style="display: none;">
            <label for="flat_price" class="block text-sm font-medium text-slate-700 mb-1">
                Flat Price <span class="text-slate-400 font-normal">({{ $settings->default_currency_code ?? 'UGX' }})</span>
            </label>
            <input type="number" id="flat_price" name="flat_price" min="0" step="0.01"
                   value="{{ old('flat_price', $plan->flat_price) }}"
                   class="pm-input">
            <p class="text-xs text-slate-400 mt-1">A fixed total, independent of the site's base monthly price.</p>
            @error('flat_price')
                <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div id="fields-seats" class="space-y-4 border-t pt-4" style="{{ $category === 'individual' ? 'display: none;' : '' }}">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="included_seats" class="block text-sm font-medium text-slate-700 mb-1">Included Members</label>
                    <input type="number" id="included_seats" name="included_seats" min="1" value="{{ old('included_seats', $plan->included_seats ?? 5) }}" class="pm-input">
                </div>
                <div>
                    <label for="additional_user_price" class="block text-sm font-medium text-slate-700 mb-1">
                        Additional User Price <span class="text-slate-400 font-normal">({{ $settings->default_currency_code ?? 'UGX' }})</span>
                    </label>
                    <input type="number" id="additional_user_price" name="additional_user_price" min="0" step="0.01" value="{{ old('additional_user_price', $plan->additional_user_price) }}" class="pm-input">
                    <p class="text-xs text-slate-400 mt-1">Per-member cost beyond the included limit — leave blank to disallow overage (must upgrade tier instead).</p>
                </div>
            </div>
            <div>
                <label for="included_extra_recording_minutes" class="block text-sm font-medium text-slate-700 mb-1">Included Recording Minutes</label>
                <input type="number" id="included_extra_recording_minutes" name="included_extra_recording_minutes" min="0" value="{{ old('included_extra_recording_minutes', $plan->included_extra_recording_minutes ?? 0) }}" class="pm-input">
                @error('included_extra_recording_minutes')
                    <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="border-t pt-4">
            <label class="block text-sm font-medium text-slate-700 mb-2">Card Color</label>
            <div class="grid grid-cols-6 gap-2 mb-3">
                @php
                    $suggestedColors = [
                        '#334155' => 'Navy — Monthly',
                        '#1D4ED8' => 'Blue — 3 Months',
                        '#7C3AED' => 'Purple — 6 Months',
                        '#0F766E' => 'Teal — Annual',
                        '#475569' => 'Slate — 3 Years',
                        '#EA580C' => 'Orange — Family & Small Team',
                        '#059669' => 'Emerald — Enterprise 5 Staff',
                        '#0891B2' => 'Cyan — Enterprise 10 Staff',
                        '#4F46E5' => 'Indigo — Enterprise 15 Staff',
                        '#D97706' => 'Amber — Enterprise 20 Staff',
                        '#E11D48' => 'Rose — Enterprise 25 Staff',
                        '#6D28D9' => 'Deep Purple — Enterprise 50 Staff',
                    ];
                    $currentColor = old('color', $plan->color ?: '#475569');
                @endphp
                @foreach ($suggestedColors as $hex => $label)
                    <button type="button" onclick="pmSelectPlanColor('{{ $hex }}')" title="{{ $label }}" aria-label="{{ $label }}"
                            class="pm-color-swatch w-9 h-9 rounded-full border-2 shadow-sm transition-transform hover:scale-110"
                            data-hex="{{ strtoupper($hex) }}"
                            style="background-color: {{ $hex }}; border-color: {{ strcasecmp($currentColor, $hex) === 0 ? '#00897B' : 'white' }};"></button>
                @endforeach
            </div>
            <div class="flex items-center gap-2">
                <input type="color" id="color_native_picker" value="{{ preg_match('/^#[0-9a-fA-F]{6}$/', $currentColor) ? $currentColor : '#475569' }}"
                       onchange="pmSelectPlanColor(this.value)" class="w-10 h-10 rounded border border-slate-300 cursor-pointer p-0.5">
                <input type="text" id="color" name="color" value="{{ $currentColor }}"
                       oninput="pmSyncPlanColorFromText(this.value)" placeholder="#475569"
                       class="pm-input font-mono text-sm" maxlength="7">
            </div>
            <p class="text-xs text-slate-400 mt-1">Pick a suggested swatch, use the color wheel, or type a hex code directly.</p>
            @error('color')
                <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="badge" class="block text-sm font-medium text-slate-700 mb-1">Badge Text</label>
            <input type="text" id="badge" name="badge" value="{{ old('badge', $plan->badge) }}" placeholder="e.g. Most Popular (optional)" class="pm-input">
        </div>

        <div class="flex items-center gap-6 border-t pt-4">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_recommended" value="1" @checked(old('is_recommended', $plan->is_recommended ?? false)) class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                <span class="text-sm text-slate-700">Mark as Recommended</span>
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_best_value" value="1" @checked(old('is_best_value', $plan->is_best_value ?? false)) class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                <span class="text-sm text-slate-700">Mark as Best Value</span>
            </label>
        </div>

        <div class="grid grid-cols-2 gap-4 border-t pt-4">
            <div>
                <label for="sort_order" class="block text-sm font-medium text-slate-700 mb-1">Sort Order</label>
                <input type="number" id="sort_order" name="sort_order" min="0"
                       value="{{ old('sort_order', $plan->sort_order) }}"
                       class="pm-input">
            </div>
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_enabled" value="1"
                           @checked(old('is_enabled', $plan->exists ? $plan->is_enabled : true))
                           class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                    <span class="text-sm text-slate-700">Enabled (visible to users)</span>
                </label>
            </div>
        </div>

        <div>
            <span class="block text-sm font-medium text-slate-700 mb-1">Included Features</span>
            <p class="text-xs text-slate-400 mb-3">
                Leave every box unchecked to include everything (the default) — only start unchecking
                boxes once you actually want this plan to be more limited than another.
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @php $selectedFeatures = old('features', $plan->features ?? []); @endphp
                @foreach (config('app_features') as $key => $feature)
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="features[]" value="{{ $key }}"
                               @checked(in_array($key, $selectedFeatures ?? []))
                               class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                        <i class="{{ $feature['icon'] }} text-slate-400 text-xs w-4 text-center" aria-hidden="true"></i>
                        {{ $feature['label'] }}
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-slate-400 mt-2">
                If NONE are checked, this plan includes everything. If ANY are checked, only those
                checked modules are included for users on this plan.
            </p>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                Save
            </button>
            <a href="{{ route('admin.subscription-plans.index') }}" class="text-sm text-slate-500 hover:underline">Cancel</a>
        </div>
    </form>

    <script>
        function pmTogglePricingFields(type) {
            document.getElementById('fields-discount').style.display = type === 'discount' ? 'block' : 'none';
            document.getElementById('fields-flat-duration').style.display = type === 'flat' ? 'block' : 'none';
            document.getElementById('fields-flat-price').style.display = (type === 'flat' || type === 'lifetime') ? 'block' : 'none';

            // Hidden fields still submit their value — only the field for
            // the ACTIVE pricing type should carry a real number, so the
            // others are blanked out to avoid saving stale leftover values
            // from a type that was previously selected.
            document.getElementById('duration_months').disabled = type !== 'discount';
            document.getElementById('discount_percent').disabled = type !== 'discount';
            document.getElementById('duration_months_flat').disabled = type !== 'flat';
            document.getElementById('flat_price').disabled = type === 'discount';
        }

        function pmToggleSeatFields(category) {
            document.getElementById('fields-seats').style.display = category === 'individual' ? 'none' : 'block';
        }

        document.addEventListener('DOMContentLoaded', function () {
            pmTogglePricingFields(document.getElementById('pricing_type').value);
            pmToggleSeatFields(document.getElementById('category').value);
        });

        function pmSelectPlanColor(hex) {
            hex = hex.toUpperCase();
            document.getElementById('color').value = hex;
            document.getElementById('color_native_picker').value = hex;
            document.querySelectorAll('.pm-color-swatch').forEach(function (swatch) {
                swatch.style.borderColor = (swatch.dataset.hex === hex) ? '#00897B' : 'white';
            });
        }

        function pmSyncPlanColorFromText(value) {
            if (/^#[0-9a-fA-F]{6}$/.test(value)) {
                pmSelectPlanColor(value);
            }
        }
    </script>
@endsection
