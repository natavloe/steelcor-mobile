<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Регион (город). */
class Region extends Model
{
    protected $guarded = ['id'];

    /** Значения по умолчанию (как в базе), чтобы новые записи сразу их видели. */
    protected $attributes = ['is_active' => false, 'sort' => 0, 'timezone' => 'Europe/Moscow'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function sellerEntity(): BelongsTo
    {
        return $this->belongsTo(SellerEntity::class);
    }

    public function pickupPoints(): HasMany
    {
        return $this->hasMany(PickupPoint::class);
    }

    public function regionProducts(): HasMany
    {
        return $this->hasMany(RegionProduct::class);
    }
}
