<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'timezone' => $this->preference?->timezone ?? 'Africa/Lagos',
            'currency' => $this->preference?->currency ?? 'NGN',
            'theme' => $this->preference?->theme ?? 'system',
            'morning_summary_enabled' => (bool) ($this->preference?->morning_summary_enabled ?? false),
            'morning_summary_time' => substr((string) ($this->preference?->morning_summary_time ?? '07:00:00'), 0, 5),
            'due_soon_days' => $this->preference?->due_soon_days ?? 3,
            'reminder_time' => substr((string) ($this->preference?->reminder_time ?? '09:00:00'), 0, 5),
        ];
    }
}
