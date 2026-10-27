<?php

namespace App\Models;

use App\Enums\Unit;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ВНУТРЕННЕЕ: предложение поставщика (закупочная цена, срок). */
class SupplierOffer extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'unit' => Unit::class,
            'available_qty' => 'decimal:4',
            'valid_until' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
