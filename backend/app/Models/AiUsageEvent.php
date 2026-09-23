<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'kind',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
