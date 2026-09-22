<?php

namespace App\Models;

use App\Models\Concerns\SyncsColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use HasUuids, SoftDeletes, SyncsColumns;

    public const RELATIONS = [
        'recurrence',
        'reminders',
        'paymentDetail',
        'task',
        'occurrences',
        'user.preference',
    ];

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'description',
        'category',
        'priority',
        'timezone',
        'location',
        'contact_id',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recurrence(): HasOne
    {
        return $this->hasOne(ActivityRecurrence::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(ActivityReminder::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(ActivityOccurrence::class)->orderBy('due_at')->chaperone();
    }

    public function paymentDetail(): HasOne
    {
        return $this->hasOne(PaymentDetail::class);
    }

    public function task(): HasOne
    {
        return $this->hasOne(Task::class);
    }
}
