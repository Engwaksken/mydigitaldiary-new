<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Admin\AdminUserController;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class BulkUserRequest extends UpdateUserSubscriptionRequest
{
    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:users,id'],
            'action' => ['required', Rule::in(['role', 'subscription', 'suspend', 'reactivate', 'delete'])],
            'role' => ['nullable', Rule::in(array_keys(AdminUserController::availableRoles()))],
            'subscription_status' => ['nullable', Rule::in(['active', 'trial', 'inactive', 'expired', 'suspended', 'cancelled'])],
            'reason' => ['nullable', 'string', 'max:1000'],
            'confirmation' => ['nullable', 'string', 'max:20'],
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty() || $this->input('action') !== 'subscription') {
                return;
            }

            // Blank bulk form inputs mean "leave unchanged", not "clear".
            foreach (User::query()->whereIn('id', $this->input('ids', []))->get() as $user) {
                $start = $this->filled('subscription_started_at') ? Carbon::parse($this->input('subscription_started_at'))->startOfDay() : $user->subscription_started_at;
                $end = $this->filled('subscription_expires_at') ? Carbon::parse($this->input('subscription_expires_at'))->endOfDay() : $user->subscription_expires_at;
                if ($start && $end && strtotime((string) $end) < strtotime((string) $start)) {
                    $validator->errors()->add('subscription_expires_at', 'Subscription expiry date cannot be before the start date for any selected user.');
                    break;
                }
            }
        });
    }
}
