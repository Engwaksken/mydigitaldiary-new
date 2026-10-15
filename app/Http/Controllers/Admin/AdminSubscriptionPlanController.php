<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSubscriptionPlanController extends Controller
{
    public function index(): View
    {
        $plans = SubscriptionPlan::orderBy('category')
            ->orderBy('individual_tier')
            ->orderBy('sort_order')
            ->paginate(15)
            ->withQueryString();

        $monthlyPrice = (float) SiteSetting::current()->monthly_price;

        return view('admin.subscription-plans.index', compact('plans', 'monthlyPrice'));
    }

    public function create(): View
    {
        $this->authorizePlanPricingManagement();

        return view('admin.subscription-plans.form', [
            'plan' => new SubscriptionPlan,
            'settings' => SiteSetting::current(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePlanPricingManagement();

        $data = $this->validated($request);

        SubscriptionPlan::create($data);

        return redirect()
            ->route('admin.subscription-plans.index')
            ->with('success', 'Plan added.');
    }

    public function edit(SubscriptionPlan $subscriptionPlan): View
    {
        $this->authorizePlanPricingManagement();

        return view('admin.subscription-plans.form', [
            'plan' => $subscriptionPlan,
            'settings' => SiteSetting::current(),
        ]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $this->authorizePlanPricingManagement();

        // Some older/partial edit forms did not submit these plan-definition
        // fields. Preserve the current values before validation so an update
        // cannot fail merely because the browser submitted a partial form.
        $request->merge([
            'category' => $request->input('category', $subscriptionPlan->category ?: 'individual'),
            'individual_tier' => $request->input('individual_tier', $subscriptionPlan->individual_tier ?: 'starter'),
            'monthly_transcription_minutes' => $request->input('monthly_transcription_minutes', $subscriptionPlan->monthly_transcription_minutes ?? 60),
            'pricing_type' => $request->input('pricing_type', $this->pricingTypeFor($subscriptionPlan)),
            'color' => $request->input('color', $subscriptionPlan->color ?: '#475569'),
        ]);

        $data = $this->validated($request, $subscriptionPlan);

        $subscriptionPlan->update($data);

        return redirect()
            ->route('admin.subscription-plans.index')
            ->with('success', 'Plan updated successfully.');
    }

    public function toggle(SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $this->authorizePlanPricingManagement();

        $subscriptionPlan->update(['is_enabled' => ! $subscriptionPlan->is_enabled]);

        return back()->with(
            'success',
            $subscriptionPlan->name . ' is now ' . ($subscriptionPlan->is_enabled ? 'enabled' : 'disabled') . '.'
        );
    }

    public function destroy(SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $this->authorizePlanPricingManagement();

        $subscriptionPlan->delete();

        return redirect()
            ->route('admin.subscription-plans.index')
            ->with('success', 'Plan removed.');
    }

    private function pricingTypeFor(SubscriptionPlan $plan): string
    {
        if (is_null($plan->duration_months)) {
            return 'lifetime';
        }

        return ! is_null($plan->flat_price) ? 'flat' : 'discount';
    }

    private function authorizePlanPricingManagement(): void
    {
        $user = request()->user();

        abort_unless(
            in_array((string) ($user?->system_role ?? ''), ['super_admin'], true)
                || (string) ($user?->role ?? '') === 'super_admin',
            403,
            'Only super administrators can create or change subscription plans and pricing.'
        );
    }

    private function validated(Request $request, ?SubscriptionPlan $existingPlan = null): array
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:255', 'alpha_dash'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:individual,family_team,organization'],
            'individual_tier' => ['nullable', 'required_if:category,individual', 'in:starter,gold,premium,custom'],
            'pricing_type' => ['required', 'in:discount,flat,lifetime'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'duration_months_flat' => ['nullable', 'integer', 'min:1', 'max:120'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'flat_price' => ['nullable', 'numeric', 'min:0'],
            'included_seats' => ['nullable', 'integer', 'min:1'],
            'additional_user_price' => ['nullable', 'numeric', 'min:0'],
            'included_extra_recording_minutes' => ['nullable', 'integer', 'min:0'],
            'transcription_capacity_mb' => ['nullable', 'integer', 'min:1', 'max:102400'],
            'monthly_transcription_minutes' => ['required', 'integer', 'min:0', 'max:1000000'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'badge' => ['nullable', 'string', 'max:50'],
            'is_recommended' => ['nullable', 'boolean'],
            'is_best_value' => ['nullable', 'boolean'],
            'is_enabled' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'in:' . implode(',', array_keys(config('app_features')))],
        ]);

        [$durationMonths, $discountPercent, $flatPrice] = match ($data['pricing_type']) {
            'discount' => [
                $data['duration_months'] ?? $existingPlan?->duration_months,
                $data['discount_percent'] ?? $existingPlan?->discount_percent ?? 0,
                null,
            ],
            'flat' => [
                $data['duration_months_flat'] ?? $existingPlan?->duration_months ?? 1,
                0,
                $data['flat_price'] ?? $existingPlan?->flat_price ?? 0,
            ],
            'lifetime' => [
                null,
                0,
                $data['flat_price'] ?? $existingPlan?->flat_price ?? 0,
            ],
        };

        return [
            'key' => $data['key'],
            'name' => $data['name'],
            'category' => $data['category'],
            'individual_tier' => $data['category'] === 'individual'
                ? ($data['individual_tier'] ?? $existingPlan?->individual_tier ?? 'starter')
                : null,
            'duration_months' => $durationMonths,
            'discount_percent' => $discountPercent,
            'flat_price' => $flatPrice,
            'included_seats' => $data['category'] === 'individual'
                ? 1
                : ($data['included_seats'] ?? $existingPlan?->included_seats ?? 1),
            'additional_user_price' => $data['category'] === 'individual'
                ? null
                : ($data['additional_user_price'] ?? $existingPlan?->additional_user_price),
            'included_extra_recording_minutes' => $data['included_extra_recording_minutes']
                ?? $existingPlan?->included_extra_recording_minutes
                ?? 0,
            'transcription_capacity_mb' => $data['transcription_capacity_mb'] ?? $existingPlan?->transcription_capacity_mb ?? 30,
            'monthly_transcription_minutes' => $data['monthly_transcription_minutes'],
            'color' => strtoupper($data['color']),
            'badge' => array_key_exists('badge', $data)
                ? $data['badge']
                : $existingPlan?->badge,
            'is_recommended' => $request->has('is_recommended')
                ? $request->boolean('is_recommended')
                : ($existingPlan?->is_recommended ?? false),
            'is_best_value' => $request->has('is_best_value')
                ? $request->boolean('is_best_value')
                : ($existingPlan?->is_best_value ?? false),
            'is_enabled' => $request->has('is_enabled')
                ? $request->boolean('is_enabled')
                : ($existingPlan?->is_enabled ?? true),
            'sort_order' => $data['sort_order'] ?? $existingPlan?->sort_order ?? 0,
            'features' => $request->has('features')
                ? (! empty($data['features']) ? $data['features'] : null)
                : $existingPlan?->features,
        ];
    }
}
