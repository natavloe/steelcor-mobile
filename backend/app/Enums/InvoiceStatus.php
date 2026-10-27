<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Статус счёта. */
enum InvoiceStatus: string implements HasLabel
{
    use LabelledEnum;

    case Draft = 'draft';
    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Issued => 'Выставлен',
            self::Cancelled => 'Аннулирован',
        };
    }
}
