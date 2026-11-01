<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Tests;

use Iamshoaibktk\Geolens\Feed\Profiles;
use Iamshoaibktk\Geolens\Feed\Rss;
use Iamshoaibktk\Geolens\Feed\Spec;
use PHPUnit\Framework\TestCase;

final class FeedSpecTest extends TestCase
{
    private const ITEM = [
        ['id', '12-0'],
        ['title', 'Cushion'],
        ['description', 'A soft cushion.'],
        ['link', 'https://shop.example/12-cushion.html'],
        ['image_link', 'https://shop.example/5/cushion.jpg'],
        ['availability', 'in_stock'],
        ['price', '19.90 EUR'],
    ];

    /** @return string[] */
    private static function problems(array $changes, string $mode = Profiles::PRIMARY, string $target = Profiles::GOOGLE): array
    {
        $items = self::ITEM;
        foreach ($changes as $name => $value) {
            $found = false;
            foreach ($items as $i => $attribute) {
                if ($attribute[0] === $name) {
                    $items[$i][1] = $value;
                    $found = true;
                }
            }
            if (!$found) {
                $items[] = [$name, $value];
            }
        }

        return Spec::check($items, $mode, $target);
    }

    public function testAGoodItemHasNoProblems(): void
    {
        $this->assertSame([], Spec::check(self::ITEM, Profiles::PRIMARY, Profiles::GOOGLE));
    }

    public function testEachRequiredFieldIsChecked(): void
    {
        foreach (self::ITEM as $i => [$name]) {
            $items = self::ITEM;
            unset($items[$i]);
            $this->assertContains("{$name}: required and missing", Spec::check(array_values($items), Profiles::PRIMARY, Profiles::GOOGLE));
        }
    }

    public function testLimitsCountCharactersNotBytes(): void
    {
        $this->assertSame([], self::problems(['title' => str_repeat('é', 150)]));
        $this->assertContains('title: longer than 150', self::problems(['title' => str_repeat('é', 151)]));
        $this->assertContains('id: longer than 50', self::problems(['id' => str_repeat('1', 51)]));
        $this->assertContains('description: longer than 5000', self::problems(['description' => str_repeat('x', 5001)]));
    }

    public function testLinksMustBeHttp(): void
    {
        $this->assertContains('link: not an http or https address', self::problems(['link' => 'ftp://shop.example/1']));
        $this->assertContains('link: not an http or https address', self::problems(['link' => 'https://shop.example/a b']));
        $this->assertContains('image_link: not an http or https address', self::problems(['image_link' => '/5/cushion.jpg']));
    }

    public function testAvailabilityValuesDependOnThePlatform(): void
    {
        $this->assertContains('availability: unknown value', self::problems(['availability' => 'in stock']));
        $this->assertSame([], self::problems(['availability' => 'in stock'], Profiles::PRIMARY, Profiles::MICROSOFT));
        $this->assertContains('availability: unknown value', self::problems(['availability' => 'in_stock'], Profiles::PRIMARY, Profiles::MICROSOFT));
        $this->assertContains('availability: unknown value', self::problems(['availability' => 'backorder'], Profiles::PRIMARY, Profiles::MICROSOFT));
    }

    public function testPricesNeedTwoDecimalsAndACurrency(): void
    {
        $this->assertContains('price: not an amount and a currency', self::problems(['price' => '19.9 EUR']));
        $this->assertContains('price: not an amount and a currency', self::problems(['price' => '19,90 EUR']));
        $this->assertContains('price: not an amount and a currency', self::problems(['price' => '19.90']));
        $this->assertContains('price: zero', self::problems(['price' => '0.00 EUR']));
    }

    public function testSalePriceMustBeBelowPrice(): void
    {
        $this->assertSame([], self::problems(['sale_price' => '15.00 EUR']));
        $this->assertContains('sale_price: not below price', self::problems(['sale_price' => '19.90 EUR']));
        $this->assertContains('sale_price: not below price', self::problems(['sale_price' => '25.00 EUR']));
    }

    public function testSaleDatesNeedAStartBeforeAnEnd(): void
    {
        $this->assertSame([], self::problems(['sale_price_effective_date' => '2026-11-01T00:00+0100 / 2026-12-01T00:00+0100']));
        $this->assertContains('sale_price_effective_date: not a start and an end', self::problems(['sale_price_effective_date' => '2026-12-01T00:00+0100 / 2026-11-01T00:00+0100']));
        $this->assertContains('sale_price_effective_date: not a start and an end', self::problems(['sale_price_effective_date' => '2026-12-01T00:00+0100']));
    }

    public function testGtinIsCheckedAgainWhateverWroteIt(): void
    {
        $this->assertSame([], self::problems(['gtin' => '4006381333931']));
        $this->assertContains('gtin: check digit fails', self::problems(['gtin' => '4006381333932']));
        $this->assertContains('gtin: restricted range', self::problems(['gtin' => '2000000000008']));
    }

    public function testNoIdentifierCannotComeWithAGtin(): void
    {
        $this->assertContains('identifier_exists: "no" on an item with a GTIN', self::problems(['gtin' => '4006381333931', 'identifier_exists' => 'no']));
    }

    public function testRepeatLimits(): void
    {
        $items = self::ITEM;
        for ($i = 0; $i < 11; ++$i) {
            $items[] = ['additional_image_link', 'https://shop.example/' . $i . '.jpg'];
        }
        $this->assertContains('additional_image_link: 11 values', Spec::check($items, Profiles::PRIMARY, Profiles::GOOGLE));
        $this->assertContains('title: 2 values', Spec::check(array_merge(self::ITEM, [['title', 'Again']]), Profiles::PRIMARY, Profiles::GOOGLE));
    }

    public function testVariantOptionNeedsAGroup(): void
    {
        $this->assertContains('variant_option: needs item_group_id', self::problems(['variant_option' => ['name' => 'Size', 'value' => 'M']]));
        $this->assertSame([], self::problems(['item_group_id' => '12', 'variant_option' => ['name' => 'Size', 'value' => 'M']]));
    }

    public function testGroupIdIsPlainAscii(): void
    {
        $this->assertContains('item_group_id: not plain ASCII', self::problems(['item_group_id' => 'a b']));
    }

    public function testProductDetailLimits(): void
    {
        $this->assertSame([], self::problems(['product_detail' => ['attribute_name' => 'Size', 'attribute_value' => '13 inch']]));
        $this->assertContains('product_detail: needs a name and a value', self::problems(['product_detail' => ['attribute_name' => 'Size', 'attribute_value' => '']]));
        $this->assertContains('product_detail: too long', self::problems(['product_detail' => ['attribute_name' => 'Size', 'attribute_value' => str_repeat('x', 150)]]));
    }

    public function testShippingSubAttributes(): void
    {
        $good = ['country' => 'FR', 'price' => '5.00 EUR', 'min_handling_time' => '0', 'max_handling_time' => '1'];
        $this->assertSame([], self::problems(['shipping' => $good]));
        $this->assertContains('shipping: needs a country code', self::problems(['shipping' => ['country' => 'France'] + $good]));
        $this->assertContains('shipping: price is not an amount and a currency', self::problems(['shipping' => ['price' => '5 EUR'] + $good]));
        $this->assertContains('shipping: handling time starts after it ends', self::problems(['shipping' => ['min_handling_time' => '3'] + $good]));
    }

    public function testWeightNeedsAKnownUnit(): void
    {
        $this->assertSame([], self::problems(['shipping_weight' => '0.3 kg']));
        $this->assertContains('shipping_weight: not a weight and a unit', self::problems(['shipping_weight' => '3 stone']));
    }

    public function testAFieldOutsideTheSpecificationIsCaught(): void
    {
        $this->assertContains('ai_citation_phrase: not in the specification', self::problems(['ai_citation_phrase' => 'buy me']));
        $this->assertContains('made_up: not in the specification', self::problems(['made_up' => ['a' => 'b']]));
    }

    public function testSupplementalNeedsOnlyAnIdAndCannotCarryWhatThePrimaryOwns(): void
    {
        $this->assertSame([], Spec::check([['id', '12-0'], ['product_detail', ['attribute_name' => 'a', 'attribute_value' => 'b']]], Profiles::SUPPLEMENTAL, Profiles::GOOGLE));
        $this->assertContains('price: belongs to the primary source', Spec::check([['id', '12-0'], ['price', '10.00 EUR']], Profiles::SUPPLEMENTAL, Profiles::GOOGLE));
        $this->assertContains('title: belongs to the primary source', Spec::check([['id', '12-0'], ['title', 'x']], Profiles::SUPPLEMENTAL, Profiles::GOOGLE));
    }

    public function testColorRules(): void
    {
        foreach (['Black', 'Off White', 'Black/White', 'Bleu marine', 'Crème', 'Чёрный', '黑'] as $color) {
            $this->assertTrue(Spec::isColor($color), $color);
        }
        foreach (['#FF0000', '12', 'N/A', 'multicolor', 'Various', 'X', 'Red-1', str_repeat('a', 41)] as $color) {
            $this->assertFalse(Spec::isColor($color), $color);
        }
    }

    private function file(array $items): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'geolens');
        file_put_contents($path, Rss::open('Shop', 'https://shop.example/', 'Test') . implode('', array_map([Rss::class, 'item'], $items)) . Rss::close());

        return $path;
    }

    public function testAWholeFileIsCheckedAndCounted(): void
    {
        $path = $this->file([self::ITEM, array_merge([['id', '13-0']], array_slice(self::ITEM, 1))]);
        $result = Spec::checkFile($path, Profiles::PRIMARY, Profiles::GOOGLE);
        unlink($path);
        $this->assertSame(2, $result['items']);
        $this->assertSame(0, $result['invalid']);
        $this->assertSame([], $result['problems']);
    }

    public function testAFileWithATwiceUsedIdIsCaught(): void
    {
        $path = $this->file([self::ITEM, self::ITEM]);
        $result = Spec::checkFile($path, Profiles::PRIMARY, Profiles::GOOGLE);
        unlink($path);
        $this->assertSame(1, $result['invalid']);
        $this->assertSame(1, $result['problems']['id: used twice']);
    }

    public function testAGroupNotHeldTogetherIsCaught(): void
    {
        $variant = static fn (string $id, string $title, array $options) => array_merge(
            [['id', $id]],
            array_slice(self::ITEM, 1),
            [['item_group_id', '12'], ['item_group_title', $title]],
            array_map(static fn ($name, $value) => ['variant_option', ['name' => $name, 'value' => $value]], array_keys($options), $options)
        );
        $path = $this->file([
            $variant('12-1', 'Tee', ['Size' => 'S']),
            $variant('12-2', 'Tee', ['Size' => 'M']),
            $variant('12-3', 'Other title', ['Size' => 'L']),
            $variant('12-4', 'Tee', ['Color' => 'Red']),
            $variant('12-5', 'Tee', ['Size' => 'S']),
        ]);
        $result = Spec::checkFile($path, Profiles::PRIMARY, Profiles::GOOGLE);
        unlink($path);
        $this->assertSame(2, $result['groups'] + 1);
        $this->assertSame(1, $result['problems']['item_group_title: differs inside a group']);
        $this->assertSame(1, $result['problems']['variant_option: names differ inside a group']);
        $this->assertSame(1, $result['problems']['variant_option: two variants with the same values']);
    }
}
