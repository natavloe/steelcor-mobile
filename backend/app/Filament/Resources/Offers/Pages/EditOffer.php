<?php

namespace App\Filament\Resources\Offers\Pages;

use App\Enums\OfferStatus;
use App\Filament\Concerns\RunsWorkflow;
use App\Filament\Resources\Offers\OfferResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Offer;
use App\Services\OfferCalculator;
use App\Services\OrderWorkflow;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditOffer extends EditRecord
{
    use RunsWorkflow;

    protected static string $resource = OfferResource::class;

    public function getTitle(): string
    {
        /** @var Offer $offer */
        $offer = $this->getRecord();

        return "Предложение v{$offer->version} — заказ {$offer->order->number} ({$offer->status->label()})";
    }

    protected function afterSave(): void
    {
        app(OfferCalculator::class)->recalculate($this->getRecord()->refresh());
        $this->fillForm();
    }

    protected function getFormActions(): array
    {
        /** @var Offer $offer */
        $offer = $this->getRecord();

        return $offer->status === OfferStatus::Draft ? parent::getFormActions() : [];
    }

    protected function getHeaderActions(): array
    {
        /** @var Offer $offer */
        $offer = $this->getRecord();
        $staff = auth('staff')->user();

        return [
            Action::make('back')->label('К заказу')->color('gray')->icon('heroicon-o-arrow-left')
                ->url(OrderResource::getUrl('view', ['record' => $offer->order_id])),

            Action::make('send')->label('Отправить клиенту')->icon('heroicon-o-paper-airplane')->color('success')
                ->visible(fn () => $offer->status === OfferStatus::Draft && $staff?->can(Permissions::OFFERS_MANAGE))
                ->requiresConfirmation()
                ->modalDescription('Сначала сохраните изменения. Клиент увидит условия в приложении и сможет их принять до окончания срока действия.')
                ->action(function () use ($offer) {
                    if (static::run(fn ($actor) => app(OrderWorkflow::class)->sendOffer($offer->refresh(), $actor), 'Предложение отправлено клиенту')) {
                        $this->redirect(OrderResource::getUrl('view', ['record' => $offer->order_id]));
                    }
                }),

            Action::make('approveMargin')->label('Одобрить низкую маржу')->icon('heroicon-o-check-badge')->color('warning')
                ->visible(fn () => $offer->status === OfferStatus::PendingApproval && $staff?->can(Permissions::OFFERS_APPROVE_LOW_MARGIN))
                ->requiresConfirmation()
                ->action(function () use ($offer) {
                    static::run(fn ($actor) => app(OrderWorkflow::class)->approveLowMargin($offer, $actor), 'Маржа одобрена — менеджер может отправлять');
                    $this->redirect(OfferResource::getUrl('edit', ['record' => $offer]));
                }),
        ];
    }
}
