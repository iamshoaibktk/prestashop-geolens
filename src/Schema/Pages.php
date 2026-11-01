<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Schema;

use Iamshoaibktk\Geolens\Support\Http;
use Iamshoaibktk\Geolens\Support\Store;

/** Fetches real pages of the shop as a crawler would and runs PageAudit on them. */
final class Pages
{
    /** @return array<int, array{key: string, url: string, product: bool}> the pages worth checking on this shop */
    public static function samples(\Context $context): array
    {
        $db = \Db::getInstance();
        $p = _DB_PREFIX_;
        $idShop = (int) $context->shop->id;
        $link = $context->link;
        $samples = [['key' => 'home', 'url' => (string) $link->getPageLink('index'), 'product' => false]];
        $idCategory = (int) $db->getValue("SELECT cp.`id_category` FROM `{$p}category_product` cp
            INNER JOIN `{$p}category_shop` cs ON cs.`id_category` = cp.`id_category` AND cs.`id_shop` = {$idShop}
            INNER JOIN `{$p}category` c ON c.`id_category` = cp.`id_category` AND c.`active` = 1 AND c.`level_depth` > 1
            WHERE c.`id_category` != " . (int) \Configuration::get('PS_HOME_CATEGORY') . ' ORDER BY c.`level_depth` DESC, cp.`id_category`');
        if ($idCategory) {
            $samples[] = ['key' => 'category', 'url' => (string) $link->getCategoryLink($idCategory), 'product' => false];
        }
        $scope = "ps.`id_shop` = {$idShop} AND ps.`active` = 1 AND ps.`visibility` != 'none'";
        $simple = (int) $db->getValue("SELECT ps.`id_product` FROM `{$p}product_shop` ps WHERE {$scope} AND ps.`cache_default_attribute` = 0 ORDER BY ps.`id_product`");
        if ($simple) {
            $samples[] = ['key' => 'product', 'url' => strtok((string) $link->getProductLink($simple), '#'), 'product' => true];
        }
        $variants = (int) $db->getValue("SELECT ps.`id_product` FROM `{$p}product_shop` ps WHERE {$scope} AND ps.`cache_default_attribute` > 0 ORDER BY ps.`id_product`");
        if ($variants) {
            $samples[] = ['key' => 'variants', 'url' => strtok((string) $link->getProductLink($variants), '#'), 'product' => true];
        }

        return $samples;
    }

    /** Only pages of this shop, so the box in the back office cannot be used to fetch anything else. */
    public static function isOwnUrl(string $url, \Context $context): bool
    {
        $parts = parse_url($url);
        if (!isset($parts['host'], $parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }
        $host = strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return in_array($host, [strtolower((string) $context->shop->domain), strtolower((string) $context->shop->domain_ssl)], true);
    }

    /** @return array{key: string, url: string, status: int, ms: int, bytes: int, problems: array, types: array<string, int>, json_ld: string} */
    public static function check(string $url, bool $isProduct, int $idShop, string $key = 'custom'): array
    {
        $response = Http::request('GET', $url, ['X-GeoLens-Check: ' . \Configuration::getGlobalValue('GEOLENS_TOKEN')], null, 20,
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36; compatible; OAI-SearchBot/1.4; +https://openai.com/searchbot');
        $audit = $response['status'] === 200
            ? PageAudit::run($response['body'], $response['headers'], $isProduct, date('Y-m-d'), (bool) \Configuration::get('GEOLENS_SHOW_FACTS'))
            : ['problems' => [['code' => 'unreachable', 'level' => PageAudit::ERROR, 'detail' => (string) $response['status']]], 'types' => []];
        $result = [
            'key' => $key,
            'url' => $url,
            'status' => $response['status'],
            'ms' => $response['ms'],
            'bytes' => strlen($response['body']),
            'problems' => $audit['problems'],
            'types' => $audit['types'],
            'json_ld' => self::productJson($response['body']),
        ];
        $all = (array) Store::get('page_results', $idShop);
        $all[$key] = array_diff_key($result, ['json_ld' => true]);
        Store::set('page_results', $all, $idShop);

        return $result;
    }

    /** The product's markup, formatted so the merchant can read what crawlers read. */
    private static function productJson(string $html): string
    {
        foreach (JsonLd::blocks($html) as $block) {
            foreach (JsonLd::nodes((array) $block['data']) as $node) {
                if (JsonLd::hasType($node, 'Product') || JsonLd::hasType($node, 'ProductGroup')) {
                    return (string) json_encode($block['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
            }
        }

        return '';
    }
}
