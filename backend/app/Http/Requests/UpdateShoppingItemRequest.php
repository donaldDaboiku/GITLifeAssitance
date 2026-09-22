<?php

namespace App\Http\Requests;

class UpdateShoppingItemRequest extends StoreShoppingItemRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['name'] = ['sometimes', 'required', 'string', 'max:255'];

        return $rules;
    }
}
