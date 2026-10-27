<?php

namespace App\Models;

use App\Enums\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Строка предложения. После принятия предложения не меняется. */
class OfferLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'line_type' => 'string',
            'qty' => 'decimal:4',
            'unit' => Unit::class,
            'qty_base_t' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'amount_net' => 'decimal:2',
            'amount_vat' => 'decimal:2',
            'amount_total' => 'decimal:2',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function cost(): HasOne
    {
        return $this->hasOne(OfferLineCost::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }
}
