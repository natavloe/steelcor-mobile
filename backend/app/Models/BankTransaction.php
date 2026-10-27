<?php

namespace App\Models;

use App\Enums\MatchStatus;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Движение по расчётному счёту из выписки банка. */
class BankTransaction extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'match_status' => MatchStatus::class,
            'op_date' => 'date',
            'amount' => 'decimal:2',
            'raw' => 'array',
        ];
    }

    public function sellerEntity(): BelongsTo
    {
        return $this->belongsTo(SellerEntity::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
