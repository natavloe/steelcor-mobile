<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class CategoryResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Category::class;

    protected static ?string $modelLabel = 'категория';

    protected static ?string $pluralModelLabel = 'Категории';

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static ?string $viewPermission = Permissions::ORDERS_VIEW;

    protected static ?string $editPermission = Permissions::CATALOG_EDIT;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->label('Название')->required()->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug($state ?? '')) : null),
                TextInput::make('slug')->label('Адрес (латиница)')->required()->unique(ignoreRecord: true)->alphaDash(),
                Select::make('parent_id')->label('Родительская категория')->relationship('parent', 'name')->searchable()->preload(),
                TextInput::make('sort')->label('Порядок')->numeric()->default(0),
                Toggle::make('is_published')->label('Показывать')->default(true),
            ]),
            Section::make('Характеристики категории')
                ->description('По ним строятся фильтры и поиск в приложении')
                ->schema([
                    Repeater::make('categoryAttributes')
                        ->hiddenLabel()
                        ->relationship('categoryAttributes')
                        ->orderColumn('sort')
                        ->columns(3)
                        ->schema([
                            Select::make('attribute_id')->label('Характеристика')->relationship('attribute', 'name')->required()->distinct(),
                            Toggle::make('is_filterable')->label('В фильтрах')->default(true),
                            Toggle::make('is_required')->label('Обязательна'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('Название')->searchable(),
                TextColumn::make('parent.name')->label('Родитель'),
                TextColumn::make('products_count')->label('Позиций')->counts('products'),
                IconColumn::make('is_published')->label('Показывается')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
