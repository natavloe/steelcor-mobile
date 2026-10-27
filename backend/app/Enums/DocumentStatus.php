<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Статус документа. */
enum DocumentStatus: string implements HasLabel
{
    use LabelledEnum;

    case Generating = 'generating';
    case AwaitingReview = 'awaiting_review';
    case SentToClient = 'sent_to_client';
    case AwaitingBuyerSignature = 'awaiting_buyer_signature';
    case AwaitingSellerSignature = 'awaiting_seller_signature';
    case Signed = 'signed';
    case Rejected = 'rejected';
    case NeedsCorrection = 'needs_correction';
    case Annulled = 'annulled';

    public function label(): string
    {
        return match ($this) {
            self::Generating => 'Формируется',
            self::AwaitingReview => 'Ожидает проверки',
            self::SentToClient => 'Отправлен клиенту',
            self::AwaitingBuyerSignature => 'Ожидает подписания покупателем',
            self::AwaitingSellerSignature => 'Ожидает подписания продавцом',
            self::Signed => 'Подписан сторонами',
            self::Rejected => 'Отклонён',
            self::NeedsCorrection => 'Требует исправления',
            self::Annulled => 'Аннулирован',
        };
    }
}
