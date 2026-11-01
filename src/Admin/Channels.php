<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Admin;

/**
 * Where a shop can be listed, as read on the date below. This moves fast, so it is data and not
 * logic: an update changes a row. "markets" says who the programme is open to: 'all', or only shops
 * selling to the US. Being open does not mean being accepted: the platform decides that.
 */
final class Channels
{
    public const CHECKED = '2026-11-01';

    public const LIST = [
        'gmc' => ['name' => 'Google Merchant Center', 'markets' => 'all', 'url' => 'https://merchants.google.com/'],
        'msmc' => ['name' => 'Microsoft Merchant Center', 'markets' => 'all', 'url' => 'https://ads.microsoft.com/'],
        'bing' => ['name' => 'Bing Webmaster Tools', 'markets' => 'all', 'url' => 'https://www.bing.com/webmasters/'],
        'gsc' => ['name' => 'Google Search Console', 'markets' => 'all', 'url' => 'https://search.google.com/search-console'],
        'chatgpt' => ['name' => 'ChatGPT shopping feed', 'markets' => 'us', 'url' => 'https://chatgpt.com/merchants'],
        'ucp' => ['name' => 'Google UCP checkout', 'markets' => 'us', 'url' => 'https://developers.google.com/merchant/ucp'],
        'copilot' => ['name' => 'Copilot Checkout', 'markets' => 'us', 'url' => 'https://about.ads.microsoft.com/en/solutions/technology/agentic-commerce'],
    ];

    /** @return string[] the prompts a merchant can paste into an assistant to see what it says */
    public static function prompts(\Context $context, \Geolens $module): array
    {
        $shop = (string) $context->shop->name;
        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;
        $db = \Db::getInstance();
        $category = (string) $db->getValue('SELECT cl.`name` FROM `' . _DB_PREFIX_ . 'category` c
            INNER JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON cs.`id_category` = c.`id_category` AND cs.`id_shop` = ' . $idShop . '
            INNER JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.`id_category` = c.`id_category` AND cl.`id_lang` = ' . $idLang . ' AND cl.`id_shop` = ' . $idShop . '
            WHERE c.`active` = 1 AND c.`level_depth` = 2 ORDER BY cs.`position`');
        $product = (string) $db->getValue('SELECT pl.`name` FROM `' . _DB_PREFIX_ . 'product_shop` ps
            INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.`id_product` = ps.`id_product` AND pl.`id_lang` = ' . $idLang . ' AND pl.`id_shop` = ' . $idShop . '
            WHERE ps.`id_shop` = ' . $idShop . ' AND ps.`active` = 1 ORDER BY ps.`id_product`');
        $country = (string) \Country::getNameById($idLang, (int) \Configuration::get('PS_COUNTRY_DEFAULT'));

        return [
            $module->t('What does %shop% sell, and is it a reliable place to buy?', ['%shop%' => $shop]),
            $module->t('Where can I buy %category% in %country%?', ['%category%' => $category, '%country%' => $country]),
            $module->t('How much is %product% and who has it in stock?', ['%product%' => $product]),
            $module->t('What is the returns policy of %shop%?', ['%shop%' => $shop]),
        ];
    }
}
