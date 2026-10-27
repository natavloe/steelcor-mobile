<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Условия оплаты. */
enum PaymentTerms: string implements HasLabel
{
    use LabelledEnum;

    case Prepay100 = 'prepay_100';
    case PrepayPartial = 'prepay_partial';
    case Deferral = 'deferral';

    public function label(): string
    {
        return match ($this) {
            self::Prepay100 => '100% предоплата',
            self::PrepayPartial => 'Частичная предоплата',
            self::Deferral => 'Отсрочка',
        };
    }
}
