<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Feed;

use Iamshoaibktk\Geolens\Schema\Gtin;
use Iamshoaibktk\Geolens\Schema\PageWriter;

/**
 * Turns the facts about one sellable item into the fields of a feed. Nothing here touches the shop,
 * and nothing is made up: a fact the catalogue does not hold stays out of the file. Field names and
 * limits follow Spec, which also checks the result.
 */
final class Mapper
{
    private const MICROSOFT_AVAILABILITY = [
        'in_stock' => 'in stock',
        'out_of_stock' => 'out of stock',
        'preorder' => 'preorder',
        // Microsoft lists no value for this. Saying "in stock" would be untrue.
        'backorder' => 'out of stock',
    ];

    private const WEIGHT_LIMITS = ['kg' => 1000, 'g' => 1000000, 'lb' => 2000, 'lbs' => 2000, 'oz' => 32000];

    /**
     * The same in every feed, and independent of the reference, which merchants change and often leave
     * empty or repeat. Marketing with Google identifies a product this way in its own events, which is
     * what lets a supplemental source line up with what that module sends.
     */
    public static function id(int $idProduct, int $idCombination): string
    {
        return $idProduct . '-' . $idCombination;
    }

    /** Why a product cannot go in the file. Checked first, because a price costs far more to work out than this. */
    public static function skipReason(array $f, string $mode): ?string
    {
        if (Text::plain((string) $f['name']) === '') {
            return 'no_name';
        }
        if ($mode === Profiles::SUPPLEMENTAL) {
            return null;
        }
        if (!$f['public']) {
            return 'not_public';
        }
        if (!$f['show_price']) {
            return 'price_hidden';
        }
        if (!$f['images']) {
            return 'no_image';
        }

        return null;
    }

    /**
     * @param array $f the item's facts: see Builder::facts()
     * @param array{mode: string, target: string, country: string, currency: string, reference: bool, weight_unit: string, shipping: array|null, fx: float, now: int} $s
     * @param array<string, int> $notes counts of things the merchant may want to fix
     *
     * @return array<int, array{0: string, 1: string|array<string, string>}>
     */
    public static function attributes(array $f, array $s, array &$notes = []): array
    {
        $out = [['id', self::id((int) $f['id_product'], (int) $f['id_combination'])]];
        $name = Text::plain((string) $f['name']);
        if ($s['mode'] !== Profiles::SUPPLEMENTAL) {
            $out = array_merge($out, self::offer($f, $s, $name, $notes));
        }
        if (!empty($f['is_variant'])) {
            $out[] = ['item_group_id', (string) $f['id_product']];
            $out[] = ['item_group_title', Text::limit($name, 150)];
            foreach (array_slice((array) $f['options'], 0, 30) as $option) {
                if (!empty($option['untranslated'])) {
                    self::note($notes, 'option_untranslated');
                }
                $group = Text::limit(Text::line((string) $option['group']), 250);
                $value = Text::limit(Text::line((string) $option['value']), 250);
                if ($group !== '' && $value !== '') {
                    $out[] = ['variant_option', ['name' => $group, 'value' => $value]];
                }
            }
        }
        foreach (self::variantFacts($f) as $key => $value) {
            $out[] = [$key, $value];
        }
        foreach (self::details((array) $f['features'], $notes) as $detail) {
            $out[] = ['product_detail', $detail];
        }

        return $out;
    }

    /** The fields only a primary source sends. A supplemental source must not contradict one, so it has none of these. */
    private static function offer(array $f, array $s, string $name, array &$notes): array
    {
        $out = [
            ['title', self::title($name, (array) $f['options'])],
            ['description', self::description($f, $name, $notes)],
            ['link', (string) $f['url']],
            ['image_link', (string) $f['images'][0]],
        ];
        foreach (array_slice((array) $f['images'], 1, 10) as $image) {
            $out[] = ['additional_image_link', (string) $image];
        }
        $condition = (string) $f['condition'];
        if (in_array($condition, ['new', 'used', 'refurbished'], true)) {
            $out[] = ['condition', $condition];
        }

        [$availability, $date] = self::availability($f, (int) $s['now']);
        $out[] = ['availability', $s['target'] === Profiles::MICROSOFT ? self::MICROSOFT_AVAILABILITY[$availability] : $availability];
        if ($date !== null && $s['target'] !== Profiles::MICROSOFT) {
            $out[] = ['availability_date', $date];
        }

        $pair = Pricing::pair((float) $f['price'], isset($f['regular']) ? (float) $f['regular'] : null, (bool) $s['reference']);
        $out[] = ['price', Pricing::amount($pair['price'], (string) $s['currency'])];
        if ($pair['sale'] !== null) {
            $out[] = ['sale_price', Pricing::amount($pair['sale'], (string) $s['currency'])];
            $dates = Pricing::saleDates($f['sale_from'] ?? null, $f['sale_to'] ?? null, (int) $s['now']);
            if ($dates !== null) {
                $out[] = ['sale_price_effective_date', $dates];
            }
        }

        $out = array_merge($out, self::identifiers($f, $notes));
        $type = Text::limit(Text::line((string) ($f['product_type'] ?? '')), 750);
        if ($type !== '') {
            $out[] = ['product_type', $type];
        }
        if (!empty($f['pack'])) {
            if ((int) $f['pack']['lines'] > 1) {
                $out[] = ['is_bundle', 'yes'];
            } elseif ((int) $f['pack']['units'] > 1) {
                $out[] = ['multipack', (string) (int) $f['pack']['units']];
            }
        }
        if (empty($f['virtual'])) {
            $shipping = self::shipping($s, (float) $f['price']);
            if ($shipping !== null) {
                $out[] = ['shipping', $shipping];
            }
            $weight = self::weight((float) ($f['weight'] ?? 0), (string) $s['weight_unit']);
            if ($weight !== null) {
                $out[] = ['shipping_weight', $weight];
            }
        }

        return $out;
    }

    /** The product name stays readable, and what tells this variant apart is never the part cut off. */
    public static function title(string $name, array $options): string
    {
        $values = array_filter(array_map(static fn ($o) => Text::line((string) $o['value']), $options), static fn ($v) => $v !== '');
        if (!$values) {
            return Text::limit($name, 150);
        }
        $suffix = ' - ' . implode(', ', $values);
        $room = 150 - mb_strlen($suffix, 'UTF-8');

        return $room >= 40 ? Text::limit($name, $room) . $suffix : Text::limit($name . $suffix, 150);
    }

    private static function description(array $f, string $name, array &$notes): string
    {
        $text = Text::plain((string) ($f['description'] ?? ''));
        if ($text === '') {
            $text = Text::plain((string) ($f['short'] ?? ''));
        }
        if ($text === '') {
            // Required, and a repeat of the name is true where a guess would not be.
            self::note($notes, 'no_description');
            $text = $name;
        }

        return Text::limit($text, 5000);
    }

    /**
     * @return array{0: string, 1: string|null} the value from Google's list, and the date for it
     */
    public static function availability(array $f, int $now): array
    {
        if (empty($f['for_sale'])) {
            return ['out_of_stock', null];
        }
        if (empty($f['manage_stock']) || (int) $f['quantity'] > 0) {
            return ['in_stock', null];
        }
        if (empty($f['allow_orders'])) {
            return ['out_of_stock', null];
        }
        $date = Pricing::timestamp($f['available_date'] ?? null);
        // Google takes dates up to a year ahead, and a date in the past says nothing.
        if ($date === null || $date <= $now || $date > $now + 31536000) {
            return ['backorder', null];
        }

        return ['backorder', Pricing::date($date)];
    }

    /** GTIN is the one fact a wrong value makes worse than none, so a code that fails stays out. */
    private static function identifiers(array $f, array &$notes): array
    {
        $out = [];
        $brand = Text::line((string) ($f['brand'] ?? ''));
        if ($brand !== '' && mb_strlen($brand, 'UTF-8') <= 70) {
            $out[] = ['brand', $brand];
        } else {
            $brand = '';
        }
        $gtin = self::gtin([(string) ($f['ean13'] ?? ''), (string) ($f['upc'] ?? ''), (string) ($f['isbn'] ?? '')], $notes);
        if ($gtin !== null) {
            $out[] = ['gtin', $gtin];
        }
        $mpn = Text::line((string) ($f['mpn'] ?? ''));
        if ($mpn !== '' && mb_strlen($mpn, 'UTF-8') <= 70) {
            $out[] = ['mpn', $mpn];
        } else {
            $mpn = '';
        }
        if ($gtin === null && ($brand === '' || $mpn === '')) {
            $out[] = ['identifier_exists', 'no'];
            self::note($notes, 'no_identifier');
        }

        return $out;
    }

    /** A variant only takes an identifier from its own combination. A code on the product belongs to no single variant. */
    public static function code(string $own, string $parent, bool $isVariant): string
    {
        return $own === '' && !$isVariant ? $parent : $own;
    }

    /** @param string[] $codes */
    public static function gtin(array $codes, array &$notes = []): ?string
    {
        $seen = false;
        foreach ($codes as $code) {
            $code = str_replace([' ', '-'], '', $code);
            if ($code === '') {
                continue;
            }
            $seen = true;
            if (Gtin::isValid($code)) {
                if (!Gtin::isRestricted($code)) {
                    return $code;
                }
                self::note($notes, 'gtin_restricted');

                return null;
            }
        }
        if ($seen) {
            self::note($notes, 'gtin_invalid');
        }

        return null;
    }

    /**
     * Color, size, material and pattern, from the combination first and then the features. A value
     * Google would refuse stays out, since the product is still listed without it.
     *
     * @return array<string, string>
     */
    public static function variantFacts(array $f): array
    {
        $facts = PageWriter::attributes((array) ($f['features'] ?? []));
        foreach ((array) ($f['options'] ?? []) as $option) {
            if (!empty($option['property'])) {
                $facts[$option['property']] = (string) $option['value'];
            }
        }
        $clean = [];
        foreach (['color' => 100, 'size' => 100, 'material' => 200, 'pattern' => 100] as $key => $max) {
            $value = Text::line((string) ($facts[$key] ?? ''));
            if ($key === 'color' || $key === 'material') {
                $value = (string) preg_replace('~\s*[,/]\s*~', '/', $value);
            }
            if ($value === '' || mb_strlen($value, 'UTF-8') > $max || ($key === 'color' && !Spec::isColor($value))) {
                continue;
            }
            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * Features as product details. Google's two pages disagree on the limit, 150 characters per
     * detail on one and 140 and 1,000 per field on the other, so a detail must meet both. A longer one
     * is left out and not cut, since half a specification is a wrong one.
     *
     * @return array<int, array{attribute_name: string, attribute_value: string}>
     */
    public static function details(array $features, array &$notes = []): array
    {
        $details = [];
        foreach ($features as $feature) {
            $name = Text::plain((string) ($feature['name'] ?? ''));
            $value = Text::plain((string) ($feature['value'] ?? ''));
            if ($name === '' || $value === '') {
                continue;
            }
            if (mb_strlen($name, 'UTF-8') > 140 || mb_strlen($name . $value, 'UTF-8') > 150) {
                self::note($notes, 'detail_too_long');
                continue;
            }
            $details[] = ['attribute_name' => $name, 'attribute_value' => $value];
            if (count($details) === 100) {
                break;
            }
        }

        return $details;
    }

    /** @return array<string, string>|null */
    private static function shipping(array $s, float $price): ?array
    {
        $rule = $s['shipping'] ?? null;
        if ($rule === null) {
            return null;
        }
        $fx = (float) ($s['fx'] ?? 1.0);
        $free = $rule['free_over'] !== null && $price >= (float) $rule['free_over'] * $fx;

        return [
            'country' => (string) $s['country'],
            'price' => Pricing::amount($free ? 0.0 : (float) $rule['rate'] * $fx, (string) $s['currency']),
            'min_handling_time' => (string) (int) $rule['handling_min'],
            'max_handling_time' => (string) (int) $rule['handling_max'],
            'min_transit_time' => (string) (int) $rule['transit_min'],
            'max_transit_time' => (string) (int) $rule['transit_max'],
        ];
    }

    private static function weight(float $weight, string $unit): ?string
    {
        $unit = strtolower(trim($unit));
        if ($weight <= 0 || !isset(self::WEIGHT_LIMITS[$unit]) || $weight > self::WEIGHT_LIMITS[$unit]) {
            return null;
        }

        return rtrim(rtrim(number_format($weight, 3, '.', ''), '0'), '.') . ' ' . ($unit === 'lbs' ? 'lb' : $unit);
    }

    private static function note(array &$notes, string $key): void
    {
        $notes[$key] = ($notes[$key] ?? 0) + 1;
    }
}
