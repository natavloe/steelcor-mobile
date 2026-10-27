<?php

namespace App\Filament\Resources\Suppliers;

use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Resources\Suppliers\RelationManagers\OffersRelationManager;
use App\Models\Supplier;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** ВНУТРЕННЕЕ: поставщики и их предложения. Клиенты этих данных не видят. */
class SupplierResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Supplier::class;

    protected static ?string $modelLabel = 'поставщик';

    protected static ?string $pluralModelLabel = 'Поставщики';

    protected static string|UnitEnum|null $navigationGroup = 'Закупки';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $viewPermission = Permissions::SUPPLIERS_MANAGE;

    protected static ?string $editPermission = Permissions::SUPPLIERS_MANAGE;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextInput::make('name')->label('Название')->required()->columnSpan(2),
                TextInput::make('inn')->label('ИНН')->regex('/^\d{10}(\d{2})?$/'),
                TextInput::make('kpp')->label('КПП'),
                Select::make('regions')->label('Регионы')->relationship('regions', 'name')->multiple()->preload(),
                TextInput::make('rating')->label('Надёжность (1–5)')->numeric()->minValue(1)->maxValue(5),
                Textarea::make('ship_from_address')->label('Адрес отгрузки')->columnSpanFull(),
                Textarea::make('payment_terms')->label('Условия оплаты поставщику')->columnSpanFull(),
                Textarea::make('note')->label('Заметки')->columnSpanFull(),
                Toggle::make('is_active')->label('Работаем')->default(true),
            ]),
            Section::make('Контакты')->schema([
                Repeater::make('contacts')->hiddenLabel()->columns(4)->defaultItems(0)->addActionLabel('Добавить контакт')->schema([
                    TextInput::make('name')->label('Имя'),
                    TextInput::make('position')->label('Должность'),
                    TextInput::make('phone')->label('Телефон'),
                    TextInput::make('email')->label('Почта')->email(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Название')->searchable(),
                TextColumn::make('inn')->label('ИНН')->searchable(),
                TextColumn::make('regions.name')->label('Регионы')->badge(),
                TextColumn::make('rating')->label('Надёжность'),
                IconColumn::make('is_active')->label('Работаем')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [OffersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
