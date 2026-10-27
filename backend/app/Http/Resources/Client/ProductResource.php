<?php

namespace App\Http\Resources\Client;

use App\Enums\Availability;
use App\Models\RegionProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Позиция каталога глазами клиента: характеристики и условия в регионе.
 * Цена — ориентировочная; после даты актуальности не показывается.
 * Поставщиков и закупочных цен здесь нет и быть не может.
 *
 * @mixin \App\Models\Product
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var RegionProduct|null $terms */
        $terms = $this->regionProducts->first();
        $priceActual = $terms && $terms->public_price !== null && $terms->availability !== Availability::PriceOnRequest
            && ($terms->price_valid_until === null || ! $terms->price_valid_until->isPast());

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'grade' => $this->grade,
            'gost' => $this->gost,
            'attrs' => $this->attrs,
            'base_unit' => $this->base_unit->value,
            'weight_per_m' => $this->weight_per_m,
            'std_length_mm' => $this->std_length_mm,
            'is_oversize' => $this->is_oversize,
            'units' => array_values(array_filter([
                'pc' => $this->weight_per_pc || ($this->weight_per_m && $this->std_length_mm) ? 'pc' : null,
                'm' => $this->weight_per_m ? 'm' : null,
                'kg' => 'kg',
                't' => 't',
            ])),
            'availability' => $terms?->availability->value,
            'availability_label' => $terms?->availability->label(),
            'price' => $priceActual ? [
                'amount' => (string) $terms->public_price,
                'unit' => $terms->price_unit?->value,
                'vat_included' => $terms->vat_included,
                'valid_until' => $terms->price_valid_until?->toDateString(),
                'is_preliminary' => true,
            ] : null,
            'lead_time_days' => $terms && $terms->lead_time_max_days !== null ? [
                'min' => $terms->lead_time_min_days, 'max' => $terms->lead_time_max_days,
            ] : null,
            'images' => $this->whenLoaded('images', fn () => $this->images->pluck('path')),
        ];
    }
}
