<?php

namespace App\Models;

use App\Enums\Carrier;
use App\Enums\ShipmentStatus;
use App\Models\Concerns\HasStatusHistory;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Отправление конкретным перевозчиком. */
class Shipment extends Model
{
    use HasStatusHistory, RecordsActivity;

    protected $guarded = ['id'];

    /** Значения по умолчанию (как в базе), чтобы новые записи сразу их видели. */
    protected $attributes = ['status' => 'not_arranged', 'cost_confirmed' => false];

    public function historyEntityType(): string
    {
        return 'shipment';
    }

    protected function casts(): array
    {
        return [
            'carrier' => Carrier::class,
            'status' => ShipmentStatus::class,
            'quoted_cost' => 'decimal:2',
            'confirmed_cost' => 'decimal:2',
            'cost_confirmed' => 'boolean',
            'weight_kg' => 'decimal:3',
            'volume_m3' => 'decimal:3',
            'planned_date' => 'date',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class);
    }

    public function toAddress(): BelongsTo
    {
        return $this->belongsTo(DeliveryAddress::class, 'to_address_id');
    }

    public function pickupPoint(): BelongsTo
    {
        return $this->belongsTo(PickupPoint::class);
    }
}
