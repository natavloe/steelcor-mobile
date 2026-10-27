<?php

namespace App\Filament\Resources\Products;

use App\Enums\Unit;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\RegionProductsRelationManager;
use App\Models\Product;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Номенклатура. Складских остатков нет: доступность и цена задаются по регионам. */
class ProductResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Product::class;

    protected static ?string $modelLabel = 'позиция';

    protected static ?string $pluralModelLabel = 'Номенклатура';

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $viewPermission = Permissions::ORDERS_VIEW;

    protected static ?string $editPermission = Permissions::CATALOG_EDIT;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Позиция')->columns(2)->schema([
                Select::make('category_id')->label('Категория')->relationship('category', 'name')->required()->searchable()->preload(),
                TextInput::make('name')->label('Наименование')->required(),
                TextInput::make('slug')->label('Адрес (латиница)')->required()->unique(ignoreRecord: true)->alphaDash(),
                TextInput::make('grade')->label('Марка'),
                TextInput::make('gost')->label('ГОСТ / ТУ'),
                TextInput::make('external_ref')->label('Код в 1С / на сайте')->unique(ignoreRecord: true),
                KeyValue::make('attrs')->label('Характеристики')->keyLabel('Код характеристики')->valueLabel('Значение')->columnSpanFull(),
            ]),
            Section::make('Единицы и габариты')->columns(3)
                ->description('Масса нужна для пересчёта шт/м/кг/т и для выбора способа доставки')
                ->schema([
                    Select::make('base_unit')->label('Базовая единица цены')->options(Unit::class)->required()->default('t'),
                    TextInput::make('weight_per_m')->label('Масса 1 м, кг')->numeric(),
                    TextInput::make('weight_per_pc')->label('Масса 1 шт, кг')->numeric(),
                    TextInput::make('std_length_mm')->label('Стандартная длина, мм')->numeric(),
                    TextInput::make('max_length_mm')->label('Наибольшая длина места, мм')->numeric(),
                    Toggle::make('is_oversize')->label('Длинномер / негабарит')->helperText('Курьерские службы не предлагаются'),
                ]),
            Toggle::make('is_published')->label('Показывать в каталоге')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Наименование')->searchable()->wrap(),
                TextColumn::make('category.name')->label('Категория'),
                TextColumn::make('grade')->label('Марка')->searchable(),
                TextColumn::make('gost')->label('ГОСТ')->searchable()->toggleable(),
                TextColumn::make('weight_per_m')->label('кг/м')->toggleable(),
                IconColumn::make('is_oversize')->label('Негабарит')->boolean()->toggleable(),
                IconColumn::make('is_published')->label('В каталоге')->boolean(),
            ])
            ->filters([SelectFilter::make('category_id')->label('Категория')->relationship('category', 'name')])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [RegionProductsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
