<?php

namespace App\Models;

use App\Models\Concerns\SyncsColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityLink extends Model
{
    use HasUuids, SoftDeletes, SyncsColumns;

    protected $fillable = [
        'user_id',
        'parent_activity_id',
        'child_activity_id',
        'relation',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'parent_activity_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'child_activity_id');
    }
}
