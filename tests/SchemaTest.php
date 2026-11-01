<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Tests;

use Iamshoaibktk\Geolens\Schema\Enricher;
use Iamshoaibktk\Geolens\Schema\Facts;
use Iamshoaibktk\Geolens\Schema\Gtin;
use Iamshoaibktk\Geolens\Schema\JsonLd;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    private const FACTS = [
        'identifiers' => ['sku' => 'demo_1'],
        'brand' => null,
        'keep_shop_brand' => false,
        'condition' => 'https://schema.org/NewCondition',
        'regular_price' => null,
        'currency' => 'USD',
        'valid_until' => null,
        'shipping' => [],
        'returns' => null,
        'attributes' => [],
        'variants' => [],
        'vary_by' => [],
        'group_id' => '1',
    ];

    /** The Product PrestaShop 9.2 prints, from a real page. */
    private const CORE_PRODUCT = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => 'Hummingbird printed t-shirt',
        'url' => 'https://shop.example/men/1-tee.html',
        'description' => 'Regular fit.',
        'image' => ['https://shop.example/1.jpg'],
        'sku' => 'demo_1',
        'brand' => ['@type' => 'Organization', 'name' => 'My Shop'],
        'weight' => ['@type' => 'QuantitativeValue', 'value' => '0.30', 'unitCode' => 'kg'],
        'offers' => [
            '@type' => 'Offer',
            'priceCurrency' => 'USD',
            'price' => '19.12',
            'url' => 'https://shop.example/men/1-tee.html',
            'priceValidUntil' => '2026-11-16',
            'availability' => 'https://schema.org/InStock',
            'sku' => 'demo_1',
            'mpn' => 'demo_1',
        ],
    ];

    public function testRollingPriceDateIsRemoved(): void
    {
        $out = Enricher::product(self::CORE_PRODUCT, self::FACTS);
        $this->assertArrayNotHasKey('priceValidUntil', $out['offers']);
    }

    public function testRealSaleEndIsKept(): void
    {
        $out = Enricher::product(self::CORE_PRODUCT, ['valid_until' => '2026-12-24T23:59:59+01:00'] + self::FACTS);
        $this->assertSame('2026-12-24T23:59:59+01:00', $out['offers']['priceValidUntil']);
    }

    public function testShopNameIsNotABrandUnlessTheMerchantSaysSo(): void
    {
        $this->assertArrayNotHasKey('brand', Enricher::product(self::CORE_PRODUCT, self::FACTS));
        $kept = Enricher::product(self::CORE_PRODUCT, ['keep_shop_brand' => true] + self::FACTS);
        $this->assertSame('My Shop', $kept['brand']['name']);
    }

    public function testRealBrandReplacesTheFallback(): void
    {
        $out = Enricher::product(self::CORE_PRODUCT, ['brand' => 'Studio Design'] + self::FACTS);
        $this->assertSame(['@type' => 'Brand', 'name' => 'Studio Design'], $out['brand']);
    }

    public function testInventedIdentifiersAreDropped(): void
    {
        // Classic prints the reference as mpn when a product has none.
        $node = self::CORE_PRODUCT + ['mpn' => 'demo_1', 'gtin13' => '123'];
        $out = Enricher::product($node, self::FACTS);
        $this->assertArrayNotHasKey('mpn', $out);
        $this->assertArrayNotHasKey('gtin13', $out);
        $this->assertArrayNotHasKey('mpn', $out['offers']);
        $this->assertSame('demo_1', $out['sku']);
    }

    public function testValidatedIdentifiersAreWritten(): void
    {
        $out = Enricher::product(self::CORE_PRODUCT, ['identifiers' => ['sku' => 'A', 'gtin13' => '4006381333931', 'mpn' => 'M']] + self::FACTS);
        $this->assertSame('4006381333931', $out['gtin13']);
        $this->assertSame('M', $out['offers']['mpn']);
    }

    public function testWeightUsesTheCodeSchemaOrgExpects(): void
    {
        $this->assertSame('KGM', Enricher::product(self::CORE_PRODUCT, self::FACTS)['weight']['unitCode']);
    }

    public function testConditionIsAlwaysStated(): void
    {
        $this->assertSame('https://schema.org/NewCondition', Enricher::product(self::CORE_PRODUCT, self::FACTS)['offers']['itemCondition']);
    }

    public function testStrikethroughFollowsGooglesShape(): void
    {
        $out = Enricher::product(self::CORE_PRODUCT, ['regular_price' => 23.9] + self::FACTS);
        $this->assertSame('19.12', $out['offers']['price']);
        $this->assertSame([
            '@type' => 'UnitPriceSpecification',
            'priceType' => 'https://schema.org/StrikethroughPrice',
            'price' => '23.90',
            'priceCurrency' => 'USD',
        ], $out['offers']['priceSpecification']);
    }

    public function testNothingIsAddedWhenThePriceIsHidden(): void
    {
        $node = self::CORE_PRODUCT;
        unset($node['offers']);
        $out = Enricher::product($node, ['variants' => [['name' => 'a'], ['name' => 'b']], 'returns' => ['x' => 1]] + self::FACTS);
        $this->assertArrayNotHasKey('offers', $out);
        $this->assertSame('Product', $out['@type']);
    }

    public function testAttributesDoNotOverwrite(): void
    {
        $node = self::CORE_PRODUCT + ['material' => 'Wool'];
        $out = Enricher::product($node, ['attributes' => ['material' => 'Cotton', 'pattern' => 'Striped', 'color' => '']] + self::FACTS);
        $this->assertSame('Wool', $out['material']);
        $this->assertSame('Striped', $out['pattern']);
        $this->assertArrayNotHasKey('color', $out);
    }

    public function testOneVariantStaysAProduct(): void
    {
        $out = Enricher::product(self::CORE_PRODUCT, ['variants' => [['name' => 'only']]] + self::FACTS);
        $this->assertSame('Product', $out['@type']);
    }

    private static function variants(array $shipping = []): array
    {
        $base = ['url' => 'u', 'availability' => 'https://schema.org/InStock', 'shipping' => $shipping];

        return [
            ['name' => 'Tee - S', 'sku' => 'T-S', 'size' => 'S', 'color' => 'White', 'price' => '19.12', 'url' => 'https://shop.example/men/1-1-tee.html'] + $base,
            ['name' => 'Tee - M', 'sku' => 'T-M', 'size' => 'M', 'color' => 'White', 'price' => '21.00', 'gtin13' => '4006381333931', 'url' => 'https://shop.example/men/1-2-tee.html'] + $base,
        ];
    }

    public function testVariantsBecomeAProductGroup(): void
    {
        $out = Enricher::product(self::CORE_PRODUCT, ['variants' => self::variants(), 'vary_by' => ['size', 'color']] + self::FACTS);
        $group = $out[0];
        $this->assertSame('ProductGroup', $group['@type']);
        $this->assertSame('1', $group['productGroupID']);
        $this->assertSame(['https://schema.org/size', 'https://schema.org/color'], $group['variesBy']);
        $this->assertArrayNotHasKey('offers', $group);
        $this->assertArrayNotHasKey('sku', $group);
        $this->assertSame('https://shop.example/men/1-tee.html', $group['url']);
        $this->assertCount(2, $group['hasVariant']);
        $variant = $group['hasVariant'][1];
        $this->assertSame('Product', $variant['@type']);
        $this->assertSame('T-M', $variant['sku']);
        $this->assertSame('4006381333931', $variant['gtin13']);
        $this->assertSame('M', $variant['size']);
        $this->assertSame('21.00', $variant['offers']['price']);
        $this->assertSame('https://shop.example/men/1-2-tee.html', $variant['offers']['url']);
        $this->assertSame('USD', $variant['offers']['priceCurrency']);
    }

    public function testVariantsShareOneCopyOfDeliveryAndReturns(): void
    {
        $details = ['@type' => 'OfferShippingDetails', 'shippingRate' => ['value' => 0]];
        $returns = ['@type' => 'MerchantReturnPolicy', 'applicableCountry' => 'FR'];
        $out = Enricher::product(self::CORE_PRODUCT, ['variants' => self::variants([$details]), 'returns' => $returns] + self::FACTS);
        $this->assertCount(3, $out, 'group, returns, one shipping');
        $ids = array_column(array_slice($out, 1), '@id');
        foreach ($out[0]['hasVariant'] as $variant) {
            $this->assertContains($variant['offers']['shippingDetails']['@id'], $ids);
            $this->assertContains($variant['offers']['hasMerchantReturnPolicy']['@id'], $ids);
        }
    }

    public function testDifferentRatesGetTheirOwnNode(): void
    {
        $variants = self::variants();
        $variants[0]['shipping'] = [['@type' => 'OfferShippingDetails', 'shippingRate' => ['value' => 4.9]]];
        $variants[1]['shipping'] = [['@type' => 'OfferShippingDetails', 'shippingRate' => ['value' => 0]]];
        $out = Enricher::product(self::CORE_PRODUCT, ['variants' => $variants] + self::FACTS);
        $this->assertNotSame($out[0]['hasVariant'][0]['offers']['shippingDetails']['@id'], $out[0]['hasVariant'][1]['offers']['shippingDetails']['@id']);
    }

    public function testOrganization(): void
    {
        $out = Enricher::organization(['@type' => 'Organization'], ['https://x.com/shop'], ['@type' => 'MerchantReturnPolicy'], null);
        $this->assertSame(['https://x.com/shop'], $out['sameAs']);
        $this->assertSame('MerchantReturnPolicy', $out['hasMerchantReturnPolicy']['@type']);
        $this->assertArrayNotHasKey('hasShippingService', $out);
    }

    /** @dataProvider gtins */
    public function testGtin(string $code, bool $valid): void
    {
        $this->assertSame($valid, Gtin::isValid($code));
    }

    public static function gtins(): array
    {
        return [
            'ean13' => ['4006381333931', true],
            'ean13 wrong check' => ['4006381333932', false],
            'upc' => ['036000291452', true],
            'ean8' => ['73513537', true],
            'gtin14' => ['00012345600012', true],
            'google example' => ['98766051104214', true],
            'too short' => ['123', false],
            'letters' => ['ABCDEFGHIJKLM', false],
            'empty' => ['', false],
            'all zeros' => ['0000000000000', false],
            'eleven digits' => ['12345678901', false],
        ];
    }

    public function testJsonLdRewriteLeavesBrokenBlocksAlone(): void
    {
        $html = '<script type="application/ld+json">{bad,}</script><script type="application/ld+json">{"@type":"Thing"}</script>';
        $out = JsonLd::rewrite($html, static fn ($d) => ['@type' => 'Changed']);
        $this->assertStringContainsString('{bad,}', $out);
        $this->assertStringContainsString('"Changed"', $out);
    }

    public function testJsonLdNullKeepsTheOriginalText(): void
    {
        $html = "<script type='application/ld+json'>\n { \"@type\" : \"Thing\" }\n</script>";
        $this->assertSame($html, JsonLd::rewrite($html, static fn ($d) => null));
    }

    public function testJsonLdCannotBeClosedFromInside(): void
    {
        $encoded = JsonLd::encode(['name' => 'x</script><script>alert(1)</script>']);
        $this->assertStringNotContainsString('</script>', $encoded);
        $this->assertSame('x</script><script>alert(1)</script>', json_decode($encoded, true)['name']);
    }

    public function testJsonLdFindsNodesInAGraph(): void
    {
        $data = ['@graph' => [['@type' => 'Product', 'name' => 'a'], ['@type' => 'WebPage']]];
        $out = JsonLd::mapType($data, 'Product', static fn ($n) => ['name' => 'b'] + $n);
        $this->assertSame('b', $out['@graph'][0]['name']);
        $this->assertSame('WebPage', $out['@graph'][1]['@type']);
    }

    public function testJsonLdMapTypeReturnsNullWhenAbsent(): void
    {
        $this->assertNull(JsonLd::mapType(['@type' => 'WebPage'], 'Product', static fn ($n) => $n));
    }

    public function testJsonLdNodeCanBecomeSeveral(): void
    {
        $out = JsonLd::mapType(['@type' => 'Product'], 'Product', static fn ($n) => [['@type' => 'ProductGroup'], ['@type' => 'MerchantReturnPolicy']]);
        $this->assertSame('ProductGroup', $out[0]['@type']);
        $this->assertCount(2, $out);
    }

    public function testJsonLdAcceptsFullTypeUrls(): void
    {
        $this->assertTrue(JsonLd::hasType(['@type' => 'https://schema.org/Organization'], 'Organization'));
        $this->assertTrue(JsonLd::hasType(['@type' => ['Thing', 'Product']], 'Product'));
        $this->assertFalse(JsonLd::hasType(['@type' => 'ProductGroup'], 'Product'));
    }

    public function testAppendGoesInTheHead(): void
    {
        $out = JsonLd::append('<html><head><title>x</title></head><body></body></html>', ['@type' => 'Thing']);
        $this->assertLessThan(strpos($out, '</head>'), strpos($out, 'ld+json'));
        $this->assertSame('<p>no head</p>', JsonLd::append('<p>no head</p>', ['@type' => 'Thing']));
    }

    private static function facts(): Facts
    {
        return new Facts([
            'currency' => 'EUR',
            'returns' => ['mode' => 'finite', 'days' => 30, 'fees' => 'free', 'countries' => ['FR', 'DE'], 'origin' => 'FR'],
            'shipping' => [
                ['countries' => ['FR'], 'rate' => 4.9, 'free_over' => 50.0, 'handling_min' => 0, 'handling_max' => 1, 'transit_min' => 1, 'transit_max' => 3],
                ['countries' => ['DE', 'BE'], 'rate' => 9.9, 'free_over' => null, 'handling_min' => 1, 'handling_max' => 2, 'transit_min' => 2, 'transit_max' => 5],
            ],
        ]);
    }

    public function testNoFactsMeansNothingIsPublished(): void
    {
        $empty = new Facts([]);
        $this->assertNull($empty->returnPolicy('EUR'));
        $this->assertSame([], $empty->offerShipping(10, 'EUR'));
        $this->assertNull($empty->organizationShipping('Shop'));
        $this->assertSame(['shipping' => null, 'returns' => null], $empty->visible('FR', 10));
    }

    public function testReturnPolicyShape(): void
    {
        $this->assertSame([
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => ['FR', 'DE'],
            'returnPolicyCountry' => 'FR',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => 30,
            'returnMethod' => 'https://schema.org/ReturnByMail',
            'returnFees' => 'https://schema.org/FreeReturn',
        ], self::facts()->returnPolicy('EUR'));
    }

    public function testFixedReturnFeeCarriesTheAmountInThePageCurrency(): void
    {
        $facts = new Facts(['returns' => ['mode' => 'finite', 'days' => 14, 'fees' => 'fixed', 'fee' => 5.0, 'countries' => ['FR']]]);
        $policy = $facts->returnPolicy('USD', 1.1);
        $this->assertSame('https://schema.org/ReturnShippingFees', $policy['returnFees']);
        $this->assertSame(['@type' => 'MonetaryAmount', 'value' => 5.5, 'currency' => 'USD'], $policy['returnShippingFeesAmount']);
        $this->assertSame('FR', $policy['applicableCountry']);
    }

    public function testCustomerPaysHasNoAmount(): void
    {
        $facts = new Facts(['returns' => ['mode' => 'finite', 'days' => 14, 'fees' => 'customer', 'countries' => ['FR']]]);
        $policy = $facts->returnPolicy('EUR');
        $this->assertSame('https://schema.org/ReturnFeesCustomerResponsibility', $policy['returnFees']);
        $this->assertArrayNotHasKey('returnShippingFeesAmount', $policy);
    }

    public function testNoReturnsSaysOnlyThat(): void
    {
        $facts = new Facts(['returns' => ['mode' => 'none', 'countries' => ['FR']]]);
        $policy = $facts->returnPolicy('EUR');
        $this->assertSame('https://schema.org/MerchantReturnNotPermitted', $policy['returnPolicyCategory']);
        $this->assertArrayNotHasKey('merchantReturnDays', $policy);
        $this->assertArrayNotHasKey('returnFees', $policy);
    }

    public function testOfferShippingUsesTheRateThisPricePays(): void
    {
        $nodes = self::facts()->offerShipping(20, 'EUR');
        $this->assertSame(4.9, $nodes[0]['shippingRate']['value']);
        $this->assertSame('FR', $nodes[0]['shippingDestination']['addressCountry']);
        $this->assertSame(0, self::facts()->offerShipping(60, 'EUR')[0]['shippingRate']['value']);
        $this->assertSame(['@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 3, 'unitCode' => 'DAY'], $nodes[0]['deliveryTime']['transitTime']);
        $this->assertCount(2, $nodes[1]['shippingDestination']);
    }

    public function testRatesFollowThePageCurrency(): void
    {
        $node = self::facts()->offerShipping(20, 'USD', 1.1)[0];
        $this->assertSame(5.39, $node['shippingRate']['value']);
        $this->assertSame('USD', $node['shippingRate']['currency']);
    }

    public function testOrganizationShippingHasPaidAndFreeConditions(): void
    {
        $services = self::facts()->organizationShipping('My Shop');
        $this->assertCount(2, $services);
        $this->assertSame('ShippingService', $services[0]['@type']);
        [$paid, $free] = $services[0]['shippingConditions'];
        $this->assertSame(49.99, $paid['orderValue']['maxValue']);
        $this->assertSame(4.9, $paid['shippingRate']['value']);
        $this->assertSame(50.0, $free['orderValue']['minValue']);
        $this->assertSame(0, $free['shippingRate']['value']);
        $this->assertCount(1, $services[1]['shippingConditions']);
    }

    public function testVisibleFactsFollowTheVisitorsCountry(): void
    {
        $seen = self::facts()->visible('FR', 20);
        $this->assertFalse($seen['shipping']['free']);
        $this->assertSame(1, $seen['shipping']['min']);
        $this->assertSame(4, $seen['shipping']['max']);
        $this->assertSame(30, $seen['returns']['days']);
        $this->assertTrue(self::facts()->visible('FR', 60)['shipping']['free']);
    }

    public function testNoClaimForACountryNotCovered(): void
    {
        $this->assertSame(['shipping' => null, 'returns' => null], self::facts()->visible('JP', 20));
    }
}
