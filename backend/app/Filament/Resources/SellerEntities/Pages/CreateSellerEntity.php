<?php

namespace App\Filament\Resources\SellerEntities\Pages;

use App\Filament\Resources\SellerEntities\SellerEntityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSellerEntity extends CreateRecord
{
    protected static string $resource = SellerEntityResource::class;
}
