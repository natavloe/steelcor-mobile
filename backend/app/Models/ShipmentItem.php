<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Что едет в отправлении. */
class ShipmentItem extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function offerLine(): BelongsTo
    {
        return $this->belongsTo(OfferLine::class);
    }
}
