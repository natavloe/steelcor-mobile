<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Сводный статус документов по заказу. */
enum DocumentsSummary: string implements HasColor, HasLabel
{
    use LabelledEnum;

    case None = 'none';
    case InProgress = 'in_progress';
    case AllSigned = 'all_signed';
    case HasProblems = 'has_problems';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Нет документов',
            self::InProgress => 'Оформляются',
            self::AllSigned => 'Все подписаны',
            self::HasProblems => 'Есть проблемы',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::AllSigned => 'success',
            self::HasProblems => 'danger',
            self::None => 'gray',
            self::InProgress => 'warning',
        };
    }
}
