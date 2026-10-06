<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DailyPlannerOccurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['occurrence_date' => ['sometimes', 'required', 'date']];
    }
}
