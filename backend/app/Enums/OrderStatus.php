<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Статус заказа. */
enum OrderStatus: string implements HasLabel
{
    use LabelledEnum;

    case Created = 'created';
    case UnderReview = 'under_review';
    case AwaitingTerms = 'awaiting_terms';
    case AwaitingPayment = 'awaiting_payment';
    case PaymentConfirmed = 'payment_confirmed';
    case Procurement = 'procurement';
    case SupplierConfirmed = 'supplier_confirmed';
    case AwaitingReadiness = 'awaiting_readiness';
    case ReadyToShip = 'ready_to_ship';
    case ArrangingDelivery = 'arranging_delivery';
    case InDelivery = 'in_delivery';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Создан',
            self::UnderReview => 'Проверяется менеджером',
            self::AwaitingTerms => 'Ожидает подтверждения условий',
            self::AwaitingPayment => 'Ожидает оплаты',
            self::PaymentConfirmed => 'Оплата подтверждена',
            self::Procurement => 'Закупка у поставщика',
            self::SupplierConfirmed => 'Поставщик подтвердил заказ',
            self::AwaitingReadiness => 'Ожидается готовность продукции',
            self::ReadyToShip => 'Продукция подготовлена к отгрузке',
            self::ArrangingDelivery => 'Организуется доставка',
            self::InDelivery => 'Передан в доставку',
            self::Delivered => 'Доставлен клиенту',
            self::Completed => 'Завершён',
            self::Cancelled => 'Отменён',
        };
    }
}
