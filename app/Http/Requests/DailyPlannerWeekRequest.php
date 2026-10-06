<?php

namespace App\Http\Requests;

use App\Services\DailyPlannerRecurrenceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DailyPlannerWeekRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'week_start' => ['required', 'date'],
            'tasks' => ['required', 'array', 'min:1', 'max:30'],
            'tasks.*.title' => ['required', 'string', 'max:255'],
            'tasks.*.description' => ['nullable', 'string'],
            'tasks.*.priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'tasks.*.start_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'tasks.*.end_time' => ['nullable', 'date_format:H:i,H:i:s', 'after:tasks.*.start_time'],
            'tasks.*.personal_goal_id' => ['nullable', 'integer', Rule::exists('personal_goals', 'id')->where('user_id', $this->user()?->id)],
            'tasks.*.days' => ['required', 'array', 'min:1'],
            'tasks.*.days.*' => [Rule::in(DailyPlannerRecurrenceService::WEEK_DAYS)],
            'tasks.*.repeat_weekly' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'tasks.*.title.required' => 'Every week task needs a name.',
            'tasks.*.days.required' => 'Tick at least one day for every week task.',
            'tasks.*.end_time.after' => 'A week task\'s end time must be after its start time.',
        ];
    }
}
