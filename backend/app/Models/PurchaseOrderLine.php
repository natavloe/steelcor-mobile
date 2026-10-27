<?php

namespace App\Models;

use App\Enums\Unit;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** ВНУТРЕННЕЕ: строка закупки. */
class PurchaseOrderLine extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    /** Значения по умолчанию (как в базе), чтобы новые записи сразу их видели. */
    protected $attributes = ['price_changed' => false];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit' => Unit::class,
            'purchase_price' => 'decimal:2',
            'price_changed' => 'boolean',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }
}
