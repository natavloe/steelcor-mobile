<?php

namespace App\Filament\Resources\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\RelationManagers\LinesRelationManager;
use App\Models\PurchaseOrder;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** ВНУТРЕННЕЕ: заказы поставщикам. Отправить поставщику можно только по оплаченным заказам клиентов. */
class PurchaseOrderResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $modelLabel = 'закупка';

    protected static ?string $pluralModelLabel = 'Закупки';

    protected static string|UnitEnum|null $navigationGroup = 'Закупки';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $viewPermission = Permissions::PROCUREMENT_MANAGE;

    protected static ?string $editPermission = Permissions::PROCUREMENT_MANAGE;

    protected static ?string $regionColumn = 'region_id';

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('supplier_id')->label('Поставщик')->relationship('supplier', 'name')->required()->searchable()->preload(),
            Select::make('region_id')->label('Регион')->relationship('region', 'name')->required(),
            Textarea::make('note')->label('Заметка')->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->schema([
                TextEntry::make('status')->label('Статус')->badge(),
                TextEntry::make('supplier.name')->label('Поставщик'),
                TextEntry::make('region.name')->label('Регион'),
                TextEntry::make('total')->label('Сумма закупки')->money('RUB'),
                TextEntry::make('supplier_ref')->label('Номер у поставщика')->placeholder('—'),
                TextEntry::make('expected_ready_date')->label('Готовность')->date('d.m.Y')->placeholder('—'),
                TextEntry::make('supplier_payment_status')->label('Оплата поставщику')
                    ->formatStateUsing(fn ($state) => ['not_paid' => 'Не оплачено', 'partially_paid' => 'Частично', 'paid' => 'Оплачено'][$state] ?? $state),
                TextEntry::make('ship_from_address')->label('Откуда забирать')->placeholder('—'),
                TextEntry::make('note')->label('Заметка')->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label('№')->searchable(),
                TextColumn::make('created_at')->label('Создана')->date('d.m.Y'),
                TextColumn::make('supplier.name')->label('Поставщик')->searchable(),
                TextColumn::make('region.name')->label('Регион'),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('total')->label('Сумма')->money('RUB'),
                TextColumn::make('expected_ready_date')->label('Готовность')->date('d.m.Y'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Статус')->options(PurchaseOrderStatus::class)->multiple(),
                SelectFilter::make('supplier_id')->label('Поставщик')->relationship('supplier', 'name'),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [LinesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'view' => ViewPurchaseOrder::route('/{record}'),
        ];
    }
}
