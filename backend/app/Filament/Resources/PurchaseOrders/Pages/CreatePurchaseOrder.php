<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\Region;
use App\Models\StaffUser;
use App\Models\Supplier;
use App\Services\ProcurementService;
use App\Support\Actor;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var StaffUser $staff */
        $staff = auth('staff')->user();

        return app(ProcurementService::class)->create(
            Supplier::findOrFail($data['supplier_id']), Region::findOrFail($data['region_id']), Actor::staff($staff), $data['note'] ?? null,
        );
    }

    protected function getRedirectUrl(): string
    {
        return PurchaseOrderResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
