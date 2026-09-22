<?php

namespace App\Models;

use App\Models\Concerns\SyncsColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationDelivery extends Model
{
    use HasUuids, SoftDeletes, SyncsColumns;

    protected $fillable = [
        'user_id',
        'occurrence_id',
        'offset_minutes',
        'delivery_key',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'offset_minutes' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ActivityOccurrence::class, 'occurrence_id');
    }
}
