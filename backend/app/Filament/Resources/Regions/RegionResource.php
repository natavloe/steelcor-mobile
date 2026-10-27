<?php

namespace App\Filament\Resources\Regions;

use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\Regions\Pages\CreateRegion;
use App\Filament\Resources\Regions\Pages\EditRegion;
use App\Filament\Resources\Regions\Pages\ListRegions;
use App\Models\Region;
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
use Filament\Tables\Table;
use UnitEnum;

class RegionResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Region::class;

    protected static ?string $modelLabel = 'регион';

    protected static ?string $pluralModelLabel = 'Регионы';

    protected static string|UnitEnum|null $navigationGroup = 'Справочники';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $viewPermission = Permissions::ORDERS_VIEW;

    protected static ?string $editPermission = Permissions::SETTINGS_MANAGE;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Код')->helperText('Как поддомен сайта: smr, msk, ekb')
                ->required()->maxLength(16)->unique(ignoreRecord: true)->alphaDash(),
            TextInput::make('name')->label('Город')->required(),
            Select::make('timezone')->label('Часовой пояс')->required()->default('Europe/Moscow')->searchable()
                ->options(fn () => array_combine($zones = array_values(array_filter(timezone_identifiers_list(), fn ($z) => str_starts_with($z, 'Europe/') || str_starts_with($z, 'Asia/'))), $zones)),
            Select::make('seller_entity_id')->label('Юрлицо-продавец')->relationship('sellerEntity', 'name_short')
                ->helperText('Чьи реквизиты будут в счетах региона (вопрос О-1)'),
            Textarea::make('ship_from_address')->label('Адрес отправления по умолчанию (для ТК)')->columnSpanFull(),
            TextInput::make('phone')->label('Телефон')->tel(),
            TextInput::make('email')->label('Почта')->email(),
            TextInput::make('sort')->label('Порядок')->numeric()->default(0),
            Toggle::make('is_active')->label('Показывать в приложении')
                ->helperText('Включайте, когда для региона готовы цены, контакты и реквизиты'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('code')->label('Код')->searchable(),
                TextColumn::make('name')->label('Город')->searchable(),
                TextColumn::make('sellerEntity.name_short')->label('Продавец'),
                TextColumn::make('phone')->label('Телефон'),
                IconColumn::make('is_active')->label('Активен')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegions::route('/'),
            'create' => CreateRegion::route('/create'),
            'edit' => EditRegion::route('/{record}/edit'),
        ];
    }
}
