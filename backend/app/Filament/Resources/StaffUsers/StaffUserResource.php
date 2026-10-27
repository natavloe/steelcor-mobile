<?php

namespace App\Filament\Resources\StaffUsers;

use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\StaffUsers\Pages\CreateStaffUser;
use App\Filament\Resources\StaffUsers\Pages\EditStaffUser;
use App\Filament\Resources\StaffUsers\Pages\ListStaffUsers;
use App\Models\StaffUser;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Сотрудники: роли и доступ к регионам. */
class StaffUserResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = StaffUser::class;

    protected static ?string $modelLabel = 'сотрудник';

    protected static ?string $pluralModelLabel = 'Сотрудники';

    protected static string|UnitEnum|null $navigationGroup = 'Администрирование';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $viewPermission = Permissions::STAFF_MANAGE;

    protected static ?string $editPermission = Permissions::STAFF_MANAGE;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('ФИО')->required(),
            TextInput::make('email')->label('Почта (логин)')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')->label('Пароль')->password()->revealable()
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->minLength(12)
                ->helperText('Не короче 12 символов. При редактировании оставьте пустым, чтобы не менять'),
            Toggle::make('is_active')->label('Доступ разрешён')->default(true),
            CheckboxList::make('roles')->label('Роли')->relationship('roles', 'name')
                ->getOptionLabelFromRecordUsing(fn ($record) => Permissions::ROLES[$record->name]['label'] ?? $record->name)
                ->columns(2)->columnSpanFull(),
            Toggle::make('all_regions')->label('Все регионы')->live(),
            Select::make('regions')->label('Регионы')->relationship('regions', 'name')->multiple()->preload()
                ->hidden(fn (Get $get) => (bool) $get('all_regions')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('ФИО')->searchable(),
                TextColumn::make('email')->label('Почта')->searchable(),
                TextColumn::make('roles.name')->label('Роли')->badge()
                    ->formatStateUsing(fn ($state) => Permissions::ROLES[$state]['label'] ?? $state),
                IconColumn::make('all_regions')->label('Все регионы')->boolean(),
                IconColumn::make('is_active')->label('Доступ')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaffUsers::route('/'),
            'create' => CreateStaffUser::route('/create'),
            'edit' => EditStaffUser::route('/{record}/edit'),
        ];
    }
}
