<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Пользователи приложения, работающие от имени клиента. Управляет ими владелец в приложении. */
class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Пользователи клиента';

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
            TextColumn::make('user.name')->label('Имя'),
            TextColumn::make('user.phone')->label('Телефон'),
            TextColumn::make('user.email')->label('Почта'),
            TextColumn::make('role')->label('Роль')->badge(),
            TextColumn::make('status')->label('Статус'),
        ]);
    }
}
