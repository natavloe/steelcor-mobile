<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Статус предложения. */
enum OfferStatus: string implements HasColor, HasLabel
{
    use LabelledEnum;

    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::PendingApproval => 'Ждёт одобрения руководителя',
            self::Sent => 'Отправлено клиенту',
            self::Accepted => 'Принято',
            self::Rejected => 'Клиент просит изменить',
            self::Expired => 'Истёк срок',
            self::Superseded => 'Заменено новой версией',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Accepted => 'success',
            self::Rejected, self::Expired => 'danger',
            self::Draft, self::Superseded => 'gray',
            self::PendingApproval => 'warning',
            self::Sent => 'primary',
        };
    }
}
