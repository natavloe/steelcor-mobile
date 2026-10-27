<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Enums\Carrier;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Filament\Concerns\RunsWorkflow;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\OrderWorkflow;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Отправления по заказу. Пока СДЭК и Деловые Линии не подключены (этап 6),
 * доступна доставка компании и наёмный транспорт с ручной ценой. Имитации ТК нет.
 */
class ShipmentsRelationManager extends RelationManager
{
    use RunsWorkflow;

    protected static string $relationship = 'shipments';

    protected static ?string $title = 'Доставка';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    private static function canManage(): bool
    {
        return (bool) auth('staff')->user()?->can(Permissions::SHIPMENTS_MANAGE);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('carrier')->label('Способ')->required()
                ->options([Carrier::Own->value => Carrier::Own->label(), Carrier::Hired->value => Carrier::Hired->label()])
                ->helperText('СДЭК и Деловые Линии подключаются на этапе 6'),
            Textarea::make('from_address')->label('Откуда (склад поставщика)'),
            TextInput::make('vehicle_requirements')->label('Транспорт')->placeholder('Длинномер 12 м, манипулятор'),
            TextInput::make('quoted_cost')->label('Стоимость, ₽')->numeric(),
            Toggle::make('cost_confirmed')->label('Стоимость подтверждена перевозчиком'),
            TextInput::make('weight_kg')->label('Вес, кг')->numeric(),
            TextInput::make('max_length_mm')->label('Длина, мм')->numeric(),
            DatePicker::make('planned_date')->label('Плановая дата'),
            TextInput::make('time_slot')->label('Интервал'),
            TextInput::make('driver_name')->label('Водитель'),
            TextInput::make('driver_phone')->label('Телефон водителя'),
            TextInput::make('vehicle_number')->label('Госномер'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('carrier')->label('Перевозчик')->badge(),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('planned_date')->label('Дата')->date('d.m.Y'),
                TextColumn::make('quoted_cost')->label('Стоимость')->money('RUB'),
                IconColumn::make('cost_confirmed')->label('Подтв.')->boolean(),
                TextColumn::make('tracking_number')->label('Трек-номер')->placeholder('—'),
                TextColumn::make('driver_name')->label('Водитель')->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->label('Оформить доставку')
                    ->visible(fn () => self::canManage() && in_array($this->getOwnerRecord()->status, [OrderStatus::ReadyToShip, OrderStatus::ArrangingDelivery, OrderStatus::AwaitingReadiness], true))
                    ->mutateDataUsing(fn (array $data) => $data + [
                        'status' => ShipmentStatus::AwaitingBooking, 'quote_source' => 'manual',
                        'to_address_id' => $this->getOwnerRecord()->delivery_address_id,
                    ])
                    ->after(fn () => app(OrderWorkflow::class)->recomputeDelivery($this->getOwnerRecord()->refresh(), static::actor())),
            ])
            ->recordActions([
                EditAction::make()->visible(fn () => self::canManage()),
                Action::make('status')->label('Сменить статус')->icon('heroicon-o-arrow-right')
                    ->visible(fn () => self::canManage())
                    ->schema([
                        Select::make('status')->label('Новый статус')->options(ShipmentStatus::class)->required(),
                        TextInput::make('note')->label('Комментарий'),
                    ])
                    ->action(function (Shipment $record, array $data) {
                        static::run(function ($actor) use ($record, $data) {
                            $status = ShipmentStatus::from($data['status']);
                            $record->setStatus('status', $status, $actor, $data['note'] ?? null);
                            $record->events()->create(['status' => $status, 'carrier_status_text' => $data['note'] ?? null, 'occurred_at' => now(), 'source' => 'staff']);
                            /** @var Order $order */
                            $order = $this->getOwnerRecord()->refresh();
                            app(OrderWorkflow::class)->recomputeDelivery($order, $actor);
                        }, 'Статус доставки обновлён');
                    }),
            ]);
    }
}
