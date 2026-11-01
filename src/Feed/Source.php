<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Feed;

use Iamshoaibktk\Geolens\Schema\PageWriter;

/**
 * Reads the catalogue a batch at a time, from a product ID onwards, with one query per kind of fact
 * and never one per product. The scope is the same as the catalogue audit's, so the two agree.
 */
final class Source
{
    private const SCOPE = 'ps.`id_shop` = %d AND ps.`active` = 1 AND ps.`visibility` != "none"';

    /** A description this long is well past the 5,000 characters a feed can carry, even with its markup. */
    private const TEXT_BYTES = 60000;

    private int $idShop;
    private int $idLang;
    private int $idGroup;
    private bool $light;
    private \Db $db;
    private string $p = _DB_PREFIX_;
    /** @var array<int, array{parent: int, depth: int, name: string}>|null */
    private ?array $categories = null;
    /** @var array<int, string> */
    private array $paths = [];

    /** @param bool $light a supplemental source needs no text, images or stock, so they are not read */
    public function __construct(int $idShop, int $idLang, int $idGroup, bool $light = false)
    {
        $this->idShop = $idShop;
        $this->idLang = $idLang;
        $this->idGroup = $idGroup;
        $this->light = $light;
        $this->db = \Db::getInstance();
    }

    public function total(): int
    {
        return (int) $this->db->getValue('SELECT COUNT(*) FROM `' . $this->p . 'product_shop` ps WHERE ' . sprintf(self::SCOPE, $this->idShop));
    }

    /**
     * @return array{products: array<int, array>, cursor: int, done: bool} a product with thousands of
     *         combinations ends its batch early, so memory stays flat whatever the catalogue holds
     */
    public function batch(int $after, int $maxProducts, int $maxItems): array
    {
        $rows = $this->products($after, $maxProducts);
        if (!$rows) {
            return ['products' => [], 'cursor' => $after, 'done' => true];
        }
        $done = count($rows) < $maxProducts;
        $counts = $this->combinationCounts(array_map('intval', array_column($rows, 'id_product')));
        $items = 0;
        foreach ($rows as $i => $row) {
            $items += max(1, $counts[(int) $row['id_product']] ?? 0);
            if ($items >= $maxItems && $i < count($rows) - 1) {
                $rows = array_slice($rows, 0, $i + 1);
                $done = false;
                break;
            }
        }

        $ids = array_map('intval', array_column($rows, 'id_product'));
        $features = $this->features($ids);
        $combinations = $counts ? $this->combinations(array_keys($counts)) : [];
        $public = $this->light ? [] : $this->visibleToVisitors($ids);
        $images = $this->light ? [] : $this->images($ids);
        $packs = $this->light ? [] : $this->packs($rows);

        $products = [];
        foreach ($rows as $row) {
            $id = (int) $row['id_product'];
            $row['features'] = $features[$id] ?? [];
            $row['combinations'] = $combinations[$id] ?? [];
            $row['public'] = $public === null || isset($public[$id]);
            $row['images'] = $images[$id] ?? [];
            $row['pack'] = $packs[$id] ?? null;
            $row['product_type'] = $this->light ? '' : $this->path((int) $row['id_category_default']);
            $products[$id] = $row;
        }

        return ['products' => $products, 'cursor' => (int) $rows[count($rows) - 1]['id_product'], 'done' => $done];
    }

    private function products(int $after, int $limit): array
    {
        $scope = sprintf(self::SCOPE, $this->idShop);
        if ($this->light) {
            $rows = $this->db->executeS("SELECT ps.`id_product`, pl.`name`
                FROM `{$this->p}product_shop` ps
                LEFT JOIN `{$this->p}product_lang` pl ON pl.`id_product` = ps.`id_product` AND pl.`id_shop` = ps.`id_shop` AND pl.`id_lang` = {$this->idLang}
                WHERE {$scope} AND ps.`id_product` > {$after}
                ORDER BY ps.`id_product` LIMIT " . $limit);

            return is_array($rows) ? $rows : [];
        }
        $stock = \StockAvailable::addSqlShopRestriction(null, $this->idShop, 'sa');
        $rows = $this->db->executeS("SELECT ps.`id_product`, ps.`id_category_default`, ps.`id_tax_rules_group`, ps.`condition`, ps.`available_for_order`, ps.`show_price`, ps.`available_date`,
                p.`ean13`, p.`upc`, p.`isbn`, p.`mpn`, p.`id_manufacturer`, p.`is_virtual`, p.`cache_is_pack`, p.`weight`,
                pl.`name`, pl.`link_rewrite`, LEFT(pl.`description`, " . self::TEXT_BYTES . ") AS description, LEFT(pl.`description_short`, " . self::TEXT_BYTES . ") AS description_short,
                m.`name` AS brand, cl.`link_rewrite` AS category_rewrite, sa.`quantity`, sa.`out_of_stock`
            FROM `{$this->p}product_shop` ps
            INNER JOIN `{$this->p}product` p ON p.`id_product` = ps.`id_product`
            LEFT JOIN `{$this->p}product_lang` pl ON pl.`id_product` = ps.`id_product` AND pl.`id_shop` = ps.`id_shop` AND pl.`id_lang` = {$this->idLang}
            LEFT JOIN `{$this->p}manufacturer` m ON m.`id_manufacturer` = p.`id_manufacturer` AND m.`active` = 1
            LEFT JOIN `{$this->p}category_lang` cl ON cl.`id_category` = ps.`id_category_default` AND cl.`id_shop` = ps.`id_shop` AND cl.`id_lang` = {$this->idLang}
            LEFT JOIN `{$this->p}stock_available` sa ON sa.`id_product` = ps.`id_product` AND sa.`id_product_attribute` = 0 {$stock}
            WHERE {$scope} AND ps.`id_product` > {$after}
            ORDER BY ps.`id_product` LIMIT " . $limit);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param int[] $ids
     *
     * @return array<int, int>
     */
    private function combinationCounts(array $ids): array
    {
        if (!\Combination::isFeatureActive()) {
            return [];
        }
        $rows = $this->db->executeS("SELECT `id_product`, COUNT(*) AS n FROM `{$this->p}product_attribute_shop`
            WHERE `id_shop` = {$this->idShop} AND `id_product` IN (" . implode(',', $ids) . ') GROUP BY `id_product`');
        $counts = [];
        foreach ((array) $rows as $row) {
            $counts[(int) $row['id_product']] = (int) $row['n'];
        }

        return $counts;
    }

    /**
     * @param int[] $ids
     *
     * @return array<int, array<int, array{name: string, value: string}>> by product, with several values of one feature joined
     */
    private function features(array $ids): array
    {
        if (!\Feature::isFeatureActive()) {
            return [];
        }
        $rows = $this->db->executeS("SELECT fp.`id_product`, fp.`id_feature`, fl.`name`, fvl.`value`
            FROM `{$this->p}feature_product` fp
            INNER JOIN `{$this->p}feature` f ON f.`id_feature` = fp.`id_feature`
            INNER JOIN `{$this->p}feature_shop` fs ON fs.`id_feature` = f.`id_feature` AND fs.`id_shop` = {$this->idShop}
            INNER JOIN `{$this->p}feature_lang` fl ON fl.`id_feature` = f.`id_feature` AND fl.`id_lang` = {$this->idLang}
            INNER JOIN `{$this->p}feature_value_lang` fvl ON fvl.`id_feature_value` = fp.`id_feature_value` AND fvl.`id_lang` = {$this->idLang}
            WHERE fp.`id_product` IN (" . implode(',', $ids) . ')
            ORDER BY fp.`id_product`, f.`position`, fp.`id_feature_value`');
        $grouped = [];
        foreach ((array) $rows as $row) {
            $id = (int) $row['id_product'];
            $feature = (int) $row['id_feature'];
            if (isset($grouped[$id][$feature])) {
                $grouped[$id][$feature]['value'] .= ', ' . $row['value'];
            } else {
                $grouped[$id][$feature] = ['name' => (string) $row['name'], 'value' => (string) $row['value']];
            }
        }

        return array_map('array_values', $grouped);
    }

    /**
     * @param int[] $ids products known to have combinations
     *
     * @return array<int, array<int, array>> id_product => id_product_attribute => combination
     */
    private function combinations(array $ids): array
    {
        $list = implode(',', $ids);
        $stock = \StockAvailable::addSqlShopRestriction(null, $this->idShop, 'sa');
        // With no name in this language, two variants would look identical, so the default language's stands in.
        $fallback = (int) \Configuration::get('PS_LANG_DEFAULT', null, null, $this->idShop);
        $rows = $this->db->executeS("SELECT pa.`id_product`, pa.`id_product_attribute`, pa.`ean13`, pa.`upc`, pa.`isbn`, pa.`mpn`,
                pas.`weight`, pas.`available_date`, sa.`quantity`,
                ag.`is_color_group`, IFNULL(agl.`name`, agd.`name`) AS group_name, IFNULL(agl.`public_name`, agd.`public_name`) AS group_public,
                IFNULL(al.`name`, ald.`name`) AS attribute_name, (al.`name` IS NULL OR agl.`name` IS NULL) AS untranslated
            FROM `{$this->p}product_attribute` pa
            INNER JOIN `{$this->p}product_attribute_shop` pas ON pas.`id_product_attribute` = pa.`id_product_attribute` AND pas.`id_shop` = {$this->idShop}
            LEFT JOIN `{$this->p}stock_available` sa ON sa.`id_product` = pa.`id_product` AND sa.`id_product_attribute` = pa.`id_product_attribute` {$stock}
            LEFT JOIN `{$this->p}product_attribute_combination` pac ON pac.`id_product_attribute` = pa.`id_product_attribute`
            LEFT JOIN `{$this->p}attribute` a ON a.`id_attribute` = pac.`id_attribute`
            LEFT JOIN `{$this->p}attribute_group` ag ON ag.`id_attribute_group` = a.`id_attribute_group`
            LEFT JOIN `{$this->p}attribute_lang` al ON al.`id_attribute` = a.`id_attribute` AND al.`id_lang` = {$this->idLang}
            LEFT JOIN `{$this->p}attribute_group_lang` agl ON agl.`id_attribute_group` = ag.`id_attribute_group` AND agl.`id_lang` = {$this->idLang}
            LEFT JOIN `{$this->p}attribute_lang` ald ON ald.`id_attribute` = a.`id_attribute` AND ald.`id_lang` = {$fallback}
            LEFT JOIN `{$this->p}attribute_group_lang` agd ON agd.`id_attribute_group` = ag.`id_attribute_group` AND agd.`id_lang` = {$fallback}
            WHERE pa.`id_product` IN ({$list})
            ORDER BY pa.`id_product`, pa.`id_product_attribute`, ag.`position`, a.`position`");
        $combinations = [];
        foreach ((array) $rows as $row) {
            $id = (int) $row['id_product'];
            $ipa = (int) $row['id_product_attribute'];
            if (!isset($combinations[$id][$ipa])) {
                $combinations[$id][$ipa] = [
                    'ean13' => (string) $row['ean13'],
                    'upc' => (string) $row['upc'],
                    'isbn' => (string) $row['isbn'],
                    'mpn' => (string) $row['mpn'],
                    'weight' => (float) $row['weight'],
                    'available_date' => $row['available_date'],
                    'quantity' => (int) $row['quantity'],
                    'options' => [],
                    'images' => [],
                ];
            }
            if ((string) $row['attribute_name'] !== '') {
                $group = (string) ($row['group_public'] ?: $row['group_name']);
                $combinations[$id][$ipa]['options'][] = [
                    'group' => $group,
                    'value' => (string) $row['attribute_name'],
                    'untranslated' => !empty($row['untranslated']),
                    'property' => !empty($row['is_color_group']) ? 'color' : (PageWriter::variantProperty($group) ?? PageWriter::variantProperty((string) $row['group_name'])),
                ];
            }
        }
        if (!$this->light) {
            $this->addCombinationImages($combinations, $list);
        }

        return $combinations;
    }

    private function addCombinationImages(array &$combinations, string $list): void
    {
        $rows = $this->db->executeS("SELECT pa.`id_product`, pai.`id_product_attribute`, pai.`id_image`
            FROM `{$this->p}product_attribute` pa
            INNER JOIN `{$this->p}product_attribute_image` pai ON pai.`id_product_attribute` = pa.`id_product_attribute`
            INNER JOIN `{$this->p}image_shop` ish ON ish.`id_image` = pai.`id_image` AND ish.`id_shop` = {$this->idShop}
            INNER JOIN `{$this->p}image` i ON i.`id_image` = pai.`id_image`
            WHERE pa.`id_product` IN ({$list})
            ORDER BY i.`position`");
        foreach ((array) $rows as $row) {
            $id = (int) $row['id_product'];
            $ipa = (int) $row['id_product_attribute'];
            if (isset($combinations[$id][$ipa])) {
                $combinations[$id][$ipa]['images'][] = (int) $row['id_image'];
            }
        }
    }

    /**
     * A product whose categories a visitor cannot open answers with an error, and Merchant Center
     * would disapprove it for that.
     *
     * @param int[] $ids
     *
     * @return array<int, bool>|null null when PrestaShop does not check at all
     */
    private function visibleToVisitors(array $ids): ?array
    {
        if (!\Group::isFeatureActive()) {
            return null;
        }
        $rows = $this->db->executeS("SELECT DISTINCT cp.`id_product` FROM `{$this->p}category_product` cp
            INNER JOIN `{$this->p}category_group` cg ON cg.`id_category` = cp.`id_category` AND cg.`id_group` = {$this->idGroup}
            WHERE cp.`id_product` IN (" . implode(',', $ids) . ')');
        $visible = [];
        foreach ((array) $rows as $row) {
            $visible[(int) $row['id_product']] = true;
        }

        return $visible;
    }

    /**
     * @param int[] $ids
     *
     * @return array<int, int[]> image IDs by product, the cover first
     */
    private function images(array $ids): array
    {
        $rows = $this->db->executeS("SELECT i.`id_product`, i.`id_image` FROM `{$this->p}image` i
            INNER JOIN `{$this->p}image_shop` ish ON ish.`id_image` = i.`id_image` AND ish.`id_shop` = {$this->idShop}
            WHERE i.`id_product` IN (" . implode(',', $ids) . ')
            ORDER BY i.`id_product`, ish.`cover` DESC, i.`position`');
        $images = [];
        foreach ((array) $rows as $row) {
            $images[(int) $row['id_product']][] = (int) $row['id_image'];
        }

        return $images;
    }

    /** @return array<int, array{lines: int, units: int}> */
    private function packs(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if (!empty($row['cache_is_pack'])) {
                $ids[] = (int) $row['id_product'];
            }
        }
        if (!$ids) {
            return [];
        }
        $found = $this->db->executeS("SELECT `id_product_pack`, COUNT(*) AS `lines`, SUM(`quantity`) AS units FROM `{$this->p}pack`
            WHERE `id_product_pack` IN (" . implode(',', $ids) . ') GROUP BY `id_product_pack`');
        $packs = [];
        foreach ((array) $found as $row) {
            $packs[(int) $row['id_product_pack']] = ['lines' => (int) $row['lines'], 'units' => (int) $row['units']];
        }

        return $packs;
    }

    /** The category path a shopper would follow, without the root and home categories. */
    private function path(int $idCategory): string
    {
        if (isset($this->paths[$idCategory])) {
            return $this->paths[$idCategory];
        }
        if ($this->categories === null) {
            $this->categories = [];
            $rows = $this->db->executeS("SELECT c.`id_category`, c.`id_parent`, c.`level_depth`, cl.`name` FROM `{$this->p}category` c
                INNER JOIN `{$this->p}category_lang` cl ON cl.`id_category` = c.`id_category` AND cl.`id_shop` = {$this->idShop} AND cl.`id_lang` = {$this->idLang}");
            foreach ((array) $rows as $row) {
                $this->categories[(int) $row['id_category']] = ['parent' => (int) $row['id_parent'], 'depth' => (int) $row['level_depth'], 'name' => (string) $row['name']];
            }
        }
        $names = [];
        $id = $idCategory;
        for ($i = 0; $i < 30 && isset($this->categories[$id]) && $this->categories[$id]['depth'] >= 2; ++$i) {
            array_unshift($names, $this->categories[$id]['name']);
            $id = $this->categories[$id]['parent'];
        }

        return $this->paths[$idCategory] = implode(' > ', $names);
    }
}
