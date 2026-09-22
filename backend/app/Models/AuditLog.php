<?php

namespace App\Models;

use App\Models\Concerns\SyncsColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuditLog extends Model
{
    use HasUuids, SoftDeletes, SyncsColumns;

    protected $fillable = [
        'user_id',
        'action',
        'metadata',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public static function write(?string $userId, string $action, array $metadata = []): void
    {
        static::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
        ]);
    }
}
