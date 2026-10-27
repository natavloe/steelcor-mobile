<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Проверка реквизитов. */
enum VerificationStatus: string implements HasLabel
{
    use LabelledEnum;

    case Unverified = 'unverified';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Не проверены',
            self::Verified => 'Проверены',
            self::Rejected => 'Отклонены',
        };
    }
}
