<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ParseAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }
}
