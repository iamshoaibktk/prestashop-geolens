<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Feed;

/**
 * What a visitor from the feed's country pays. The core does the calculation, for the same reason the
 * page relies on it: combination prices, price rules, group reductions, ecotax and tax rules all
 * meet there, and a second version of that logic would drift from what the page shows.
 */
final class Prices
{
    private int $idShop;
    private int $idCountry;
    private int $idCurrency;
    private int $idGroup;
    private bool $withTax;
    private int $precision;

    public function __construct(int $idShop, int $idCountry, int $idCurrency, int $idGroup, bool $withTax, int $precision)
    {
        $this->idShop = $idShop;
        $this->idCountry = $idCountry;
        $this->idCurrency = $idCurrency;
        $this->idGroup = $idGroup;
        $this->withTax = $withTax;
        $this->precision = max(0, min(2, $precision));
    }

    /**
     * @return array{current: float, regular: float|null, from: string|null, to: string|null}|null null for a product the core cannot price
     */
    public function of(int $idProduct, int $idCombination): ?array
    {
        $specific = null;
        $current = $this->calculate($idProduct, $idCombination, true, $specific);
        if ($current === null) {
            return null;
        }
        $regular = null;
        // Only a discount can make the two prices differ, and most products have none.
        if ($specific && (float) ($specific['reduction'] ?? 0) > 0) {
            $unused = null;
            $regular = $this->calculate($idProduct, $idCombination, false, $unused);
        }

        return [
            'current' => $current,
            'regular' => $regular,
            'from' => $specific['from'] ?? null,
            'to' => $specific['to'] ?? null,
        ];
    }

    private function calculate(int $idProduct, int $idCombination, bool $reduced, &$specific): ?float
    {
        $price = \Product::priceCalculation(
            $this->idShop,
            $idProduct,
            $idCombination,
            $this->idCountry,
            0,
            '0',
            $this->idCurrency,
            $this->idGroup,
            1,
            $this->withTax,
            6,
            false,
            $reduced,
            true,
            $specific,
            true
        );

        return $price === null ? null : (float) \Tools::ps_round((float) $price, $this->precision);
    }

    /** One query saved per product, from a value the batch has already read. */
    public function knowTaxGroup(int $idProduct, int $idTaxRulesGroup): void
    {
        \Cache::store('product_id_tax_rules_group_' . $idProduct . '_' . $this->idShop, $idTaxRulesGroup);
    }

    /** The core remembers every price it works out. Over a whole catalogue that adds up. */
    public static function forget(): void
    {
        \Product::flushPriceCache();
        \SpecificPrice::flushCache();
        \Cache::clean('product_id_tax_rules_group_*');
    }
}
