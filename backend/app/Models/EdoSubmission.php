<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Отправка документа в ЭДО. */
class EdoSubmission extends Model
{
    protected $guarded = ['id'];

    /** Значения по умолчанию (как в базе), чтобы новые записи сразу их видели. */
    protected $attributes = ['status' => 'queued', 'attempts' => 0];

    protected function casts(): array
    {
        return [
            'last_sync_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
