<?php

namespace App\Filament\Resources\PickupPoints\Pages;

use App\Filament\Resources\PickupPoints\PickupPointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPickupPoints extends ListRecords
{
    protected static string $resource = PickupPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
