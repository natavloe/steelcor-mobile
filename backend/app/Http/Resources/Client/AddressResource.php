<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DeliveryAddress */
class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->only(['id', 'label', 'address', 'city', 'lat', 'lng', 'contact_name', 'contact_phone', 'access_notes']);
    }
}
