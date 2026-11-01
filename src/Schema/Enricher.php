<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Schema;

/**
 * Turns the Product a theme printed into an accurate one. Everything here either removes something
 * invented or adds a fact the shop really holds. There are no custom tags, because engines cannot
 * read what schema.org does not define.
 */
final class Enricher
{
    /** PrestaShop stores the weight unit as text. schema.org wants the UN/CEFACT code. */
    private const WEIGHT_UNITS = ['kg' => 'KGM', 'g' => 'GRM', 'lb' => 'LBR', 'lbs' => 'LBR', 'oz' => 'ONZ', 't' => 'TNE'];

    private const IDENTIFIERS = ['gtin', 'gtin8', 'gtin12', 'gtin13', 'gtin14', 'isbn', 'mpn', 'sku'];

    /**
     * @param array $node the Product as printed
     * @param array{
     *     identifiers: array<string, string>,
     *     brand: string|null,
     *     keep_shop_brand: bool,
     *     condition: string|null,
     *     regular_price: float|null,
     *     currency: string,
     *     valid_until: string|null,
     *     shipping: array<int, array>,
     *     returns: array|null,
     *     attributes: array<string, string>,
     *     variants: array<int, array>,
     *     vary_by: string[],
     *     group_id: string
     * } $facts
     *
     * @return array one node, or a list of nodes when the product became a ProductGroup
     */
    public static function product(array $node, array $facts): array
    {
        foreach (self::IDENTIFIERS as $key) {
            unset($node[$key]);
        }
        foreach ($facts['identifiers'] as $key => $value) {
            $node[$key] = $value;
        }

        if ($facts['brand'] !== null && $facts['brand'] !== '') {
            $node['brand'] = ['@type' => 'Brand', 'name' => $facts['brand']];
        } elseif (!$facts['keep_shop_brand']) {
            // With no manufacturer, the core names the shop as the brand. A reseller's shop is not.
            unset($node['brand']);
        }

        if (isset($node['weight']['unitCode'])) {
            $unit = strtolower((string) $node['weight']['unitCode']);
            $node['weight']['unitCode'] = self::WEIGHT_UNITS[$unit] ?? $node['weight']['unitCode'];
            unset($node['weight']['@context']);
        }

        foreach ($facts['attributes'] as $key => $value) {
            if (!isset($node[$key]) && $value !== '') {
                $node[$key] = $value;
            }
        }

        if (isset($node['offers']) && is_array($node['offers'])) {
            $node['offers'] = array_is_list($node['offers'])
                ? array_map(static fn ($o) => self::offer($o, $facts), $node['offers'])
                : self::offer($node['offers'], $facts);
        }

        return count($facts['variants']) > 1 && isset($node['offers']) ? self::group($node, $facts) : $node;
    }

    private static function offer(array $offer, array $facts): array
    {
        // The core and every theme print today + 15 days, on every page, for every product. A real
        // end date comes from a real sale. Otherwise the property stays out.
        unset($offer['priceValidUntil'], $offer['mpn'], $offer['sku']);
        if (isset($facts['identifiers']['sku'])) {
            $offer['sku'] = $facts['identifiers']['sku'];
        }
        if (isset($facts['identifiers']['mpn'])) {
            $offer['mpn'] = $facts['identifiers']['mpn'];
        }
        if ($facts['valid_until'] !== null) {
            $offer['priceValidUntil'] = $facts['valid_until'];
        }
        if ($facts['condition'] !== null) {
            $offer['itemCondition'] = $facts['condition'];
        }
        if ($facts['regular_price'] !== null) {
            $offer['priceSpecification'] = self::strikethrough($facts['regular_price'], $facts['currency']);
        }
        if ($facts['shipping']) {
            $offer['shippingDetails'] = count($facts['shipping']) === 1 ? $facts['shipping'][0] : $facts['shipping'];
        }
        if ($facts['returns'] !== null) {
            $offer['hasMerchantReturnPolicy'] = $facts['returns'];
        }

        return $offer;
    }

    private static function strikethrough(float $price, string $currency): array
    {
        return [
            '@type' => 'UnitPriceSpecification',
            'priceType' => 'https://schema.org/StrikethroughPrice',
            'price' => number_format($price, 2, '.', ''),
            'priceCurrency' => $currency,
        ];
    }

    /**
     * Google's single-page variant shape: one ProductGroup holding each variant as a Product with
     * its own offer. Delivery and returns sit once at the top level and are referenced by @id, so a
     * product with 50 sizes does not repeat them 50 times.
     *
     * @return array<int, array>
     */
    private static function group(array $node, array $facts): array
    {
        $group = ['@context' => $node['@context'] ?? 'https://schema.org', '@type' => 'ProductGroup'];
        foreach (['name', 'description', 'url', 'brand', 'category', 'image', 'additionalProperty', 'aggregateRating', 'review', 'material', 'pattern'] as $key) {
            if (isset($node[$key])) {
                $group[$key] = $node[$key];
            }
        }
        $group['productGroupID'] = $facts['group_id'];
        if ($facts['vary_by']) {
            $group['variesBy'] = array_map(static fn ($p) => 'https://schema.org/' . $p, $facts['vary_by']);
        }

        $extra = [];
        $shippingIds = [];
        $returnsId = null;
        if ($facts['returns'] !== null) {
            $returnsId = '#geolens-returns';
            $extra[] = ['@context' => 'https://schema.org', '@id' => $returnsId] + $facts['returns'];
        }

        $group['hasVariant'] = [];
        foreach ($facts['variants'] as $v) {
            $variant = ['@type' => 'Product'];
            foreach (['name', 'sku', 'gtin13', 'gtin12', 'gtin14', 'gtin8', 'isbn', 'mpn', 'image', 'color', 'size', 'material', 'pattern', 'weight'] as $key) {
                if (isset($v[$key]) && $v[$key] !== '') {
                    $variant[$key] = $v[$key];
                }
            }
            $offer = [
                '@type' => 'Offer',
                'url' => $v['url'],
                'price' => $v['price'],
                'priceCurrency' => $facts['currency'],
                'availability' => $v['availability'],
            ];
            if ($facts['condition'] !== null) {
                $offer['itemCondition'] = $facts['condition'];
            }
            if (!empty($v['regular_price'])) {
                $offer['priceSpecification'] = self::strikethrough((float) $v['regular_price'], $facts['currency']);
            }
            if (!empty($v['valid_until'])) {
                $offer['priceValidUntil'] = $v['valid_until'];
            }
            $refs = [];
            foreach ((array) ($v['shipping'] ?? []) as $details) {
                $key = md5((string) json_encode($details));
                if (!isset($shippingIds[$key])) {
                    $shippingIds[$key] = '#geolens-shipping-' . (count($shippingIds) + 1);
                    $extra[] = ['@context' => 'https://schema.org', '@id' => $shippingIds[$key]] + $details;
                }
                $refs[] = ['@id' => $shippingIds[$key]];
            }
            if ($refs) {
                $offer['shippingDetails'] = count($refs) === 1 ? $refs[0] : $refs;
            }
            if ($returnsId !== null) {
                $offer['hasMerchantReturnPolicy'] = ['@id' => $returnsId];
            }
            $variant['offers'] = $offer;
            $group['hasVariant'][] = $variant;
        }

        return array_merge([$group], $extra);
    }

    /**
     * @param string[] $sameAs the shop's social profiles
     * @param array|null $returns standard return policy
     * @param array|null $shipping ShippingService
     */
    public static function organization(array $node, array $sameAs, ?array $returns, ?array $shipping): array
    {
        if ($sameAs) {
            $node['sameAs'] = array_values($sameAs);
        }
        if ($returns !== null) {
            $node['hasMerchantReturnPolicy'] = $returns;
        }
        if ($shipping !== null) {
            $node['hasShippingService'] = $shipping;
        }

        return $node;
    }

    /** dateModified is the date the facts on the page last really changed. */
    public static function webPage(array $node, string $language, ?string $dateModified): array
    {
        $node['inLanguage'] = $language;
        if ($dateModified !== null) {
            $node['dateModified'] = $dateModified;
        }

        return $node;
    }
}
