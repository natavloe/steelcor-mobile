<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Роль в организации. */
enum MemberRole: string implements HasLabel
{
    use LabelledEnum;

    case Owner = 'owner';
    case Buyer = 'buyer';
    case Accountant = 'accountant';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Владелец',
            self::Buyer => 'Закупщик',
            self::Accountant => 'Бухгалтер',
            self::Viewer => 'Наблюдатель',
        };
    }
}
