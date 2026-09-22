<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $estimated = $this->estimatedTotalMinor();
        $actual = $this->actualTotalMinor();
        $remaining = $this->remainingTotalMinor();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'notes' => $this->notes,
            'activity_id' => $this->activity_id,
            'totals' => [
                'estimated_minor' => $estimated,
                'actual_minor' => $actual,
                'remaining_minor' => $remaining,
                'estimated_display' => Money::format($estimated),
                'actual_display' => Money::format($actual),
                'remaining_display' => Money::format($remaining),
                'label' => 'expected',
            ],
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'estimated_price_minor' => $item->estimated_price_minor,
                'actual_price_minor' => $item->actual_price_minor,
                'priority' => $item->priority,
                'purchased' => $item->purchased,
                'store' => $item->store,
                'notes' => $item->notes,
            ])),
        ];
    }
}
