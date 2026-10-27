<?php

namespace App\Filament\Resources\BankTransactions;

use App\Enums\InvoiceStatus;
use App\Enums\MatchStatus;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Concerns\RunsWorkflow;
use App\Filament\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\PaymentAllocation;
use App\Services\Bank\PaymentMatcher;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Движения по расчётному счёту из выписки. Источник истины об оплате.
 * Ручного ввода сумм нет — только загрузка выписки (или API банка на этапе 4).
 */
class BankTransactionResource extends Resource
{
    use GuardsByPermission, RunsWorkflow;

    protected static ?string $model = BankTransaction::class;

    protected static ?string $modelLabel = 'платёж';

    protected static ?string $pluralModelLabel = 'Выписка банка';

    protected static string|UnitEnum|null $navigationGroup = 'Финансы';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $viewPermission = Permissions::PAYMENTS_MATCH;

    protected static ?string $editPermission = null;

    public static function getNavigationBadge(): ?string
    {
        $count = BankTransaction::query()->where('direction', 'in')->where('match_status', MatchStatus::Unmatched->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('op_date', 'desc')
            ->columns([
                TextColumn::make('op_date')->label('Дата')->date('d.m.Y')->sortable(),
                TextColumn::make('direction')->label('')->formatStateUsing(fn ($state) => $state === 'in' ? '⬇ приход' : '⬆ расход'),
                TextColumn::make('amount')->label('Сумма')->money('RUB')->sortable(),
                TextColumn::make('payer_name')->label('Контрагент')->wrap()->searchable(),
                TextColumn::make('payer_inn')->label('ИНН')->searchable(),
                TextColumn::make('purpose')->label('Назначение платежа')->wrap()->limit(120)->searchable(),
                TextColumn::make('match_status')->label('Разбор')->badge(),
                TextColumn::make('allocated')->label('Привязано к')->listWithLineBreaks()
                    ->state(fn (BankTransaction $record) => $record->allocations()->whereNull('cancelled_at')->with('invoice')->get()
                        ->map(fn ($a) => $a->invoice->number.': '.$a->amount.' ₽ ('.($a->method === 'auto' ? 'авто' : 'вручную').')')->all()),
            ])
            ->filters([
                SelectFilter::make('match_status')->label('Разбор')->options(MatchStatus::class)->default(MatchStatus::Unmatched->value),
                SelectFilter::make('direction')->label('Направление')->options(['in' => 'Приход', 'out' => 'Расход']),
            ])
            ->recordActions([
                Action::make('match')->label('Привязать к счёту')->icon('heroicon-o-link')
                    ->visible(fn (BankTransaction $record) => $record->direction === 'in' && in_array($record->match_status, [MatchStatus::Unmatched, MatchStatus::PartiallyMatched], true))
                    ->schema(fn (BankTransaction $record) => [
                        Select::make('invoice_id')->label('Счёт')->required()->searchable()
                            ->options(fn () => Invoice::query()->where('status', InvoiceStatus::Issued->value)
                                ->where('seller_entity_id', $record->seller_entity_id)
                                ->with('organization')->latest()->limit(200)->get()
                                ->mapWithKeys(fn (Invoice $i) => [$i->id => "{$i->number} · {$i->organization->name_short} (ИНН {$i->organization->inn}) · {$i->amount} ₽"])->all())
                            ->helperText('Проверьте, что плательщик и сумма соответствуют счёту'),
                        TextInput::make('amount')->label('Сумма привязки, ₽')->numeric()->required()
                            ->default(fn () => app(PaymentMatcher::class)->unallocated($record)),
                    ])
                    ->action(fn (BankTransaction $record, array $data) => static::run(
                        fn ($actor) => app(PaymentMatcher::class)->manualMatch($record, Invoice::findOrFail($data['invoice_id']), (string) $data['amount'], $actor),
                        'Платёж привязан',
                    )),
                Action::make('unmatch')->label('Отменить привязку')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (BankTransaction $record) => $record->allocations()->whereNull('cancelled_at')->exists())
                    ->schema(fn (BankTransaction $record) => [
                        Select::make('allocation_id')->label('Привязка')->required()
                            ->options($record->allocations()->whereNull('cancelled_at')->with('invoice')->get()
                                ->mapWithKeys(fn (PaymentAllocation $a) => [$a->id => $a->invoice->number.': '.$a->amount.' ₽'])->all()),
                        Textarea::make('reason')->label('Причина')->required(),
                    ])
                    ->requiresConfirmation()
                    ->action(fn (array $data) => static::run(
                        fn ($actor) => app(PaymentMatcher::class)->cancelAllocation(PaymentAllocation::findOrFail($data['allocation_id']), $actor, $data['reason']),
                        'Привязка отменена',
                    )),
                Action::make('ignore')->label('Не относится к заказам')->icon('heroicon-o-eye-slash')->color('gray')
                    ->visible(fn (BankTransaction $record) => $record->match_status === MatchStatus::Unmatched)
                    ->requiresConfirmation()
                    ->action(fn (BankTransaction $record) => static::run(fn ($actor) => app(PaymentMatcher::class)->ignore($record, $actor), 'Отмечено')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankTransactions::route('/'),
        ];
    }
}
