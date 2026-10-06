<?php

namespace App\Http\Requests;

use App\Models\DailyPlanItem;
use App\Services\DailyPlannerRecurrenceService;
use App\Services\DailyPlannerTaskReminderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DailyPlannerItemRequest extends FormRequest
{
    // Authorization remains at the existing route/controller boundary pending Policies.
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Web add/edit forms submit complete checkbox groups. An entirely
        // unchecked group is omitted by the browser; API PATCH omission
        // instead means retain. Presence markers can explicitly clear API
        // arrays too, without entering the persisted field allowlist.
        foreach (['repeat_days', 'reminder_channels'] as $field) {
            if (! $this->exists($field) && (! $this->is('api/*') || $this->boolean($field.'_present'))) {
                $this->merge([$field => []]);
            } elseif ($this->exists($field) && $this->input($field) === null) {
                $this->merge([$field => []]);
            }
        }

        if ($this->input('reminder_offset_minutes') === 'custom') {
            $this->merge(['reminder_offset_minutes' => null]);
        }
    }

    public function rules(): array
    {
        $creating = ! $this->route('item');
        $api = $this->is('api/*');

        return [
            'plan_date' => [$creating ? 'required' : 'nullable', 'date'],
            'title' => [$creating || ! $api ? 'required' : 'sometimes', 'required', 'string', 'max:255'],
            'personal_goal_id' => ['nullable', 'integer', Rule::exists('personal_goals', 'id')->where('user_id', $this->user()?->id)],
            'description' => ['nullable', 'string'],
            'achievements' => ['nullable', 'string'],
            'challenges' => ['nullable', 'string'],
            'priority' => [$creating || ! $api ? 'required' : 'sometimes', 'required', Rule::in(['low', 'medium', 'high'])],
            'start_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'end_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'repeat_type' => ['sometimes', 'required', Rule::in(DailyPlannerRecurrenceService::REPEAT_TYPES)],
            'repeat_days' => ['nullable', 'array'],
            'repeat_days.*' => [Rule::in(DailyPlannerRecurrenceService::WEEK_DAYS)],
            'repeat_interval' => ['nullable', 'integer', 'min:1', 'max:52'],
            'repeat_starts_on' => ['nullable', 'date'],
            'repeat_ends_on' => ['nullable', 'date'],
            'occurrence_date' => ['nullable', 'date'],
            'edit_scope' => ['nullable', Rule::in(['occurrence', 'series'])],
            'reminder_enabled' => ['nullable', 'boolean'],
            'reminder_offset_minutes' => ['nullable', 'integer', Rule::in(DailyPlannerTaskReminderService::OFFSETS)],
            'reminder_custom_at' => ['nullable', 'date'],
            'reminder_channels' => ['nullable', 'array'],
            'reminder_channels.*' => [Rule::in(['in_app', 'push', 'email'])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $item = $this->route('item');
            $overrides = [];
            if ($item instanceof DailyPlanItem && $item->isRecurring() && $this->input('edit_scope') === 'occurrence') {
                $date = $this->input('occurrence_date') ?: $this->input('plan_date') ?: $item->plan->plan_date->toDateString();
                $overrides = $this->occurrenceData($item, $date, []);
            }
            $effective = fn (string $key) => $this->exists($key)
                ? $this->input($key)
                : ($overrides[$key] ?? ($item instanceof DailyPlanItem ? $item->{$key} : null));
            $start = $effective('start_time');
            $end = $effective('end_time');
            if ($start && $end && strtotime($end) <= strtotime($start)) {
                $validator->errors()->add('end_time', 'The end time must be after the start time.');
            }

            if (($effective('repeat_type') === 'specific_days') && empty($effective('repeat_days'))) {
                $validator->errors()->add('repeat_days', 'Select at least one repeat day.');
            }

            $startsOn = $effective('repeat_starts_on') ?: $this->input('plan_date', $item?->plan?->plan_date);
            $endsOn = $effective('repeat_ends_on');
            if ($effective('repeat_type') !== 'once' && $startsOn && $endsOn && strtotime((string) $endsOn) < strtotime((string) $startsOn)) {
                $validator->errors()->add('repeat_ends_on', 'The repeat end date must be on or after the start date.');
            }

            if ($effective('reminder_enabled') && $this->exists('reminder_offset_minutes') && $this->input('reminder_offset_minutes') === null && ! $effective('reminder_custom_at')) {
                $validator->errors()->add('reminder_custom_at', 'Enter a custom reminder date and time.');
            }
        });
    }

    public function itemData(): array
    {
        $data = $this->validated();
        // _planner_form and _editing_task_id stay in request/flashed input,
        // deliberately outside this validated/persisted allowlist.
        // Web forms submit an unchecked checkbox by omission; API edits are partial.
        if (! $this->is('api/*')) {
            $data['reminder_enabled'] = $this->boolean('reminder_enabled');
        }
        // An empty delivery selection disables the reminder, preventing
        // sync's legacy empty-array default from restoring in-app/push.
        if (array_key_exists('reminder_channels', $data) && $data['reminder_channels'] === []) {
            $data['reminder_enabled'] = false;
        }
        if ($this->exists('reminder_offset_minutes') && $this->input('reminder_offset_minutes') !== null) {
            $data['reminder_custom_at'] = null;
        }

        return $data;
    }

    public function recurrenceInput(DailyPlanItem $item, array $data): array
    {
        return array_replace([
            'repeat_type' => $item->repeat_type ?: 'once',
            'repeat_days' => $item->repeat_days,
            'repeat_interval' => $item->repeat_interval,
            'repeat_starts_on' => $item->repeat_starts_on?->toDateString(),
            'repeat_ends_on' => $item->repeat_ends_on?->toDateString(),
            'recurrence_group_id' => $item->recurrence_group_id,
        ], $data);
    }

    public function occurrenceData(DailyPlanItem $item, string $date, array $data): array
    {
        $occurrence = $item->occurrences()->whereDate('occurrence_date', $date)
            ->where('user_id', $this->user()->id)->first();

        if ($occurrence) {
            foreach (['title', 'description', 'priority', 'start_time', 'end_time', 'personal_goal_id'] as $field) {
                if (! array_key_exists($field, $data)) {
                    $data[$field] = $occurrence->{$field.'_override'};
                }
            }
        }

        return $data;
    }
}
