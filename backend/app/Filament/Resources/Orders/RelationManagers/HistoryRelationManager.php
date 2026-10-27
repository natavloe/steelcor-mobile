<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Enums\ActorType;
use App\Models\StaffUser;
use App\Models\StatusHistory;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** История статусов заказа: кто, когда, откуда, куда и почему. */
class HistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistories';

    protected static ?string $title = 'История';

    private const FIELDS = [
        'status' => ['Заказ', \App\Enums\OrderStatus::class],
        'payment_status' => ['Оплата', \App\Enums\PaymentStatus::class],
        'procurement_status' => ['Закупка', \App\Enums\ProcurementStatus::class],
        'delivery_status' => ['Доставка', \App\Enums\ShipmentStatus::class],
        'documents_status' => ['Документы', \App\Enums\DocumentsSummary::class],
    ];

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    private static function label(string $field, ?string $value): string
    {
        $enum = self::FIELDS[$field][1] ?? null;

        return $value === null ? '—' : ($enum && ($case = $enum::tryFrom($value)) ? $case->label() : $value);
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('created_at')->label('Когда')->dateTime('d.m.Y H:i:s'),
                TextColumn::make('field')->label('Что')->formatStateUsing(fn ($state) => self::FIELDS[$state][0] ?? $state),
                TextColumn::make('from_value')->label('Было')->formatStateUsing(fn ($state, StatusHistory $record) => self::label($record->field, $state))->placeholder('—'),
                TextColumn::make('to_value')->label('Стало')->formatStateUsing(fn ($state, StatusHistory $record) => self::label($record->field, $state)),
                TextColumn::make('actor')->label('Кто')->state(fn (StatusHistory $record) => match ($record->actor_type) {
                    ActorType::Staff->value => 'Сотрудник: '.(StaffUser::find($record->actor_id)?->name ?? $record->actor_id),
                    ActorType::User->value => 'Клиент: '.(User::find($record->actor_id)?->name ?? $record->actor_id),
                    ActorType::Integration->value => 'Интеграция',
                    default => 'Система',
                }),
                TextColumn::make('reason')->label('Причина')->wrap()->placeholder('—'),
            ]);
    }
}
