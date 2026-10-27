<?php

namespace App\Models;

use App\Enums\Availability;
use App\Enums\Unit;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Условия продажи позиции в регионе (видит клиент). */
class RegionProduct extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    /** Значения по умолчанию (как в базе), чтобы новые записи сразу их видели. */
    protected $attributes = ['vat_included' => true, 'is_visible' => true];

    protected function casts(): array
    {
        return [
            'availability' => Availability::class,
            'public_price' => 'decimal:2',
            'price_unit' => Unit::class,
            'vat_included' => 'boolean',
            'price_valid_until' => 'date',
            'is_visible' => 'boolean',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
