<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Catalog;

use Iamshoaibktk\Geolens\Schema\Gtin;

/**
 * Which facts each product really has, fact by fact. Stated price and specs are the best supported
 * on-page signal, and these are the same fields Merchant Center asks for. There is no blended
 * score on purpose: a score measures a checklist, not visibility.
 */
final class Audit
{
    public const FACTS = ['price', 'brand', 'gtin', 'gtin_valid', 'reference', 'short', 'long', 'features', 'image', 'alt', 'category'];

    /** Merchant Center requires this from 31 January 2027. */
    public const MIN_IMAGE_SIZE = 500;

    private const SCOPE = 'ps.`id_shop` = %d AND ps.`active` = 1 AND ps.`visibility` != "none"';

    private int $idShop;
    private int $idLang;
    private string $p = _DB_PREFIX_;
    private \Db $db;

    public function __construct(int $idShop, int $idLang)
    {
        $this->idShop = $idShop;
        $this->idLang = $idLang;
        $this->db = \Db::getInstance();
    }

    private function scope(): string
    {
        return sprintf(self::SCOPE, $this->idShop);
    }

    private function home(): int
    {
        return (int) \Configuration::get('PS_HOME_CATEGORY', null, null, $this->idShop);
    }

    /** @return array{total: int, facts: array<string, int>, image_sample: array{checked: int, small: int}, in_stock: int} counts of products that have each fact */
    public function run(): array
    {
        $scope = $this->scope();
        $base = $this->db->getRow("SELECT COUNT(*) AS total,
                SUM(ps.`price` > 0) AS price,
                SUM(p.`id_manufacturer` > 0) AS brand,
                SUM(p.`reference` != '' OR p.`mpn` != '') AS reference,
                SUM(LENGTH(IFNULL(pl.`description_short`, '')) > 0) AS short,
                SUM(LENGTH(IFNULL(pl.`description`, '')) >= 200) AS `long`,
                SUM(ps.`id_category_default` != {$this->home()}) AS category
            FROM `{$this->p}product_shop` ps
            INNER JOIN `{$this->p}product` p ON p.`id_product` = ps.`id_product`
            LEFT JOIN `{$this->p}product_lang` pl ON pl.`id_product` = ps.`id_product` AND pl.`id_shop` = ps.`id_shop` AND pl.`id_lang` = {$this->idLang}
            WHERE {$scope}");

        $gtin = (int) $this->db->getValue("SELECT COUNT(*) FROM (
                SELECT p.`id_product` FROM `{$this->p}product` p
                    INNER JOIN `{$this->p}product_shop` ps ON ps.`id_product` = p.`id_product`
                    WHERE {$scope} AND (p.`ean13` != '' OR p.`upc` != '' OR p.`isbn` != '')
                UNION
                SELECT pa.`id_product` FROM `{$this->p}product_attribute` pa
                    INNER JOIN `{$this->p}product_shop` ps ON ps.`id_product` = pa.`id_product`
                    WHERE {$scope} AND (pa.`ean13` != '' OR pa.`upc` != '' OR pa.`isbn` != '')
            ) t");
        $features = (int) $this->db->getValue("SELECT COUNT(DISTINCT fp.`id_product`) FROM `{$this->p}feature_product` fp
            INNER JOIN `{$this->p}product_shop` ps ON ps.`id_product` = fp.`id_product` WHERE {$scope}");
        $image = (int) $this->db->getValue("SELECT COUNT(*) FROM `{$this->p}image_shop` i
            INNER JOIN `{$this->p}product_shop` ps ON ps.`id_product` = i.`id_product` AND ps.`id_shop` = i.`id_shop`
            WHERE {$scope} AND i.`cover` = 1");
        $alt = (int) $this->db->getValue("SELECT COUNT(*) FROM `{$this->p}image_shop` i
            INNER JOIN `{$this->p}product_shop` ps ON ps.`id_product` = i.`id_product` AND ps.`id_shop` = i.`id_shop`
            INNER JOIN `{$this->p}image_lang` il ON il.`id_image` = i.`id_image` AND il.`id_lang` = {$this->idLang}
            WHERE {$scope} AND i.`cover` = 1 AND il.`legend` IS NOT NULL AND il.`legend` != ''");
        $inStock = (int) $this->db->getValue("SELECT COUNT(*) FROM `{$this->p}stock_available` sa
            INNER JOIN `{$this->p}product_shop` ps ON ps.`id_product` = sa.`id_product`
            WHERE {$scope} AND sa.`id_product_attribute` = 0 AND sa.`quantity` > 0" . \StockAvailable::addSqlShopRestriction(null, $this->idShop, 'sa'));

        return [
            'total' => (int) $base['total'],
            'facts' => [
                'price' => (int) $base['price'],
                'brand' => (int) $base['brand'],
                'gtin' => $gtin,
                'gtin_valid' => max(0, $gtin - $this->countInvalidGtins()),
                'reference' => (int) $base['reference'],
                'short' => (int) $base['short'],
                'long' => (int) $base['long'],
                'features' => $features,
                'image' => $image,
                'alt' => $alt,
                'category' => (int) $base['category'],
            ],
            'in_stock' => $inStock,
            'reviews' => $this->countReviewed(),
            'image_sample' => $this->sampleImages(),
        ];
    }

    /** MySQL cannot check a GS1 digit in a readable way, so the codes are read as a stream, not loaded together. */
    private function countInvalidGtins(): int
    {
        $invalid = 0;
        $result = $this->db->query("SELECT p.`ean13`, p.`upc` FROM `{$this->p}product` p
            INNER JOIN `{$this->p}product_shop` ps ON ps.`id_product` = p.`id_product`
            WHERE {$this->scope()} AND (p.`ean13` != '' OR p.`upc` != '')");
        while ($result && ($row = $this->db->nextRow($result))) {
            if (($row['ean13'] !== '' && !Gtin::isValid((string) $row['ean13'])) || ($row['upc'] !== '' && !Gtin::isValid((string) $row['upc']))) {
                ++$invalid;
            }
        }

        return $invalid;
    }

    private function countReviewed(): ?int
    {
        if (!\Module::isEnabled('productcomments')) {
            return null;
        }

        return (int) $this->db->getValue("SELECT COUNT(DISTINCT pc.`id_product`) FROM `{$this->p}product_comment` pc
            INNER JOIN `{$this->p}product_shop` ps ON ps.`id_product` = pc.`id_product`
            WHERE {$this->scope()} AND pc.`validate` = 1 AND pc.`deleted` = 0");
    }

    /**
     * Reading every image of a big catalogue would take minutes, and a sample of 200 answers the
     * question: are your photos big enough?
     *
     * @return array{checked: int, small: int}
     */
    private function sampleImages(): array
    {
        $rows = $this->db->executeS("SELECT i.`id_image` FROM `{$this->p}image_shop` i
            INNER JOIN `{$this->p}product_shop` ps ON ps.`id_product` = i.`id_product` AND ps.`id_shop` = i.`id_shop`
            WHERE {$this->scope()} AND i.`cover` = 1 ORDER BY RAND() LIMIT 200");
        $checked = 0;
        $small = 0;
        foreach ((array) $rows as $row) {
            $file = _PS_PRODUCT_IMG_DIR_ . \Image::getImgFolderStatic((int) $row['id_image']) . (int) $row['id_image'] . '.jpg';
            $size = is_file($file) ? @getimagesize($file) : false;
            if ($size) {
                ++$checked;
                if ($size[0] < self::MIN_IMAGE_SIZE || $size[1] < self::MIN_IMAGE_SIZE) {
                    ++$small;
                }
            }
        }

        return ['checked' => $checked, 'small' => $small];
    }

    /**
     * Products missing one fact, 50 at a time from a product ID.
     *
     * @return array{rows: array<int, array{id_product: int, name: string, reference: string}>, next: int}
     */
    public function missing(string $fact, int $after): array
    {
        $limit = 50;
        if ($fact === 'gtin_valid') {
            return $this->invalidGtins($after, $limit);
        }
        $where = $this->missingCondition($fact);
        if ($where === null) {
            return ['rows' => [], 'next' => 0];
        }
        $rows = $this->db->executeS("SELECT p.`id_product`, IFNULL(pl.`name`, CONCAT('#', p.`id_product`)) AS name, p.`reference` FROM `{$this->p}product_shop` ps
            INNER JOIN `{$this->p}product` p ON p.`id_product` = ps.`id_product`
            LEFT JOIN `{$this->p}product_lang` pl ON pl.`id_product` = ps.`id_product` AND pl.`id_shop` = ps.`id_shop` AND pl.`id_lang` = {$this->idLang}
            WHERE {$this->scope()} AND p.`id_product` > {$after} AND ({$where})
            ORDER BY p.`id_product` LIMIT " . ($limit + 1));
        $rows = (array) $rows;
        $next = count($rows) > $limit ? (int) $rows[$limit - 1]['id_product'] : 0;

        return ['rows' => array_slice($rows, 0, $limit), 'next' => $next];
    }

    private function missingCondition(string $fact): ?string
    {
        switch ($fact) {
            case 'price':
                return 'ps.`price` <= 0';
            case 'brand':
                return 'p.`id_manufacturer` = 0';
            case 'gtin':
                return "p.`ean13` = '' AND p.`upc` = '' AND p.`isbn` = '' AND NOT EXISTS (SELECT 1 FROM `{$this->p}product_attribute` pa
                    WHERE pa.`id_product` = p.`id_product` AND (pa.`ean13` != '' OR pa.`upc` != '' OR pa.`isbn` != ''))";
            case 'reference':
                return "p.`reference` = '' AND p.`mpn` = ''";
            case 'short':
                return "LENGTH(IFNULL(pl.`description_short`, '')) = 0";
            case 'long':
                return "LENGTH(IFNULL(pl.`description`, '')) < 200";
            case 'features':
                return "NOT EXISTS (SELECT 1 FROM `{$this->p}feature_product` fp WHERE fp.`id_product` = p.`id_product`)";
            case 'image':
                return "NOT EXISTS (SELECT 1 FROM `{$this->p}image_shop` i WHERE i.`id_product` = p.`id_product` AND i.`id_shop` = ps.`id_shop` AND i.`cover` = 1)";
            case 'alt':
                return "EXISTS (SELECT 1 FROM `{$this->p}image_shop` i
                    LEFT JOIN `{$this->p}image_lang` il ON il.`id_image` = i.`id_image` AND il.`id_lang` = {$this->idLang}
                    WHERE i.`id_product` = p.`id_product` AND i.`id_shop` = ps.`id_shop` AND i.`cover` = 1 AND (il.`legend` IS NULL OR il.`legend` = ''))";
            case 'category':
                return 'ps.`id_category_default` = ' . $this->home();
        }

        return null;
    }

    /** @return array{rows: array<int, array{id_product: int, name: string, reference: string}>, next: int} */
    private function invalidGtins(int $after, int $limit): array
    {
        $found = [];
        $result = $this->db->query("SELECT p.`id_product`, IFNULL(pl.`name`, CONCAT('#', p.`id_product`)) AS name, p.`reference`, p.`ean13`, p.`upc` FROM `{$this->p}product_shop` ps
            INNER JOIN `{$this->p}product` p ON p.`id_product` = ps.`id_product`
            LEFT JOIN `{$this->p}product_lang` pl ON pl.`id_product` = ps.`id_product` AND pl.`id_shop` = ps.`id_shop` AND pl.`id_lang` = {$this->idLang}
            WHERE {$this->scope()} AND p.`id_product` > {$after} AND (p.`ean13` != '' OR p.`upc` != '')
            ORDER BY p.`id_product`");
        $next = 0;
        while ($result && ($row = $this->db->nextRow($result))) {
            if (($row['ean13'] !== '' && !Gtin::isValid((string) $row['ean13'])) || ($row['upc'] !== '' && !Gtin::isValid((string) $row['upc']))) {
                if (count($found) === $limit) {
                    $next = (int) $found[$limit - 1]['id_product'];
                    break;
                }
                $found[] = $row;
            }
        }

        return ['rows' => $found, 'next' => $next];
    }
}
