<?php

namespace App\Models;

use App\Models\Concerns\SyncsColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentDetail extends Model
{
    use HasUuids, SoftDeletes, SyncsColumns;

    protected $fillable = [
        'activity_id',
        'user_id',
        'amount_minor',
        'currency',
        'payment_category',
        'payment_method',
        'account_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'account_reference' => 'encrypted',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
