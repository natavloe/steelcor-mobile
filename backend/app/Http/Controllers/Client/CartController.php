<?php

namespace App\Http\Controllers\Client;

use App\Enums\Availability;
use App\Enums\Unit;
use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Client\ProductResource;
use App\Models\Cart;
use App\Models\Product;
use App\Services\UnitConverter;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Корзина: своя у пользователя в каждой организации и регионе.
 * Сумма — предварительная: окончательную цену и срок подтверждает менеджер.
 */
class CartController extends Controller
{
    public function show(Request $request, UnitConverter $converter): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->cart($request), $converter)]);
    }

    public function add(Request $request, UnitConverter $converter): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer',
            'qty' => 'required|numeric|gt:0|max:100000',
            'unit' => ['required', Rule::enum(Unit::class)],
            'comment' => 'nullable|string|max:500',
        ]);
        $region = $request->attributes->get('region');
        $product = Product::query()->where('is_published', true)
            ->whereHas('regionProducts', fn ($q) => $q->where('region_id', $region->id)->where('is_visible', true)
                ->where('availability', '!=', Availability::Unavailable->value))
            ->findOrFail($data['product_id']);
        $unit = Unit::from($data['unit']);
        if ($unit !== $product->base_unit && $converter->convert($product, '1', $unit, $product->base_unit) === null) {
            throw new WorkflowException('unit_unavailable', 'Эту позицию нельзя заказать в выбранных единицах.');
        }

        $cart = $this->cart($request);
        $cart->items()->create(['product_id' => $product->id, 'qty' => $data['qty'], 'unit' => $unit, 'comment' => $data['comment'] ?? null]);

        return response()->json(['data' => $this->payload($cart, $converter)], 201);
    }

    public function update(Request $request, int $itemId, UnitConverter $converter): JsonResponse
    {
        $data = $request->validate([
            'qty' => 'sometimes|numeric|gt:0|max:100000',
            'unit' => ['sometimes', Rule::enum(Unit::class)],
            'comment' => 'sometimes|nullable|string|max:500',
        ]);
        $cart = $this->cart($request);
        $cart->items()->findOrFail($itemId)->update($data);

        return response()->json(['data' => $this->payload($cart, $converter)]);
    }

    public function remove(Request $request, int $itemId, UnitConverter $converter): JsonResponse
    {
        $cart = $this->cart($request);
        $cart->items()->findOrFail($itemId)->delete();

        return response()->json(['data' => $this->payload($cart, $converter)]);
    }

    private function cart(Request $request): Cart
    {
        return Cart::firstOrCreate([
            'user_id' => $request->user()->id,
            'organization_id' => $request->attributes->get('organization')->id,
            'region_id' => $request->attributes->get('region')->id,
        ]);
    }

    private function payload(Cart $cart, UnitConverter $converter): array
    {
        $regionId = $cart->region_id;
        $items = $cart->items()->with(['product.regionProducts' => fn ($q) => $q->where('region_id', $regionId)])->orderBy('id')->get();
        $total = '0';
        $complete = true;
        $weightKg = '0';
        $lines = $items->map(function ($item) use (&$total, &$complete, &$weightKg, $converter) {
            $productData = (new ProductResource($item->product))->resolve();
            $price = $productData['price'];
            $amount = null;
            if ($price !== null) {
                $qtyInPriceUnit = $converter->convert($item->product, (string) $item->qty, $item->unit, Unit::from($price['unit']));
                $amount = $qtyInPriceUnit === null ? null : Money::mul($qtyInPriceUnit, $price['amount']);
            }
            if ($amount === null) {
                $complete = false;
            } else {
                $total = Money::add($total, $amount);
            }
            $kg = $converter->toKg($item->product, (string) $item->qty, $item->unit);
            $weightKg = $kg === null ? $weightKg : Money::round(Money::of($weightKg)->plus($kg), 3);

            return [
                'id' => $item->id,
                'product' => $productData,
                'qty' => (string) $item->qty,
                'unit' => $item->unit->value,
                'comment' => $item->comment,
                'preliminary_amount' => $amount,
            ];
        });

        return [
            'items' => $lines->values(),
            'preliminary_total' => $total,
            'total_is_complete' => $complete,
            'theoretical_weight_kg' => $weightKg,
            'note' => 'Сумма предварительная. Окончательную цену и срок подтвердит менеджер.',
        ];
    }
}
