<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShoppingListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['nullable', 'array'],
            'items.*.name' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.estimated_price_minor' => ['nullable', 'integer', 'min:0'],
            'items.*.actual_price_minor' => ['nullable', 'integer', 'min:0'],
            'items.*.priority' => ['nullable', 'in:low,normal,high,urgent'],
            'items.*.purchased' => ['nullable', 'boolean'],
            'items.*.store' => ['nullable', 'string', 'max:255'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
