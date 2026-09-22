<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShoppingItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'estimated_price_minor' => ['nullable', 'integer', 'min:0'],
            'actual_price_minor' => ['nullable', 'integer', 'min:0'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'purchased' => ['nullable', 'boolean'],
            'store' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
