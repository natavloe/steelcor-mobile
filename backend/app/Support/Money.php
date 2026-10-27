<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Точная арифметика для денег и количеств (без float).
 * Деньги — 2 знака, количество — 4 знака, округление «половина вверх».
 */
final class Money
{
    public static function of(string|int|float|null $value): BigDecimal
    {
        return BigDecimal::of($value === null || $value === '' ? '0' : (string) $value);
    }

    public static function round(BigDecimal $value, int $scale = 2): string
    {
        return (string) $value->toScale($scale, RoundingMode::HalfUp);
    }

    public static function add(string|int|null ...$values): string
    {
        $sum = BigDecimal::zero();
        foreach ($values as $value) {
            $sum = $sum->plus(self::of($value));
        }

        return self::round($sum);
    }

    /** a − b − c … */
    public static function sub(string|int|null $from, string|int|null ...$values): string
    {
        $result = self::of($from);
        foreach ($values as $value) {
            $result = $result->minus(self::of($value));
        }

        return self::round($result);
    }

    public static function mul(string|int|null $a, string|int|null $b, int $scale = 2): string
    {
        return self::round(self::of($a)->multipliedBy(self::of($b)), $scale);
    }

    public static function percentOf(string|int|null $amount, string|int|null $percent): string
    {
        return self::round(self::of($amount)->multipliedBy(self::of($percent))->dividedBy(100, 6, RoundingMode::HalfUp));
    }

    /** НДС, включённый в сумму: amount * rate / (100 + rate). */
    public static function vatIncluded(string|int|null $amount, string|int|null $rate): string
    {
        $rate = self::of($rate);
        if ($rate->isZero()) {
            return '0.00';
        }

        return self::round(self::of($amount)->multipliedBy($rate)->dividedBy($rate->plus(100), 6, RoundingMode::HalfUp));
    }

    public static function compare(string|int|null $a, string|int|null $b): int
    {
        return self::of($a)->compareTo(self::of($b));
    }
}
