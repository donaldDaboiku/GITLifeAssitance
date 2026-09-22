<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateActivityRequest extends StoreActivityRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['id'], $rules['type']);

        $type = $this->route('activity')?->type;
        $rules['payment'] = [Rule::requiredIf($type === 'payment'), 'nullable', 'array'];
        $rules['payment.amount_minor'] = [Rule::requiredIf($type === 'payment'), 'integer', 'min:0'];

        return $rules;
    }
}
