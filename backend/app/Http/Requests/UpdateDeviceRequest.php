<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'push_token' => ['nullable', 'string', 'max:4096'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'active' => ['sometimes', 'boolean'],
            'last_sync_at' => ['sometimes', 'boolean'],
        ];
    }
}
