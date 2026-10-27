<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Services\Bank\PaymentMatcher;
use App\Models\Invoice;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Счета по заказу и остаток к оплате (по привязанным платежам банка). */
class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Счета';

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
            TextColumn::make('number')->label('Номер'),
            TextColumn::make('created_at')->label('Дата')->date('d.m.Y'),
            TextColumn::make('kind')->label('Вид')->formatStateUsing(fn ($state) => ['prepayment' => 'Аванс', 'final' => 'Полная оплата', 'additional' => 'Доплата'][$state] ?? $state),
            TextColumn::make('amount')->label('Сумма')->money('RUB'),
            TextColumn::make('remaining')->label('Осталось')->money('RUB')
                ->state(fn (Invoice $record) => app(PaymentMatcher::class)->remaining($record)),
            TextColumn::make('due_date')->label('Оплатить до')->date('d.m.Y'),
            TextColumn::make('status')->label('Статус')->badge(),
        ]);
    }
}
