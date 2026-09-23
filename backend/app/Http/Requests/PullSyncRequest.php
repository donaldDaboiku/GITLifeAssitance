<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PullSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'device_id' => ['nullable', 'uuid'],
        ];
    }
}
