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
            'contact_id' => $this->contact_id,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
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
            'task' => $this->when($this->relationLoaded('task') && $this->task, fn () => [
                'follow_up_after_days' => $this->task->follow_up_after_days,
                'follow_up_rule' => $this->task->follow_up_rule,
                'subtasks' => $this->task->relationLoaded('subtasks')
                    ? $this->task->subtasks->map(fn ($subtask) => [
                        'id' => $subtask->id,
                        'title' => $subtask->title,
                        'completed' => $subtask->completed,
                        'position' => $subtask->position,
                    ])
                    : [],
            ]),
            'links' => $this->when($this->relationLoaded('childLinks'), fn () => $this->childLinks->map(fn ($link) => [
                'id' => $link->id,
                'relation' => $link->relation,
                'child_activity_id' => $link->child_activity_id,
                'child_title' => $link->child?->title,
                'child_type' => $link->child?->type,
            ])),
            'occurrences' => OccurrenceResource::collection($this->whenLoaded('occurrences')),
        ];
    }
}
