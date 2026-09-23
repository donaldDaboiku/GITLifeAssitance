<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'suggestion_id' => ['required', 'string', 'max:100'],
            'payload' => ['required', 'array'],
            'payload.type' => ['required', 'string'],
            'payload.title' => ['required', 'string', 'max:255'],
            'payload.due_on' => ['required', 'date'],
            'payload.timezone' => ['nullable', 'timezone'],
            'payload.contact_id' => ['nullable', 'uuid'],
            'payload.priority' => ['nullable', 'string'],
            'payload.reminder_offsets_minutes' => ['nullable', 'array'],
            'payload.notes' => ['nullable', 'string'],
            'payload.source_activity_id' => ['nullable', 'uuid'],
        ];
    }
}
