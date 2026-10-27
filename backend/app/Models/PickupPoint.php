<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Пункт самовывоза или отгрузки. */
class PickupPoint extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
