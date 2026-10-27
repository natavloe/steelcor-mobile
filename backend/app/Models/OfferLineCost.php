<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ВНУТРЕННЕЕ: себестоимость строки предложения. */
class OfferLineCost extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'purchase_amount' => 'decimal:2',
            'logistics_cost' => 'decimal:2',
            'handling_cost' => 'decimal:2',
            'other_costs' => 'decimal:2',
            'margin_amount' => 'decimal:2',
            'margin_percent' => 'decimal:2',
        ];
    }

    public function offerLine(): BelongsTo
    {
        return $this->belongsTo(OfferLine::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierOffer(): BelongsTo
    {
        return $this->belongsTo(SupplierOffer::class);
    }
}
