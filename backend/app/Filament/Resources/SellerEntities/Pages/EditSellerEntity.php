<?php

namespace App\Filament\Resources\SellerEntities\Pages;

use App\Filament\Resources\SellerEntities\SellerEntityResource;
use Filament\Resources\Pages\EditRecord;

class EditSellerEntity extends EditRecord
{
    protected static string $resource = SellerEntityResource::class;
}
