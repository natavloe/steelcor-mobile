<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\SupplierOffer;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * ВНУТРЕННЕЕ: подсказки по цене для менеджера. Цену клиента фиксирует только принятое предложение,
 * правила наценки на неё потом не влияют.
 */
class PricingService
{
    /** Минимальная маржа в %, ниже которой нужно одобрение руководителя; null — без ограничения. */
    public function minMarginPercent(Order $order): ?string
    {
        $rule = $this->applicableRules($order->region_id, $order->organization_id, null)
            ->whereNotNull('min_margin_percent')
            ->first();

        return $rule?->min_margin_percent;
    }

    /** Подсказка цены продажи: лучшая актуальная закупочная цена + наценка по правилам. */
    public function suggest(Product $product, int $regionId, ?int $organizationId): ?array
    {
        $supplierOffer = SupplierOffer::query()
            ->where('product_id', $product->id)
            ->where(fn ($q) => $q->whereNull('region_id')->orWhere('region_id', $regionId))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()))
            ->orderBy('purchase_price')
            ->first();
        if ($supplierOffer === null) {
            return null;
        }

        $rule = $this->applicableRules($regionId, $organizationId, $product)
            ->where(fn ($q) => $q->whereNotNull('markup_percent')->orWhereNotNull('markup_fixed'))
            ->first();

        $price = Money::of($supplierOffer->purchase_price);
        if ($rule?->markup_percent !== null) {
            $price = $price->plus(Money::of(Money::percentOf($supplierOffer->purchase_price, $rule->markup_percent)));
        }
        if ($rule?->markup_fixed !== null) {
            $price = $price->plus(Money::of($rule->markup_fixed));
        }

        return [
            'supplier_offer' => $supplierOffer,
            'purchase_price' => (string) $supplierOffer->purchase_price,
            'unit' => $supplierOffer->unit,
            'suggested_price' => Money::round($price),
            'rule' => $rule,
        ];
    }

    /** Правила по убыванию «частности»: клиент → позиция → категория → регион → общее. */
    private function applicableRules(int $regionId, ?int $organizationId, ?Product $product): Builder
    {
        $today = now()->toDateString();

        return PricingRule::query()
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
            ->where(function ($q) use ($regionId, $organizationId, $product) {
                $q->where('scope', 'global')
                    ->orWhere(fn ($q) => $q->where('scope', 'region')->where('scope_id', $regionId));
                if ($organizationId) {
                    $q->orWhere(fn ($q) => $q->where('scope', 'organization')->where('scope_id', $organizationId));
                }
                if ($product) {
                    $q->orWhere(fn ($q) => $q->where('scope', 'product')->where('scope_id', $product->id))
                        ->orWhere(fn ($q) => $q->where('scope', 'category')->where('scope_id', $product->category_id));
                }
            })
            ->orderByRaw("CASE scope WHEN 'organization' THEN 1 WHEN 'product' THEN 2 WHEN 'category' THEN 3 WHEN 'region' THEN 4 ELSE 5 END")
            ->orderByDesc('priority');
    }
}
