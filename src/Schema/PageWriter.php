<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Schema;

use Iamshoaibktk\Geolens\Indexing\Fresh;
use Iamshoaibktk\Geolens\Support\Store;

/**
 * Connects the page PrestaShop just rendered to Enricher. Almost everything comes from the product
 * the page already built, so this adds no queries for a product without combinations.
 */
final class PageWriter
{
    private const SOCIAL = ['BLOCKSOCIAL_FACEBOOK', 'BLOCKSOCIAL_TWITTER', 'BLOCKSOCIAL_YOUTUBE', 'BLOCKSOCIAL_PINTEREST', 'BLOCKSOCIAL_VIMEO', 'BLOCKSOCIAL_INSTAGRAM', 'BLOCKSOCIAL_LINKEDIN', 'BLOCKSOCIAL_TIKTOK'];

    private const FEATURE_NAMES = [
        'material' => '/^(material|materials|composition|mati[eè]re|materiale?|tessuto|materia[lł]|materi[aá]l|slo[zž]en[ií]|materiaal|samenstelling|stoff|zusammensetzung|composici[oó]n|composi[cç][aã]o|материал|состав)$/iu',
        'color' => '/^(colou?r|couleur|color|colore|kolor|barva|kleur|farbe|cor|цвет)$/iu',
        'pattern' => '/^(pattern|motif|patr[oó]n|estampado|motivo|wz[oó]r|vzor|patroon|muster|padr[aã]o|узор)$/iu',
        'size' => '/^(size|taille|talla|taglia|rozmiar|velikost|maat|gr[oö][sß]+e|tamanho|размер)$/iu',
    ];

    private \Context $context;

    public function __construct(\Context $context)
    {
        $this->context = $context;
    }

    public function rewrite(string $html): string
    {
        $page = (string) ($this->context->controller->php_self ?? '');
        if ($page === 'product') {
            return $this->productPage($html);
        }
        if ($page === 'index') {
            return $this->homePage($html);
        }

        return $this->anyPage($html, null);
    }

    public static function isEuropeanUnion(string $iso): bool
    {
        return in_array(strtoupper($iso), ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'], true);
    }

    public function facts(): Facts
    {
        $config = Store::get('facts', (int) $this->context->shop->id);
        $config = is_array($config) ? $config : [];
        $config['currency'] = (string) (\Currency::getDefaultCurrency()->iso_code ?? '');

        return new Facts($config);
    }

    private function anyPage(string $html, ?string $dateModified): string
    {
        $language = (string) ($this->context->language->locale ?: $this->context->language->language_code);

        return JsonLd::rewrite($html, static function (array $data) use ($language, $dateModified) {
            return JsonLd::mapType($data, 'WebPage', static fn (array $node) => Enricher::webPage($node, $language, $dateModified));
        });
    }

    private function homePage(string $html): string
    {
        $facts = $this->facts();
        $currency = (string) $this->context->currency->iso_code;
        $fx = (float) $this->context->currency->conversion_rate ?: 1.0;
        $returns = $facts->returnPolicy($currency, $fx);
        $shipping = $facts->organizationShipping((string) $this->context->shop->name);
        $sameAs = [];
        foreach (self::SOCIAL as $name) {
            $url = trim((string) \Configuration::get($name));
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $url)) {
                $sameAs[] = $url;
            }
        }
        $html = JsonLd::rewrite($html, static function (array $data) use ($sameAs, $returns, $shipping) {
            return JsonLd::mapType($data, 'Organization', static fn (array $node) => Enricher::organization($node, $sameAs, $returns, $shipping));
        });

        return $this->anyPage($html, null);
    }

    private function productPage(string $html): string
    {
        $product = $this->context->controller->getProduct();
        $presented = $this->context->smarty->getTemplateVars('product');
        if (!$product || !\Validate::isLoadedObject($product) || !$presented) {
            return $html;
        }
        $facts = $this->productFacts($product, $presented);
        $found = false;
        $rewrite = static function (array $data) use ($facts, &$found) {
            $result = null;
            if (!$found) {
                $result = JsonLd::mapType($data, 'Product', static fn (array $node) => Enricher::product($node, $facts));
                $found = $result !== null;
            }

            return $result;
        };
        $html = JsonLd::rewrite($html, $rewrite);
        if (!$found) {
            // A theme that prints no JSON-LD at all. The page has to say something.
            $html = JsonLd::append($html, Enricher::product($this->baseProduct($presented), $facts));
        }
        $changed = Fresh::date((int) $this->context->shop->id, (int) $product->id, (string) $product->date_upd);

        return $this->anyPage($html, $changed);
    }

    /** @param \ArrayAccess|array $p the product as presented to the template */
    private function baseProduct($p): array
    {
        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => (string) $p['name'],
            'url' => (string) $p['canonical_url'],
            'description' => trim(strip_tags((string) $p['description_short'])),
        ];
        if (!empty($p['cover']['large']['url'])) {
            $node['image'] = [$p['cover']['large']['url']];
        }
        if ($p['show_price']) {
            $node['offers'] = [
                '@type' => 'Offer',
                'url' => (string) $p['canonical_url'],
                'price' => number_format((float) $p['price_amount'], 2, '.', ''),
                'priceCurrency' => (string) $this->context->currency->iso_code,
                'availability' => (string) $p['seo_availability'],
            ];
        }

        return $node;
    }

    /** @param \ArrayAccess|array $p */
    private function productFacts(\Product $product, $p): array
    {
        $idShop = (int) $this->context->shop->id;
        $currency = (string) $this->context->currency->iso_code;
        $fx = (float) $this->context->currency->conversion_rate ?: 1.0;
        $merchant = $this->facts();
        $price = (float) ($p['price_amount'] ?? 0);
        $strike = (bool) \Configuration::get('GEOLENS_STRIKETHROUGH');

        $regular = null;
        if ($strike && !empty($p['has_discount']) && (float) $p['regular_price_amount'] > $price) {
            $regular = (float) $p['regular_price_amount'];
        }
        $validUntil = null;
        $to = (string) ($p['specific_prices']['to'] ?? '');
        if ($to !== '' && strpos($to, '0000') !== 0 && !empty($p['has_discount'])) {
            $validUntil = date('c', (int) strtotime($to));
        }

        $facts = [
            'identifiers' => self::identifiers((string) $product->reference, (string) $product->mpn, (string) $product->ean13, (string) $product->upc, (string) $product->isbn),
            'brand' => empty($product->id_manufacturer) ? null : (string) ($p['manufacturer_name'] ?? \Manufacturer::getNameById((int) $product->id_manufacturer)),
            'keep_shop_brand' => (bool) \Configuration::get('GEOLENS_SHOP_BRAND'),
            'condition' => self::condition((string) $product->condition),
            'regular_price' => $regular,
            'currency' => $currency,
            'valid_until' => $validUntil,
            'shipping' => $merchant->offerShipping($price, $currency, $fx),
            'returns' => $merchant->returnPolicy($currency, $fx),
            'attributes' => self::attributes((array) ($p['grouped_features'] ?? [])),
            'variants' => [],
            'vary_by' => [],
            'group_id' => (string) $product->id,
        ];
        if (\Configuration::get('GEOLENS_VARIANTS') && !empty($p['show_price']) && $product->hasAttributes()) {
            [$facts['variants'], $facts['vary_by']] = $this->variants($product, $p, $merchant, $currency, $fx, $strike, $idShop);
        }

        return $facts;
    }

    /** @return array<string, string> only values that are real, and a GTIN only if its check digit is right */
    public static function identifiers(string $reference, string $mpn, string $ean13, string $upc, string $isbn): array
    {
        $ids = [];
        if ($reference !== '') {
            $ids['sku'] = $reference;
        }
        if ($mpn !== '') {
            $ids['mpn'] = $mpn;
        }
        foreach ([$ean13, $upc] as $code) {
            if (Gtin::isValid($code)) {
                $ids['gtin' . strlen($code)] = $code;
            }
        }
        if ($isbn !== '') {
            $ids['isbn'] = $isbn;
        }

        return $ids;
    }

    private static function condition(string $condition): ?string
    {
        return [
            'new' => 'https://schema.org/NewCondition',
            'used' => 'https://schema.org/UsedCondition',
            'refurbished' => 'https://schema.org/RefurbishedCondition',
        ][$condition] ?? null;
    }

    /**
     * Features whose name means material, color, pattern or size also fill the property Google
     * reads. PrestaShop's demo calls material "Composition".
     *
     * @return array<string, string>
     */
    public static function attributes(array $features): array
    {
        $attributes = [];
        foreach ($features as $feature) {
            $name = trim((string) ($feature['name'] ?? ''));
            $value = (string) preg_replace('/\R+/', ', ', trim((string) ($feature['value'] ?? '')));
            foreach (self::FEATURE_NAMES as $property => $pattern) {
                if ($value !== '' && preg_match($pattern, $name)) {
                    $attributes[$property] = $value;
                }
            }
        }

        return $attributes;
    }

    /**
     * Every combination with its own price, stock and URL.
     *
     * @param \ArrayAccess|array $p
     *
     * @return array{0: array<int, array>, 1: string[]}
     */
    private function variants(\Product $product, $p, Facts $merchant, string $currency, float $fx, bool $strike, int $idShop): array
    {
        $limit = max(2, (int) \Configuration::get('GEOLENS_VARIANT_LIMIT'));
        $idLang = (int) $this->context->language->id;
        $combinations = self::combinations((int) $product->id, $idLang, $idShop, $limit);
        if (count($combinations) < 2) {
            return [[], []];
        }

        $stock = [];
        $stockRows = \Db::getInstance()->executeS('SELECT `id_product_attribute`, `quantity` FROM `' . _DB_PREFIX_ . 'stock_available`
            WHERE `id_product` = ' . (int) $product->id . \StockAvailable::addSqlShopRestriction(null, $idShop));
        foreach ((array) $stockRows as $row) {
            $stock[(int) $row['id_product_attribute']] = (int) $row['quantity'];
        }
        $manageStock = (bool) \Configuration::get('PS_STOCK_MANAGEMENT');
        $canBackorder = \Product::isAvailableWhenOutOfStock((int) $product->out_of_stock);

        $images = [];
        foreach ((array) ($p['images'] ?? []) as $image) {
            foreach ((array) ($image['associatedVariants'] ?? []) as $ipa) {
                $images[(int) $ipa] = $images[(int) $ipa] ?? ($image['large']['url'] ?? null);
            }
        }
        $cover = $p['cover']['large']['url'] ?? null;
        $withTax = (int) \Product::getTaxCalculationMethod((int) $this->context->cookie->id_customer) === 0;

        $variants = [];
        $varyBy = [];
        $urls = [];
        $skus = [];
        foreach ($combinations as $ipa => $attributes) {
            $first = $attributes[0];
            $names = [];
            $variant = self::identifiers((string) $first['reference'], (string) ($first['mpn'] ?? ''), (string) $first['ean13'], (string) $first['upc'], (string) ($first['isbn'] ?? ''));
            // Google needs an identifier unique to each variant, and shops often leave the parent's
            // reference on every combination.
            $sku = $variant['sku'] ?? '';
            if ($sku === '' || isset($skus[$sku]) || $sku === (string) $product->reference) {
                $variant['sku'] = ((string) $product->reference !== '' ? $product->reference : $product->id) . '-' . $ipa;
            }
            $skus[$variant['sku']] = true;
            foreach ($attributes as $attribute) {
                $names[] = (string) $attribute['attribute_name'];
                $property = !empty($attribute['is_color_group']) ? 'color' : self::variantProperty((string) $attribute['group_name']);
                if ($property !== null) {
                    $variant[$property] = (string) $attribute['attribute_name'];
                    $varyBy[$property] = true;
                }
            }
            $url = (string) $this->context->link->getProductLink($product, null, null, null, $idLang, $idShop, $ipa, false, false, false, [], false);
            $urls[$url] = true;
            $price = (float) \Product::getPriceStatic((int) $product->id, $withTax, $ipa);
            $before = $strike ? (float) \Product::getPriceStatic((int) $product->id, $withTax, $ipa, 6, null, false, false) : 0.0;
            $quantity = $stock[$ipa] ?? 0;
            $variants[] = $variant + [
                'name' => $product->name . ' - ' . implode(', ', $names),
                'image' => $images[$ipa] ?? $cover,
                'url' => $url,
                'price' => number_format($price, 2, '.', ''),
                'regular_price' => $before > $price ? $before : null,
                'availability' => !$manageStock || $quantity > 0 ? 'https://schema.org/InStock' : ($canBackorder ? 'https://schema.org/BackOrder' : 'https://schema.org/OutOfStock'),
                'shipping' => $merchant->offerShipping($price, $currency, $fx),
            ];
        }
        // Friendly URLs without a combination in them (Clean URLs and similar). PrestaShop preselects
        // a combination from this parameter on any URL, which is what Google asks for.
        if (count($urls) < count($combinations)) {
            foreach (array_keys($combinations) as $i => $ipa) {
                $variants[$i]['url'] .= (strpos($variants[$i]['url'], '?') === false ? '?' : '&') . 'id_product_attribute=' . $ipa;
            }
        }

        return [$variants, array_keys($varyBy)];
    }

    /**
     * Product::getAttributeCombinations() runs one more query per row for stock, which is more than a
     * product page should pay. This is a single query, and none at all past the limit.
     *
     * @return array<int, array<int, array>> id_product_attribute => one row per attribute
     */
    private static function combinations(int $idProduct, int $idLang, int $idShop, int $limit): array
    {
        $db = \Db::getInstance();
        $p = _DB_PREFIX_;
        if ((int) $db->getValue("SELECT COUNT(*) FROM `{$p}product_attribute` WHERE `id_product` = {$idProduct}") > $limit) {
            return [];
        }
        $rows = $db->executeS("SELECT pa.`id_product_attribute`, pa.`reference`, pa.`ean13`, pa.`upc`, pa.`mpn`, pa.`isbn`,
                ag.`is_color_group`, agl.`name` AS group_name, al.`name` AS attribute_name
            FROM `{$p}product_attribute` pa
            INNER JOIN `{$p}product_attribute_shop` pas ON pas.`id_product_attribute` = pa.`id_product_attribute` AND pas.`id_shop` = {$idShop}
            INNER JOIN `{$p}product_attribute_combination` pac ON pac.`id_product_attribute` = pa.`id_product_attribute`
            INNER JOIN `{$p}attribute` a ON a.`id_attribute` = pac.`id_attribute`
            INNER JOIN `{$p}attribute_group` ag ON ag.`id_attribute_group` = a.`id_attribute_group`
            LEFT JOIN `{$p}attribute_lang` al ON al.`id_attribute` = a.`id_attribute` AND al.`id_lang` = {$idLang}
            LEFT JOIN `{$p}attribute_group_lang` agl ON agl.`id_attribute_group` = ag.`id_attribute_group` AND agl.`id_lang` = {$idLang}
            WHERE pa.`id_product` = {$idProduct}
            ORDER BY pa.`id_product_attribute`, ag.`position`, a.`position`");
        $combinations = [];
        foreach ((array) $rows as $row) {
            $combinations[(int) $row['id_product_attribute']][] = $row;
        }

        return $combinations;
    }

    public static function variantProperty(string $groupName): ?string
    {
        foreach (['size', 'material', 'pattern', 'color'] as $property) {
            if (preg_match(self::FEATURE_NAMES[$property], trim($groupName))) {
                return $property;
            }
        }

        return null;
    }
}
