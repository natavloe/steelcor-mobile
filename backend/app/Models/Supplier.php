<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** ВНУТРЕННЕЕ: поставщик. */
class Supplier extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    /** Значения по умолчанию (как в базе), чтобы новые записи сразу их видели. */
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'contacts' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class, 'supplier_regions');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(SupplierOffer::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
