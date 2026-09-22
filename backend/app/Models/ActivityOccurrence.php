<?php

namespace App\Models;

use App\Models\Concerns\SyncsColumns;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityOccurrence extends Model
{
    use HasUuids, SoftDeletes, SyncsColumns;

    protected $fillable = [
        'activity_id',
        'user_id',
        'due_at',
        'due_local_date',
        'status',
        'completed_at',
        'snoozed_until',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'due_local_date' => 'date',
            'completed_at' => 'datetime',
            'snoozed_until' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Upcoming, Due Soon, Due Today, and Overdue are derived. They are never stored.
     */
    public function computedStatus(): string
    {
        if ($this->status !== 'pending') {
            return $this->status;
        }

        $timezone = $this->activity->timezone ?: 'Africa/Lagos';
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $dueDay = CarbonImmutable::parse($this->due_local_date->toDateString(), $timezone)->startOfDay();

        if ($dueDay->lt($today)) {
            return 'overdue';
        }

        if ($dueDay->equalTo($today)) {
            return 'due_today';
        }

        $soonDays = $this->activity->user?->preference?->due_soon_days ?? 3;

        if ($dueDay->lessThanOrEqualTo($today->addDays($soonDays))) {
            return 'due_soon';
        }

        return 'upcoming';
    }
}
