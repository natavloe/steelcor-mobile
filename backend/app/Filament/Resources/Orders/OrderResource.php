<?php

namespace App\Filament\Resources\Orders;

use App\Enums\DateKind;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\HistoryRelationManager;
use App\Filament\Resources\Orders\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Orders\RelationManagers\IssuesRelationManager;
use App\Filament\Resources\Orders\RelationManagers\OffersRelationManager;
use App\Filament\Resources\Orders\RelationManagers\ShipmentsRelationManager;
use App\Models\Order;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Заказы клиентов: пять независимых статусов, действия менеджера — на странице заказа. */
class OrderResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Order::class;

    protected static ?string $modelLabel = 'заказ';

    protected static ?string $pluralModelLabel = 'Заказы';

    protected static string|UnitEnum|null $navigationGroup = 'Продажи';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?int $navigationSort = -10;

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $viewPermission = Permissions::ORDERS_VIEW;

    protected static ?string $editPermission = null;

    protected static ?string $regionColumn = 'region_id';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', OrderStatus::Created->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Статусы')->columns(['default' => 2, 'md' => 3, '2xl' => 5])->schema([
                TextEntry::make('status')->label('Заказ')->badge(),
                TextEntry::make('payment_status')->label('Оплата')->badge(),
                TextEntry::make('procurement_status')->label('Закупка')->badge(),
                TextEntry::make('documents_status')->label('Документы')->badge(),
                TextEntry::make('delivery_status')->label('Доставка')->badge(),
                TextEntry::make('is_reagreement')->label('Повторное согласование')
                    ->formatStateUsing(fn (bool $state) => $state ? 'Да — закупка заморожена' : 'Нет')
                    ->color(fn (bool $state) => $state ? 'danger' : 'gray')
                    ->visible(fn (Order $record) => $record->is_reagreement),
            ]),
            Grid::make(['default' => 1, 'lg' => 2])->columnSpanFull()->schema([
                Section::make('Клиент')->columnSpan(['default' => 'full', 'lg' => 1])->schema([
                    TextEntry::make('organization.name_short')->label('Организация')
                        ->url(fn (Order $record) => \App\Filament\Resources\Organizations\OrganizationResource::getUrl('edit', ['record' => $record->organization_id])),
                    TextEntry::make('organization.inn')->label('ИНН'),
                    TextEntry::make('organization.verification_status')->label('Реквизиты')->badge(),
                    TextEntry::make('contact_name')->label('Контакт'),
                    TextEntry::make('contact_phone')->label('Телефон'),
                    TextEntry::make('client_comment')->label('Комментарий клиента')->placeholder('—'),
                ]),
                Section::make('Получение и сроки')->columnSpan(['default' => 'full', 'lg' => 1])->schema([
                    TextEntry::make('region.name')->label('Регион'),
                    TextEntry::make('receive_method')->label('Способ'),
                    TextEntry::make('deliveryAddress.address')->label('Адрес доставки')->placeholder('—'),
                    TextEntry::make('deliveryAddress.access_notes')->label('Подъезд и разгрузка')->placeholder('—'),
                    TextEntry::make('pickupPoint.name')->label('Пункт самовывоза')->placeholder('—'),
                    TextEntry::make('desired')->label('Желаемый срок клиента')
                        ->state(fn (Order $record) => trim(($record->desired_date_from?->format('d.m.Y') ?? '').' – '.($record->desired_date_to?->format('d.m.Y') ?? '').' '.($record->desired_time_slot ?? ''), ' –'))
                        ->placeholder('—'),
                    TextEntry::make('dates_summary')->label('Сроки')->listWithLineBreaks()
                        ->state(fn (Order $record) => collect(DateKind::cases())
                            ->map(fn (DateKind $kind) => ($d = $record->currentDate($kind))
                                ? $kind->label().': '.$d->date_from->format('d.m.Y').($d->date_to ? ' – '.$d->date_to->format('d.m.Y') : '')
                                : null)
                            ->filter()->values()->all())
                        ->placeholder('Сроки ещё не заданы'),
                ]),
            ]),
            Section::make('Согласованные условия')
                ->visible(fn (Order $record) => $record->agreed_offer_id !== null)
                ->schema([
                    TextEntry::make('agreedOffer.version')->label('Версия предложения')->inlineLabel(),
                    TextEntry::make('agreedOffer.grand_total')->label('Итого')->money('RUB')->inlineLabel(),
                    TextEntry::make('agreedOffer.payment_terms')->label('Оплата')->inlineLabel(),
                    RepeatableEntry::make('agreedOffer.lines')->label('Строки')->table([
                        RepeatableEntry\TableColumn::make('Наименование'),
                        RepeatableEntry\TableColumn::make('Кол-во'),
                        RepeatableEntry\TableColumn::make('Цена'),
                        RepeatableEntry\TableColumn::make('Сумма'),
                    ])->schema([
                        TextEntry::make('name')->label('Наименование'),
                        TextEntry::make('qty')->label('Кол-во')->formatStateUsing(fn ($state, $record) => rtrim(rtrim((string) $state, '0'), '.').' '.$record->unit->label()),
                        TextEntry::make('unit_price')->label('Цена')->money('RUB'),
                        TextEntry::make('amount_total')->label('Сумма')->money('RUB'),
                    ]),
                ]),
            Section::make('Запрос клиента')
                ->visible(fn (Order $record) => $record->agreed_offer_id === null)
                ->schema([
                    RepeatableEntry::make('items')->hiddenLabel()->table([
                        RepeatableEntry\TableColumn::make('Позиция'),
                        RepeatableEntry\TableColumn::make('Кол-во'),
                        RepeatableEntry\TableColumn::make('Комментарий'),
                    ])->schema([
                        TextEntry::make('description')->label('Позиция'),
                        TextEntry::make('qty')->label('Кол-во')->formatStateUsing(fn ($state, $record) => rtrim(rtrim((string) $state, '0'), '.').' '.$record->unit->label()),
                        TextEntry::make('comment')->label('Комментарий')->placeholder('—'),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label('№')->searchable(),
                TextColumn::make('created_at')->label('Создан')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('organization.name_short')->label('Клиент')->searchable()->wrap(),
                TextColumn::make('region.name')->label('Регион')->toggleable(),
                TextColumn::make('status')->label('Заказ')->badge(),
                TextColumn::make('payment_status')->label('Оплата')->badge(),
                TextColumn::make('procurement_status')->label('Закупка')->badge()->toggleable(),
                TextColumn::make('delivery_status')->label('Доставка')->badge()->toggleable(),
                TextColumn::make('agreedOffer.grand_total')->label('Сумма')->money('RUB'),
                TextColumn::make('manager.name')->label('Менеджер')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Статус заказа')->options(OrderStatus::class)->multiple(),
                SelectFilter::make('payment_status')->label('Оплата')->options(PaymentStatus::class)->multiple(),
                SelectFilter::make('region_id')->label('Регион')->relationship('region', 'name'),
                Filter::make('mine')->label('Мои заказы')->query(fn (Builder $q) => $q->where('manager_id', auth('staff')->id())),
                Filter::make('reagreement')->label('На повторном согласовании')->query(fn (Builder $q) => $q->where('is_reagreement', true)),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            OffersRelationManager::class,
            InvoicesRelationManager::class,
            ShipmentsRelationManager::class,
            IssuesRelationManager::class,
            HistoryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
