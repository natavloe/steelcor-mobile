<?php

namespace App\Filament\Resources\Invoices;

use App\Enums\InvoiceStatus;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Invoice;
use App\Services\Bank\PaymentMatcher;
use App\Support\Permissions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Все счета. Создаются автоматически при принятии клиентом условий. */
class InvoiceResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Invoice::class;

    protected static ?string $modelLabel = 'счёт';

    protected static ?string $pluralModelLabel = 'Счета';

    protected static string|UnitEnum|null $navigationGroup = 'Финансы';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static ?string $viewPermission = Permissions::INVOICES_ISSUE;

    protected static ?string $editPermission = null;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $ids = static::staff()?->regionIds();

        return $ids === null ? $query : $query->whereHas('order', fn ($q) => $q->whereIn('region_id', $ids));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label('Номер')->searchable(),
                TextColumn::make('created_at')->label('Дата')->date('d.m.Y'),
                TextColumn::make('organization.name_short')->label('Плательщик')->searchable(),
                TextColumn::make('order.number')->label('Заказ')
                    ->url(fn (Invoice $record) => OrderResource::getUrl('view', ['record' => $record->order_id])),
                TextColumn::make('amount')->label('Сумма')->money('RUB'),
                TextColumn::make('remaining')->label('Осталось')->money('RUB')->state(fn (Invoice $record) => app(PaymentMatcher::class)->remaining($record)),
                TextColumn::make('due_date')->label('Оплатить до')->date('d.m.Y'),
                TextColumn::make('status')->label('Статус')->badge(),
            ])
            ->filters([SelectFilter::make('status')->label('Статус')->options(InvoiceStatus::class)]);
    }

    public static function getPages(): array
    {
        return ['index' => ListInvoices::route('/')];
    }
}
