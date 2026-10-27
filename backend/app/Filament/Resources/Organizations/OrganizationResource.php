<?php

namespace App\Filament\Resources\Organizations;

use App\Enums\OrganizationType;
use App\Enums\VerificationStatus;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Concerns\RunsWorkflow;
use App\Filament\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Resources\Organizations\RelationManagers\AddressesRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\MembersRelationManager;
use App\Models\Organization;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Организации-покупатели. Документы формируются только для проверенных реквизитов. */
class OrganizationResource extends Resource
{
    use GuardsByPermission, RunsWorkflow;

    protected static ?string $model = Organization::class;

    protected static ?string $modelLabel = 'клиент';

    protected static ?string $pluralModelLabel = 'Клиенты';

    protected static string|UnitEnum|null $navigationGroup = 'Продажи';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'name_short';

    protected static ?string $viewPermission = Permissions::CLIENTS_VIEW;

    protected static ?string $editPermission = Permissions::CLIENTS_VERIFY;

    protected static ?string $regionColumn = 'region_id';

    public static function form(Schema $schema): Schema
    {
        $canCredit = fn () => (bool) auth('staff')->user()?->can(Permissions::CLIENTS_CREDIT);

        return $schema->components([
            Section::make('Реквизиты')->columns(3)->schema([
                Select::make('type')->label('Тип')->options(OrganizationType::class)->required()->default('legal'),
                TextInput::make('inn')->label('ИНН')->required()->regex('/^\d{10}(\d{2})?$/'),
                TextInput::make('kpp')->label('КПП')->regex('/^\d{9}$/'),
                TextInput::make('name_full')->label('Полное наименование')->required()->columnSpan(2),
                TextInput::make('name_short')->label('Краткое наименование')->required(),
                TextInput::make('ogrn')->label('ОГРН / ОГРНИП')->regex('/^\d{13}(\d{2})?$/'),
                Textarea::make('legal_address')->label('Юридический адрес')->columnSpan(2),
                TextInput::make('bank_name')->label('Банк'),
                TextInput::make('bik')->label('БИК')->regex('/^\d{9}$/'),
                TextInput::make('account')->label('Р/с')->regex('/^\d{20}$/'),
                TextInput::make('corr_account')->label('Корр. счёт')->regex('/^\d{20}$/'),
                TextInput::make('signer_name')->label('Подписант (ФИО)'),
                TextInput::make('signer_position')->label('Должность'),
                TextInput::make('signer_basis')->label('Основание'),
            ]),
            Section::make('Работа с клиентом')->columns(3)->schema([
                Select::make('region_id')->label('Регион')->relationship('region', 'name'),
                Select::make('manager_id')->label('Менеджер')->relationship('manager', 'name'),
                TextInput::make('edo_operator')->label('Оператор ЭДО клиента'),
                Select::make('payment_terms')->label('Условия оплаты')
                    ->options(['prepay' => 'Предоплата', 'deferral' => 'Отсрочка'])->default('prepay')
                    ->disabled(fn () => ! $canCredit()),
                TextInput::make('deferral_days')->label('Отсрочка, дней')->numeric()->disabled(fn () => ! $canCredit()),
                TextInput::make('credit_limit')->label('Кредитный лимит, ₽')->numeric()->disabled(fn () => ! $canCredit()),
                Textarea::make('internal_note')->label('Внутренняя заметка (клиент не видит)')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_short')->label('Клиент')->searchable(),
                TextColumn::make('inn')->label('ИНН')->searchable(),
                TextColumn::make('region.name')->label('Регион'),
                TextColumn::make('verification_status')->label('Реквизиты')->badge()
                    ->color(fn (VerificationStatus $state) => match ($state) {
                        VerificationStatus::Verified => 'success', VerificationStatus::Rejected => 'danger', default => 'warning',
                    }),
                TextColumn::make('payment_terms')->label('Оплата')
                    ->formatStateUsing(fn ($state) => $state === 'deferral' ? 'Отсрочка' : 'Предоплата'),
                TextColumn::make('manager.name')->label('Менеджер'),
            ])
            ->filters([
                SelectFilter::make('verification_status')->label('Реквизиты')->options(VerificationStatus::class),
                SelectFilter::make('region_id')->label('Регион')->relationship('region', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                static::verifyAction(),
            ]);
    }

    public static function verifyAction(): Action
    {
        return Action::make('verify')
            ->label('Реквизиты проверены')
            ->icon('heroicon-o-shield-check')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Подтвердите, что реквизиты сверены с выпиской ЕГРЮЛ/ЕГРИП. После этого по клиенту можно формировать документы.')
            ->visible(fn (Organization $record) => $record->verification_status !== VerificationStatus::Verified
                && auth('staff')->user()?->can(Permissions::CLIENTS_VERIFY))
            ->action(fn (Organization $record) => static::run(fn ($actor) => $record->update([
                'verification_status' => VerificationStatus::Verified, 'verified_by' => $actor->id, 'verified_at' => now(),
            ]), 'Реквизиты отмечены как проверенные'));
    }

    public static function getRelations(): array
    {
        return [MembersRelationManager::class, AddressesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizations::route('/'),
            'create' => CreateOrganization::route('/create'),
            'edit' => EditOrganization::route('/{record}/edit'),
        ];
    }
}
