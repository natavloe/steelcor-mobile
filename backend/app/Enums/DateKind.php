<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Вид срока. */
enum DateKind: string implements HasLabel
{
    use LabelledEnum;

    case Preliminary = 'preliminary';
    case Agreed = 'agreed';
    case SupplierConfirmed = 'supplier_confirmed';
    case ActualShipment = 'actual_shipment';
    case EstimatedDelivery = 'estimated_delivery';
    case ConfirmedDelivery = 'confirmed_delivery';

    public function label(): string
    {
        return match ($this) {
            self::Preliminary => 'Предварительный',
            self::Agreed => 'Согласованный с клиентом',
            self::SupplierConfirmed => 'Подтверждён поставщиком',
            self::ActualShipment => 'Фактическая отгрузка',
            self::EstimatedDelivery => 'Расчётная доставка',
            self::ConfirmedDelivery => 'Подтверждённая доставка',
        };
    }
}
