<?php

namespace App\Models;

use App\Models\Concerns\SyncsColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityReminder extends Model
{
    use HasUuids, SoftDeletes, SyncsColumns;

    protected $fillable = [
        'activity_id',
        'user_id',
        'offset_minutes',
    ];

    protected function casts(): array
    {
        return [
            'offset_minutes' => 'integer',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
