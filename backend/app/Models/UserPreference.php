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
        'user_id',
        'timezone',
        'reminder_time',
        'due_soon_days',
        'currency',
        'theme',
        'morning_summary_enabled',
        'morning_summary_time',
        'privacy_notice_accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'morning_summary_enabled' => 'boolean',
            'due_soon_days' => 'integer',
            'privacy_notice_accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
