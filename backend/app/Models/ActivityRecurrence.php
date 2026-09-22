<?php

namespace App\Models;

use App\Models\Concerns\SyncsColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityRecurrence extends Model
{
    use HasUuids, SoftDeletes, SyncsColumns;

    protected $fillable = [
        'activity_id',
        'user_id',
        'rrule',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
