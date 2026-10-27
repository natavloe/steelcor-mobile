<?php

namespace App\Filament\Resources\SellerEntities;

use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\SellerEntities\Pages\CreateSellerEntity;
use App\Filament\Resources\SellerEntities\Pages\EditSellerEntity;
use App\Filament\Resources\SellerEntities\Pages\ListSellerEntities;
use App\Filament\Resources\SellerEntities\RelationManagers\RequisitesRelationManager;
use App\Models\SellerEntity;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Юрлица-продавцы и версии их реквизитов. В документы попадают только утверждённые реквизиты. */
class SellerEntityResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = SellerEntity::class;

    protected static ?string $modelLabel = 'юрлицо-продавец';

    protected static ?string $pluralModelLabel = 'Юрлица-продавцы';

    protected static string|UnitEnum|null $navigationGroup = 'Справочники';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $viewPermission = Permissions::DOCUMENTS_MANAGE;

    protected static ?string $editPermission = Permissions::TEMPLATES_UPLOAD;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_short')->label('Краткое наименование')->required(),
            TextInput::make('inn')->label('ИНН')->required()->regex('/^\d{10}(\d{2})?$/')->unique(ignoreRecord: true),
            Toggle::make('is_active')->label('Действует')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_short')->label('Наименование')->searchable(),
                TextColumn::make('inn')->label('ИНН')->searchable(),
                TextColumn::make('requisites_count')->label('Версий реквизитов')->counts('requisites'),
                IconColumn::make('is_active')->label('Действует')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [RequisitesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSellerEntities::route('/'),
            'create' => CreateSellerEntity::route('/create'),
            'edit' => EditSellerEntity::route('/{record}/edit'),
        ];
    }
}
