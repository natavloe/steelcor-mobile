<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Доступность для клиента. */
enum Availability: string implements HasLabel
{
    use LabelledEnum;

    case Orderable = 'orderable';
    case NeedsSupplierCheck = 'needs_supplier_check';
    case MadeToOrder = 'made_to_order';
    case Unavailable = 'unavailable';
    case PriceOnRequest = 'price_on_request';

    public function label(): string
    {
        return match ($this) {
            self::Orderable => 'Доступен для заказа',
            self::NeedsSupplierCheck => 'Требует подтверждения наличия',
            self::MadeToOrder => 'Поставка под заказ',
            self::Unavailable => 'Временно недоступен',
            self::PriceOnRequest => 'Цена по запросу',
        };
    }
}
