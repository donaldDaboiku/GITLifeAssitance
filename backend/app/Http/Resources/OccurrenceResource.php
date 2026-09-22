<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OccurrenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'activity_id' => $this->activity_id,
            'due_at' => $this->due_at?->toIso8601String(),
            'due_local_date' => $this->due_local_date?->toDateString(),
            'status' => $this->status,
            'computed_status' => $this->computedStatus(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'snoozed_until' => $this->snoozed_until?->toIso8601String(),
            'version' => $this->version,
        ];
    }
}
