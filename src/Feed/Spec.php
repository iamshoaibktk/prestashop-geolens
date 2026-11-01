<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Feed;

use Iamshoaibktk\Geolens\Schema\Gtin;

/**
 * Google's product data specification as rules, read on the date below. Every item is checked before
 * it is written, and tests/check-feed.php runs the same rules over a finished file. This is the
 * module's reading of the documentation. Only Merchant Center itself can say what it will accept.
 */
final class Spec
{
    public const CHECKED = '2026-11-01';

    public const SOURCE = 'https://support.google.com/merchants/answer/7052112';

    /** What a file item must have. A supplemental source needs only the ID that ties it to the primary source. */
    private const REQUIRED = ['id', 'title', 'description', 'link', 'image_link', 'availability', 'price'];

    /** Fields a supplemental source must never carry, because the primary source owns them. */
    private const PRIMARY_ONLY = ['title', 'description', 'link', 'image_link', 'additional_image_link', 'availability', 'availability_date', 'price', 'sale_price', 'sale_price_effective_date', 'shipping', 'shipping_weight', 'condition', 'brand', 'gtin', 'mpn', 'identifier_exists', 'product_type', 'multipack', 'is_bundle'];

    private const LENGTHS = ['id' => 50, 'title' => 150, 'description' => 5000, 'link' => 2000, 'image_link' => 2000, 'additional_image_link' => 2000, 'brand' => 70, 'mpn' => 70, 'product_type' => 750, 'item_group_id' => 50, 'item_group_title' => 150, 'color' => 100, 'size' => 100, 'material' => 200, 'pattern' => 100];

    private const REPEATS = ['additional_image_link' => 10, 'variant_option' => 30, 'product_detail' => 100];

    private const NOT_COLORS = ['multicolor', 'multi-color', 'multi color', 'various', 'variety', 'see image', "men's", "women's", 'n/a'];

    public static function isColor(string $color): bool
    {
        foreach (explode('/', $color) as $part) {
            if (!preg_match('/^[\p{L}\p{M}][\p{L}\p{M}\s\'\-]*$/u', $part) || mb_strlen($part, 'UTF-8') > 40 || in_array(mb_strtolower($part, 'UTF-8'), self::NOT_COLORS, true)) {
                return false;
            }
            // A single letter is a code, not a color. In CJK scripts one character is a word.
            if (mb_strlen($part, 'UTF-8') === 1 && !preg_match('/\p{Han}|\p{Hiragana}|\p{Katakana}|\p{Hangul}/u', $part)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array{0: string, 1: string|array<string, string>}> $attributes
     *
     * @return string[] what is wrong, in words. Empty means the item meets the rules.
     */
    public static function check(array $attributes, string $mode, string $target): array
    {
        $problems = [];
        $seen = [];
        foreach ($attributes as [$name, $value]) {
            $seen[$name] = ($seen[$name] ?? 0) + 1;
            if ($mode === Profiles::SUPPLEMENTAL && in_array($name, self::PRIMARY_ONLY, true)) {
                $problems[] = "{$name}: belongs to the primary source";
            }
            foreach (self::checkValue($name, $value, $target) as $problem) {
                $problems[] = $name . ': ' . $problem;
            }
        }
        foreach ($mode === Profiles::SUPPLEMENTAL ? ['id'] : self::REQUIRED as $name) {
            if (empty($seen[$name])) {
                $problems[] = "{$name}: required and missing";
            }
        }
        foreach ($seen as $name => $count) {
            if ($count > (self::REPEATS[$name] ?? 1)) {
                $problems[] = "{$name}: {$count} values";
            }
        }
        if (isset($seen['variant_option']) && !isset($seen['item_group_id'])) {
            $problems[] = 'variant_option: needs item_group_id';
        }
        $values = [];
        foreach ($attributes as [$name, $value]) {
            if (is_string($value)) {
                $values[$name] = $value;
            }
        }
        if (isset($values['sale_price'], $values['price']) && (float) $values['sale_price'] >= (float) $values['price']) {
            $problems[] = 'sale_price: not below price';
        }
        if (($values['identifier_exists'] ?? '') === 'no' && isset($values['gtin'])) {
            $problems[] = 'identifier_exists: "no" on an item with a GTIN';
        }

        return $problems;
    }

    /**
     * @param string|array<string, string> $value
     *
     * @return string[]
     */
    private static function checkValue(string $name, $value, string $target): array
    {
        if (is_array($value)) {
            return self::checkGroup($name, $value);
        }
        $problems = [];
        if ($value === '') {
            return ['empty'];
        }
        if (isset(self::LENGTHS[$name]) && mb_strlen($value, 'UTF-8') > self::LENGTHS[$name]) {
            $problems[] = 'longer than ' . self::LENGTHS[$name];
        }
        switch ($name) {
            case 'id':
            case 'title':
            case 'description':
            case 'brand':
            case 'mpn':
            case 'product_type':
            case 'item_group_title':
            case 'size':
            case 'material':
            case 'pattern':
                break;
            case 'link':
            case 'image_link':
            case 'additional_image_link':
                if (!preg_match('~^https?://[^\s]+$~i', $value)) {
                    $problems[] = 'not an http or https address';
                }
                break;
            case 'availability':
                $allowed = $target === Profiles::MICROSOFT ? ['in stock', 'out of stock', 'preorder'] : ['in_stock', 'out_of_stock', 'preorder', 'backorder'];
                if (!in_array($value, $allowed, true)) {
                    $problems[] = 'unknown value';
                }
                break;
            case 'availability_date':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(Z|[+-]\d{4})$/', $value)) {
                    $problems[] = 'not an ISO 8601 date';
                }
                break;
            case 'price':
            case 'sale_price':
                if (!preg_match('/^\d+\.\d{2} [A-Z]{3}$/', $value)) {
                    $problems[] = 'not an amount and a currency';
                } elseif ($name === 'price' && (float) $value <= 0) {
                    $problems[] = 'zero';
                }
                break;
            case 'sale_price_effective_date':
                if (strlen($value) > 51 || !preg_match('~^(\S+) / (\S+)$~', $value, $m) || strtotime($m[1]) >= strtotime($m[2])) {
                    $problems[] = 'not a start and an end';
                }
                break;
            case 'gtin':
                if (!Gtin::isValid($value)) {
                    $problems[] = 'check digit fails';
                } elseif (Gtin::isRestricted($value)) {
                    $problems[] = 'restricted range';
                }
                break;
            case 'identifier_exists':
                if ($value !== 'no' && $value !== 'yes') {
                    $problems[] = 'unknown value';
                }
                break;
            case 'condition':
                if (!in_array($value, ['new', 'used', 'refurbished'], true)) {
                    $problems[] = 'unknown value';
                }
                break;
            case 'item_group_id':
                if (!preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
                    $problems[] = 'not plain ASCII';
                }
                break;
            case 'color':
                if (!self::isColor($value)) {
                    $problems[] = 'not a color name';
                }
                break;
            case 'multipack':
                if ((int) $value < 2 || (string) (int) $value !== $value) {
                    $problems[] = 'not a count';
                }
                break;
            case 'is_bundle':
                if ($value !== 'yes') {
                    $problems[] = 'unknown value';
                }
                break;
            case 'shipping_weight':
                if (!preg_match('/^\d+(\.\d+)? (lb|oz|g|kg)$/', $value)) {
                    $problems[] = 'not a weight and a unit';
                }
                break;
            default:
                $problems[] = 'not in the specification';
        }

        return $problems;
    }

    /**
     * @param array<string, string> $value
     *
     * @return string[]
     */
    private static function checkGroup(string $name, array $value): array
    {
        $problems = [];
        switch ($name) {
            case 'product_detail':
                $attribute = $value['attribute_name'] ?? '';
                $text = $value['attribute_value'] ?? '';
                if ($attribute === '' || $text === '') {
                    $problems[] = 'needs a name and a value';
                }
                if (mb_strlen($attribute, 'UTF-8') > 140 || mb_strlen($attribute . $text, 'UTF-8') > 150) {
                    $problems[] = 'too long';
                }
                break;
            case 'variant_option':
                $option = $value['name'] ?? '';
                $text = $value['value'] ?? '';
                if ($option === '' || $text === '') {
                    $problems[] = 'needs a name and a value';
                }
                if (mb_strlen($option, 'UTF-8') > 250 || mb_strlen($text, 'UTF-8') > 250) {
                    $problems[] = 'too long';
                }
                break;
            case 'shipping':
                if (!preg_match('/^[A-Z]{2}$/', $value['country'] ?? '')) {
                    $problems[] = 'needs a country code';
                }
                if (isset($value['price']) && !preg_match('/^\d+\.\d{2} [A-Z]{3}$/', $value['price'])) {
                    $problems[] = 'price is not an amount and a currency';
                }
                foreach (['handling', 'transit'] as $kind) {
                    if ((int) ($value['min_' . $kind . '_time'] ?? 0) > (int) ($value['max_' . $kind . '_time'] ?? 0)) {
                        $problems[] = $kind . ' time starts after it ends';
                    }
                }
                break;
            default:
                $problems[] = 'not in the specification';
        }

        return $problems;
    }

    /**
     * The same rules over a finished file, read as a stream so its size does not matter. Also checks
     * what only shows across items: duplicate IDs, and a group that is not held together.
     *
     * @return array{items: int, invalid: int, problems: array<string, int>, examples: string[], groups: int}
     */
    public static function checkFile(string $path, string $mode, string $target): array
    {
        $reader = new \XMLReader();
        if (!$reader->open($path, 'UTF-8', LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            throw new \RuntimeException('Cannot read ' . $path);
        }
        $result = ['items' => 0, 'invalid' => 0, 'problems' => [], 'examples' => [], 'groups' => 0];
        $ids = [];
        $groups = [];
        $add = static function (string $problem, string $id) use (&$result) {
            $key = (string) preg_replace('/\d+/', 'N', $problem);
            $result['problems'][$key] = ($result['problems'][$key] ?? 0) + 1;
            if (count($result['examples']) < 20) {
                $result['examples'][] = $id . ' - ' . $problem;
            }
        };
        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'item') {
                continue;
            }
            $attributes = self::readItem((string) $reader->readOuterXml());
            ++$result['items'];
            $id = '';
            $group = null;
            $groupTitle = null;
            $options = [];
            foreach ($attributes as [$name, $value]) {
                if ($name === 'id') {
                    $id = (string) $value;
                } elseif ($name === 'item_group_id') {
                    $group = (string) $value;
                } elseif ($name === 'item_group_title') {
                    $groupTitle = (string) $value;
                } elseif ($name === 'variant_option' && is_array($value)) {
                    $options[$value['name'] ?? ''] = $value['value'] ?? '';
                }
            }
            $problems = self::check($attributes, $mode, $target);
            if (isset($ids[$id])) {
                $problems[] = 'id: used twice';
            }
            $ids[$id] = true;
            if ($group !== null) {
                $names = array_keys($options);
                sort($names);
                $signature = implode('|', $names);
                ksort($options);
                $combination = (string) json_encode($options);
                if (!isset($groups[$group])) {
                    $groups[$group] = ['title' => $groupTitle, 'names' => $signature, 'seen' => []];
                } else {
                    if ($groups[$group]['title'] !== $groupTitle) {
                        $problems[] = 'item_group_title: differs inside a group';
                    }
                    if ($groups[$group]['names'] !== $signature) {
                        $problems[] = 'variant_option: names differ inside a group';
                    }
                    if (isset($groups[$group]['seen'][$combination])) {
                        $problems[] = 'variant_option: two variants with the same values';
                    }
                }
                $groups[$group]['seen'][$combination] = true;
            }
            foreach ($problems as $problem) {
                $add($problem, $id);
            }
            if ($problems) {
                ++$result['invalid'];
            }
            $reader->next();
        }
        $reader->close();
        $result['groups'] = count($groups);

        return $result;
    }

    /** @return array<int, array{0: string, 1: string|array<string, string>}> */
    private static function readItem(string $xml): array
    {
        $item = new \SimpleXMLElement('<?xml version="1.0"?><r xmlns:g="' . Rss::NAMESPACE_URI . '">' . $xml . '</r>', LIBXML_NONET | LIBXML_PARSEHUGE);
        $attributes = [];
        foreach ($item->item->children(Rss::NAMESPACE_URI) as $name => $node) {
            $children = $node->children(Rss::NAMESPACE_URI);
            if ($children->count() > 0) {
                $group = [];
                foreach ($children as $child => $text) {
                    $group[$child] = (string) $text;
                }
                $attributes[] = [$name, $group];
            } else {
                $attributes[] = [$name, (string) $node];
            }
        }

        return $attributes;
    }
}
