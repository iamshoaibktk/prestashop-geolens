<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Schema;

/** GS1 check digit test. A wrong GTIN is worse than none: feeds reject it and it matches nothing. */
final class Gtin
{
    public static function isValid(string $code): bool
    {
        $code = trim($code);
        if (!preg_match('/^(\d{8}|\d{12}|\d{13}|\d{14})$/', $code) || ltrim($code, '0') === '') {
            return false;
        }
        $sum = 0;
        $weight = 3;
        for ($i = strlen($code) - 2; $i >= 0; --$i) {
            $sum += (int) $code[$i] * $weight;
            $weight = 4 - $weight;
        }

        return (10 - ($sum % 10)) % 10 === (int) $code[strlen($code) - 1];
    }

    /**
     * Ranges GS1 keeps for in-store labels and coupons. These pass the check digit, but Merchant
     * Center refuses them: prefixes 02, 04 and 2, and coupons at 98 and 99.
     */
    public static function isRestricted(string $code): bool
    {
        $length = strlen($code);
        if ($length !== 12 && $length !== 13 && $length !== 14) {
            return false;
        }
        // As a GTIN-13: a 12-digit code gains a leading zero, and a 14-digit one loses its packaging digit.
        $thirteen = $length === 12 ? '0' . $code : ($length === 14 ? substr($code, 1) : $code);

        return $thirteen[0] === '2' || in_array(substr($thirteen, 0, 2), ['02', '04', '98', '99'], true);
    }
}
