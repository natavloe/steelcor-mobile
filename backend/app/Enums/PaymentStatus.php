<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Статус оплаты. */
enum PaymentStatus: string implements HasColor, HasLabel
{
    use LabelledEnum;

    case NotInvoiced = 'not_invoiced';
    case AwaitingPayment = 'awaiting_payment';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Refund = 'refund';
    case Refunded = 'refunded';
    case PaymentError = 'payment_error';

    public function label(): string
    {
        return match ($this) {
            self::NotInvoiced => 'Счёт не выставлен',
            self::AwaitingPayment => 'Ожидает оплаты',
            self::AwaitingConfirmation => 'Ожидает подтверждения поступления',
            self::PartiallyPaid => 'Частично оплачен',
            self::Paid => 'Оплачен',
            self::Refund => 'Возврат средств',
            self::Refunded => 'Возврат выполнен',
            self::PaymentError => 'Ошибка оплаты',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::PaymentError => 'danger',
            self::AwaitingPayment, self::AwaitingConfirmation, self::PartiallyPaid, self::Refund => 'warning',
            self::NotInvoiced, self::Refunded => 'gray',
        };
    }
}
