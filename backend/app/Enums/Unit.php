<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Единица измерения. */
enum Unit: string implements HasLabel
{
    use LabelledEnum;

    case T = 't';
    case Kg = 'kg';
    case M = 'm';
    case Pc = 'pc';
    case M2 = 'm2';

    public function label(): string
    {
        return match ($this) {
            self::T => 'т',
            self::Kg => 'кг',
            self::M => 'м',
            self::Pc => 'шт',
            self::M2 => 'м²',
        };
    }
}
