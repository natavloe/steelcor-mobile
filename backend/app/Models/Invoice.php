<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Счёт на оплату. Оплаченность считается по привязанным платежам банка. */
class Invoice extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'amount' => 'decimal:2',
            'vat' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function sellerEntity(): BelongsTo
    {
        return $this->belongsTo(SellerEntity::class);
    }

    public function sellerRequisites(): BelongsTo
    {
        return $this->belongsTo(SellerRequisites::class, 'seller_requisites_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
