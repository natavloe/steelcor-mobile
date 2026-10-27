<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\Unit;
use App\Filament\Concerns\RunsWorkflow;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\Allocation;
use App\Models\OfferLine;
use App\Models\PurchaseOrder;
use App\Models\Product;
use App\Models\PurchaseOrderLine;
use App\Services\ProcurementService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;

/** Карточка закупки: строки с распределением на заказы, отправка и движение по статусам. */
class ViewPurchaseOrder extends ViewRecord
{
    use RunsWorkflow;

    protected static string $resource = PurchaseOrderResource::class;

    private function po(): PurchaseOrder
    {
        /** @var PurchaseOrder */
        return $this->getRecord()->refresh();
    }

    private function service(): ProcurementService
    {
        return app(ProcurementService::class);
    }

    private function done(): void
    {
        $this->getRecord()->refresh();
        $this->dispatch('$refresh');
    }

    /** Строки согласованных предложений заказов этого региона, ещё не полностью обеспеченные закупками. */
    private function allocatableLines(): array
    {
        $po = $this->po();
        $lines = OfferLine::query()
            ->where('line_type', 'goods')
            ->whereHas('offer.order', fn ($q) => $q->where('region_id', $po->region_id)
                ->whereColumn('orders.agreed_offer_id', 'offers.id')
                ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Completed->value, OrderStatus::Delivered->value]))
            ->with('offer.order.organization')
            ->get();

        $options = [];
        foreach ($lines as $line) {
            $covered = Money::add(...Allocation::query()->where('offer_line_id', $line->id)
                ->whereHas('purchaseOrderLine.purchaseOrder', fn ($q) => $q->whereNotIn('status', ['cancelled', 'refused']))
                ->pluck('qty')->all());
            $left = Money::round(Money::of($line->qty)->minus(Money::of($covered)), 4);
            if (Money::compare($left, '0') <= 0) {
                continue;
            }
            $order = $line->offer->order;
            $paid = in_array($order->status, [OrderStatus::PaymentConfirmed, OrderStatus::Procurement, OrderStatus::SupplierConfirmed, OrderStatus::AwaitingReadiness, OrderStatus::ReadyToShip], true);
            $options[$line->id] = sprintf('%s · %s · %s — осталось %s %s%s',
                $order->number, $order->organization->name_short, $line->name, rtrim(rtrim($left, '0'), '.'), $line->unit->label(), $paid ? '' : ' (НЕ ОПЛАЧЕН)');
        }

        return $options;
    }

    protected function getHeaderActions(): array
    {
        $isDraft = fn () => $this->po()->status === PurchaseOrderStatus::Draft;

        return [
            Action::make('addLine')->label('Добавить строку')->icon('heroicon-o-plus')
                ->visible($isDraft)
                ->schema([
                    Select::make('product_id')->label('Позиция каталога')->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Product::query()->where('name', 'ilike', "%{$search}%")->limit(30)->pluck('name', 'id')->all())
                        ->getOptionLabelUsing(fn ($value) => Product::find($value)?->name),
                    Textarea::make('description')->label('Наименование у поставщика')->required(),
                    TextInput::make('qty')->label('Количество')->numeric()->required(),
                    Select::make('unit')->label('Ед.')->options(Unit::class)->required()->default('t'),
                    TextInput::make('purchase_price')->label('Закупочная цена за ед., ₽')->numeric()->required(),
                    Repeater::make('allocations')->label('Для каких заказов')->columns(3)->defaultItems(1)->addActionLabel('Ещё заказ')->schema([
                        Select::make('offer_line_id')->label('Строка заказа')->options(fn () => $this->allocatableLines())->required()->columnSpan(2)->searchable(),
                        TextInput::make('qty')->label('Сколько')->numeric()->required(),
                    ]),
                ])
                ->action(function (array $data) {
                    $allocations = [];
                    foreach ($data['allocations'] ?? [] as $row) {
                        $allocations[(int) $row['offer_line_id']] = (string) $row['qty'];
                    }
                    static::run(fn ($actor) => $this->service()->addLine($this->po(), $data, $allocations, $actor), 'Строка добавлена');
                    $this->done();
                }),

            Action::make('send')->label('Отправить поставщику')->icon('heroicon-o-paper-airplane')->color('success')
                ->visible($isDraft)
                ->requiresConfirmation()
                ->modalDescription('Проверка: все связанные заказы клиентов должны быть оплачены (или с одобренной отсрочкой).')
                ->action(function () {
                    static::run(fn ($actor) => $this->service()->send($this->po(), $actor), 'Закупка отправлена поставщику');
                    $this->done();
                }),

            Action::make('confirm')->label('Поставщик подтвердил')->icon('heroicon-o-check')->color('success')
                ->visible(fn () => $this->po()->status === PurchaseOrderStatus::Sent)
                ->schema([
                    TextInput::make('supplier_ref')->label('Номер счёта/заказа у поставщика'),
                    DatePicker::make('expected_ready_date')->label('Дата готовности')->required(),
                ])
                ->action(function (array $data) {
                    static::run(fn ($actor) => $this->service()->confirm($this->po(), $actor, $data['supplier_ref'] ?? null, $data['expected_ready_date']), 'Подтверждение записано');
                    $this->done();
                }),

            Action::make('advance')->label('Следующий этап')->icon('heroicon-o-arrow-right')
                ->visible(fn () => in_array($this->po()->status, [PurchaseOrderStatus::Confirmed, PurchaseOrderStatus::PaidToSupplier, PurchaseOrderStatus::AwaitingReady, PurchaseOrderStatus::Ready], true))
                ->schema([
                    Select::make('to')->label('Новый статус')->required()->options(fn () => collect([
                        PurchaseOrderStatus::PaidToSupplier, PurchaseOrderStatus::AwaitingReady, PurchaseOrderStatus::Ready, PurchaseOrderStatus::Shipped,
                    ])->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()),
                    TextInput::make('note')->label('Комментарий'),
                ])
                ->action(function (array $data) {
                    static::run(fn ($actor) => $this->service()->advance($this->po(), PurchaseOrderStatus::from($data['to']), $actor, $data['note'] ?? null), 'Статус закупки обновлён');
                    $this->done();
                }),

            ActionGroup::make([
                Action::make('changePrice')->label('Поставщик изменил цену')->icon('heroicon-o-currency-dollar')->color('warning')
                    ->visible(fn () => $this->po()->status === PurchaseOrderStatus::Confirmed)
                    ->modalDescription('Цена клиента НЕ меняется. По каждому связанному заказу откроется инцидент: решите, искать другого поставщика или согласовывать условия с клиентом.')
                    ->schema([
                        Select::make('line_id')->label('Строка')->required()
                            ->options(fn () => $this->po()->lines()->get()->mapWithKeys(fn (PurchaseOrderLine $l) => [$l->id => $l->description.' — '.$l->purchase_price.' ₽'])->all()),
                        TextInput::make('price')->label('Новая цена, ₽')->numeric()->required(),
                        Textarea::make('reason')->label('Причина')->required(),
                    ])
                    ->action(function (array $data) {
                        static::run(fn ($actor) => $this->service()->changePrice(PurchaseOrderLine::findOrFail($data['line_id']), (string) $data['price'], $data['reason'], $actor), 'Изменение цены записано, инциденты открыты');
                        $this->done();
                    }),
                Action::make('acceptPrice')->label('Принять новую цену (внутреннее решение)')->icon('heroicon-o-check-badge')
                    ->visible(fn () => $this->po()->status === PurchaseOrderStatus::PriceChanged)
                    ->schema([Textarea::make('note')->label('Обоснование')->required()])
                    ->action(function (array $data) {
                        static::run(fn ($actor) => $this->service()->acceptPriceChange($this->po(), $actor, $data['note']), 'Закупка снова подтверждена');
                        $this->done();
                    }),
                Action::make('refuse')->label('Поставщик отказал')->icon('heroicon-o-no-symbol')->color('danger')
                    ->visible(fn () => $this->po()->status === PurchaseOrderStatus::Sent)
                    ->schema([Textarea::make('reason')->label('Причина')->required()])
                    ->requiresConfirmation()
                    ->action(function (array $data) {
                        static::run(fn ($actor) => $this->service()->refuse($this->po(), $actor, $data['reason']), 'Отказ записан, инциденты открыты');
                        $this->done();
                    }),
                Action::make('cancelPo')->label('Отменить закупку')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn () => in_array($this->po()->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Sent, PurchaseOrderStatus::Confirmed, PurchaseOrderStatus::PriceChanged], true))
                    ->schema([Textarea::make('reason')->label('Причина')->required()])
                    ->requiresConfirmation()
                    ->action(function (array $data) {
                        static::run(fn ($actor) => $this->service()->advance($this->po(), PurchaseOrderStatus::Cancelled, $actor, $data['reason']), 'Закупка отменена');
                        $this->done();
                    }),
            ])->label('Ещё')->button(),
        ];
    }
}
