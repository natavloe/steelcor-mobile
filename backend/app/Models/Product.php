<?php

namespace App\Models;

use App\Enums\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Позиция номенклатуры. */
class Product extends Model
{
    protected $guarded = ['id'];

    /** Значения по умолчанию (как в базе), чтобы новые записи сразу их видели. */
    protected $attributes = ['attrs' => '{}', 'is_oversize' => false, 'is_published' => true];

    protected function casts(): array
    {
        return [
            'attrs' => 'array',
            'is_oversize' => 'boolean',
            'is_published' => 'boolean',
            'base_unit' => Unit::class,
            'weight_per_m' => 'decimal:4',
            'weight_per_pc' => 'decimal:4',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function regionProducts(): HasMany
    {
        return $this->hasMany(RegionProduct::class);
    }
}
