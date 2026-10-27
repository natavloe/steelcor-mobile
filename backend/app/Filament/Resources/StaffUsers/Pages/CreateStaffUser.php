<?php

namespace App\Filament\Resources\StaffUsers\Pages;

use App\Filament\Resources\StaffUsers\StaffUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStaffUser extends CreateRecord
{
    protected static string $resource = StaffUserResource::class;
}
