<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Tests;

use Iamshoaibktk\Geolens\Feed\Mapper;
use Iamshoaibktk\Geolens\Feed\Profiles;
use Iamshoaibktk\Geolens\Feed\Spec;
use PHPUnit\Framework\TestCase;

final class FeedMapperTest extends TestCase
{
    private const NOW = 1793540000; // 2026-11-01

    private const SETTINGS = [
        'mode' => Profiles::PRIMARY,
        'target' => Profiles::GOOGLE,
        'country' => 'FR',
        'currency' => 'EUR',
        'reference' => false,
        'weight_unit' => 'kg',
        'shipping' => null,
        'fx' => 1.0,
        'now' => self::NOW,
    ];

    private const RULE = ['countries' => ['FR'], 'rate' => 5.0, 'free_over' => 100.0, 'handling_min' => 0, 'handling_max' => 1, 'transit_min' => 1, 'transit_max' => 3];

    private static function facts(array $changes = []): array
    {
        return $changes + [
            'id_product' => 12,
            'id_combination' => 0,
            'name' => 'Mountain fox cushion',
            'description' => '<p>A soft cushion.</p>',
            'short' => '',
            'url' => 'https://shop.example/12-cushion.html',
            'images' => ['https://shop.example/5/cushion.jpg', 'https://shop.example/6/cushion.jpg'],
            'brand' => 'Studio Design',
            'ean13' => '',
            'upc' => '',
            'isbn' => '',
            'mpn' => '',
            'condition' => 'new',
            'for_sale' => true,
            'manage_stock' => true,
            'allow_orders' => false,
            'quantity' => 3,
            'available_date' => null,
            'options' => [],
            'is_variant' => false,
            'features' => [],
            'product_type' => 'Home > Cushions',
            'weight' => 0.0,
            'virtual' => false,
            'pack' => null,
            'public' => true,
            'show_price' => true,
            'price' => 19.9,
            'regular' => null,
            'sale_from' => null,
            'sale_to' => null,
        ];
    }

    /** @return array<string, array<int, string|array>> values by attribute name */
    private static function map(array $facts = [], array $settings = [], array &$notes = []): array
    {
        $out = [];
        foreach (Mapper::attributes(self::facts($facts), $settings + self::SETTINGS, $notes) as [$name, $value]) {
            $out[$name][] = $value;
        }

        return $out;
    }

    public function testOrdinaryProductHasEveryRequiredField(): void
    {
        $items = Mapper::attributes(self::facts(), self::SETTINGS);
        $this->assertSame([], Spec::check($items, Profiles::PRIMARY, Profiles::GOOGLE));
        $out = self::map();
        $this->assertSame('12-0', $out['id'][0]);
        $this->assertSame('Mountain fox cushion', $out['title'][0]);
        $this->assertSame('A soft cushion.', $out['description'][0]);
        $this->assertSame('in_stock', $out['availability'][0]);
        $this->assertSame('19.90 EUR', $out['price'][0]);
        $this->assertSame('https://shop.example/5/cushion.jpg', $out['image_link'][0]);
        $this->assertSame(['https://shop.example/6/cushion.jpg'], $out['additional_image_link']);
        $this->assertSame('new', $out['condition'][0]);
        $this->assertSame('Home > Cushions', $out['product_type'][0]);
    }

    public function testIdIsStableAndMatchesTheOfficialModulesEvents(): void
    {
        $this->assertSame('12-0', Mapper::id(12, 0));
        $this->assertSame('12-34', Mapper::id(12, 34));
    }

    public function testOnlyTenExtraImages(): void
    {
        $images = array_map(static fn ($i) => "https://shop.example/{$i}.jpg", range(1, 15));
        $out = self::map(['images' => $images]);
        $this->assertCount(10, $out['additional_image_link']);
        $this->assertSame('https://shop.example/1.jpg', $out['image_link'][0]);
    }

    public function testNoNameOrImageOrVisiblePriceLeavesTheProductOut(): void
    {
        $this->assertNull(Mapper::skipReason(self::facts(), Profiles::PRIMARY));
        $this->assertSame('no_name', Mapper::skipReason(self::facts(['name' => '  <b></b> ']), Profiles::PRIMARY));
        $this->assertSame('no_image', Mapper::skipReason(self::facts(['images' => []]), Profiles::PRIMARY));
        $this->assertSame('price_hidden', Mapper::skipReason(self::facts(['show_price' => false]), Profiles::PRIMARY));
        $this->assertSame('not_public', Mapper::skipReason(self::facts(['public' => false]), Profiles::PRIMARY));
    }

    public function testSupplementalNeedsOnlyAName(): void
    {
        $this->assertNull(Mapper::skipReason(self::facts(['images' => [], 'show_price' => false, 'public' => false]), Profiles::SUPPLEMENTAL));
        $this->assertSame('no_name', Mapper::skipReason(self::facts(['name' => '']), Profiles::SUPPLEMENTAL));
    }

    public function testStockAndStockManagement(): void
    {
        $this->assertSame(['in_stock', null], Mapper::availability(self::facts(), self::NOW));
        $this->assertSame(['out_of_stock', null], Mapper::availability(self::facts(['quantity' => 0]), self::NOW));
        $this->assertSame(['in_stock', null], Mapper::availability(self::facts(['quantity' => 0, 'manage_stock' => false]), self::NOW));
        $this->assertSame(['out_of_stock', null], Mapper::availability(self::facts(['for_sale' => false]), self::NOW), 'Not available for order');
    }

    public function testBackorderStatesADateOnlyWithinAYear(): void
    {
        $backorder = ['quantity' => 0, 'allow_orders' => true];
        $this->assertSame(['backorder', null], Mapper::availability(self::facts($backorder), self::NOW));
        $this->assertSame(['backorder', null], Mapper::availability(self::facts($backorder + ['available_date' => '0000-00-00']), self::NOW));
        $this->assertSame(['backorder', null], Mapper::availability(self::facts($backorder + ['available_date' => '2020-01-01']), self::NOW), 'in the past');
        $this->assertSame(['backorder', null], Mapper::availability(self::facts($backorder + ['available_date' => '2030-01-01']), self::NOW), 'too far ahead');
        [$value, $date] = Mapper::availability(self::facts($backorder + ['available_date' => '2026-12-15']), self::NOW);
        $this->assertSame('backorder', $value);
        $this->assertMatchesRegularExpression('/^2026-12-15T00:00[+-]\d{4}$/', (string) $date);
        $out = self::map($backorder + ['available_date' => '2026-12-15']);
        $this->assertSame($date, $out['availability_date'][0]);
    }

    public function testMicrosoftGetsItsOwnSpelling(): void
    {
        $settings = ['target' => Profiles::MICROSOFT];
        $this->assertSame('in stock', self::map([], $settings)['availability'][0]);
        $this->assertSame('out of stock', self::map(['quantity' => 0], $settings)['availability'][0]);
    }

    public function testMicrosoftHasNoBackorderSoItSaysOutOfStock(): void
    {
        $out = self::map(['quantity' => 0, 'allow_orders' => true, 'available_date' => '2026-12-15'], ['target' => Profiles::MICROSOFT]);
        $this->assertSame('out of stock', $out['availability'][0]);
        $this->assertArrayNotHasKey('availability_date', $out);
        $this->assertSame([], Spec::check(Mapper::attributes(self::facts(['quantity' => 0, 'allow_orders' => true]), ['target' => Profiles::MICROSOFT] + self::SETTINGS), Profiles::PRIMARY, Profiles::MICROSOFT));
    }

    public function testNoReferencePriceMeansTodaysPriceAlone(): void
    {
        $out = self::map(['price' => 80.0, 'regular' => 100.0, 'sale_to' => '2026-12-01 00:00:00'], ['reference' => false]);
        $this->assertSame('80.00 EUR', $out['price'][0]);
        $this->assertArrayNotHasKey('sale_price', $out);
        $this->assertArrayNotHasKey('sale_price_effective_date', $out);
    }

    public function testReferencePriceStatesBothAndTheDates(): void
    {
        $out = self::map(['price' => 80.0, 'regular' => 100.0, 'sale_from' => '2026-10-01 00:00:00', 'sale_to' => '2026-12-01 00:00:00'], ['reference' => true]);
        $this->assertSame('100.00 EUR', $out['price'][0]);
        $this->assertSame('80.00 EUR', $out['sale_price'][0]);
        $this->assertArrayHasKey('sale_price_effective_date', $out);
    }

    public function testAnEndlessSaleHasNoDates(): void
    {
        $out = self::map(['price' => 80.0, 'regular' => 100.0, 'sale_from' => '0000-00-00 00:00:00', 'sale_to' => '0000-00-00 00:00:00'], ['reference' => true]);
        $this->assertSame('80.00 EUR', $out['sale_price'][0]);
        $this->assertArrayNotHasKey('sale_price_effective_date', $out);
    }

    public function testValidGtinIsKept(): void
    {
        $notes = [];
        $out = self::map(['ean13' => '4006381333931'], [], $notes);
        $this->assertSame('4006381333931', $out['gtin'][0]);
        $this->assertArrayNotHasKey('identifier_exists', $out);
        $this->assertArrayNotHasKey('no_identifier', $notes);
    }

    public function testGtinWithAWrongCheckDigitStaysOut(): void
    {
        $notes = [];
        $out = self::map(['ean13' => '4006381333932'], [], $notes);
        $this->assertArrayNotHasKey('gtin', $out);
        $this->assertSame(1, $notes['gtin_invalid']);
        $this->assertSame('no', $out['identifier_exists'][0]);
    }

    public function testGtinInARestrictedRangeStaysOut(): void
    {
        $notes = [];
        $out = self::map(['ean13' => '2000000000008'], [], $notes);
        $this->assertArrayNotHasKey('gtin', $out);
        $this->assertSame(1, $notes['gtin_restricted']);
    }

    public function testNextCodeIsTriedAfterABadOne(): void
    {
        $this->assertSame('012345678905', self::map(['ean13' => '123', 'upc' => '012345678905'])['gtin'][0]);
    }

    public function testDashesInAnIsbnAreIgnored(): void
    {
        $this->assertSame('9780201379624', self::map(['isbn' => '978-0-201-37962-4'])['gtin'][0]);
    }

    public function testIdentifierExistsSaysNoOnlyWithNoWayToIdentify(): void
    {
        $this->assertSame('no', self::map()['identifier_exists'][0], 'A brand alone is not enough');
        $this->assertArrayNotHasKey('identifier_exists', self::map(['mpn' => 'ABC-1']), 'Brand and part number');
        $this->assertSame('no', self::map(['mpn' => 'ABC-1', 'brand' => ''])['identifier_exists'][0], 'Part number alone');
    }

    public function testVariantTakesOnlyItsOwnCodes(): void
    {
        $this->assertSame('', Mapper::code('', '4006381333931', true), 'The product code belongs to no single variant');
        $this->assertSame('4006381333931', Mapper::code('', '4006381333931', false));
        $this->assertSame('111', Mapper::code('111', '222', true));
    }

    public function testBrandStaysOutWhenThereIsNoneOrItIsTooLong(): void
    {
        $this->assertArrayNotHasKey('brand', self::map(['brand' => '']));
        $this->assertArrayNotHasKey('brand', self::map(['brand' => str_repeat('x', 71)]));
    }

    public function testASimpleProductIsNotAVariant(): void
    {
        $out = self::map();
        $this->assertArrayNotHasKey('item_group_id', $out);
        $this->assertArrayNotHasKey('variant_option', $out);
    }

    public function testACombinationIsAVariant(): void
    {
        $options = [
            ['group' => 'Size', 'value' => 'M', 'property' => 'size'],
            ['group' => 'Color', 'value' => 'Black', 'property' => 'color'],
            ['group' => 'Dimension', 'value' => '40x60cm', 'property' => null],
        ];
        $facts = ['id_combination' => 34, 'is_variant' => true, 'options' => $options];
        $out = self::map($facts);
        $this->assertSame('12-34', $out['id'][0]);
        $this->assertSame('12', $out['item_group_id'][0]);
        $this->assertSame('Mountain fox cushion', $out['item_group_title'][0]);
        $this->assertSame('Mountain fox cushion - M, Black, 40x60cm', $out['title'][0]);
        $this->assertSame(['name' => 'Size', 'value' => 'M'], $out['variant_option'][0]);
        $this->assertCount(3, $out['variant_option']);
        $this->assertSame('Black', $out['color'][0]);
        $this->assertSame('M', $out['size'][0]);
        $this->assertSame([], Spec::check(Mapper::attributes(self::facts($facts), self::SETTINGS), Profiles::PRIMARY, Profiles::GOOGLE));
    }

    public function testAnOptionWithNoNameInThisLanguageIsCounted(): void
    {
        $notes = [];
        self::map(['is_variant' => true, 'options' => [['group' => 'Size', 'value' => 'S', 'property' => 'size', 'untranslated' => true]]], [], $notes);
        $this->assertSame(1, $notes['option_untranslated']);
    }

    public function testTitleLimitKeepsWhatTellsTheVariantApart(): void
    {
        $title = Mapper::title(str_repeat('Very long name ', 20), [['value' => 'XXL'], ['value' => 'Midnight Blue']]);
        $this->assertLessThanOrEqual(150, mb_strlen($title, 'UTF-8'));
        $this->assertStringEndsWith(' - XXL, Midnight Blue', $title);
    }

    public function testCombinationFirstThenFeaturesForVariantFacts(): void
    {
        $features = [['name' => 'Color', 'value' => 'Red'], ['name' => 'Composition', 'value' => 'Cotton, Wool']];
        $this->assertSame(['color' => 'Red', 'material' => 'Cotton/Wool'], Mapper::variantFacts(['features' => $features, 'options' => []]));
        $this->assertSame('Blue', Mapper::variantFacts(['features' => $features, 'options' => [['group' => 'Color', 'value' => 'Blue', 'property' => 'color']]])['color']);
    }

    public function testValuesGoogleRefusesAsColorsStayOut(): void
    {
        foreach (['#FF0000', '123', 'N/A', 'Multicolor', 'R', 'see image'] as $color) {
            $this->assertArrayNotHasKey('color', Mapper::variantFacts(['features' => [['name' => 'Color', 'value' => $color]], 'options' => []]), $color);
        }
    }

    public function testFeaturesBecomeProductDetails(): void
    {
        $details = Mapper::details([['name' => 'Composition', 'value' => 'Cotton'], ['name' => '', 'value' => 'x'], ['name' => 'y', 'value' => '  ']]);
        $this->assertSame([['attribute_name' => 'Composition', 'attribute_value' => 'Cotton']], $details);
    }

    public function testAFeatureTooLongIsLeftOutNotCut(): void
    {
        $notes = [];
        $details = Mapper::details([['name' => 'Long', 'value' => str_repeat('x', 200)], ['name' => 'Short', 'value' => 'ok']], $notes);
        $this->assertCount(1, $details);
        $this->assertSame('Short', $details[0]['attribute_name']);
        $this->assertSame(1, $notes['detail_too_long']);
    }

    public function testAHundredDetailsAtMost(): void
    {
        $features = array_map(static fn ($i) => ['name' => "F{$i}", 'value' => 'v'], range(1, 130));
        $this->assertCount(100, Mapper::details($features));
    }

    public function testShippingFollowsTheMerchantsRule(): void
    {
        $out = self::map([], ['shipping' => self::RULE]);
        $this->assertSame(['country' => 'FR', 'price' => '5.00 EUR', 'min_handling_time' => '0', 'max_handling_time' => '1', 'min_transit_time' => '1', 'max_transit_time' => '3'], $out['shipping'][0]);
    }

    public function testShippingIsFreeAboveTheThreshold(): void
    {
        $this->assertSame('0.00 EUR', self::map(['price' => 120.0], ['shipping' => self::RULE])['shipping'][0]['price']);
    }

    public function testShippingConvertsFromTheShopCurrency(): void
    {
        $out = self::map(['price' => 95.0], ['shipping' => self::RULE, 'fx' => 2.0, 'currency' => 'CHF']);
        $this->assertSame('10.00 CHF', $out['shipping'][0]['price'], 'Threshold is 200 in this currency');
    }

    public function testNoRuleOrNoParcelMeansNoShipping(): void
    {
        $this->assertArrayNotHasKey('shipping', self::map());
        $out = self::map(['virtual' => true, 'weight' => 1.0], ['shipping' => self::RULE]);
        $this->assertArrayNotHasKey('shipping', $out);
        $this->assertArrayNotHasKey('shipping_weight', $out);
    }

    public function testWeightKeepsItsUnit(): void
    {
        $this->assertSame('10 kg', self::map(['weight' => 10.0])['shipping_weight'][0]);
        $this->assertSame('0.3 kg', self::map(['weight' => 0.3])['shipping_weight'][0]);
        $this->assertSame('2.5 lb', self::map(['weight' => 2.5], ['weight_unit' => 'lbs'])['shipping_weight'][0]);
        $this->assertArrayNotHasKey('shipping_weight', self::map(['weight' => 2.0], ['weight_unit' => 'stone']));
        $this->assertArrayNotHasKey('shipping_weight', self::map(['weight' => 0.0]));
    }

    public function testPacksAreStated(): void
    {
        $this->assertSame('yes', self::map(['pack' => ['lines' => 3, 'units' => 3]])['is_bundle'][0]);
        $this->assertSame('6', self::map(['pack' => ['lines' => 1, 'units' => 6]])['multipack'][0]);
        $this->assertArrayNotHasKey('multipack', self::map(['pack' => ['lines' => 1, 'units' => 1]]));
    }

    public function testNoDescriptionFallsBackToTheNameAndSaysSo(): void
    {
        $notes = [];
        $out = self::map(['description' => '', 'short' => ''], [], $notes);
        $this->assertSame('Mountain fox cushion', $out['description'][0]);
        $this->assertSame(1, $notes['no_description']);
    }

    public function testShortDescriptionIsUsedWhenThereIsNoLongOne(): void
    {
        $this->assertSame('Short text.', self::map(['description' => '', 'short' => '<p>Short text.</p>'])['description'][0]);
    }

    public function testLongDescriptionStaysWithinTheLimit(): void
    {
        $out = self::map(['description' => str_repeat('word ', 2000)]);
        $this->assertLessThanOrEqual(5000, mb_strlen($out['description'][0], 'UTF-8'));
    }

    public function testSupplementalCarriesNoPriceStockOrTitle(): void
    {
        $settings = ['mode' => Profiles::SUPPLEMENTAL];
        $facts = ['is_variant' => true, 'id_combination' => 34, 'options' => [['group' => 'Size', 'value' => 'M', 'property' => 'size']], 'features' => [['name' => 'Composition', 'value' => 'Cotton']]];
        $items = Mapper::attributes(self::facts($facts), $settings + self::SETTINGS);
        $this->assertSame([], Spec::check($items, Profiles::SUPPLEMENTAL, Profiles::GOOGLE));
        $names = array_unique(array_column($items, 0));
        sort($names);
        $this->assertSame(['id', 'item_group_id', 'item_group_title', 'material', 'product_detail', 'size', 'variant_option'], $names);
    }

    public function testSupplementalWithNothingToAddIsOnlyAnId(): void
    {
        $this->assertCount(1, Mapper::attributes(self::facts(), ['mode' => Profiles::SUPPLEMENTAL] + self::SETTINGS));
    }
}
