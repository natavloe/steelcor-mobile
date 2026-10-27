<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Тип инцидента. */
enum IssueType: string implements HasLabel
{
    use LabelledEnum;

    case SupplierRefused = 'supplier_refused';
    case OutOfStock = 'out_of_stock';
    case PriceChanged = 'price_changed';
    case SupplierDelay = 'supplier_delay';
    case ClientCancel = 'client_cancel';
    case CancelAfterPurchase = 'cancel_after_purchase';
    case DeliveryProblem = 'delivery_problem';
    case Quality = 'quality';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SupplierRefused => 'Отказ поставщика',
            self::OutOfStock => 'Нет у поставщика',
            self::PriceChanged => 'Изменилась закупочная цена',
            self::SupplierDelay => 'Поставщик срывает срок',
            self::ClientCancel => 'Клиент отменяет',
            self::CancelAfterPurchase => 'Отмена после закупки',
            self::DeliveryProblem => 'Проблема с доставкой',
            self::Quality => 'Качество',
            self::Other => 'Другое',
        };
    }
}
