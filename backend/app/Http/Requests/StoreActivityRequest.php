<?php

namespace App\Http\Requests;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use RRule\RRule;

class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'uuid', 'unique:activities,id'],
            'type' => ['required', 'in:payment,task'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'timezone' => ['nullable', 'timezone:all'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'due_on' => ['required', 'date_format:Y-m-d'],
            'rrule' => ['nullable', 'string', 'max:512', $this->rruleRule()],
            'reminder_offsets_minutes' => ['nullable', 'array', 'max:10'],
            'reminder_offsets_minutes.*' => ['integer', 'min:0', 'max:525600'],
            'payment' => ['required_if:type,payment', 'nullable', 'array'],
            'payment.amount_minor' => ['required_if:type,payment', 'integer', 'min:0'],
            'payment.currency' => ['nullable', 'string', 'size:3'],
            'payment.payment_category' => ['nullable', 'string', 'max:100'],
            'payment.payment_method' => ['nullable', 'string', 'max:100'],
            'payment.account_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function rruleRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            try {
                new RRule($value, new DateTimeImmutable('2026-01-01 09:00:00'));
            } catch (\Throwable) {
                $fail('The recurrence rule is not a valid RRULE.');
            }
        };
    }
}
