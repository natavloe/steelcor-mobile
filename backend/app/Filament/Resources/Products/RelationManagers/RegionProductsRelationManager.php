<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Enums\Availability;
use App\Enums\Unit;
use App\Models\StaffUser;
use App\Support\Permissions;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Что видит клиент в регионе: статус доступности, ориентировочная цена и срок. */
class RegionProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'regionProducts';

    protected static ?string $title = 'Цены и доступность по регионам';

    protected static ?string $modelLabel = 'условия в регионе';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    private static function canEditPrices(): bool
    {
        return (bool) auth('staff')->user()?->can(Permissions::PRICES_EDIT);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('region_id')->label('Регион')->relationship('region', 'name',
                fn (Builder $query) => ($ids = auth('staff')->user()?->regionIds()) === null ? $query : $query->whereIn('id', $ids))
                ->required(),
            Select::make('availability')->label('Доступность')->options(Availability::class)->required(),
            TextInput::make('public_price')->label('Ориентировочная цена, ₽')->numeric()
                ->helperText('Пусто — «цена по запросу». Клиент видит её как предварительную'),
            Select::make('price_unit')->label('За единицу')->options(Unit::class),
            Toggle::make('vat_included')->label('Цена с НДС')->default(true),
            DatePicker::make('price_valid_until')->label('Цена актуальна до'),
            TextInput::make('lead_time_min_days')->label('Срок от, дней')->numeric(),
            TextInput::make('lead_time_max_days')->label('Срок до, дней')->numeric(),
            Toggle::make('is_visible')->label('Показывать')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $staff = auth('staff')->user();
                if ($staff instanceof StaffUser && ($ids = $staff->regionIds()) !== null) {
                    $query->whereIn('region_id', $ids);
                }
            })
            ->columns([
                TextColumn::make('region.name')->label('Регион'),
                TextColumn::make('availability')->label('Доступность')->badge(),
                TextColumn::make('public_price')->label('Цена')->money('RUB'),
                TextColumn::make('price_unit')->label('За'),
                TextColumn::make('price_valid_until')->label('До')->date('d.m.Y'),
                TextColumn::make('lead_time_max_days')->label('Срок до, дн.'),
                IconColumn::make('is_visible')->label('Видна')->boolean(),
            ])
            ->headerActions([CreateAction::make()->visible(fn () => self::canEditPrices())])
            ->recordActions([EditAction::make()->visible(fn () => self::canEditPrices())]);
    }
}
