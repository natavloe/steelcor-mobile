<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Тип организации. */
enum OrganizationType: string implements HasLabel
{
    use LabelledEnum;

    case Legal = 'legal';
    case Ip = 'ip';
    case Individual = 'individual';

    public function label(): string
    {
        return match ($this) {
            self::Legal => 'Юрлицо',
            self::Ip => 'ИП',
            self::Individual => 'Физлицо',
        };
    }
}
