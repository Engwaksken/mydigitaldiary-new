<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTranscriptionCapacityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            // Bound operator input to prevent accidental/unbounded storage and
            // downstream upload limits. Null restores the effective 30 MB default.
            'transcription_capacity_mb' => ['present', 'nullable', 'integer', 'min:50', 'max:10240'],
        ];
    }
}
