<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Юрлицо-продавец. */
class SellerEntity extends Model
{
    protected $guarded = ['id'];

    /** Значения по умолчанию (как в базе), чтобы новые записи сразу их видели. */
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function requisites(): HasMany
    {
        return $this->hasMany(SellerRequisites::class);
    }

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }
}
