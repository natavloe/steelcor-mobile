<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Статус возврата. */
enum RefundStatus: string implements HasLabel
{
    use LabelledEnum;

    case Requested = 'requested';
    case Approved = 'approved';
    case Sent = 'sent';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Запрошен',
            self::Approved => 'Утверждён',
            self::Sent => 'Отправлен',
            self::Completed => 'Выполнен',
            self::Rejected => 'Отклонён',
        };
    }
}
