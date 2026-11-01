<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Llms;

use Iamshoaibktk\Geolens\Support\Store;

/**
 * Collects what llms.txt links to: categories and policy pages, in the language asked for. A file
 * sits at the root and one under each language folder, and the root one links to the others.
 */
final class Builder
{
    private const MAX_CATEGORIES = 40;
    private const MAX_PAGES = 15;

    public static function forRequest(\Context $context, string $uri): string
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $base = (string) $context->shop->getBaseURI();
        $relative = ltrim(str_starts_with($path, $base) ? substr($path, strlen($base)) : $path, '/');
        $idLang = (int) \Configuration::get('PS_LANG_DEFAULT');
        $isRoot = true;
        if (preg_match('~^([a-z]{2})/~', $relative, $m) && ($id = (int) \Language::getIdByIso($m[1]))) {
            $idLang = $id;
            $isRoot = false;
        }

        return self::build($idLang, (int) $context->shop->id, $isRoot);
    }

    public static function build(int $idLang, int $idShop, bool $withLanguages): string
    {
        $settings = Store::get('llms', $idShop);
        $settings = is_array($settings) ? $settings : [];
        $link = new \Link();
        $locale = (string) \Language::getLocaleById($idLang);
        $translator = \Context::getContext()->getTranslator();
        $t = static fn (string $text) => $translator->trans($text, [], 'Modules.Geolens.Shop', $locale);

        $sections = [
            ['title' => $t('Categories'), 'links' => self::categories($link, $idLang, $idShop)],
            ['title' => $t('Policies and information'), 'links' => self::pages($link, $idLang, $idShop, $t)],
        ];
        if ($withLanguages && \Language::countActiveLanguages($idShop) > 1) {
            $sections[] = ['title' => $t('Other languages'), 'links' => self::languages($idLang, $idShop)];
        }
        $summary = (string) ($settings['summary'][$idLang] ?? '');
        if ($summary === '') {
            $meta = \Meta::getMetaByPage('index', $idLang);
            $summary = (string) ($meta['description'] ?? '');
        }

        return Renderer::render((string) \Configuration::get('PS_SHOP_NAME', null, null, $idShop), $summary, $sections);
    }

    /** @return array<int, array{name: string, url: string, note?: string}> */
    private static function categories(\Link $link, int $idLang, int $idShop): array
    {
        $home = (int) \Configuration::get('PS_HOME_CATEGORY', null, null, $idShop);
        $rows = \Db::getInstance()->executeS('SELECT c.`id_category`, cl.`name`, cl.`description`, c.`level_depth`
            FROM `' . _DB_PREFIX_ . 'category` c
            INNER JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON cs.`id_category` = c.`id_category` AND cs.`id_shop` = ' . $idShop . '
            INNER JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.`id_category` = c.`id_category` AND cl.`id_lang` = ' . $idLang . ' AND cl.`id_shop` = ' . $idShop . '
            WHERE c.`active` = 1 AND c.`id_category` != ' . $home . ' AND c.`level_depth` > ' . (int) (new \Category($home))->level_depth . '
            ORDER BY c.`level_depth`, cs.`position`
            LIMIT ' . self::MAX_CATEGORIES);
        $links = [];
        foreach ((array) $rows as $row) {
            $note = Renderer::note((string) $row['description']);
            $links[] = ['name' => (string) $row['name'], 'url' => (string) $link->getCategoryLink((int) $row['id_category'], null, $idLang, null, $idShop)] + ($note === '' ? [] : ['note' => $note]);
        }

        return $links;
    }

    /** @return array<int, array{name: string, url: string, note?: string}> */
    private static function pages(\Link $link, int $idLang, int $idShop, callable $t): array
    {
        $links = [];
        foreach (array_slice((array) \CMS::getCMSPages($idLang, null, true, $idShop), 0, self::MAX_PAGES) as $page) {
            $note = Renderer::note((string) ($page['meta_description'] ?? ''));
            $links[] = ['name' => (string) $page['meta_title'], 'url' => (string) $link->getCMSLink((int) $page['id_cms'], null, null, $idLang, $idShop)] + ($note === '' ? [] : ['note' => $note]);
        }
        $links[] = ['name' => $t('Contact'), 'url' => (string) $link->getPageLink('contact', null, $idLang, null, false, $idShop)];

        return $links;
    }

    /** @return array<int, array{name: string, url: string}> */
    private static function languages(int $current, int $idShop): array
    {
        $links = [];
        $shop = new \Shop($idShop);
        $domain = (\Configuration::get('PS_SSL_ENABLED') ? 'https://' . $shop->domain_ssl : 'http://' . $shop->domain) . $shop->getBaseURI();
        foreach (\Language::getLanguages(true, $idShop) as $language) {
            if ((int) $language['id_lang'] !== $current) {
                $links[] = ['name' => (string) $language['name'], 'url' => $domain . $language['iso_code'] . '/llms.txt'];
            }
        }

        return $links;
    }
}
