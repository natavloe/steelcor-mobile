<?php

namespace App\Filament\Resources\PickupPoints;

use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\PickupPoints\Pages\CreatePickupPoint;
use App\Filament\Resources\PickupPoints\Pages\EditPickupPoint;
use App\Filament\Resources\PickupPoints\Pages\ListPickupPoints;
use App\Models\PickupPoint;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PickupPointResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = PickupPoint::class;

    protected static ?string $modelLabel = 'пункт самовывоза';

    protected static ?string $pluralModelLabel = 'Пункты самовывоза';

    protected static string|UnitEnum|null $navigationGroup = 'Справочники';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $viewPermission = Permissions::ORDERS_VIEW;

    protected static ?string $editPermission = Permissions::SHIPMENTS_MANAGE;

    protected static ?string $regionColumn = 'region_id';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('region_id')->label('Регион')->relationship('region', 'name')->required(),
            TextInput::make('name')->label('Название')->required(),
            Textarea::make('address')->label('Адрес')->required()->columnSpanFull(),
            TextInput::make('work_hours')->label('Часы работы'),
            TextInput::make('lat')->label('Широта')->numeric(),
            TextInput::make('lng')->label('Долгота')->numeric(),
            Textarea::make('instructions')->label('Как проехать, какие документы нужны')->columnSpanFull(),
            Toggle::make('is_public')->label('Показывать клиентам')->default(true)
                ->helperText('Склады поставщиков клиентам не показываются'),
            Toggle::make('is_active')->label('Работает')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('region.name')->label('Регион'),
                TextColumn::make('name')->label('Название')->searchable(),
                TextColumn::make('address')->label('Адрес')->limit(60),
                IconColumn::make('is_public')->label('Клиентам')->boolean(),
                IconColumn::make('is_active')->label('Работает')->boolean(),
            ])
            ->filters([SelectFilter::make('region_id')->label('Регион')->relationship('region', 'name')])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPickupPoints::route('/'),
            'create' => CreatePickupPoint::route('/create'),
            'edit' => EditPickupPoint::route('/{record}/edit'),
        ];
    }
}
