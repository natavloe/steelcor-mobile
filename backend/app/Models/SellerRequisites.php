<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Версия реквизитов продавца. В документы попадает только утверждённая. */
class SellerRequisites extends Model
{
    use RecordsActivity;

    protected $table = 'seller_requisites';
    protected $guarded = ['id'];

    /** Значения по умолчанию (как в базе), чтобы новые записи сразу их видели. */
    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'valid_from' => 'date',
            'approved_at' => 'datetime',
            'vat_rate_default' => 'decimal:2',
        ];
    }

    public function sellerEntity(): BelongsTo
    {
        return $this->belongsTo(SellerEntity::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'created_by');
    }
}
