<?php

namespace App\Support;

/**
 * Money helpers for safe arithmetic on currency values.
 *
 * The DB stores `decimal(12,2)` — so we can read/write rand-and-cents strings
 * directly. The danger is in PHP: native float math accumulates rounding drift,
 * and a long sales day produces visible "off by a cent" errors that wreck cash
 * drawer reconciliation.
 *
 * Convention: convert to integer cents at the boundary, do all arithmetic on
 * ints, convert back to a 2-decimal string only when persisting / displaying.
 */
class Money
{
    /**
     * Convert a numeric value (string|int|float|null) to integer cents.
     * Examples: "12.34" → 1234, 12.34 → 1234, "0" → 0, null → 0.
     *
     * Uses string formatting to dodge float drift on the boundary itself
     * (e.g. (int) (12.34 * 100) is 1233 on some platforms).
     */
    public static function toCents($value): int
    {
        if ($value === null || $value === '') return 0;
        // number_format with 2dp + strip the dot is exact on any platform.
        $formatted = number_format((float) $value, 2, '.', '');
        [$rand, $cents] = explode('.', $formatted);
        $sign = $rand[0] === '-' ? -1 : 1;
        $rand = ltrim($rand, '-+');
        return $sign * ((int) $rand * 100 + (int) $cents);
    }

    /**
     * Convert integer cents back to a 2-decimal string suitable for the DB
     * decimal(12,2) column or for display.
     * 1234 → "12.34", 0 → "0.00", -50 → "-0.50".
     */
    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Sum a list of values (mixed types) by converting each to cents first.
     * Returns an int (cents). Convert with fromCents() if you need a string.
     */
    public static function sumCents(iterable $values): int
    {
        $total = 0;
        foreach ($values as $v) $total += self::toCents($v);
        return $total;
    }
}
