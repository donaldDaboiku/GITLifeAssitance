<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'relationship' => $this->relationship,
            'birthday' => $this->birthday?->toDateString(),
            'anniversary' => $this->anniversary?->toDateString(),
            'notes' => $this->notes,
            'version' => $this->version,
        ];
    }
}
