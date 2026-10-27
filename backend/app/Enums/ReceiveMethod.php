<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Способ получения. */
enum ReceiveMethod: string implements HasLabel
{
    use LabelledEnum;

    case Delivery = 'delivery';
    case Pickup = 'pickup';

    public function label(): string
    {
        return match ($this) {
            self::Delivery => 'Доставка',
            self::Pickup => 'Самовывоз',
        };
    }
}
