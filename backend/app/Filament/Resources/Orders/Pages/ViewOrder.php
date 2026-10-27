<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\DateKind;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\OrderStatus;
use App\Enums\ReceiveMethod;
use App\Filament\Concerns\RunsWorkflow;
use App\Filament\Resources\Offers\OfferResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\OrderWorkflow;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ViewRecord;

/** Карточка заказа. Все действия идут через OrderWorkflow — «замки» проверяются там. */
class ViewOrder extends ViewRecord
{
    use RunsWorkflow;

    protected static string $resource = OrderResource::class;

    private function order(): Order
    {
        /** @var Order */
        return $this->getRecord();
    }

    private function can(string $permission): bool
    {
        return (bool) auth('staff')->user()?->can($permission);
    }

    private function workflow(): OrderWorkflow
    {
        return app(OrderWorkflow::class);
    }

    private function done(): void
    {
        $this->getRecord()->refresh();
        $this->dispatch('$refresh');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('take')
                ->label('Взять в работу')
                ->icon('heroicon-o-hand-raised')
                ->visible(fn () => $this->order()->status === OrderStatus::Created && $this->can(Permissions::OFFERS_MANAGE))
                ->action(function () {
                    static::run(fn ($actor) => $this->workflow()->takeForReview($this->order(), $actor), 'Заказ взят в работу');
                    $this->done();
                }),

            Action::make('newOffer')
                ->label(fn () => $this->order()->offers()->exists() ? 'Новая версия предложения' : 'Подготовить предложение')
                ->icon('heroicon-o-document-plus')
                ->color('primary')
                ->visible(fn () => in_array($this->order()->status, [OrderStatus::UnderReview, OrderStatus::AwaitingTerms], true)
                    && $this->can(Permissions::OFFERS_MANAGE))
                ->schema([
                    TextInput::make('reason')->label('Причина новой версии (видна в истории)')
                        ->visible(fn () => $this->order()->offers()->exists()),
                ])
                ->action(function (array $data) {
                    $offer = null;
                    $ok = static::run(function ($actor) use (&$offer, $data) {
                        $offer = $this->workflow()->createOfferDraft($this->order(), $actor, $data['reason'] ?? null);
                    }, 'Черновик предложения создан');
                    if ($ok && $offer) {
                        $this->redirect(OfferResource::getUrl('edit', ['record' => $offer]));
                    }
                }),

            Action::make('approveDeferral')
                ->label('Разрешить закупку до оплаты')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->visible(fn () => $this->order()->status === OrderStatus::AwaitingPayment && $this->can(Permissions::PROCUREMENT_BEFORE_PAYMENT))
                ->schema([Textarea::make('reason')->label('Основание (отсрочка по договору, решение руководителя…)')->required()])
                ->requiresConfirmation()
                ->action(function (array $data) {
                    static::run(fn ($actor) => $this->workflow()->approveDeferral($this->order(), $actor, $data['reason']), 'Закупка до оплаты разрешена');
                    $this->done();
                }),

            Action::make('pickedUp')
                ->label('Выдан клиенту (самовывоз)')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->order()->status === OrderStatus::ReadyToShip
                    && $this->order()->receive_method === ReceiveMethod::Pickup && $this->can(Permissions::SHIPMENTS_MANAGE))
                ->schema([TextInput::make('received_by')->label('Кто получил (ФИО, доверенность)')->required()])
                ->action(function (array $data) {
                    static::run(fn ($actor) => $this->workflow()->markPickedUp($this->order(), $actor, $data['received_by']), 'Отмечено: товар выдан');
                    $this->done();
                }),

            ActionGroup::make([
                Action::make('setDate')
                    ->label('Изменить срок')
                    ->icon('heroicon-o-calendar')
                    ->visible(fn () => $this->can(Permissions::OFFERS_MANAGE) || $this->can(Permissions::PROCUREMENT_MANAGE) || $this->can(Permissions::SHIPMENTS_MANAGE))
                    ->schema([
                        Select::make('kind')->label('Какой срок')->options(DateKind::class)->required(),
                        DatePicker::make('from')->label('Дата (с)')->required(),
                        DatePicker::make('to')->label('по (если интервал)'),
                        Textarea::make('reason')->label('Причина изменения')->required(),
                        Toggle::make('notify')->label('Сообщить клиенту')->default(true),
                    ])
                    ->action(function (array $data) {
                        static::run(fn ($actor) => $this->workflow()->setDate(
                            $this->order(), DateKind::from($data['kind']), $data['from'], $data['to'] ?? null, $actor, $data['reason'], (bool) $data['notify'],
                        ), 'Срок записан');
                        $this->done();
                    }),

                Action::make('openIssue')
                    ->label('Открыть инцидент')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn () => $this->can(Permissions::ISSUES_MANAGE))
                    ->schema([
                        Select::make('type')->label('Что случилось')->options(IssueType::class)->required(),
                        Textarea::make('description')->label('Описание')->required(),
                    ])
                    ->action(function (array $data) {
                        static::run(fn ($actor) => $this->order()->issues()->create([
                            'type' => $data['type'], 'status' => IssueStatus::Open, 'description' => $data['description'], 'created_by' => $actor->id,
                        ]), 'Инцидент открыт');
                        $this->done();
                    }),

                Action::make('reagree')
                    ->label('Повторное согласование')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn () => in_array($this->order()->status, [OrderStatus::PaymentConfirmed, OrderStatus::Procurement, OrderStatus::SupplierConfirmed, OrderStatus::AwaitingReadiness], true)
                        && $this->can(Permissions::ISSUES_MANAGE))
                    ->modalDescription('Закупка по заказу замораживается, менеджер готовит новую версию условий. Клиент должен принять её в приложении.')
                    ->schema([Textarea::make('reason')->label('Причина')->required()])
                    ->requiresConfirmation()
                    ->action(function (array $data) {
                        static::run(fn ($actor) => $this->workflow()->startReagreement($this->order(), $actor, $data['reason']), 'Заказ переведён на повторное согласование');
                        $this->done();
                    }),

                Action::make('cancel')
                    ->label('Отменить заказ')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn () => ! in_array($this->order()->status, [OrderStatus::Completed, OrderStatus::Cancelled], true) && $this->can(Permissions::ISSUES_MANAGE))
                    ->modalDescription('После оплаты отмена возможна только при открытом инциденте с причиной. Возврат денег оформляется отдельно в разделе «Возвраты».')
                    ->schema([Textarea::make('reason')->label('Причина отмены')->required()])
                    ->requiresConfirmation()
                    ->action(function (array $data) {
                        static::run(fn ($actor) => $this->workflow()->cancel($this->order(), $actor, $data['reason']), 'Заказ отменён');
                        $this->done();
                    }),
            ])->label('Ещё')->button(),
        ];
    }
}
