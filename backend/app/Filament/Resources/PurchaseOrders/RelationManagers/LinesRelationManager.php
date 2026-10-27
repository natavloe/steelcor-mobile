<?php

namespace App\Filament\Resources\PurchaseOrders\RelationManagers;

use App\Models\PurchaseOrderLine;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Строки закупки и на какие заказы клиентов они распределены. */
class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Строки и распределение';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('description')->label('Наименование')->wrap(),
            TextColumn::make('qty')->label('Кол-во')->formatStateUsing(fn ($state, PurchaseOrderLine $record) => rtrim(rtrim((string) $state, '0'), '.').' '.$record->unit->label()),
            TextColumn::make('purchase_price')->label('Цена')->money('RUB'),
            IconColumn::make('price_changed')->label('Цена менялась')->boolean(),
            TextColumn::make('allocations_list')->label('Для заказов')->listWithLineBreaks()
                ->state(fn (PurchaseOrderLine $record) => $record->allocations()->with('offerLine.offer.order')->get()
                    ->map(fn ($a) => $a->offerLine->offer->order->number.': '.rtrim(rtrim((string) $a->qty, '0'), '.'))->all()),
        ]);
    }
}
