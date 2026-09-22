<?php

namespace App\Models;

use App\Models\Concerns\SyncsColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShoppingList extends Model
{
    use HasUuids, SoftDeletes, SyncsColumns;

    protected $fillable = [
        'user_id',
        'activity_id',
        'name',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShoppingItem::class);
    }

    public function estimatedTotalMinor(): int
    {
        return (int) $this->items->sum(fn (ShoppingItem $item) => $item->estimated_price_minor ?? 0);
    }

    public function actualTotalMinor(): int
    {
        return (int) $this->items->sum(fn (ShoppingItem $item) => $item->actual_price_minor ?? 0);
    }

    public function remainingTotalMinor(): int
    {
        return (int) $this->items
            ->where('purchased', false)
            ->sum(fn (ShoppingItem $item) => $item->estimated_price_minor ?? 0);
    }
}
