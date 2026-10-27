<?php

namespace App\Filament\Resources\Attributes;

use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Models\Attribute;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Справочник характеристик: марка, ГОСТ, диаметр, толщина, длина… */
class AttributeResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Attribute::class;

    protected static ?string $modelLabel = 'характеристика';

    protected static ?string $pluralModelLabel = 'Характеристики';

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $viewPermission = Permissions::ORDERS_VIEW;

    protected static ?string $editPermission = Permissions::CATALOG_EDIT;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Код (латиница)')->required()->alphaDash()->unique(ignoreRecord: true)
                ->helperText('Например: grade, gost, diameter, thickness, length'),
            TextInput::make('name')->label('Название')->required(),
            Select::make('type')->label('Тип')->required()->live()
                ->options(['number' => 'Число', 'text' => 'Текст', 'enum' => 'Выбор из списка']),
            TextInput::make('unit')->label('Единица')->placeholder('мм'),
            TagsInput::make('options')->label('Варианты')->visible(fn (Get $get) => $get('type') === 'enum'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Название')->searchable(),
                TextColumn::make('code')->label('Код'),
                TextColumn::make('type')->label('Тип'),
                TextColumn::make('unit')->label('Единица'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttributes::route('/'),
            'create' => CreateAttribute::route('/create'),
            'edit' => EditAttribute::route('/{record}/edit'),
        ];
    }
}
