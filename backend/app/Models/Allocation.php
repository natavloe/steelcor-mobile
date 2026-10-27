<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ВНУТРЕННЕЕ: распределение закупки на строки заказов клиентов. */
class Allocation extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
        ];
    }

    public function offerLine(): BelongsTo
    {
        return $this->belongsTo(OfferLine::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }
}
