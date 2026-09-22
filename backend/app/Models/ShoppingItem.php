<?php

namespace App\Models;

use App\Models\Concerns\SyncsColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShoppingItem extends Model
{
    use HasUuids, SoftDeletes, SyncsColumns;

    protected $fillable = [
        'user_id',
        'shopping_list_id',
        'activity_id',
        'name',
        'quantity',
        'unit',
        'estimated_price_minor',
        'actual_price_minor',
        'priority',
        'purchased',
        'store',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'estimated_price_minor' => 'integer',
            'actual_price_minor' => 'integer',
            'purchased' => 'boolean',
        ];
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class, 'shopping_list_id');
    }
}
