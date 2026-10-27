<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Проверка реквизитов. */
enum VerificationStatus: string implements HasColor, HasLabel
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

    public function getColor(): string
    {
        return match ($this) {
            self::Unverified => 'warning',
            self::Verified => 'success',
            self::Rejected => 'danger',
        };
    }
}
