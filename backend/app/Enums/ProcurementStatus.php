<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Сводный статус закупки по заказу. */
enum ProcurementStatus: string implements HasColor, HasLabel
{
    use LabelledEnum;

    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Confirmed = 'confirmed';
    case Ready = 'ready';
    case Problem = 'problem';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Не начата',
            self::InProgress => 'В работе',
            self::Confirmed => 'Подтверждена',
            self::Ready => 'Готово',
            self::Problem => 'Проблема',
            self::Done => 'Выполнена',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ready, self::Done => 'success',
            self::Problem => 'danger',
            self::InProgress, self::Confirmed => 'primary',
            self::NotStarted => 'gray',
        };
    }
}
