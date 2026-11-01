<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Feed;

/**
 * Product and image addresses, from the core's own link builder so they are the ones the shop serves.
 * Given only an ID it loads the whole product, once per link, which a catalogue cannot afford. On a
 * standard route it needs just the fields a batch has already read, so it gets those.
 */
final class Links
{
    /** Route keywords that need more than a batch reads. A shop using one pays for it with slower builds. */
    private const HEAVY_KEYWORDS = ['meta_title', 'manufacturer', 'supplier', 'price', 'tags', 'reference', 'categories'];

    private \Link $link;
    private int $idLang;
    private int $idShop;
    /** @var array<string, int> */
    private array $extra;
    private bool $fast;
    private \Product $shell;

    /** @param array<string, int> $extra added to each product address */
    public function __construct(int $idLang, int $idShop, array $extra = [])
    {
        $this->link = \Context::getContext()->link ?: new \Link();
        $this->idLang = $idLang;
        $this->idShop = $idShop;
        $this->extra = $extra;
        $this->shell = new \Product();
        $dispatcher = \Dispatcher::getInstance();
        $this->fast = true;
        foreach (self::HEAVY_KEYWORDS as $keyword) {
            if ($dispatcher->hasKeyword('product_rule', $idLang, $keyword, $idShop)) {
                $this->fast = false;
            }
        }
    }

    public function product(array $row, int $idCombination): string
    {
        $product = (int) $row['id_product'];
        if ($this->fast) {
            $this->shell->id = $product;
            $this->shell->ean13 = (string) $row['ean13'];
            $this->shell->category = (string) $row['category_rewrite'];
            $product = $this->shell;
        }

        return (string) $this->link->getProductLink(
            $product,
            (string) $row['link_rewrite'],
            (string) $row['category_rewrite'],
            (string) $row['ean13'],
            $this->idLang,
            $this->idShop,
            $idCombination ?: null,
            false,
            false,
            false,
            $this->extra,
            false
        );
    }

    /** The original photo: never padded out or watermarked, and the same file the catalogue audit measures. */
    public function image(string $rewrite, int $idImage): string
    {
        return (string) $this->link->getImageLink($rewrite, $idImage);
    }

    /**
     * Friendly URLs without a combination in them (Clean URLs and similar) give every variant the same
     * address. PrestaShop preselects a combination from this parameter on any URL, and Google wants
     * each variant to have its own.
     *
     * @param array<int, string> $urls by combination
     *
     * @return array<int, string>
     */
    public static function distinguish(array $urls): array
    {
        if (count(array_unique($urls)) === count($urls)) {
            return $urls;
        }
        foreach ($urls as $idCombination => $url) {
            $urls[$idCombination] = $url . (strpos($url, '?') === false ? '?' : '&') . 'id_product_attribute=' . $idCombination;
        }

        return $urls;
    }
}
