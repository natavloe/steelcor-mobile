<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Адрес доставки организации. */
class DeliveryAddress extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
