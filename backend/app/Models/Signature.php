<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Факт подписания документа стороной. */
class Signature extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
            'certificate_info' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
