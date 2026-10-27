<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Способ доставки. */
enum Carrier: string implements HasLabel
{
    use LabelledEnum;

    case Cdek = 'cdek';
    case Dellin = 'dellin';
    case Own = 'own';
    case Hired = 'hired';
    case Pickup = 'pickup';

    public function label(): string
    {
        return match ($this) {
            self::Cdek => 'СДЭК',
            self::Dellin => 'Деловые Линии',
            self::Own => 'Доставка компании',
            self::Hired => 'Наёмный транспорт',
            self::Pickup => 'Самовывоз',
        };
    }
}
