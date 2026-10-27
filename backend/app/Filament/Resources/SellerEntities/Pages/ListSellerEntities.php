<?php

namespace App\Filament\Resources\SellerEntities\Pages;

use App\Filament\Resources\SellerEntities\SellerEntityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSellerEntities extends ListRecords
{
    protected static string $resource = SellerEntityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
