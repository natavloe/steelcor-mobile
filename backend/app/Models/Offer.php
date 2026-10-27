<?php

namespace App\Models;

use App\Enums\OfferStatus;
use App\Enums\PaymentTerms;
use App\Enums\ReceiveMethod;
use App\Models\Concerns\HasStatusHistory;
use App\Models\Concerns\RecordsActivity;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Предложение (версия условий) по заказу. Принятое предложение не меняется. */
class Offer extends Model
{
    use HasStatusHistory, RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'payment_terms' => PaymentTerms::class,
            'receive_method' => ReceiveMethod::class,
            'valid_until' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'prepay_percent' => 'decimal:2',
            'goods_net' => 'decimal:2',
            'goods_vat' => 'decimal:2',
            'goods_total' => 'decimal:2',
            'delivery_total' => 'decimal:2',
            'services_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    public function historyEntityType(): string
    {
        return 'offer';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OfferLine::class)->orderBy('sort')->orderBy('id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function isLocked(): bool
    {
        return in_array($this->status, [OfferStatus::Accepted, OfferStatus::Superseded, OfferStatus::Expired, OfferStatus::Rejected], true);
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    /** Сумма, которую нужно получить до запуска закупки. */
    public function requiredPrepayment(): string
    {
        return match ($this->payment_terms) {
            PaymentTerms::Prepay100 => (string) $this->grand_total,
            PaymentTerms::PrepayPartial => Money::percentOf($this->grand_total, $this->prepay_percent),
            PaymentTerms::Deferral => '0.00',
        };
    }
}
