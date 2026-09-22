<?php

namespace App\Models\Concerns;

trait SyncsColumns
{
    public function initializeSyncsColumns(): void
    {
        $this->mergeCasts([
            'version' => 'integer',
        ]);
    }

    protected static function bootSyncsColumns(): void
    {
        static::creating(function ($model) {
            if (! $model->version) {
                $model->version = 1;
            }
        });

        static::updating(function ($model) {
            if (! $model->isDirty('version')) {
                $model->version = ((int) $model->version) + 1;
            }
        });
    }
}
