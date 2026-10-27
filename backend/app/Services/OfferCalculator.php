<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\OfferLine;
use App\Support\Money;
use Brick\Math\RoundingMode;

/**
 * Пересчёт сумм предложения. Цена строки — с НДС (если ставка > 0),
 * НДС выделяется из суммы. Правило округления — построчно (О-5).
 */
class OfferCalculator
{
    public function recalculate(Offer $offer): Offer
    {
        $goods = ['net' => '0', 'vat' => '0', 'total' => '0'];
        $delivery = '0';
        $services = '0';

        foreach ($offer->lines()->with('cost')->get() as $line) {
            $this->recalculateLine($line);
            match ($line->line_type) {
                'delivery' => $delivery = Money::add($delivery, $line->amount_total),
                'service' => $services = Money::add($services, $line->amount_total),
                default => $goods = [
                    'net' => Money::add($goods['net'], $line->amount_net),
                    'vat' => Money::add($goods['vat'], $line->amount_vat),
                    'total' => Money::add($goods['total'], $line->amount_total),
                ],
            };
        }

        $offer->forceFill([
            'goods_net' => $goods['net'],
            'goods_vat' => $goods['vat'],
            'goods_total' => $goods['total'],
            'delivery_total' => $delivery,
            'services_total' => $services,
            'grand_total' => Money::add($goods['total'], $delivery, $services),
        ])->save();

        return $offer;
    }

    public function recalculateLine(OfferLine $line): void
    {
        $total = Money::mul($line->qty, $line->unit_price);
        $vat = Money::vatIncluded($total, $line->vat_rate);
        $line->forceFill([
            'amount_total' => $total,
            'amount_vat' => $vat,
            'amount_net' => Money::sub($total, $vat),
        ])->save();

        $cost = $line->cost;
        if ($cost === null) {
            return;
        }

        $purchaseAmount = $cost->purchase_price !== null ? Money::mul($line->qty, $cost->purchase_price) : null;
        $margin = $purchaseAmount === null ? null
            : Money::sub($total, $purchaseAmount, $cost->logistics_cost, $cost->handling_cost, $cost->other_costs);
        $percent = ($margin === null || Money::compare($total, '0') === 0) ? null
            : Money::round(Money::of($margin)->multipliedBy(100)->dividedBy(Money::of($total), 4, RoundingMode::HalfUp));

        $cost->forceFill([
            'purchase_amount' => $purchaseAmount,
            'margin_amount' => $margin,
            'margin_percent' => $percent,
        ])->save();
    }
}
