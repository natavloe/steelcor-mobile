<?php

namespace App\Models;

use App\Enums\ContractMode;
use App\Enums\DateKind;
use App\Enums\DocumentsSummary;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcurementStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ReceiveMethod;
use App\Enums\ShipmentStatus;
use App\Models\Concerns\HasStatusHistory;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Заказ клиента. Пять независимых статусов: заказ, оплата, закупка, доставка, документы.
 * Статусы меняются только через App\Services\OrderWorkflow.
 */
class Order extends Model
{
    use HasStatusHistory, RecordsActivity;

    protected $guarded = ['id'];

    /** Значения по умолчанию (как в базе), чтобы новые записи сразу их видели. */
    protected $attributes = ['is_reagreement' => false];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'procurement_status' => ProcurementStatus::class,
            'delivery_status' => ShipmentStatus::class,
            'documents_status' => DocumentsSummary::class,
            'resume_status' => OrderStatus::class,
            'receive_method' => ReceiveMethod::class,
            'contract_mode' => ContractMode::class,
            'is_reagreement' => 'boolean',
            'desired_date_from' => 'date',
            'desired_date_to' => 'date',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function historyEntityType(): string
    {
        return 'order';
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function sellerEntity(): BelongsTo
    {
        return $this->belongsTo(SellerEntity::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'manager_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class)->orderBy('version');
    }

    public function agreedOffer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'agreed_offer_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(StatusHistory::class, 'entity_id')->where('entity_type', 'order')->orderByDesc('id');
    }

    public function dates(): HasMany
    {
        return $this->hasMany(OrderDate::class)->orderBy('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function paymentNotices(): HasMany
    {
        return $this->hasMany(PaymentNotice::class);
    }

    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(DeliveryAddress::class);
    }

    public function pickupPoint(): BelongsTo
    {
        return $this->belongsTo(PickupPoint::class);
    }

    /** Действующий срок указанного вида (последняя запись). */
    public function currentDate(DateKind $kind): ?OrderDate
    {
        return $this->dates()->where('kind', $kind->value)->reorder('id', 'desc')->first();
    }

    /** Есть ли по заказу закупки, которые не отменены и не отклонены поставщиком. */
    public function purchaseOrdersActive(): bool
    {
        return PurchaseOrder::query()
            ->whereNotIn('status', [PurchaseOrderStatus::Cancelled->value, PurchaseOrderStatus::Refused->value])
            ->whereHas('lines.allocations.offerLine.offer', fn ($q) => $q->where('order_id', $this->id))
            ->exists();
    }

    /** Последнее отправленное клиенту и ещё не закрытое предложение. */
    public function pendingOffer(): ?Offer
    {
        return $this->offers()->where('status', 'sent')->reorder('version', 'desc')->first();
    }
}
