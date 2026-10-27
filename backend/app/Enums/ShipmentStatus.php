<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Статус доставки. */
enum ShipmentStatus: string implements HasLabel
{
    use LabelledEnum;

    case NotArranged = 'not_arranged';
    case Calculating = 'calculating';
    case AwaitingBooking = 'awaiting_booking';
    case Booked = 'booked';
    case AcceptedByCarrier = 'accepted_by_carrier';
    case InTransit = 'in_transit';
    case Arrived = 'arrived';
    case ReadyForPickup = 'ready_for_pickup';
    case Delivered = 'delivered';
    case Problem = 'problem';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NotArranged => 'Не оформлена',
            self::Calculating => 'Рассчитывается',
            self::AwaitingBooking => 'Ожидает оформления',
            self::Booked => 'Заказана',
            self::AcceptedByCarrier => 'Принята перевозчиком',
            self::InTransit => 'В пути',
            self::Arrived => 'Прибыла в пункт назначения',
            self::ReadyForPickup => 'Готова к выдаче',
            self::Delivered => 'Доставлена',
            self::Problem => 'Проблема с доставкой',
            self::Cancelled => 'Отменена',
        };
    }
}
