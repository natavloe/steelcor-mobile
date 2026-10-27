<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Модель договора. */
enum ContractMode: string implements HasLabel
{
    use LabelledEnum;

    case Single = 'single';
    case Framework = 'framework';
    case Existing = 'existing';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Договор на поставку',
            self::Framework => 'Рамочный + спецификация',
            self::Existing => 'По действующему договору',
        };
    }
}
