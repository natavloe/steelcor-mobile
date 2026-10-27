<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Инциденты: причина, действия менеджера, решение клиента. */
class IssuesRelationManager extends RelationManager
{
    protected static string $relationship = 'issues';

    protected static ?string $title = 'Инциденты';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    private static function canManage(): bool
    {
        return (bool) auth('staff')->user()?->can(Permissions::ISSUES_MANAGE);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Открыт')->dateTime('d.m.Y H:i'),
                TextColumn::make('type')->label('Тип')->badge(),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('description')->label('Что случилось')->wrap(),
                TextColumn::make('actions_list')->label('Действия')->listWithLineBreaks()
                    ->state(fn (Issue $record) => $record->actions()->orderBy('id')->get()
                        ->map(fn ($a) => $a->created_at->format('d.m H:i').' — '.$a->action)->all())
                    ->placeholder('—'),
                TextColumn::make('client_decision')->label('Решение клиента')->wrap()->placeholder('—'),
            ])
            ->recordActions([
                Action::make('addAction')->label('Записать действие')->icon('heroicon-o-pencil')
                    ->visible(fn () => self::canManage())
                    ->schema([Textarea::make('action')->label('Что сделано')->required()])
                    ->action(fn (Issue $record, array $data) => $record->actions()->create(['staff_id' => auth('staff')->id(), 'action' => $data['action']])),
                Action::make('decision')->label('Решение клиента')->icon('heroicon-o-chat-bubble-left-right')
                    ->visible(fn () => self::canManage())
                    ->schema([
                        Textarea::make('client_decision')->label('Что согласовано с клиентом')->required(),
                        Select::make('client_agreed_via')->label('Как получено согласие')->required()
                            ->options(['app' => 'В приложении', 'email' => 'Письмом', 'edo' => 'Через ЭДО', 'phone' => 'По телефону', 'paper' => 'На бумаге']),
                        DateTimePicker::make('client_agreed_at')->label('Когда')->required()->default(now()),
                    ])
                    ->action(fn (Issue $record, array $data) => $record->update($data + ['status' => IssueStatus::Resolved])),
                Action::make('close')->label('Закрыть')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (Issue $record) => self::canManage() && $record->status !== IssueStatus::Closed)
                    ->requiresConfirmation()
                    ->action(fn (Issue $record) => $record->update(['status' => IssueStatus::Closed, 'resolved_by' => auth('staff')->id(), 'resolved_at' => now()])),
            ]);
    }
}
