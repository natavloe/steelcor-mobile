<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\HasStatusHistory;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** ВНУТРЕННЕЕ: заказ поставщику. */
class PurchaseOrder extends Model
{
    use HasStatusHistory, RecordsActivity;

    protected $guarded = ['id'];

    /** Значения по умолчанию (как в базе), чтобы новые записи сразу их видели. */
    protected $attributes = ['status' => 'draft', 'total' => 0, 'supplier_payment_status' => 'not_paid'];

    public function historyEntityType(): string
    {
        return 'purchase_order';
    }

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'total' => 'decimal:2',
            'expected_ready_date' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'created_by');
    }
}
