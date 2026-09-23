<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'timezone' => ['sometimes', 'timezone'],
            'reminder_time' => ['sometimes', 'date_format:H:i'],
            'due_soon_days' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'theme' => ['sometimes', 'in:system,light,dark'],
            'morning_summary_enabled' => ['sometimes', 'boolean'],
            'morning_summary_time' => ['sometimes', 'date_format:H:i'],
        ];
    }
}
