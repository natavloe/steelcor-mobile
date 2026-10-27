<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Filament\Resources\Offers\OfferResource;
use App\Models\Offer;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Версии предложения (условий) по заказу. */
class OffersRelationManager extends RelationManager
{
    protected static string $relationship = 'offers';

    protected static ?string $title = 'Предложения (версии условий)';

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
        return $table
            ->defaultSort('version', 'desc')
            ->columns([
                TextColumn::make('version')->label('Версия'),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('grand_total')->label('Итого')->money('RUB'),
                TextColumn::make('payment_terms')->label('Оплата'),
                TextColumn::make('valid_until')->label('Действует до')->dateTime('d.m.Y H:i'),
                TextColumn::make('reason')->label('Причина версии')->wrap()->placeholder('—'),
                TextColumn::make('client_comment')->label('Комментарий клиента')->wrap()->placeholder('—'),
                TextColumn::make('accepted_at')->label('Принято')->dateTime('d.m.Y H:i'),
            ])
            ->recordActions([
                Action::make('open')->label('Открыть')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Offer $record) => OfferResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
