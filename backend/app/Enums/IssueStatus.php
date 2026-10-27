<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Статус инцидента. */
enum IssueStatus: string implements HasColor, HasLabel
{
    use LabelledEnum;

    case Open = 'open';
    case WaitingClient = 'waiting_client';
    case WaitingSupplier = 'waiting_supplier';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Открыт',
            self::WaitingClient => 'Ждём клиента',
            self::WaitingSupplier => 'Ждём поставщика',
            self::Resolved => 'Решён',
            self::Closed => 'Закрыт',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::WaitingClient, self::WaitingSupplier => 'warning',
            self::Resolved, self::Closed => 'success',
        };
    }
}
