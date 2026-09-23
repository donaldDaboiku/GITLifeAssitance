<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PushSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['nullable', 'uuid'],
            'mutations' => ['required', 'array', 'min:1', 'max:100'],
            'mutations.*.client_mutation_id' => ['required', 'uuid'],
            'mutations.*.entity' => ['required', Rule::in(['activity', 'occurrence', 'contact', 'shopping_list', 'shopping_item'])],
            'mutations.*.op' => ['required', Rule::in(['upsert', 'delete'])],
            'mutations.*.id' => ['required', 'uuid'],
            'mutations.*.data' => ['nullable', 'array'],
            'mutations.*.reopen' => ['sometimes', 'boolean'],
            'mutations.*.base_updated_at' => ['nullable', 'date'],
        ];
    }
}
