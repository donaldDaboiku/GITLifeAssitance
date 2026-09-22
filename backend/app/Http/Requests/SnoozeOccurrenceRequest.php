<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SnoozeOccurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'preset' => ['required_without:until', 'nullable', 'in:15min,1hour,tomorrow,next_week'],
            'until' => ['required_without:preset', 'nullable', 'date'],
        ];
    }
}
