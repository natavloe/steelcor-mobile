<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Статус заказа поставщику. */
enum PurchaseOrderStatus: string implements HasColor, HasLabel
{
    use LabelledEnum;

    case Draft = 'draft';
    case Sent = 'sent';
    case Confirmed = 'confirmed';
    case PriceChanged = 'price_changed';
    case Refused = 'refused';
    case PaidToSupplier = 'paid_to_supplier';
    case AwaitingReady = 'awaiting_ready';
    case Ready = 'ready';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Sent => 'Отправлена поставщику',
            self::Confirmed => 'Подтверждена поставщиком',
            self::PriceChanged => 'Цена изменена — нужно согласование',
            self::Refused => 'Отказ поставщика',
            self::PaidToSupplier => 'Оплачена поставщику',
            self::AwaitingReady => 'Ожидается готовность',
            self::Ready => 'Готово к отгрузке',
            self::Shipped => 'Получено / отгружено',
            self::Cancelled => 'Отменена',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ready, self::Shipped => 'success',
            self::Refused, self::PriceChanged => 'danger',
            self::Draft, self::Cancelled => 'gray',
            self::Sent, self::Confirmed, self::PaidToSupplier, self::AwaitingReady => 'primary',
        };
    }
}
