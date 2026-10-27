<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Разбор платежа. */
enum MatchStatus: string implements HasColor, HasLabel
{
    use LabelledEnum;

    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case PartiallyMatched = 'partially_matched';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Unmatched => 'Не разобран',
            self::Matched => 'Привязан',
            self::PartiallyMatched => 'Привязан частично',
            self::Ignored => 'Не относится к заказам',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unmatched => 'danger',
            self::PartiallyMatched => 'warning',
            self::Matched => 'success',
            self::Ignored => 'gray',
        };
    }
}
