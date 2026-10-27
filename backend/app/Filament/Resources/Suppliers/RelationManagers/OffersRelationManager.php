<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Enums\Unit;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Что поставщик может поставить и по какой закупочной цене. */
class OffersRelationManager extends RelationManager
{
    protected static string $relationship = 'offers';

    protected static ?string $title = 'Предложения поставщика';

    protected static ?string $modelLabel = 'предложение поставщика';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Select::make('product_id')->label('Позиция каталога')->relationship('product', 'name')->searchable()->columnSpan(2),
            Select::make('region_id')->label('Регион')->relationship('region', 'name'),
            Textarea::make('description')->label('Как у поставщика')->columnSpanFull(),
            TextInput::make('purchase_price')->label('Закупочная цена, ₽')->numeric()->required(),
            Select::make('unit')->label('За')->options(Unit::class)->required(),
            TextInput::make('available_qty')->label('Доступно')->numeric(),
            TextInput::make('lead_time_days')->label('Срок готовности, дней')->numeric(),
            DatePicker::make('valid_until')->label('Цена до'),
            Select::make('source')->label('Источник')->required()->default('call')
                ->options(['price_list' => 'Прайс', 'call' => 'Звонок', 'email' => 'Письмо', 'api' => 'API']),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('product.name')->label('Позиция')->placeholder('—')->wrap(),
                TextColumn::make('description')->label('У поставщика')->limit(40),
                TextColumn::make('region.name')->label('Регион'),
                TextColumn::make('purchase_price')->label('Цена')->money('RUB'),
                TextColumn::make('unit')->label('За'),
                TextColumn::make('lead_time_days')->label('Срок, дн.'),
                TextColumn::make('valid_until')->label('До')->date('d.m.Y'),
            ])
            ->headerActions([CreateAction::make()->mutateDataUsing(fn (array $data) => $data + ['recorded_by' => auth('staff')->id()])])
            ->recordActions([EditAction::make()]);
    }
}
