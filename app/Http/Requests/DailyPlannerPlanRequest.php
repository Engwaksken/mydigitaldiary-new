<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DailyPlannerPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Existing authenticated route boundary; Policy integration pending.
    }

    public function rules(): array
    {
        return [
            'plan_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'achievements' => ['nullable', 'string'],
            'challenges' => ['nullable', 'string'],
        ];
    }
}
