<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Кто выполнил действие. */
enum ActorType: string implements HasLabel
{
    use LabelledEnum;

    case Staff = 'staff';
    case User = 'user';
    case System = 'system';
    case Integration = 'integration';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Сотрудник',
            self::User => 'Клиент',
            self::System => 'Система',
            self::Integration => 'Интеграция',
        };
    }
}
