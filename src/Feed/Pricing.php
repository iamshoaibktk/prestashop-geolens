<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Feed;

use Iamshoaibktk\Geolens\Schema\PageWriter;

/** The rules around a feed price, apart from the calculation itself, which is the core's. See Prices. */
final class Pricing
{
    /** Google and Microsoft both want tax left out of the price here, and included everywhere else. */
    private const TAX_EXCLUDED = ['US', 'CA'];

    public static function taxIncluded(string $country): bool
    {
        return !in_array(strtoupper($country), self::TAX_EXCLUDED, true);
    }

    /**
     * Whether this feed may state a reference price. In the EU a reduction must be calculated from
     * the lowest price of the previous 30 days (CJEU C-330/23), which the module cannot check, so
     * GEOLENS_STRIKETHROUGH starts off there and turning it on is the merchant's own statement. A shop
     * outside the EU was never asked that question, because its setting starts on, so its feed for
     * an EU country states no reference price at all.
     */
    public static function referenceAllowed(bool $setting, string $targetCountry, string $shopCountry): bool
    {
        if (!$setting) {
            return false;
        }

        return !PageWriter::isEuropeanUnion($targetCountry) || PageWriter::isEuropeanUnion($shopCountry);
    }

    /**
     * What goes in price and sale_price. With no reference price allowed, price is what the customer
     * pays today, which is what the page shows. It is never the higher price with the discount
     * left out.
     *
     * @return array{price: float, sale: float|null}
     */
    public static function pair(float $current, ?float $regular, bool $referenceAllowed): array
    {
        if ($referenceAllowed && $regular !== null && $regular > $current) {
            return ['price' => $regular, 'sale' => $current];
        }

        return ['price' => $current, 'sale' => null];
    }

    public static function amount(float $value, string $currency): string
    {
        return number_format($value, 2, '.', '') . ' ' . $currency;
    }

    /** @return string|null Google's "start / end", when the sale has a real end date. Without one the attribute stays out. */
    public static function saleDates(?string $from, ?string $to, int $now): ?string
    {
        $end = self::timestamp($to);
        if ($end === null || $end <= $now) {
            return null;
        }
        $start = self::timestamp($from) ?? $now;
        if ($start >= $end) {
            return null;
        }

        return self::date($start) . ' / ' . self::date($end);
    }

    public static function date(int $timestamp): string
    {
        return date('Y-m-d\TH:iO', $timestamp);
    }

    /** PrestaShop writes "no date" as zeros. */
    public static function timestamp(?string $date): ?int
    {
        if ($date === null || $date === '' || strpos($date, '0000') === 0) {
            return null;
        }
        $time = strtotime($date);

        return $time === false ? null : $time;
    }
}
