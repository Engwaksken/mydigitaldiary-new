<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Admin\AdminUserController;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Users page Bulk Manage. scope=ids (default) acts on the ticked ids[];
 * scope=filtered acts on every user matching filter[...] (the same query as
 * the list) and is only allowed for the sending actions, never for role,
 * subscription, suspend, reactivate or delete.
 */
class BulkUserRequest extends UpdateUserSubscriptionRequest
{
    public const SCOPE_IDS = 'ids';

    public const SCOPE_FILTERED = 'filtered';

    /** Actions that accept scope=filtered. */
    public const FILTERED_ACTIONS = ['appreciation', 'announcement'];

    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'scope' => ['nullable', Rule::in([self::SCOPE_IDS, self::SCOPE_FILTERED])],
            'ids' => ['exclude_if:scope,'.self::SCOPE_FILTERED, 'required', 'array', 'min:1'],
            'ids.*' => ['exclude_if:scope,'.self::SCOPE_FILTERED, 'integer', 'exists:users,id'],
            'filter' => ['nullable', 'array'],
            'filter.search' => ['nullable', 'string', 'max:255'],
            'filter.subscription_status' => ['nullable', 'string', 'max:30'],
            'filter.verification' => ['nullable', Rule::in(['verified', 'unverified'])],
            'filter.appreciation' => ['nullable', Rule::in(['sent_this_week', 'not_sent_this_week'])],
            'action' => ['required', Rule::in(['role', 'subscription', 'suspend', 'reactivate', 'delete', 'appreciation', 'announcement'])],
            'role' => ['nullable', Rule::in(array_keys(AdminUserController::availableRoles()))],
            'subscription_status' => ['nullable', Rule::in(['active', 'trial', 'inactive', 'expired', 'suspended', 'cancelled'])],
            'reason' => ['nullable', 'string', 'max:1000'],
            'confirmation' => ['nullable', 'string', 'max:20'],
        ]);
    }

    public function isFiltered(): bool
    {
        return $this->input('scope') === self::SCOPE_FILTERED;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->isFiltered() && ! in_array($this->input('action'), self::FILTERED_ACTIONS, true)) {
                $validator->errors()->add('scope',
                    '"Select all users matching these filters" only works for Send Appreciation Email and Send Announcement. '
                    .'For this action, tick the users individually on the page.');

                return;
            }

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
