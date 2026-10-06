<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Existing auth/admin route middleware applies; a User Policy is still needed.
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('subscription_status'))) {
            $status = strtolower(trim($this->input('subscription_status')));
            $this->merge(['subscription_status' => match ($status) {
                'trialing' => 'trial',
                'canceled' => 'cancelled',
                default => $status,
            }]);
        }
    }

    public function rules(): array
    {
        // _managed_user_id is UI-only input. Laravel flashes request input
        // on validation failure, not just this validated field allowlist.
        return [
            'subscription_status' => ['required', 'string', Rule::in(['active', 'trial', 'inactive', 'expired', 'suspended', 'cancelled'])],
            'subscription_plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'subscription_started_at' => ['nullable', 'date'],
            'subscription_expires_at' => ['nullable', 'date'],
            'trial_ends_at' => ['nullable', 'date'],
        ];
    }
}
