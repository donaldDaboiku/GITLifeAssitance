<?php

namespace App\Models;

use App\Models\Concerns\SyncsColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\PersonalAccessToken;

class Device extends Model
{
    use HasUuids, SoftDeletes, SyncsColumns;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'last_sync_at',
        'app_version',
        'push_token',
        'active',
        'access_token_id',
    ];

    protected function casts(): array
    {
        return [
            'last_sync_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'access_token_id');
    }
}
