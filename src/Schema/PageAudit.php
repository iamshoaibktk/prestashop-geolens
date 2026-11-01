<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Schema;

/**
 * What a crawler that runs no JavaScript gets from one page. Input is the raw response, output is
 * a list of problems by code, so the wording stays in the back office and this stays testable.
 */
final class PageAudit
{
    /** Googlebot stops reading at 2 MB. Warn before a page gets there. */
    public const SIZE_WARNING = 1500000;

    /** Bing says noarchive and nocache lose their AI meaning on this date. */
    public const ARCHIVE_RULE_ENDS = '2027-03-01';

    public const ERROR = 'error';
    public const WARNING = 'warning';

    /**
     * @param array<string, string> $headers lower case names
     *
     * @return array{problems: array<int, array{code: string, level: string, detail?: string}>, types: array<string, int>}
     */
    public static function run(string $html, array $headers, bool $isProduct, string $today, bool $expectFacts = false): array
    {
        $problems = [];
        $add = static function (string $code, string $level, string $detail = '') use (&$problems) {
            $problems[] = ['code' => $code, 'level' => $level] + ($detail === '' ? [] : ['detail' => $detail]);
        };

        $directives = strtolower(self::metaRobots($html) . ',' . ($headers['x-robots-tag'] ?? ''));
        if (self::has($directives, 'noindex') || self::has($directives, 'none')) {
            $add('noindex', self::ERROR);
        }
        if (self::has($directives, 'nosnippet') || preg_match('/max-snippet\s*:\s*0\b/', $directives)) {
            $add('nosnippet', self::ERROR);
        }
        if ($today < self::ARCHIVE_RULE_ENDS) {
            if (self::has($directives, 'noarchive')) {
                $add('noarchive', self::WARNING);
            }
            if (self::has($directives, 'nocache')) {
                $add('nocache', self::WARNING);
            }
        }

        if (strlen($html) > self::SIZE_WARNING) {
            $add('too_big', self::WARNING, (string) round(strlen($html) / 1048576, 1));
        }
        if (!preg_match('~<link\b[^>]*\brel\s*=\s*["\']canonical["\']~i', $html)) {
            $add('no_canonical', self::WARNING);
        }

        $types = [];
        $invalid = 0;
        $products = [];
        foreach (JsonLd::blocks($html) as $block) {
            if ($block['data'] === null) {
                ++$invalid;
                continue;
            }
            foreach (JsonLd::nodes($block['data']) as $node) {
                foreach ((array) ($node['@type'] ?? []) as $type) {
                    if (is_string($type)) {
                        $name = (string) preg_replace('~^https?://schema\.org/~i', '', $type);
                        $types[$name] = ($types[$name] ?? 0) + 1;
                    }
                }
                if (JsonLd::hasType($node, 'Product') || JsonLd::hasType($node, 'ProductGroup')) {
                    $products[] = $node;
                }
            }
        }
        if ($invalid > 0) {
            $add('ld_invalid', self::ERROR, (string) $invalid);
        }
        if (count($products) > 1) {
            $add('ld_duplicate', self::ERROR, (string) count($products));
        }
        foreach (['Organization' => 'ld_duplicate_org'] as $type => $code) {
            if (($types[$type] ?? 0) > 1) {
                $add($code, self::WARNING, (string) $types[$type]);
            }
        }

        if ($isProduct) {
            self::product($html, $products, $add);
            // A page builder may not print the hook the block sits in. The markup would then state
            // delivery and returns that the visitor cannot read, which is what search engines object to.
            if ($expectFacts && $products && self::statesFacts($products[0]) && strpos($html, 'geolens-facts') === false) {
                $add('facts_not_visible', self::WARNING);
            }
        }

        return ['problems' => $problems, 'types' => $types];
    }

    private static function product(string $html, array $products, callable $add): void
    {
        if (!$products) {
            $add('ld_no_product', self::ERROR);

            return;
        }
        if (preg_match('~itemtype\s*=\s*["\']https?://schema\.org/Product["\']~i', $html)) {
            $add('microdata_product', self::WARNING);
        }

        $node = $products[0];
        $offers = self::offers($node);
        if (!$offers) {
            $add('no_offer', self::WARNING);
        }
        $text = self::visibleText($html);
        if (!empty($node['name']) && stripos($text, html_entity_decode((string) $node['name'])) === false) {
            $add('name_not_visible', self::ERROR);
        }
        foreach ($offers as $offer) {
            if (empty($offer['availability'])) {
                $add('no_availability', self::WARNING);
                break;
            }
        }
        // In markup but not in the page: engines and Merchant Center both compare the two.
        if ($offers && isset($offers[0]['price']) && !self::priceVisible((string) $offers[0]['price'], $text)) {
            $add('price_not_visible', self::ERROR, (string) $offers[0]['price']);
        }
        if (isset($offers[0]['priceValidUntil'])) {
            $days = (strtotime((string) $offers[0]['priceValidUntil']) - time()) / 86400;
            if ($days > 13 && $days < 16) {
                $add('rolling_date', self::WARNING);
            }
        }
    }

    private static function statesFacts(array $node): bool
    {
        foreach (self::offers($node) as $offer) {
            if (isset($offer['shippingDetails']) || isset($offer['hasMerchantReturnPolicy'])) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, array> offers of the product or of its variants */
    private static function offers(array $node): array
    {
        $offers = [];
        foreach ((array) ($node['hasVariant'] ?? []) as $variant) {
            if (isset($variant['offers']) && is_array($variant['offers'])) {
                $offers[] = $variant['offers'];
            }
        }
        if ($offers) {
            return $offers;
        }
        if (isset($node['offers']) && is_array($node['offers'])) {
            return array_is_list($node['offers']) ? $node['offers'] : [$node['offers']];
        }

        return [];
    }

    /** The digits of the price, in any format the page uses: 19.12, 19,12 or 1 234,50. */
    public static function priceVisible(string $price, string $text): bool
    {
        $number = (float) str_replace(',', '.', $price);
        $digits = number_format($number, 2, '', '');
        $whole = number_format($number, 0, '', '');
        $flat = (string) preg_replace('/(?<=\d)[\s\x{00A0}\x{202F}.,\'’](?=\d)/u', '', $text);

        return (bool) preg_match('/(?<!\d)' . preg_quote($digits, '/') . '(?!\d)/', $flat)
            || (abs($number - round($number)) < 0.001 && preg_match('/(?<!\d)' . preg_quote($whole, '/') . '(?!\d)/', $flat));
    }

    public static function visibleText(string $html): string
    {
        $body = (string) preg_replace('~<(script|style|noscript|template)\b.*?</\1>~is', ' ', $html);
        $body = (string) preg_replace('~<head\b.*?</head>~is', ' ', $body);

        return trim(html_entity_decode((string) preg_replace('/\s+/u', ' ', strip_tags($body)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function metaRobots(string $html): string
    {
        $values = [];
        if (preg_match_all('~<meta\b[^>]*>~i', $html, $tags)) {
            foreach ($tags[0] as $tag) {
                if (preg_match('~\bname\s*=\s*["\'](robots|googlebot|bingbot)["\']~i', $tag)
                    && preg_match('~\bcontent\s*=\s*["\']([^"\']*)["\']~i', $tag, $content)) {
                    $values[] = $content[1];
                }
            }
        }

        return implode(',', $values);
    }

    private static function has(string $directives, string $word): bool
    {
        return (bool) preg_match('/(^|[\s,])' . preg_quote($word, '/') . '($|[\s,])/', $directives);
    }
}
