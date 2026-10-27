<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Адреса доставки клиента (ведёт клиент в приложении). */
class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $title = 'Адреса доставки';

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
            TextColumn::make('label')->label('Название'),
            TextColumn::make('address')->label('Адрес')->wrap(),
            TextColumn::make('contact_name')->label('Контакт'),
            TextColumn::make('contact_phone')->label('Телефон'),
            TextColumn::make('access_notes')->label('Подъезд / разгрузка')->wrap(),
            IconColumn::make('is_archived')->label('В архиве')->boolean(),
        ]);
    }
}
