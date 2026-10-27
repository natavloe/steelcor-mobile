<?php

namespace App\Services;

use App\Enums\Unit;
use App\Models\Product;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Пересчёт шт ↔ м ↔ кг ↔ т по теоретической массе позиции.
 * Если массы нет — пересчёт между массой и длиной/штуками невозможен (возвращает null).
 */
class UnitConverter
{
    /** Количество в килограммах или null, если пересчитать нельзя. */
    public function toKg(Product $product, string $qty, Unit $unit): ?BigDecimal
    {
        $q = Money::of($qty);

        return match ($unit) {
            Unit::T => $q->multipliedBy(1000),
            Unit::Kg => $q,
            Unit::M => $product->weight_per_m ? $q->multipliedBy(Money::of($product->weight_per_m)) : null,
            Unit::Pc => $this->kgPerPiece($product)?->multipliedBy($q),
            Unit::M2 => null,
        };
    }

    public function convert(Product $product, string $qty, Unit $from, Unit $to): ?string
    {
        if ($from === $to) {
            return Money::round(Money::of($qty), 4);
        }
        $kg = $this->toKg($product, $qty, $from);
        if ($kg === null) {
            return null;
        }

        $result = match ($to) {
            Unit::T => $kg->dividedBy(1000, 6, RoundingMode::HalfUp),
            Unit::Kg => $kg,
            Unit::M => $product->weight_per_m ? $kg->dividedBy(Money::of($product->weight_per_m), 6, RoundingMode::HalfUp) : null,
            Unit::Pc => ($perPc = $this->kgPerPiece($product)) ? $kg->dividedBy($perPc, 6, RoundingMode::HalfUp) : null,
            Unit::M2 => null,
        };

        return $result === null ? null : Money::round($result, 4);
    }

    public function toTonnes(Product $product, string $qty, Unit $unit): ?string
    {
        return $this->convert($product, $qty, $unit, Unit::T);
    }

    private function kgPerPiece(Product $product): ?BigDecimal
    {
        if ($product->weight_per_pc) {
            return Money::of($product->weight_per_pc);
        }
        if ($product->weight_per_m && $product->std_length_mm) {
            return Money::of($product->weight_per_m)->multipliedBy(Money::of($product->std_length_mm))->dividedBy(1000, 6, RoundingMode::HalfUp);
        }

        return null;
    }
}
