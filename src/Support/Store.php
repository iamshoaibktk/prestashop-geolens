<?php
/**
 * GEO Lens - AI Search Visibility
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Geolens\Support;

/**
 * Larger values: IP lists, last check results, the robots.txt backup. PrestaShop loads the whole
 * configuration table into memory on every request, so these must not live there. id_shop 0 means
 * the value belongs to the whole installation.
 */
final class Store
{
    /** @return mixed|null */
    public static function get(string $name, int $idShop = 0)
    {
        $row = \Db::getInstance()->getValue('SELECT `value` FROM `' . _DB_PREFIX_ . 'geolens_store`
            WHERE `name` = "' . pSQL($name) . '" AND `id_shop` = ' . $idShop);

        return $row === false || $row === null ? null : json_decode((string) $row, true);
    }

    /** @param mixed $value */
    public static function set(string $name, $value, int $idShop = 0): void
    {
        $json = pSQL((string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), true);
        \Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'geolens_store` (`name`, `id_shop`, `value`, `date_upd`)
            VALUES ("' . pSQL($name) . '", ' . $idShop . ', "' . $json . '", NOW())
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `date_upd` = NOW()');
    }

    public static function age(string $name, int $idShop = 0): ?int
    {
        $date = \Db::getInstance()->getValue('SELECT `date_upd` FROM `' . _DB_PREFIX_ . 'geolens_store`
            WHERE `name` = "' . pSQL($name) . '" AND `id_shop` = ' . $idShop);

        return $date ? time() - (int) strtotime((string) $date) : null;
    }

    public static function delete(string $name, int $idShop = 0): void
    {
        \Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'geolens_store` WHERE `name` = "' . pSQL($name) . '" AND `id_shop` = ' . $idShop);
    }
}
