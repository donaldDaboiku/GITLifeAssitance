<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VisitFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'choices' => ['required', 'array', 'min:1'],
            'choices.*' => ['in:follow_up_task,quotation_reminder,report_task,next_visit'],
            'next_visit_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
