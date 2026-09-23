<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'proposal' => ['required', 'array'],
            'proposal.intent' => ['required', 'string'],
            'proposal.type' => ['nullable', 'string'],
            'proposal.title' => ['nullable', 'string', 'max:255'],
            'proposal.amount_minor' => ['nullable', 'integer', 'min:0'],
            'proposal.currency' => ['nullable', 'string', 'size:3'],
            'proposal.due_on' => ['nullable', 'date'],
            'proposal.rrule' => ['nullable', 'string', 'max:512'],
            'proposal.reminder_offsets_minutes' => ['nullable', 'array'],
            'proposal.missing_fields' => ['present', 'array'],
            'proposal.ambiguities' => ['present', 'array'],
            'proposal.confidence' => ['required', 'numeric', 'between:0,1'],
            'proposal.notes' => ['nullable', 'string'],
            'proposal.requires_confirmation' => ['required', 'boolean'],
        ];
    }
}
