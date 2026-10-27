<?php

namespace App\Http\Resources\Client;

use App\Enums\DateKind;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Bank\PaymentMatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Заказ глазами клиента. Внутренние этапы закупки показываются нейтрально:
 * для клиента «Стилкор» — единая точка контакта (слово «поставщик» не используется).
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /** Подписи статусов для клиента. */
    public const CLIENT_LABELS = [
        'created' => 'Заказ получен',
        'under_review' => 'Менеджер уточняет цену и сроки',
        'awaiting_terms' => 'Подтвердите условия',
        'awaiting_payment' => 'Ожидает оплаты',
        'payment_confirmed' => 'Оплата получена',
        'procurement' => 'Заказ в работе',
        'supplier_confirmed' => 'Поставка подтверждена',
        'awaiting_readiness' => 'Продукция готовится к отгрузке',
        'ready_to_ship' => 'Готово к отгрузке',
        'arranging_delivery' => 'Оформляем доставку',
        'in_delivery' => 'В доставке',
        'delivered' => 'Получено',
        'completed' => 'Выполнен',
        'cancelled' => 'Отменён',
    ];

    /** Коды статусов для приложения: внутренние этапы закупки клиенту не раскрываются. */
    public const CLIENT_CODES = [
        'procurement' => 'in_work',
        'supplier_confirmed' => 'supply_confirmed',
    ];

    public static function clientStatus(OrderStatus $status): string
    {
        return self::CLIENT_CODES[$status->value] ?? $status->value;
    }

    public function toArray(Request $request): array
    {
        $detailed = $request->route('order') !== null;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'created_at' => $this->created_at->toIso8601String(),
            'organization' => ['id' => $this->organization_id, 'name' => $this->organization->name_short],
            'region' => $this->region->code,
            'status' => self::clientStatus($this->status),
            'status_label' => self::CLIENT_LABELS[$this->status->value],
            'payment_status' => $this->payment_status->value,
            'payment_status_label' => $this->payment_status->label(),
            'delivery_status' => $this->delivery_status->value,
            'delivery_status_label' => $this->delivery_status->label(),
            'documents_status' => $this->documents_status->value,
            'is_reagreement' => $this->is_reagreement,
            'total' => $this->agreedOffer ? (string) $this->agreedOffer->grand_total : null,
            'action_required' => $this->actionRequired(),
            $this->mergeWhen($detailed, fn () => [
                'receive_method' => $this->receive_method->value,
                'delivery_address' => $this->deliveryAddress ? new AddressResource($this->deliveryAddress) : null,
                'pickup_point' => $this->pickupPoint ? new PickupPointResource($this->pickupPoint) : null,
                'desired' => [
                    'from' => $this->desired_date_from?->toDateString(),
                    'to' => $this->desired_date_to?->toDateString(),
                    'time_slot' => $this->desired_time_slot,
                ],
                'contact' => ['name' => $this->contact_name, 'phone' => $this->contact_phone],
                'comment' => $this->client_comment,
                'items' => $this->items->map(fn ($i) => [
                    'product_id' => $i->product_id, 'name' => $i->description, 'qty' => (string) $i->qty, 'unit' => $i->unit->value, 'comment' => $i->comment,
                ])->values(),
                'dates' => collect([
                    'preliminary' => DateKind::Preliminary,
                    'agreed' => DateKind::Agreed,
                    'ready_confirmed' => DateKind::SupplierConfirmed,
                    'delivery_confirmed' => DateKind::ConfirmedDelivery,
                    'shipped' => DateKind::ActualShipment,
                ])->map(function (DateKind $kind) {
                    $d = $this->currentDate($kind);

                    return $d ? ['from' => $d->date_from->toDateString(), 'to' => $d->date_to?->toDateString(), 'label' => $this->clientDateLabel($kind)] : null;
                }),
                'current_offer' => ($offer = $this->pendingOffer() ?? $this->agreedOffer) ? new OfferResource($offer->load('lines')) : null,
                'invoices' => $this->invoices()->where('status', 'issued')->get()->map(fn ($inv) => [
                    'id' => $inv->id,
                    'number' => $inv->number,
                    'amount' => (string) $inv->amount,
                    'remaining' => app(PaymentMatcher::class)->remaining($inv),
                    'due_date' => $inv->due_date?->toDateString(),
                    'kind' => $inv->kind,
                ])->values(),
                'shipments' => $this->shipments->map(fn ($s) => [
                    'carrier' => $s->carrier->value,
                    'carrier_label' => $s->carrier->label(),
                    'status' => $s->status->value,
                    'status_label' => $s->status->label(),
                    'tracking_number' => $s->tracking_number,
                    'tracking_url' => $s->tracking_url,
                    'planned_date' => $s->planned_date?->toDateString(),
                    // Расчётная стоимость не выдаётся за подтверждённую.
                    'cost' => ($amount = $s->cost_confirmed ? ($s->confirmed_cost ?? $s->quoted_cost) : $s->quoted_cost) === null ? null : [
                        'amount' => (string) $amount,
                        'is_confirmed' => $s->cost_confirmed,
                    ],
                    'events' => $s->events()->orderBy('occurred_at')->get()->map(fn ($e) => [
                        'status_label' => $e->status->label(), 'text' => $e->carrier_status_text, 'at' => $e->occurred_at->toIso8601String(),
                    ]),
                ])->values(),
                'can_cancel' => in_array($this->status, [OrderStatus::Created, OrderStatus::UnderReview, OrderStatus::AwaitingTerms, OrderStatus::AwaitingPayment], true),
            ]),
        ];
    }

    private function clientDateLabel(DateKind $kind): string
    {
        return match ($kind) {
            DateKind::Preliminary => 'Предварительный срок',
            DateKind::Agreed => 'Согласованный срок',
            DateKind::SupplierConfirmed => 'Подтверждённая готовность',
            DateKind::ConfirmedDelivery => 'Подтверждённая доставка',
            DateKind::ActualShipment => 'Отгружено',
            default => $kind->label(),
        };
    }

    /** Что клиенту нужно сделать прямо сейчас (блок «Нужно ваше действие»). */
    private function actionRequired(): ?array
    {
        return match (true) {
            $this->status === OrderStatus::AwaitingTerms => ['code' => 'accept_terms', 'label' => 'Проверьте и подтвердите условия'],
            $this->status === OrderStatus::AwaitingPayment && in_array($this->payment_status, [PaymentStatus::AwaitingPayment, PaymentStatus::PartiallyPaid], true)
                => ['code' => 'pay', 'label' => 'Оплатите счёт'],
            default => null,
        };
    }
}
