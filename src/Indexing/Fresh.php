<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Indexing;

/**
 * The date a product's facts last really changed. PrestaShop's date_upd misleads both ways: stock
 * and specific prices never touch it, while reordering a category bumps it for products that did
 * not change. Google trusts a date only when it has proven accurate, so the difference matters.
 */
final class Fresh
{
    public static function touch(int $idShop, int $idProduct): void
    {
        $p = _DB_PREFIX_ . 'geolens_';
        \Db::getInstance()->execute("INSERT INTO `{$p}fresh` (`id_shop`, `id_product`, `date_upd`) VALUES ({$idShop}, {$idProduct}, NOW())
            ON DUPLICATE KEY UPDATE `date_upd` = NOW()");
    }

    public static function date(int $idShop, int $idProduct, string $dateUpd): string
    {
        $p = _DB_PREFIX_ . 'geolens_';
        $own = \Db::getInstance()->getValue("SELECT `date_upd` FROM `{$p}fresh` WHERE `id_shop` = {$idShop} AND `id_product` = {$idProduct}");

        return date('c', max((int) strtotime($dateUpd), $own ? (int) strtotime((string) $own) : 0));
    }

    /**
     * A sale that starts or ends on its own schedule fires no hook, yet the price changed. Once a
     * day this finds them and treats them as the price changes they are.
     */
    public static function sweepSales(): void
    {
        $last = (int) \Configuration::getGlobalValue('GEOLENS_SWEPT_AT');
        $since = date('Y-m-d H:i:s', $last > 0 ? $last : time() - 86400);
        $now = date('Y-m-d H:i:s');
        $rows = \Db::getInstance()->executeS('SELECT DISTINCT `id_product`, `id_shop` FROM `' . _DB_PREFIX_ . 'specific_price`
            WHERE `id_product` > 0 AND ((`from` > "' . $since . '" AND `from` <= "' . $now . '") OR (`to` > "' . $since . '" AND `to` <= "' . $now . '"))
            LIMIT 5000');
        foreach ((array) $rows as $row) {
            // id_shop 0 on a specific price means every shop.
            $shops = (int) $row['id_shop'] > 0 ? [(int) $row['id_shop']] : \Shop::getShops(true, null, true);
            foreach ($shops as $idShop) {
                self::touch((int) $idShop, (int) $row['id_product']);
                IndexNow::queue((int) $idShop, 'product', (int) $row['id_product']);
            }
        }
        \Configuration::updateGlobalValue('GEOLENS_SWEPT_AT', time());
    }
}
