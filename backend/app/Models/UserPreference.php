<?php

namespace App\Models;

use App\Models\Concerns\SyncsColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserPreference extends Model
{
    use HasUuids, SoftDeletes, SyncsColumns;

    protected $fillable = [
        'timezone',
        'reminder_time',
        'due_soon_days',
        'currency',
        'theme',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
