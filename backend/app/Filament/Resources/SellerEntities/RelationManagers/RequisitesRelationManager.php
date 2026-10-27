<?php

namespace App\Filament\Resources\SellerEntities\RelationManagers;

use App\Enums\ApprovalStatus;
use App\Filament\Concerns\RunsWorkflow;
use App\Exceptions\WorkflowException;
use App\Models\SellerRequisites;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Версии реквизитов продавца. Черновик правится, утверждённая версия — неизменна.
 * Утверждает другой сотрудник, не тот, кто вносил (принцип двух ключей).
 */
class RequisitesRelationManager extends RelationManager
{
    use RunsWorkflow;

    protected static string $relationship = 'requisites';

    protected static ?string $title = 'Реквизиты (версии)';

    protected static ?string $modelLabel = 'версия реквизитов';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Организация')->columns(2)->schema([
                TextInput::make('name_full')->label('Полное наименование')->required()->columnSpanFull(),
                TextInput::make('name_short')->label('Краткое наименование')->required(),
                TextInput::make('inn')->label('ИНН')->required()->regex('/^\d{10}(\d{2})?$/'),
                TextInput::make('kpp')->label('КПП')->regex('/^\d{9}$/'),
                TextInput::make('ogrn')->label('ОГРН')->regex('/^\d{13}(\d{2})?$/'),
                TextInput::make('okpo')->label('ОКПО'),
                TextInput::make('legal_address')->label('Юридический адрес')->required()->columnSpanFull(),
                TextInput::make('postal_address')->label('Почтовый адрес')->columnSpanFull(),
            ]),
            Section::make('Банк')->columns(2)->schema([
                TextInput::make('bank_name')->label('Банк')->required()->columnSpanFull(),
                TextInput::make('bik')->label('БИК')->required()->regex('/^\d{9}$/'),
                TextInput::make('account')->label('Расчётный счёт')->required()->regex('/^\d{20}$/'),
                TextInput::make('corr_account')->label('Корр. счёт')->regex('/^\d{20}$/'),
            ]),
            Section::make('Подписант и прочее')->columns(2)->schema([
                TextInput::make('signer_name')->label('ФИО подписанта')->required(),
                TextInput::make('signer_position')->label('Должность')->required(),
                TextInput::make('signer_basis')->label('Действует на основании')->required()->placeholder('Устава'),
                TextInput::make('vat_rate_default')->label('Ставка НДС по умолчанию, %')->numeric()
                    ->helperText('Уточнить у бухгалтерии (вопрос О-5)'),
                TextInput::make('phone')->label('Телефон'),
                TextInput::make('email')->label('Почта')->email(),
                TextInput::make('website')->label('Сайт'),
                DatePicker::make('valid_from')->label('Действует с')->required()->default(now()),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('version', 'desc')
            ->columns([
                TextColumn::make('version')->label('Версия'),
                TextColumn::make('status')->label('Статус')->badge()
                    ->color(fn (ApprovalStatus $state) => match ($state) {
                        ApprovalStatus::Approved => 'success', ApprovalStatus::Draft => 'warning', default => 'gray',
                    }),
                TextColumn::make('valid_from')->label('Действует с')->date('d.m.Y'),
                TextColumn::make('bank_name')->label('Банк'),
                TextColumn::make('account')->label('Р/с'),
                TextColumn::make('creator.name')->label('Внёс'),
                TextColumn::make('approver.name')->label('Утвердил'),
                TextColumn::make('approved_at')->label('Когда')->dateTime('d.m.Y H:i'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Новая версия')
                    ->visible(fn () => auth('staff')->user()?->can(Permissions::TEMPLATES_UPLOAD))
                    ->mutateDataUsing(function (array $data): array {
                        $data['version'] = (int) $this->getOwnerRecord()->requisites()->max('version') + 1;
                        $data['status'] = ApprovalStatus::Draft;
                        $data['created_by'] = auth('staff')->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (SellerRequisites $record) => $record->status === ApprovalStatus::Draft
                        && auth('staff')->user()?->can(Permissions::TEMPLATES_UPLOAD)),
                Action::make('approve')
                    ->label('Утвердить')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('После утверждения реквизиты попадут в счета и договоры. Изменить утверждённую версию нельзя — только создать новую.')
                    ->visible(fn (SellerRequisites $record) => $record->status === ApprovalStatus::Draft
                        && auth('staff')->user()?->can(Permissions::TEMPLATES_APPROVE))
                    ->action(fn (SellerRequisites $record) => static::run(function ($actor) use ($record) {
                        if ($record->created_by === $actor->id) {
                            throw new WorkflowException('same_person', 'Утверждать должен другой сотрудник, не тот, кто вносил реквизиты.');
                        }
                        $record->update(['status' => ApprovalStatus::Approved, 'approved_by' => $actor->id, 'approved_at' => now()]);
                    }, 'Реквизиты утверждены')),
                Action::make('archive')
                    ->label('В архив')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (SellerRequisites $record) => $record->status === ApprovalStatus::Approved
                        && auth('staff')->user()?->can(Permissions::TEMPLATES_APPROVE))
                    ->action(fn (SellerRequisites $record) => $record->update(['status' => ApprovalStatus::Archived])),
            ]);
    }
}
