<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PickupPoint */
class PickupPointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->only(['id', 'name', 'address', 'lat', 'lng', 'work_hours', 'instructions']);
    }
}
