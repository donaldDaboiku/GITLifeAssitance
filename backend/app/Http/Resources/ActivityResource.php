<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'priority' => $this->priority,
            'timezone' => $this->timezone,
            'location' => $this->location,
            'notes' => $this->notes,
            'version' => $this->version,
            'rrule' => $this->recurrence?->rrule,
            'reminder_offsets_minutes' => $this->whenLoaded(
                'reminders',
                fn () => $this->reminders->pluck('offset_minutes')->values(),
            ),
            'payment' => $this->when($this->relationLoaded('paymentDetail') && $this->paymentDetail, fn () => [
                'amount_minor' => $this->paymentDetail->amount_minor,
                'currency' => $this->paymentDetail->currency,
                'amount_label' => 'expected',
                'amount_display' => Money::format($this->paymentDetail->amount_minor, $this->paymentDetail->currency),
                'payment_category' => $this->paymentDetail->payment_category,
                'payment_method' => $this->paymentDetail->payment_method,
                'account_reference' => $this->paymentDetail->account_reference,
            ]),
            'occurrences' => OccurrenceResource::collection($this->whenLoaded('occurrences')),
        ];
    }
}
