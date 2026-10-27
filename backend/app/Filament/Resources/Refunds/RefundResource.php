<?php

namespace App\Filament\Resources\Refunds;

use App\Enums\RefundStatus;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Concerns\RunsWorkflow;
use App\Filament\Resources\Refunds\Pages\ListRefunds;
use App\Models\BankTransaction;
use App\Models\Issue;
use App\Models\Order;
use App\Models\Refund;
use App\Services\RefundService;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Возвраты. Сумму и основание определяет сотрудник по договору; утверждает другой сотрудник;
 * выполненным возврат считается только по исходящему платежу из выписки.
 */
class RefundResource extends Resource
{
    use GuardsByPermission, RunsWorkflow;

    protected static ?string $model = Refund::class;

    protected static ?string $modelLabel = 'возврат';

    protected static ?string $pluralModelLabel = 'Возвраты';

    protected static string|UnitEnum|null $navigationGroup = 'Финансы';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static ?string $viewPermission = Permissions::REFUNDS_REQUEST;

    protected static ?string $editPermission = null;

    public static function requestAction(): Action
    {
        return Action::make('request')->label('Оформить возврат')->icon('heroicon-o-plus')
            ->visible(fn () => auth('staff')->user()?->can(Permissions::REFUNDS_REQUEST))
            ->schema([
                Select::make('order_id')->label('Заказ')->required()->searchable()
                    ->getSearchResultsUsing(fn (string $search) => Order::query()->where('number', 'ilike', "%{$search}%")->limit(20)->pluck('number', 'id')->all())
                    ->getOptionLabelUsing(fn ($value) => Order::find($value)?->number)
                    ->live(),
                Select::make('issue_id')->label('Инцидент')
                    ->options(fn ($get) => $get('order_id') ? Issue::where('order_id', $get('order_id'))->get()->mapWithKeys(fn ($i) => [$i->id => $i->type->label().': '.mb_substr($i->description, 0, 60)])->all() : []),
                TextInput::make('amount')->label('Сумма, ₽')->numeric()->required(),
                Textarea::make('basis')->label('Основание (пункт договора, соглашение с клиентом)')->required(),
            ])
            ->action(fn (array $data) => static::run(fn ($actor) => app(RefundService::class)->request(
                Order::findOrFail($data['order_id']), isset($data['issue_id']) ? Issue::find($data['issue_id']) : null,
                (string) $data['amount'], $data['basis'], $actor,
            ), 'Возврат оформлен, ждёт утверждения'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Дата')->date('d.m.Y'),
                TextColumn::make('order.number')->label('Заказ'),
                TextColumn::make('amount')->label('Сумма')->money('RUB'),
                TextColumn::make('basis')->label('Основание')->wrap()->limit(80),
                TextColumn::make('status')->label('Статус')->badge(),
            ])
            ->recordActions([
                Action::make('approve')->label('Утвердить')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (Refund $record) => $record->status === RefundStatus::Requested && auth('staff')->user()?->can(Permissions::REFUNDS_APPROVE))
                    ->requiresConfirmation()
                    ->action(fn (Refund $record) => static::run(fn ($actor) => app(RefundService::class)->approve($record, $actor), 'Возврат утверждён')),
                Action::make('reject')->label('Отклонить')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (Refund $record) => $record->status === RefundStatus::Requested && auth('staff')->user()?->can(Permissions::REFUNDS_APPROVE))
                    ->schema([Textarea::make('reason')->label('Причина')->required()])
                    ->action(fn (Refund $record, array $data) => static::run(fn ($actor) => app(RefundService::class)->reject($record, $actor, $data['reason']), 'Возврат отклонён')),
                Action::make('complete')->label('Найден в выписке')->icon('heroicon-o-banknotes')
                    ->visible(fn (Refund $record) => in_array($record->status, [RefundStatus::Approved, RefundStatus::Sent], true) && auth('staff')->user()?->can(Permissions::REFUNDS_APPROVE))
                    ->schema(fn (Refund $record) => [
                        Select::make('tx_id')->label('Исходящий платёж из выписки')->required()
                            ->options(BankTransaction::query()->where('direction', 'out')->where('amount', $record->amount)
                                ->whereNotIn('id', Refund::whereNotNull('bank_transaction_id')->pluck('bank_transaction_id'))
                                ->get()->mapWithKeys(fn ($t) => [$t->id => $t->op_date->format('d.m.Y').' · '.$t->amount.' ₽ · '.$t->payer_name])->all()),
                    ])
                    ->action(fn (Refund $record, array $data) => static::run(fn ($actor) => app(RefundService::class)->complete($record, BankTransaction::findOrFail($data['tx_id']), $actor), 'Возврат выполнен')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListRefunds::route('/')];
    }
}
